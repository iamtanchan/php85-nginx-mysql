<?php
declare(strict_types=1);

const APP_ENV_FILES = array(
    __DIR__ . '/../../.env',
    __DIR__ . '/../.env',
);

function app_load_env_file(string $path): void
{
    static $loaded = array();

    if (isset($loaded[$path])) {
        return;
    }
    $loaded[$path] = true;

    if (!is_file($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        throw new RuntimeException(sprintf('Unable to read env file: %s', $path));
    }

    foreach ($lines as $line_number => $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '#') || str_starts_with($trimmed, ';')) {
            continue;
        }

        if (!preg_match('/^(?:export\s+)?([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*)$/', $trimmed, $matches)) {
            throw new RuntimeException(
                sprintf('Unable to parse env file %s on line %d', basename($path), $line_number + 1)
            );
        }

        $name = $matches[1];
        if (app_env_exists($name)) {
            continue;
        }

        $value = app_normalize_env_value($matches[2]);
        putenv($name . '=' . $value);
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }
}

function app_normalize_env_value(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    $quote = $value[0];
    if (($quote === '"' || $quote === "'") && substr($value, -1) === $quote) {
        $value = substr($value, 1, -1);
        if ($quote === '"') {
            return stripcslashes($value);
        }
    }

    return $value;
}

function app_env_exists(string $name): bool
{
    if (array_key_exists($name, $_ENV) || array_key_exists($name, $_SERVER)) {
        return true;
    }

    return getenv($name) !== false;
}

function app_env(string $name, ?string $default = null): ?string
{
    if (array_key_exists($name, $_ENV)) {
        return (string)$_ENV[$name];
    }
    if (array_key_exists($name, $_SERVER)) {
        return (string)$_SERVER[$name];
    }

    $value = getenv($name);
    if ($value !== false) {
        return (string)$value;
    }

    return $default;
}

function app_required_env(string $name): string
{
    $value = app_env($name);
    if ($value !== null) {
        return $value;
    }

    throw new RuntimeException(
        sprintf('Environment variable %s is not set. Copy .env.example to .env and update the database settings.', $name)
    );
}

function app_create_database_connection(): PDO
{
    foreach (APP_ENV_FILES as $env_file) {
        app_load_env_file($env_file);
    }

    $dsn = trim((string)app_env('DB_DSN', ''));
    if ($dsn === '') {
        $host = app_required_env('DB_HOST');
        $name = app_first_env_value(array('DB_DATABASE', 'DB_NAME'));
        $charset = trim((string)app_env('DB_CHARSET', 'utf8mb4'));
        $port = trim((string)app_env('DB_PORT', ''));

        $dsn_parts = array(
            'host=' . $host,
            'dbname=' . $name,
            'charset=' . $charset,
        );
        if ($port !== '') {
            $dsn_parts[] = 'port=' . $port;
        }

        $dsn = 'mysql:' . implode(';', $dsn_parts);
    }

    $options = array(
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    );

    app_apply_database_ssl_options($options);

    return new PDO(
        $dsn,
        app_first_env_value(array('DB_USERNAME', 'DB_USER')),
        app_first_env_value(array('DB_PASSWORD', 'DB_PASS')),
        $options
    );
}

function app_first_env_value(array $names, ?string $default = null): ?string
{
    foreach ($names as $name) {
        $value = app_env($name);
        if ($value !== null && $value !== '') {
            return $value;
        }
    }

    if ($default !== null) {
        return $default;
    }

    throw new RuntimeException(
        sprintf(
            'Environment variable %s is not set. Copy .env.example to .env and update the database settings.',
            implode(' or ', $names)
        )
    );
}

function app_pdo_mysql_attribute(string $modern, string $legacy): ?int
{
    $modern_constant = 'Pdo\\Mysql::' . $modern;

    if (defined($modern_constant)) {
        return constant($modern_constant);
    }

    if (defined($legacy)) {
        return constant($legacy);
    }

    return null;
}

function app_database_ssl_ca_path(): ?string
{
    $configured_path = trim((string)app_env('DB_SSL_CA', ''));
    if ($configured_path !== '') {
        return $configured_path;
    }

    foreach (array(__DIR__ . '/../../ca-certificate.crt', __DIR__ . '/../ca-certificate.crt') as $candidate) {
        if (is_file($candidate)) {
            return $candidate;
        }
    }

    $pem = app_env('DB_SSL_CA_PEM');
    if ($pem === null || trim($pem) === '') {
        return null;
    }

    $temp_file = tempnam(sys_get_temp_dir(), 'do-mysql-ca-');
    if ($temp_file === false) {
        throw new RuntimeException('Unable to create a temporary CA certificate file.');
    }

    if (file_put_contents($temp_file, $pem) === false) {
        throw new RuntimeException('Unable to write the temporary CA certificate file.');
    }

    return $temp_file;
}

function app_apply_database_ssl_options(array &$options): void
{
    $ssl_mode = strtoupper(trim((string)app_env('DB_SSL_MODE', 'DISABLED')));
    if ($ssl_mode === '' || $ssl_mode === 'DISABLED') {
        return;
    }

    $ssl_ca_path = app_database_ssl_ca_path();
    $ssl_ca_attribute = app_pdo_mysql_attribute('ATTR_SSL_CA', 'PDO::MYSQL_ATTR_SSL_CA');
    $ssl_verify_attribute = app_pdo_mysql_attribute(
        'ATTR_SSL_VERIFY_SERVER_CERT',
        'PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT'
    );

    if ($ssl_ca_path !== null && $ssl_ca_attribute !== null) {
        $options[$ssl_ca_attribute] = $ssl_ca_path;
    }

    if ($ssl_verify_attribute !== null) {
        $options[$ssl_verify_attribute] = $ssl_ca_path !== null;
    }
}
