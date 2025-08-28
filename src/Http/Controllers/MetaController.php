<?php
declare(strict_types=1);
namespace Sparrow\Http\Controllers;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use function db; use function view; use function config;

final class MetaController
{
    public function category(Request $request, array $vars): Response
    {
        $slug = $vars['slug']; $pdo = db();
        $m = $pdo->prepare("SELECT * FROM metas WHERE slug=? AND type='category'"); $m->execute([$slug]); $meta=$m->fetch();
        if (!$meta) return new Response('分类不存在', 404);
        $stmt = $pdo->prepare("SELECT p.* FROM posts p JOIN relationships r ON r.cid=p.id WHERE r.mid=? AND p.status='publish' ORDER BY p.created_at DESC");
        $stmt->execute([$meta['id']]); $posts = $stmt->fetchAll();
        return view('index.html.twig', ['posts'=>$posts, 'meta'=>$meta, 'page'=>1, 'pages'=>1]);
    }

    public function tag(Request $request, array $vars): Response
    {
        $slug = $vars['slug']; $pdo = db();
        $m = $pdo->prepare("SELECT * FROM metas WHERE slug=? AND type='tag'"); $m->execute([$slug]); $meta=$m->fetch();
        if (!$meta) return new Response('标签不存在', 404);
        $stmt = $pdo->prepare("SELECT p.* FROM posts p JOIN relationships r ON r.cid=p.id WHERE r.mid=? AND p.status='publish' ORDER BY p.created_at DESC");
        $stmt->execute([$meta['id']]); $posts = $stmt->fetchAll();
        return view('index.html.twig', ['posts'=>$posts, 'meta'=>$meta, 'page'=>1, 'pages'=>1]);
    }
}
