<?php

use Sparrow\Core\Config;
use Sparrow\Core\View;
use Sparrow\Core\DB;
use Symfony\Component\HttpFoundation\Response;

if (!function_exists('base_path')) {
    function base_path(string $path = ''): string {
        $base = dirname(__DIR__);
        return $path ? $base . DIRECTORY_SEPARATOR . $path : $base;
    }
}

if (!function_exists('env')) {
    function env(string $key, $default = null) {
        $val = $_ENV[$key] ?? getenv($key);
        return $val !== false && $val !== null ? $val : $default;
    }
}

if (!function_exists('config')) {
    function config(string $key, $default = null) {
        return Config::get($key, $default);
    }
}

if (!function_exists('view')) {
    function view(string $template, array $data = []): Response {
        $html = View::render($template, $data);
        return new Response($html);
    }
}

if (!function_exists('redirect')) {
    function redirect(string $to, int $status = 302): Response {
        return new Response('', $status, ['Location' => $to]);
    }
}

if (!function_exists('url')) {
    function url(string $path = '/'): string {
        $root = rtrim((string)env('SITE_URL', ''), '/');
        return $root . '/' . ltrim($path, '/');
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string {
        if (empty($_SESSION['_token'])) {
            $_SESSION['_token'] = bin2hex(random_bytes(16));
        }
        return $_SESSION['_token'];
    }
}

if (!function_exists('verify_csrf')) {
    function verify_csrf(string $token): bool {
        return isset($_SESSION['_token']) && hash_equals($_SESSION['_token'], $token);
    }
}

if (!function_exists('db')) {
    function db(): PDO {
        return DB::pdo();
    }
}

if (!function_exists('slugify')) {
    function slugify(string $s): string {
        $s = strtolower(trim($s));
        // 保留中文（\p{Han}）、英数、连字符与空白，其他替换为连字符
        $s = preg_replace('/[^a-z0-9\-\s\p{Han}]+/u', '-', $s);
        $s = preg_replace('/\s+/', '-', $s);
        $s = preg_replace('/-+/', '-', $s);
        return trim($s, '-');
    }
}

if (!function_exists('theme_option')) {
    function theme_option(string $key, $default = null) {
        $k = 'theme:' . Config::get('theme') . ':' . $key;
        $stmt = db()->prepare("SELECT v FROM options WHERE k=?");
        $stmt->execute([$k]);
        $row = $stmt->fetch();
        return $row ? $row['v'] : $default;
    }
}

/**
 * ✅ 新增：统一生成文章 URL（与固定链接结构兼容）
 * - 从 .env 读取 PERMALINK_STRUCTURE（id-slug | slug），默认 id-slug
 * - 自动拼接站点根地址（沿用现有 url() 函数）
 * - 要求 $post 至少包含 id、slug 字段
 */
if (!function_exists('post_url')) {
    function post_url(array $post): string {
        $structure = (string)env('PERMALINK_STRUCTURE', 'id-slug');
        $id   = (int)($post['id'] ?? 0);
        $slug = trim((string)($post['slug'] ?? ''), '/');

        // ✅ 兜底：slug 为空或仅为 '-' 时，回退为 post-{id}
        if ($slug === '' || $slug === '-') {
            $slug = 'post-' . $id;
        }

        if ($structure === 'slug') {
            return url('/' . $slug);
        }
        return url('/post/' . $id . '-' . $slug); // 默认 id-slug
    }
}
