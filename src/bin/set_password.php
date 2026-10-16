<?php

declare(strict_types=1);

// Command-line only: create an admin login or reset its password.
//
//   php bin/set_password.php <login_id>                 reset an existing login
//   php bin/set_password.php <login_id> <station_id> [auth]   create a login (auth defaults to 0)
//
// The password is read from the terminal (or stdin) and stored as a password_hash().

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../lib/database.php';
require_once __DIR__ . '/../lib/password_auth.php';

$login_id = $argv[1] ?? '';
if ($login_id === '' || $login_id === '-h' || $login_id === '--help') {
    fwrite(STDERR, "Usage: php bin/set_password.php <login_id> [<station_id> [auth]]\n");
    exit(1);
}
$station_id = $argv[2] ?? null;
$auth = (int)($argv[3] ?? 0);

$password = app_read_password();
if (strlen($password) < 8) {
    fwrite(STDERR, "Password must be at least 8 characters.\n");
    exit(1);
}

$db = app_create_database_connection();

$stt = $db->prepare('SELECT COUNT(*) FROM login WHERE login_id = :id');
$stt->bindValue(':id', $login_id);
$stt->execute();
$exists = (int)$stt->fetchColumn() === 1;

if (!$exists) {
    if ($station_id === null) {
        fwrite(STDERR, "Login '$login_id' does not exist. Pass a station_id to create it.\n");
        exit(1);
    }
    $stt = $db->prepare('INSERT INTO login (login_id, auth, station_id) VALUES (:id, :auth, :station)');
    $stt->bindValue(':id', $login_id);
    $stt->bindValue(':auth', $auth, PDO::PARAM_INT);
    $stt->bindValue(':station', (int)$station_id, PDO::PARAM_INT);
    $stt->execute();
}

app_store_password_hash($db, $login_id, $password);
fwrite(STDOUT, ($exists ? 'Password updated' : 'Login created') . " for '$login_id'.\n");

function app_read_password(): string
{
    $interactive = function_exists('posix_isatty') ? posix_isatty(STDIN) : stream_isatty(STDIN);
    if (!$interactive) {
        return rtrim((string)fgets(STDIN), "\r\n");
    }

    fwrite(STDOUT, 'New password: ');
    shell_exec('stty -echo');
    $first = rtrim((string)fgets(STDIN), "\r\n");
    fwrite(STDOUT, "\nRepeat password: ");
    $second = rtrim((string)fgets(STDIN), "\r\n");
    shell_exec('stty echo');
    fwrite(STDOUT, "\n");

    if ($first !== $second) {
        fwrite(STDERR, "Passwords do not match.\n");
        exit(1);
    }
    return $first;
}
