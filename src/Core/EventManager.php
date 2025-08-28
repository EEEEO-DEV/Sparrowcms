<?php
declare(strict_types=1);
namespace Sparrow\Core;

final class EventManager
{
    private static array $actions = [];
    private static array $filters = [];

    /**
     * 初始化事件系统
     */
    public static function boot(): void
    {
        self::$actions = [];
        self::$filters = [];
    }

    /**
     * 注册 Action 钩子
     */
    public static function addAction(string $hook, callable $callback, int $priority = 10): void
    {
        self::$actions[$hook][$priority][] = $callback;
    }

    /**
     * 触发 Action 钩子
     */
    public static function doAction(string $hook, ...$args): void
    {
        if (!isset(self::$actions[$hook])) return;
        ksort(self::$actions[$hook]);
        foreach (self::$actions[$hook] as $callbacks) {
            foreach ($callbacks as $cb) {
                $cb(...$args);
            }
        }
    }

    /**
     * 注册 Filter 钩子
     */
    public static function addFilter(string $hook, callable $callback, int $priority = 10): void
    {
        self::$filters[$hook][$priority][] = $callback;
    }

    /**
     * 应用 Filter 钩子
     */
    public static function applyFilters(string $hook, $value, ...$args)
    {
        if (!isset(self::$filters[$hook])) return $value;
        ksort(self::$filters[$hook]);
        $v = $value;
        foreach (self::$filters[$hook] as $callbacks) {
            foreach ($callbacks as $cb) {
                $v = $cb($v, ...$args);
            }
        }
        return $v;
    }
}
