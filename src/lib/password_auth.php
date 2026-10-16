<?php

declare(strict_types=1);

/**
 * Checks a login ID and password, returning the `login` row on success or null.
 *
 * Passwords are stored as password_hash() hashes. Accounts that still have a
 * legacy plaintext password are checked with the original SQL comparison (so
 * the column collation behaves exactly as before) and upgraded to a hash on
 * their next successful login, once `login.pass` is wide enough
 * (sql/migrations/20261016_login_pass_varchar255.sql).
 */
function app_authenticate(PDO $db, string $login_id, string $password): ?array
{
    $stt = $db->prepare('SELECT * FROM login WHERE login_id = :id');
    $stt->bindValue(':id', $login_id);
    $stt->execute();
    $rows = $stt->fetchAll(PDO::FETCH_ASSOC);
    if (count($rows) !== 1) {
        return null;
    }

    $row = $rows[0];
    $stored = (string)($row['pass'] ?? '');
    if ($stored === '' || $password === '') {
        return null;
    }

    if (app_is_password_hash($stored)) {
        if (!password_verify($password, $stored)) {
            return null;
        }
        if (password_needs_rehash($stored, PASSWORD_DEFAULT)) {
            app_try_store_password_hash($db, (string)$row['login_id'], $password);
        }
        return $row;
    }

    // Legacy plaintext password.
    $stt = $db->prepare('SELECT COUNT(*) FROM login WHERE login_id = :id AND pass = :pass');
    $stt->bindValue(':id', $login_id);
    $stt->bindValue(':pass', $password);
    $stt->execute();
    if ((int)$stt->fetchColumn() !== 1) {
        return null;
    }

    app_try_store_password_hash($db, (string)$row['login_id'], $password);
    return $row;
}

function app_is_password_hash(string $value): bool
{
    return password_get_info($value)['algo'] !== null;
}

/**
 * True when `login.pass` can hold a password_hash() result. Until the
 * migration has run the column is varchar(50), too short for a hash.
 */
function app_password_column_fits_hash(PDO $db): bool
{
    static $fits = null;
    if ($fits === null) {
        $length = $db->query(
            "SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'login' AND COLUMN_NAME = 'pass'"
        )->fetchColumn();
        $fits = $length !== false && (int)$length >= 255;
    }
    return $fits;
}

function app_store_password_hash(PDO $db, string $login_id, string $password): void
{
    if (!app_password_column_fits_hash($db)) {
        throw new RuntimeException(
            'login.pass is too short for a password hash; run sql/migrations/20261016_login_pass_varchar255.sql'
        );
    }
    $stt = $db->prepare('UPDATE login SET pass = :pass WHERE login_id = :id');
    $stt->bindValue(':pass', password_hash($password, PASSWORD_DEFAULT));
    $stt->bindValue(':id', $login_id);
    $stt->execute();
}

/** Upgrades the stored password when possible; never blocks a valid login. */
function app_try_store_password_hash(PDO $db, string $login_id, string $password): void
{
    try {
        if (app_password_column_fits_hash($db)) {
            app_store_password_hash($db, $login_id, $password);
        }
    } catch (Throwable $e) {
        error_log('Password hash upgrade failed for ' . $login_id . ': ' . $e->getMessage());
    }
}
