<?php
declare(strict_types=1);

namespace Bulbula\Core;

use PDO;
use PDOException;

/**
 * Shared-Hosting Safe PDO Database Wrapper.
 * - Uses non-persistent connections (`PDO::ATTR_PERSISTENT => false`) so shared hosting
 *   `max_user_connections` is never exhausted by hung idle processes.
 * - Disables emulated prepares (`PDO::ATTR_EMULATE_PREPARES => false`) for true SQLi defense.
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        $driver   = (string) Config::get('database.driver', 'mysql');
        $host     = (string) Config::get('database.host', '127.0.0.1');
        $port     = (int) Config::get('database.port', 3306);
        $dbName   = (string) Config::get('database.database', 'bulbula_v1');
        $username = (string) Config::get('database.username', 'root');
        $password = (string) Config::get('database.password', '');
        $charset  = (string) Config::get('database.charset', 'utf8mb4');

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_PERSISTENT         => false, // CRITICAL on shared hosting: never hold open connections
        ];

        if ($driver === 'sqlite') {
            $sqlitePath = BULBULA_ROOT . '/' . ltrim((string) Config::get('database.sqlite_fallback_path', 'storage/bulbula_v1.sqlite'), '/');
            if (!is_file($sqlitePath) && is_file(BULBULA_ROOT . '/database/init_sqlite.php')) {
                require BULBULA_ROOT . '/database/init_sqlite.php';
            }
            self::$pdo = new PDO('sqlite:' . $sqlitePath, null, null, $options);
            self::$pdo->exec('PRAGMA foreign_keys = ON; PRAGMA journal_mode = WAL;');
            return self::$pdo;
        }

        try {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $dbName, $charset);
            self::$pdo = new PDO($dsn, $username, $password, $options);
        } catch (PDOException $e) {
            // Automatic fallback to local SQLite in dev/preview if MySQL daemon is not running
            if ((string) Config::get('app.env', 'production') !== 'production') {
                $sqlitePath = BULBULA_ROOT . '/' . ltrim((string) Config::get('database.sqlite_fallback_path', 'storage/bulbula_v1.sqlite'), '/');
                if (!is_file($sqlitePath) && is_file(BULBULA_ROOT . '/database/init_sqlite.php')) {
                    require BULBULA_ROOT . '/database/init_sqlite.php';
                }
                self::$pdo = new PDO('sqlite:' . $sqlitePath, null, null, $options);
                self::$pdo->exec('PRAGMA foreign_keys = ON; PRAGMA journal_mode = WAL;');
                return self::$pdo;
            }
            throw $e;
        }

        return self::$pdo;
    }

    public static function fetchAll(string $sql, array $params = []): array
    {
        $stmt = self::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = self::connection()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public static function execute(string $sql, array $params = []): int
    {
        $stmt = self::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public static function insert(string $sql, array $params = []): int
    {
        $pdo = self::connection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $pdo->lastInsertId();
    }

    public static function transaction(callable $callback): mixed
    {
        $pdo = self::connection();
        $pdo->beginTransaction();
        try {
            $result = $callback($pdo);
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function disconnect(): void
    {
        self::$pdo = null;
    }
}
