<?php
declare(strict_types=1);
namespace Sparrow\Http\Controllers;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use function db;

final class ApiController
{
    public function posts(Request $request): Response
    {
        $stmt = db()->query("SELECT id, title, slug, content, created_at FROM posts WHERE type='post' AND status='publish' ORDER BY created_at DESC LIMIT 50");
        $posts = $stmt->fetchAll();
        return new Response(json_encode($posts, JSON_UNESCAPED_UNICODE), 200, ['Content-Type'=>'application/json']);
    }
    public function post(Request $request, array $vars): Response
    {
        $id = (int)$vars['id'];
        $stmt = db()->prepare("SELECT id, title, slug, content, created_at FROM posts WHERE id=? AND status='publish'");
        $stmt->execute([$id]); $post = $stmt->fetch();
        if (!$post) return new Response('{"error":"not found"}', 404, ['Content-Type'=>'application/json']);
        return new Response(json_encode($post, JSON_UNESCAPED_UNICODE), 200, ['Content-Type'=>'application/json']);
    }
}
