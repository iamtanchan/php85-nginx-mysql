<?php

declare(strict_types=1);

const APP_ENV_FILE = __DIR__ . '/../.env';

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

function app_required_env_any(array $names): string
{
    foreach ($names as $name) {
        $value = app_env($name);
        if ($value !== null) {
            return $value;
        }
    }

    throw new RuntimeException(
        sprintf(
            'Environment variable %s is not set. Copy .env.example to .env and update the database settings.',
            implode(' or ', $names)
        )
    );
}

function app_create_database_connection(): PDO
{
    app_load_env_file(APP_ENV_FILE);

    $dsn = trim((string)app_env('DB_DSN', ''));
    if ($dsn === '') {
        $host = app_required_env('DB_HOST');
        $name = app_required_env_any(array('DB_NAME', 'DB_DATABASE'));
        $charset = trim((string)app_env('DB_CHARSET', 'utf8'));
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

    return new PDO(
        $dsn,
        app_required_env_any(array('DB_USER', 'DB_USERNAME')),
        app_required_env_any(array('DB_PASS', 'DB_PASSWORD')),
        array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        )
    );
}
