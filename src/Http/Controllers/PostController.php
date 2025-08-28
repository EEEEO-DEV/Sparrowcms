<?php
declare(strict_types=1);
namespace Sparrow\Http\Controllers;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use function view;
use function db;
use function verify_csrf;
use function redirect;
use function config;
use function post_url;

final class PostController
{
    /**
     * /post/{id}-{slug}
     */
    public function show(Request $request, array $vars): Response
    {
        $id = (int)$vars['id'];
        return $this->showById($request, ['id' => $id]);
    }

    /**
     * /archives/{id}.html
     */
    public function showById(Request $request, array $vars): Response
    {
        $id  = (int)$vars['id'];
        $pdo = db();

        $stmt = $pdo->prepare("SELECT * FROM posts WHERE id=? AND status='publish' LIMIT 1");
        $stmt->execute([$id]);
        $post = $stmt->fetch();

        if (!$post) {
            return new Response('Post not found', 404);
        }

        // 注入 URL
        $post['url'] = post_url($post);
        $post['permalink'] = $post['url']; // 兼容老模板字段

        // 上一篇/下一篇
        [$prev, $next] = $this->adjacent($post);

        // 评论列表
        $comments = $pdo->prepare("SELECT * FROM comments WHERE post_id=? AND status='approved' ORDER BY created_at ASC");
        $comments->execute([$id]);
        $list = $comments->fetchAll() ?: [];

        // Typecho 兼容渲染
        if (config('theme.mode') === 'typecho') {
            // 兼容：Typecho 渲染器读取 permalink
            $post['permalink'] = $post['url'];
            $html = \Sparrow\Compat\Typecho\Renderer::renderArchive([$post], true);
            return new Response($html);
        }

        return view('post.html.twig', [
            'post'     => $post,
            'comments' => $list,
            'prev'     => $prev,
            'next'     => $next,
        ]);
    }

    /**
     * /{slug}
     */
    public function showBySlug(Request $request, array $vars): Response
    {
        $slug = (string)$vars['slug'];
        // 保留前缀防护，避免被通配路由吃掉
        if (in_array($slug, ['admin','api','tag','category','archives','install','page'], true)) {
            return new Response('Not Found', 404);
        }

        $pdo  = db();
        $stmt = $pdo->prepare("SELECT * FROM posts WHERE slug=? AND status='publish' ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$slug]);
        $post = $stmt->fetch();

        if (!$post) {
            return new Response('Not Found', 404);
        }

        return $this->showById($request, ['id' => (int)$post['id']]);
    }

    /**
     * 评论提交
     * POST /comment
     */
    public function comment(Request $request): Response
    {
        $data = $request->request->all();
        if (!verify_csrf($data['_token'] ?? '')) {
            return new Response('Invalid CSRF token', 400);
        }

        $postId  = (int)($data['post_id'] ?? 0);
        $author  = trim((string)($data['author'] ?? ''));
        $email   = trim((string)($data['email'] ?? ''));
        $content = trim((string)($data['content'] ?? ''));

        if (!$postId || !$author || !$content) {
            return new Response('Missing fields', 422);
        }

        // 写入评论（DATETIME 用 Y-m-d H:i:s，避免 MySQL/MariaDB 报错）
        $stmt = db()->prepare("INSERT INTO comments (post_id, author, email, content, status, ip, created_at) VALUES (?,?,?,?,?,?,?)");
        $stmt->execute([
            $postId,
            $author,
            $email,
            $content,
            'approved',
            $request->getClientIp(),
            date('Y-m-d H:i:s'),
        ]);

        // 获取文章 slug 并重定向到正确的文章页
        $p = db()->prepare("SELECT id, slug FROM posts WHERE id=? LIMIT 1");
        $p->execute([$postId]);
        $post = $p->fetch();

        if ($post) {
            $to = post_url($post);
        } else {
            // 兜底：回首页
            $to = '/';
        }

        return redirect($to);
    }

    /**
     * 取上一篇/下一篇（按 created_at）
     * 返回时注入 url 与 permalink 字段
     */
    private function adjacent(array $post): array
    {
        $pdo = db();

        $p = $pdo->prepare("SELECT id, title, slug, created_at FROM posts 
                            WHERE type='post' AND status='publish' AND created_at < ?
                            ORDER BY created_at DESC LIMIT 1");
        $p->execute([$post['created_at']]);
        $prev = $p->fetch() ?: null;

        $n = $pdo->prepare("SELECT id, title, slug, created_at FROM posts 
                            WHERE type='post' AND status='publish' AND created_at > ?
                            ORDER BY created_at ASC LIMIT 1");
        $n->execute([$post['created_at']]);
        $next = $n->fetch() ?: null;

        if ($prev) {
            $prev['url'] = post_url($prev);
            $prev['permalink'] = $prev['url'];
        }
        if ($next) {
            $next['url'] = post_url($next);
            $next['permalink'] = $next['url'];
        }

        return [$prev, $next];
    }
}
