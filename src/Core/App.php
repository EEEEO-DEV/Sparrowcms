<?php
declare(strict_types=1);
namespace Sparrow\Core;

use Dotenv\Dotenv;
use FastRoute\Dispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use function FastRoute\simpleDispatcher;

class App
{
    public function __construct()
    {
        $envDir = dirname(__DIR__, 2);
        if (is_file($envDir . '/.env')) {
            $dotenv = Dotenv::createImmutable($envDir);
            $dotenv->safeLoad();
        }
        Config::boot();
    }

    public function run(): void
    {
        $request = Request::createFromGlobals();

        // ✅ 安装检查：直接读取 APP_INSTALLED
        $envFile = dirname(__DIR__, 2) . '/.env';
        $installed = false;
        if (is_file($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos($line, 'APP_INSTALLED=') === 0) {
                    $val = trim(substr($line, 14));
                    if ($val === '1') {
                        $installed = true;
                    }
                }
            }
        }

        $installing = !$installed;
        if ($installing && strpos($request->getPathInfo(), '/install') !== 0) {
            (new Response('', 302, ['Location' => '/install']))->send();
            return;
        }
        if (!$installing) {
            DB::boot();
            EventManager::boot();
            PluginManager::boot();
            View::boot();
        }

        $dispatcher = $this->routes();
        $routeInfo = $dispatcher->dispatch($request->getMethod(), $request->getPathInfo());
        switch ($routeInfo[0]) {
            case Dispatcher::NOT_FOUND:
                $resp = new Response('Not Found', 404);
                break;
            case Dispatcher::METHOD_NOT_ALLOWED:
                $resp = new Response('Method Not Allowed', 405);
                break;
            case Dispatcher::FOUND:
                [$class, $method] = $routeInfo[1];
                $vars = $routeInfo[2];
                $controller = new $class();
                $resp = $controller->$method($request, $vars);
                break;
            default:
                $resp = new Response('Server Error', 500);
        }

        $content = EventManager::applyFilters('response_content', $resp->getContent());
        $resp->setContent($content);
        $resp->send();
    }

    private function routes(): \FastRoute\Dispatcher
{
    return \FastRoute\simpleDispatcher(function (\FastRoute\RouteCollector $r) {
        // ===== Installer =====
        $r->addRoute(['GET','POST'], '/install', [\Sparrow\Http\Controllers\InstallerController::class, 'index']);

        // ===== Front 固定路由（静态优先）=====
        $r->addRoute('GET', '/', [\Sparrow\Http\Controllers\HomeController::class, 'index']);
        $r->addRoute('GET', '/archives', [\Sparrow\Http\Controllers\HomeController::class, 'archives']);
        $r->addRoute('GET', '/page/{slug}', [\Sparrow\Http\Controllers\PageController::class, 'show']);

        // 固定链接变体
        $r->addRoute('GET', '/post/{id:\d+}-{slug}', [\Sparrow\Http\Controllers\PostController::class, 'show']);
        $r->addRoute('GET', '/archives/{id:\d+}.html', [\Sparrow\Http\Controllers\PostController::class, 'showById']);

        // 分类/标签
        $r->addRoute('GET', '/category/{slug}', [\Sparrow\Http\Controllers\MetaController::class, 'category']);
        $r->addRoute('GET', '/tag/{slug}', [\Sparrow\Http\Controllers\MetaController::class, 'tag']);

        // 评论提交
        $r->addRoute('POST', '/comment', [\Sparrow\Http\Controllers\PostController::class, 'comment']);

        // ===== Admin 后台（不要重复定义）=====
        // 登录（合并为一条，避免重复）
        $r->addRoute(['GET','POST'], '/admin/login', [\Sparrow\Http\Controllers\AdminController::class, 'login']);

        // 控制台
        $r->addRoute('GET', '/admin', [\Sparrow\Http\Controllers\AdminController::class, 'dashboard']);

        // 文章
        $r->addRoute('GET', '/admin/posts/new', [\Sparrow\Http\Controllers\AdminController::class, 'newPost']);
        $r->addRoute('POST', '/admin/posts', [\Sparrow\Http\Controllers\AdminController::class, 'createPost']);

        // 分类 & 标签
        $r->addRoute('GET', '/admin/categories', [\Sparrow\Http\Controllers\AdminController::class, 'categories']);
        // 如果暂时没有保存接口，可以先不加下面这一行
        // $r->addRoute('POST', '/admin/categories', [\Sparrow\Http\Controllers\AdminController::class, 'saveCategory']);

        // 评论管理
        $r->addRoute('GET', '/admin/comments', [\Sparrow\Http\Controllers\AdminController::class, 'comments']);

        // 插件
        $r->addRoute('GET', '/admin/plugins', [\Sparrow\Http\Controllers\AdminController::class, 'plugins']);
        $r->addRoute('GET', '/admin/plugins/toggle', [\Sparrow\Http\Controllers\AdminController::class, 'togglePlugin']);
        $r->addRoute(['GET','POST'], '/admin/plugins/config', [\Sparrow\Http\Controllers\AdminController::class, 'pluginConfig']);
        $r->addRoute('GET', '/admin/plugins/market', [\Sparrow\Http\Controllers\AdminController::class, 'pluginMarket']);
        $r->addRoute('POST', '/admin/plugins/market/install', [\Sparrow\Http\Controllers\AdminController::class, 'pluginInstall']);

        // 主题
        $r->addRoute('GET', '/admin/themes/customize', [\Sparrow\Http\Controllers\AdminController::class, 'customize']);
        $r->addRoute('POST', '/admin/themes/customize', [\Sparrow\Http\Controllers\AdminController::class, 'customizeSave']);

        // 站点设置
        $r->addRoute(['GET','POST'], '/admin/settings', [\Sparrow\Http\Controllers\AdminController::class, 'settings']);

        // ===== API =====
        $r->addRoute('GET', '/api/posts', [\Sparrow\Http\Controllers\ApiController::class, 'posts']);
        $r->addRoute('GET', '/api/posts/{id:\d+}', [\Sparrow\Http\Controllers\ApiController::class, 'post']);

        // ===== Catch-all 放在最后，并排除后台/保留前缀，避免遮蔽 =====
        $r->addRoute(
            'GET',
            '/{slug:(?!admin|api|tag|category|archives|install|page)[a-z0-9\-]+}',
            [\Sparrow\Http\Controllers\PostController::class, 'showBySlug']
        );
    });
}

}
