<!doctype html><html lang="zh-CN"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>SparrowCMS 安装向导</title>
<style>
body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,"PingFang SC","Microsoft YaHei",sans-serif;background:#f6f7f9;color:#222;margin:0}
.wrap{max-width:960px;margin:40px auto;background:#fff;padding:24px;border-radius:12px;box-shadow:0 6px 20px rgba(0,0,0,.06)} h1{margin-top:0}
.row{display:flex;gap:12px}.row>div{flex:1} input,select{width:100%;padding:10px;border:1px solid #ddd;border-radius:8px}
.btn{display:inline-block;padding:10px 14px;border-radius:8px;background:#111;color:#fff;text-decoration:none;border:0;cursor:pointer}
.muted{color:#666;font-size:13px}.error{background:#fff5f5;border:1px solid #f7d7d7;color:#c00;padding:12px;border-radius:8px;margin-bottom:12px}
</style></head><body><div class="wrap">
<h1>🚀 SparrowCMS 安装向导</h1><p class="muted">支持 SQLite 或 MySQL/MariaDB（10.0+），适配 PHP 8.0+，Nginx 任意版本。</p>
<?php if (!empty($errors)): ?><div class="error"><strong>环境检查失败：</strong><ul><?php foreach($errors as $e): ?><li><?php echo htmlspecialchars($e,ENT_QUOTES); ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form method="post" action="/install">
<h3>站点</h3>
<div class="row"><div><label>站点名称</label><input type="text" name="site_name" value="<?php echo htmlspecialchars(($prefill['site_name'] ?? 'SparrowCMS'),ENT_QUOTES); ?>"></div>
<div><label>站点 URL</label><input type="text" name="site_url" value="<?php echo htmlspecialchars(($prefill['site_url'] ?? 'http://localhost:8000'),ENT_QUOTES); ?>"></div></div>
<div class="row"><div><label>主题模式</label><select name="theme_mode"><option value="twig">Twig</option><option value="typecho">Typecho 兼容 (PHP 模板)</option></select></div>
<div><label>默认主题</label><select name="theme"><option value="default">default</option><option value="typecho_demo">typecho_demo</option></select></div></div>
<div><label>固定链接结构</label><select name="permalink"><option value="id-slug">/post/{id}-{slug}</option><option value="slug">/{slug}</option><option value="id-html">/archives/{id}.html</option></select></div>
<h3>数据库</h3>
<div class="row"><div><label>驱动</label><select name="db_driver" id="db_driver" onchange="toggleDb()"><option value="sqlite">SQLite</option><option value="mysql">MySQL/MariaDB</option></select></div></div>
<div id="mysql_fields" style="display:none">
  <div class="row"><div><label>主机</label><input type="text" name="db_host" value="127.0.0.1"></div><div><label>端口</label><input type="text" name="db_port" value="3306"></div></div>
  <div class="row"><div><label>数据库名</label><input type="text" name="db_name" value="sparrow"></div><div><label>用户名</label><input type="text" name="db_user"></div></div>
  <div><label>密码</label><input type="password" name="db_pass"></div>
</div>
<h3>管理员</h3>
<div class="row"><div><label>用户名</label><input type="text" name="admin_user" value="admin"></div><div><label>密码</label><input type="password" name="admin_pass" value="admin"></div></div>
<h3>（可选）导入 Typecho</h3>
<div><label>Typecho DSN</label><input type="text" name="ty_dsn" placeholder="mysql:host=127.0.0.1;dbname=typecho;charset=utf8mb4"></div>
<div class="row"><div><label>用户名</label><input type="text" name="ty_user"></div><div><label>密码</label><input type="password" name="ty_pass"></div></div>
<div><label>表前缀</label><input type="text" name="ty_prefix" value="typecho_"></div>
<p><button class="btn" type="submit">开始安装</button></p></form>
</div><script>
function toggleDb(){var drv=document.getElementById('db_driver').value;document.getElementById('mysql_fields').style.display=(drv==='mysql'?'block':'none');}toggleDb();
</script></body></html>
