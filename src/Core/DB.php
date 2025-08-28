<?php
declare(strict_types=1);
namespace Sparrow\Core;

use PDO;
use PDOException;

final class DB
{
    private static ?PDO $pdo = null;

    public static function boot(): void
    {
        $dsn = Config::get('db.dsn');
        $user = Config::get('db.user');
        $pass = Config::get('db.pass');
        try {
            self::$pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $e) {
            die('DB connection failed: ' . $e->getMessage());
        }
    }

    public static function pdo(): PDO
    {
        return self::$pdo;
    }
}
