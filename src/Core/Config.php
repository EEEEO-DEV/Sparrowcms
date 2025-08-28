<?php
declare(strict_types=1);
namespace Sparrow\Core;

final class Config
{
    private static array $data = [];

    public static function boot(): void
    {
        self::$data = [
            'site.name' => getenv('SITE_NAME') ?: 'SparrowCMS',
            'site.url' => rtrim(getenv('SITE_URL') ?: 'http://localhost:8000', '/'),
            'db.dsn' => getenv('DB_DSN') ?: 'sqlite:' . dirname(__DIR__, 2) . '/storage/sparrow.sqlite',
            'db.user' => getenv('DB_USER') ?: null,
            'db.pass' => getenv('DB_PASS') ?: null,
            'theme' => getenv('DEFAULT_THEME') ?: 'default',
            'theme.mode' => getenv('THEME_MODE') ?: 'twig',
            'app.env' => getenv('APP_ENV') ?: 'production',
            'installed' => getenv('APP_INSTALLED') ?: '0',
            'permalink' => getenv('PERMALINK_STRUCTURE') ?: 'id-slug',
            'page.size' => 10,
        ];
    }

    public static function get(string $key, $default = null)
    {
        return self::$data[$key] ?? $default;
    }

    public static function set(string $key, $value): void
    {
        self::$data[$key] = $value;
    }
}
