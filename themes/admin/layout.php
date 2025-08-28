<?php
use Sparrow\Core\Config;
$siteName = Config::get('SITE_NAME', 'SparrowCMS');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title><?php echo $siteName; ?> - 后台</title>
    <link rel="stylesheet" href="/assets/admin.css">
</head>
<body>
<div class="admin-wrapper">
    <!-- 左侧导航 -->
    <aside class="admin-sidebar">
        <div class="admin-logo">
            <?php echo $siteName; ?>
        </div>
        <nav class="admin-nav">
            <ul>
                <li><a href="/admin">控制台</a></li>
                <li><a href="/admin/posts/new">文章管理</a></li>
                <li><a href="/admin/categories">分类 & 标签</a></li>
                <li><a href="/admin/comments">评论管理</a></li>
                <li><a href="/admin/plugins">插件管理</a></li>
                <li><a href="/admin/plugins/market">插件市场</a></li>
                <li><a href="/admin/themes/customize">主题定制</a></li>
                <li><a href="/admin/settings">站点设置</a></li>
            </ul>
        </nav>
    </aside>

    <!-- 主内容 -->
    <main class="admin-main">
        <header class="admin-header">
            <div class="admin-title">后台管理</div>
            <div class="admin-user">
                欢迎，管理员 | <a href="/admin/logout">退出</a>
            </div>
        </header>

        <section class="admin-content">
            <?php echo $content ?? ''; ?>
        </section>
    </main>
</div>
</body>
</html>
