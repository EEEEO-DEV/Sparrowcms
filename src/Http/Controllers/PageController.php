<?php
declare(strict_types=1);
namespace Sparrow\Http\Controllers;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use function view; use function db;

final class PageController
{
    public function show(Request $request, array $vars): Response
    {
        $slug = (string)$vars['slug'];
        $stmt = db()->prepare("SELECT * FROM posts WHERE type='page' AND slug=? AND status='publish' ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$slug]); $page = $stmt->fetch();
        if (!$page) return new Response('Page not found', 404);
        return view('page.html.twig', ['page'=>$page]);
    }
}
