<?php
declare(strict_types=1);
namespace Sparrow\Compat\Typecho;
use function db;

final class Options
{
    public static function get(string $key, $default = null)
    {
        $stmt = db()->prepare("SELECT v FROM options WHERE k = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        return $row ? $row['v'] : $default;
    }
    public static function set(string $key, string $value): void
    {
        $pdo = db();
        try {
            $pdo->prepare("INSERT INTO options (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v=VALUES(v)")->execute([$key, $value]);
        } catch (\Throwable $e) {
            $pdo->prepare("INSERT INTO options (k, v) VALUES (?, ?) ON CONFLICT(k) DO UPDATE SET v=excluded.v")->execute([$key, $value]);
        }
    }
}
