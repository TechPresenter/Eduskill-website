<?php
/**
 * Database — a single lazy PDO connection + small query helpers used everywhere.
 *
 * The two SET statements are the reason this isn't a bare `new PDO`: MariaDB (which XAMPP and most
 * shared hosts run) ships WITHOUT strict mode, so an over-long string silently truncates instead of
 * erroring — a data-loss bug that hides in testing. We force STRICT mode, and pin the timezone to
 * IST as a numeric offset (the named 'Asia/Kolkata' needs mysql tz tables that aren't loaded).
 */

declare(strict_types=1);

defined('ESK') || exit('No direct access.');

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ]);
    } catch (PDOException $e) {
        // Never leak the DSN/credentials to the browser.
        error_log('[DB] ' . $e->getMessage());
        http_response_code(500);
        exit(APP_DEBUG ? 'DB connection failed: ' . htmlspecialchars($e->getMessage()) : 'Database connection failed.');
    }

    $pdo->exec("SET SESSION sql_mode='STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION,NO_ZERO_DATE,NO_ZERO_IN_DATE'");
    $pdo->exec("SET time_zone='+05:30'");
    return $pdo;
}

/** @return array<int,array<string,mixed>> */
function db_all(string $sql, array $params = []): array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/** @return array<string,mixed>|null */
function db_one(string $sql, array $params = []): ?array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

function db_val(string $sql, array $params = []): mixed
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

function db_exec(string $sql, array $params = []): int
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount();
}

function db_insert(string $sql, array $params = []): int
{
    db_exec($sql, $params);
    return (int) db()->lastInsertId();
}

function db_transaction(callable $work): mixed
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $result = $work($pdo);
        $pdo->commit();
        return $result;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
