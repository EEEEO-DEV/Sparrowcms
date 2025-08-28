<?php
declare(strict_types=1);
namespace Sparrow\Core;

final class ThemeManager
{
    public static function current(): string
    {
        return Config::get('theme', 'default');
    }
    public static function path(?string $theme = null): string
    {
        $theme = $theme ?? self::current();
        return dirname(__DIR__, 2) . '/themes/' . $theme;
    }
}
