<?php

declare(strict_types=1);

function envValue(string $key, ?string $default = null): ?string
{
    $value = getenv($key);

    if ($value === false || $value === '') {
        return $default;
    }

    return $value;
}

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function pdoMysqlAttribute(string $modern, string $legacy): ?int
{
    $modernConstant = 'Pdo\\Mysql::' . $modern;

    if (defined($modernConstant)) {
        return constant($modernConstant);
    }

    if (defined($legacy)) {
        return constant($legacy);
    }

    return null;
}

function sslCaPath(array $config): ?string
{
    if (!empty($config['ssl_ca'])) {
        return $config['ssl_ca'];
    }

    $bundledCertificate = dirname(__DIR__) . '/ca-certificate.crt';

    if (is_file($bundledCertificate)) {
        return $bundledCertificate;
    }

    if (empty($config['ssl_ca_pem'])) {
        return null;
    }

    $tempFile = tempnam(sys_get_temp_dir(), 'do-mysql-ca-');

    if ($tempFile === false) {
        throw new RuntimeException('Unable to create a temporary CA certificate file.');
    }

    if (file_put_contents($tempFile, $config['ssl_ca_pem']) === false) {
        throw new RuntimeException('Unable to write the temporary CA certificate file.');
    }

    return $tempFile;
}

$config = [
    'host' => envValue('DB_HOST', 'mysql'),
    'port' => envValue('DB_PORT', '3306'),
    'database' => envValue('DB_DATABASE', envValue('MYSQL_DATABASE', 'development')),
    'username' => envValue('DB_USERNAME', envValue('MYSQL_USER', 'mysql')),
    'password' => envValue('DB_PASSWORD', envValue('MYSQL_PASSWORD', 'mysql')),
    'ssl_mode' => strtoupper((string) envValue('DB_SSL_MODE', 'DISABLED')),
    'ssl_ca' => envValue('DB_SSL_CA'),
    'ssl_ca_pem' => envValue('DB_SSL_CA_PEM'),
];

$missingKeys = [];

foreach (['host', 'port', 'database', 'username', 'password'] as $key) {
    if ($config[$key] === null || $config[$key] === '') {
        $missingKeys[] = strtoupper('DB_' . $key);
    }
}

$status = 'Not connected';
$message = 'Set DB_* environment variables to test the database connection.';
$sslCipher = 'n/a';
$connectedAt = 'n/a';

if ($missingKeys === []) {
    try {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $config['host'],
            $config['port'],
            $config['database']
        );

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        $sslCaPath = sslCaPath($config);
        $sslCaAttribute = pdoMysqlAttribute('ATTR_SSL_CA', 'PDO::MYSQL_ATTR_SSL_CA');
        $sslVerifyAttribute = pdoMysqlAttribute(
            'ATTR_SSL_VERIFY_SERVER_CERT',
            'PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT'
        );

        if (!empty($sslCaPath) && $sslCaAttribute !== null) {
            $options[$sslCaAttribute] = $sslCaPath;
        }

        if ($sslVerifyAttribute !== null) {
            $options[$sslVerifyAttribute] = !empty($sslCaPath);
        }

        $pdo = new PDO($dsn, $config['username'], $config['password'], $options);

        $sslStatus = $pdo->query("SHOW STATUS LIKE 'Ssl_cipher'")->fetch();
        $sslCipher = $sslStatus['Value'] ?? '';

        if ($config['ssl_mode'] === 'REQUIRED' && $sslCipher === '') {
            throw new RuntimeException(
                'DB_SSL_MODE is REQUIRED, but the MySQL session is not using SSL/TLS. ' .
                'Download the DigitalOcean CA certificate and set DB_SSL_CA or DB_SSL_CA_PEM.'
            );
        }

        $connectedAtRow = $pdo->query('SELECT NOW() AS connected_at')->fetch();
        $connectedAt = $connectedAtRow['connected_at'] ?? 'unknown';

        $status = 'Connected';
        $message = 'The app can reach the configured MySQL database.';
        $sslCipher = $sslCipher !== '' ? $sslCipher : 'not negotiated';
    } catch (Throwable $exception) {
        $status = 'Connection failed';
        $message = $exception->getMessage();
        $sslCipher = $sslCipher !== '' ? $sslCipher : 'not negotiated';
    }
} else {
    $message = 'Missing environment variables: ' . implode(', ', $missingKeys);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Status</title>
    <style>
        body {
            margin: 0;
            font-family: "Helvetica Neue", Arial, sans-serif;
            background: linear-gradient(135deg, #f5f7fa, #dfe7f1);
            color: #1f2933;
        }

        main {
            max-width: 720px;
            margin: 48px auto;
            padding: 32px;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 18px;
            box-shadow: 0 24px 60px rgba(15, 23, 42, 0.12);
        }

        h1 {
            margin-top: 0;
            font-size: 2rem;
        }

        .status {
            display: inline-block;
            margin-bottom: 20px;
            padding: 10px 14px;
            border-radius: 999px;
            font-weight: 700;
            color: #fff;
            background: <?= $status === 'Connected' ? "'#127a39'" : "'#b42318'" ?>;
        }

        dl {
            display: grid;
            grid-template-columns: 180px 1fr;
            gap: 12px 18px;
        }

        dt {
            font-weight: 700;
        }

        dd {
            margin: 0;
            word-break: break-word;
        }

        p.note {
            margin-top: 24px;
            color: #52606d;
        }

        a {
            color: #0b6efd;
        }
    </style>
</head>
<body>
<main>
    <h1>DigitalOcean Managed MySQL</h1>
    <div class="status"><?= escape($status) ?></div>
    <p><?= escape($message) ?></p>

    <dl>
        <dt>Host</dt>
        <dd><?= escape((string) $config['host']) ?></dd>

        <dt>Port</dt>
        <dd><?= escape((string) $config['port']) ?></dd>

        <dt>Database</dt>
        <dd><?= escape((string) $config['database']) ?></dd>

        <dt>Username</dt>
        <dd><?= escape((string) $config['username']) ?></dd>

        <dt>SSL mode</dt>
        <dd><?= escape((string) $config['ssl_mode']) ?></dd>

        <dt>CA source</dt>
        <dd><?= escape(!empty($config['ssl_ca']) ? 'DB_SSL_CA' : (is_file(dirname(__DIR__) . '/ca-certificate.crt') ? 'bundled ca-certificate.crt' : (!empty($config['ssl_ca_pem']) ? 'DB_SSL_CA_PEM' : 'not configured'))) ?></dd>

        <dt>SSL cipher</dt>
        <dd><?= escape((string) $sslCipher) ?></dd>

        <dt>Connected at</dt>
        <dd><?= escape((string) $connectedAt) ?></dd>
    </dl>

    <p class="note">
        <a href="/info.php">phpinfo()</a>
    </p>
</main>
</body>
</html>
