<?php
declare(strict_types=1);
namespace Sparrow\Core;

final class PluginManager
{
    private static array $plugins = [];

    public static function boot(): void
    {
        self::$plugins = [];
        foreach (self::enabled() as $name) {
            $class = "\\Plugins\\{$name}\\{$name}";
            $file = dirname(__DIR__, 2) . "/plugins/{$name}/{$name}.php";
            if (is_file($file)) {
                require_once $file;
                if (class_exists($class) && method_exists($class, 'register')) {
                    // ✅ 支持实例化调用
                    $plugin = new $class();
                    $plugin->register();
                    self::$plugins[$name] = $plugin;
                }
            }
        }
    }

    /** 获取所有插件目录 */
    public static function all(): array
    {
        $dir = dirname(__DIR__, 2) . '/plugins';
        $list = [];
        foreach (scandir($dir) as $f) {
            if ($f === '.' || $f === '..') continue;
            if (is_dir($dir . '/' . $f)) $list[] = $f;
        }
        return $list;
    }

    /** 兼容旧调用 */
    public static function listAll(): array
    {
        return self::all();
    }

    public static function enabled(): array
    {
        $row = DB::pdo()->prepare("SELECT v FROM options WHERE k=?");
        $row->execute(['plugins.enabled']);
        $val = $row->fetchColumn();
        return $val ? json_decode($val, true) : [];
    }

    public static function setEnabled(array $plugins): void
    {
        $json = json_encode($plugins);
        $pdo = DB::pdo();
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($driver === 'mysql') {
            $stmt = $pdo->prepare("INSERT INTO options (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v=VALUES(v)");
        } else {
            $stmt = $pdo->prepare("INSERT OR REPLACE INTO options (k, v) VALUES (?, ?)");
        }
        $stmt->execute(['plugins.enabled', $json]);
    }

    public static function isEnabled(string $name): bool
    {
        return in_array($name, self::enabled(), true);
    }

    public static function toggle(string $name): void
    {
        $enabled = self::enabled();
        if (in_array($name, $enabled, true)) {
            $enabled = array_values(array_diff($enabled, [$name]));
        } else {
            $enabled[] = $name;
        }
        self::setEnabled($enabled);
    }

    public static function plugins(): array
    {
        return self::$plugins;
    }
}
