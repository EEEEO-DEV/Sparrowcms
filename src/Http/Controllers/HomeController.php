<?php
declare(strict_types=1);
namespace Sparrow\Http\Controllers;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use function view; 
use function db; 
use function config;
use function post_url;

final class HomeController
{
    /**
     * 首页文章列表
     */
    public function index(Request $request): Response
    {
        $page   = max(1, (int)$request->query->get('page', 1));
        $size   = (int)config('page.size', 10);
        $offset = ($page - 1) * $size;

        $pdo   = db();
        $total = (int)($pdo->query("SELECT COUNT(*) AS c FROM posts WHERE type='post' AND status='publish'")->fetch()['c'] ?? 0);

        $stmt = $pdo->prepare("SELECT * FROM posts WHERE type='post' AND status='publish' ORDER BY created_at DESC LIMIT ? OFFSET ?");
        $stmt->bindValue(1, $size,   \PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, \PDO::PARAM_INT);
        $stmt->execute();

        // 为每篇文章注入 URL（post_url），并保留兼容的 permalink 字段
        $posts = array_map([$this, 'withUrl'], $stmt->fetchAll() ?: []);

        // Typecho 兼容渲染模式
        if (config('theme.mode') === 'typecho') {
            $html = \Sparrow\Compat\Typecho\Renderer::renderArchive($posts, false);
            return new Response($html);
        }

        return view('index.html.twig', [
            'posts' => $posts,
            'page'  => $page,
            'pages' => max(1, (int)ceil($total / $size)),
        ]);
    }

    /**
     * 归档页
     */
    public function archives(Request $request): Response
    {
        $pdo  = db();
        $rows = $pdo->query("SELECT id, title, slug, created_at FROM posts WHERE type='post' AND status='publish' ORDER BY created_at DESC")->fetchAll() ?: [];

        $groups = [];
        foreach ($rows as $r) {
            // 注入 URL
            $r['url']       = post_url($r);
            $r['permalink'] = $r['url']; // 兼容老模板字段名

            $ym = date('Y-m', strtotime((string)$r['created_at']));
            $groups[$ym][] = $r;
        }

        return view('archives.html.twig', ['groups' => $groups]);
    }

    /**
     * 注入 URL（post_url），并保留兼容字段
     */
    private function withUrl(array $p): array
    {
        $u = post_url($p);
        $p['url'] = $u;
        $p['permalink'] = $u; // 兼容老模板
        return $p;
    }
}
