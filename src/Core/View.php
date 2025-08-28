<?php
declare(strict_types=1);
namespace Sparrow\Core;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

final class View
{
    private static ?Environment $twig = null;

    public static function boot(): void
    {
        if (self::$twig !== null) {
            return; // 幂等
        }

        // 1) 主题加载
        $loader = new FilesystemLoader([ ThemeManager::path() ]);

        // 2) Twig 环境
        self::$twig = new Environment($loader, [
            'cache'           => false,   // 生产可改为 storage/twig
            'autoescape'      => 'html',  // 更安全
            'strict_variables'=> false,
        ]);

        // 3) 站点名 / URL（全局变量，带默认值）
        $siteName = (string)(Config::get('site.name')
                    ?? Config::get('SITE_NAME')
                    ?? 'SparrowCMS');
        $siteUrl  = rtrim((string)(Config::get('site.url')
                    ?? Config::get('SITE_URL')
                    ?? ''), '/');

        self::$twig->addGlobal('site_name', $siteName);
        self::$twig->addGlobal('site_url',  $siteUrl);

        // 4) CSRF：同时提供全局值 & 函数（两种写法都兼容）
        self::$twig->addGlobal('csrf_token', \csrf_token());
        self::$twig->addFunction(new TwigFunction('csrf_token', function (): string {
            return \csrf_token();
        }));

        // 5) 常用函数映射到 Twig
        self::$twig->addGlobal('theme', (string)Config::get('theme', 'default'));
        self::$twig->addFunction(new TwigFunction('theme_option', '\theme_option'));
        self::$twig->addFunction(new TwigFunction('url', '\url'));
        self::$twig->addFunction(new TwigFunction('post_url', '\post_url'));
    }

    public static function render(string $template, array $data = []): string
    {
        if (self::$twig === null) {
            self::boot();
        }

        // 兜底注入（控制器未传时也安全）
        $defaults = [
            'site_name'  => (string)(Config::get('site.name') ?? Config::get('SITE_NAME') ?? 'SparrowCMS'),
            'site_url'   => rtrim((string)(Config::get('site.url') ?? Config::get('SITE_URL') ?? ''), '/'),
            'csrf_token' => \csrf_token(),
        ];

        return self::$twig->render($template, $defaults + $data);
    }
}
