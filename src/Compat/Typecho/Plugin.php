<?php
declare(strict_types=1);
namespace Sparrow\Compat\Typecho;

final class Plugin
{
    private static array $hooks = [];
    public static function factory(string $component): object
    {
        return new class($component) {
            private string $component;
            public function __construct(string $component) { $this->component = $component; }
            public function __set(string $hook, $callable): void {
                \Sparrow\Compat\Typecho\Plugin::on($this->component, $hook, $callable);
            }
        };
    }
    public static function on(string $component, string $hook, $callable): void
    {
        if (is_callable($callable)) self::$hooks[$component][$hook][] = $callable;
    }
    public static function call(string $component, string $hook, ...$args): void
    {
        foreach (self::$hooks[$component][$hook] ?? [] as $cb) { $cb(...$args); }
    }
    public static function apply(string $component, string $hook, $value, ...$args)
    {
        $v = $value;
        foreach (self::$hooks[$component][$hook] ?? [] as $cb) { $v = $cb($v, ...$args); }
        return $v;
    }
}
