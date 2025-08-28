<?php
declare(strict_types=1);
namespace Sparrow\Http\Controllers;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use function view; use function db; use function redirect; use function verify_csrf; use function slugify; use function csrf_token;
use Sparrow\Core\PluginManager;
use Sparrow\Core\Config;

final class AdminController
{
    public function login(Request $request): Response
    {
        if ($request->getMethod()==='POST') {
            $data=$request->request->all();
            if (!verify_csrf($data['_token'] ?? '')) return new Response('Invalid CSRF token',400);
            $username=trim((string)($data['username'] ?? ''));
            $password=(string)($data['password'] ?? '');
            $stmt=db()->prepare("SELECT * FROM users WHERE username=?"); $stmt->execute([$username]); $user=$stmt->fetch();
            if ($user && password_verify($password, $user['password_hash'])) { $_SESSION['uid']=$user['id']; return redirect('/admin'); }
            return view('admin_login.html.twig', ['error'=>'用户名或密码错误']);
        }
        return view('admin_login.html.twig');
    }

    public function dashboard(Request $request): Response
    {
        $this->ensureAuth();
        $count = db()->query("SELECT COUNT(*) as c FROM posts WHERE type='post'")->fetch()['c'] ?? 0;

        ob_start(); ?>
        <div class="card">
            <h2>控制台</h2>
            <p>文章数量：<?php echo (int)$count; ?></p>
            <p>
                <a href="/admin/posts/new">发布新文章</a> |
                <a href="/admin/plugins">插件管理</a> |
                <a href="/admin/plugins/market">插件市场</a> |
                <a href="/admin/themes/customize">主题定制</a> |
                <a href="/admin/settings">站点设置</a>
            </p>
        </div>
        <?php
        $html = ob_get_clean();
        return new Response($this->renderLayout($html));
    }

    public function newPost(Request $request): Response
    {
        $this->ensureAuth();
        $token = csrf_token();
        ob_start(); ?>
        <div class="card">
            <h2>发布新文章</h2>
            <form method="post" action="/admin/posts">
                <input type="hidden" name="_token" value="<?php echo htmlspecialchars($token); ?>">
                <label>类型
                    <select name="type">
                        <option value="post">文章</option>
                        <option value="page">独立页面</option>
                    </select>
                </label><br><br>
                <label>标题<br><input type="text" name="title" style="width:100%;"></label><br><br>
                <label>自定义短链（留空自动生成）<br><input type="text" name="slug" style="width:100%;"></label><br><br>
                <label>内容<br><textarea name="content" rows="12" style="width:100%;"></textarea></label><br><br>
                <label>分类（逗号分隔）<br><input type="text" name="categories" style="width:100%;"></label><br><br>
                <label>标签（逗号分隔）<br><input type="text" name="tags" style="width:100%;"></label><br><br>
                <button type="submit">发布</button>
            </form>
        </div>
        <?php
        $html = ob_get_clean();
        return new Response($this->renderLayout($html));
    }

    public function createPost(Request $request): Response
{
    $this->ensureAuth();
    $data=$request->request->all();
    if (!verify_csrf($data['_token'] ?? '')) return new Response('Invalid CSRF token',400);

    $type = in_array(($data['type'] ?? 'post'), ['post','page'], true) ? $data['type'] : 'post';
    $title=trim((string)($data['title'] ?? ''));
    $slugSrc = (string)($data['slug'] ?? '');
    // 1) 清洗 slug（只保留英文、数字、连字符）
    $slug=strtolower(preg_replace('/[^a-z0-9\-]+/i','-', $slugSrc ?: $title));
    $slug = trim($slug, '-');

    $content=(string)($data['content'] ?? '');
    if (!$title || !$content) return new Response('请输入标题和内容',422);

    // 2) 先插入（允许 slug 暂时为空），拿到 ID
    $stmt=db()->prepare("INSERT INTO posts (type, title, slug, content, status, author_id, created_at) VALUES (?,?,?,?,?,?,?)");
    $stmt->execute([$type, $title, $slug ?: '', $content, 'publish', (int)$_SESSION['uid'], date('Y-m-d H:i:s')]);
    $postId = (int)db()->lastInsertId();

    // 3) 若 slug 为空（如中文标题清洗后为空），回退为基于 ID 的 slug
    if ($slug === '' || $slug === null) {
        $slug = 'post-' . $postId;
    }

    // 4) 唯一性兜底（避免重名）
    $base = $slug;
    $n = 2;
    $q = db()->prepare("SELECT COUNT(*) FROM posts WHERE slug=? AND id<>?");
    while (true) {
        $q->execute([$slug, $postId]);
        $exists = (int)$q->fetchColumn();
        if ($exists === 0) break;
        $slug = $base . '-' . $n;
        $n++;
    }

    // 5) 回写最终 slug
    db()->prepare("UPDATE posts SET slug=? WHERE id=?")->execute([$slug, $postId]);

    // 6) 分类/标签关系
    $this->assignMetas($postId, (string)($data['categories'] ?? ''), 'category');
    $this->assignMetas($postId, (string)($data['tags'] ?? ''), 'tag');

    return redirect('/admin');
}

    public function plugins(Request $request): Response
    {
        $this->ensureAuth();
        $plugins = PluginManager::listAll();
        ob_start(); ?>
        <div class="card">
            <h2>插件管理</h2>
            <ul>
                <?php foreach ($plugins as $p): ?>
                    <li><?php echo htmlspecialchars($p); ?>
                        <?php if (PluginManager::isEnabled($p)): ?>
                            [已启用] <a href="/admin/plugins/toggle?name=<?php echo urlencode($p); ?>">停用</a>
                            | <a href="/admin/plugins/config?name=<?php echo urlencode($p); ?>">配置</a>
                        <?php else: ?>
                            [未启用] <a href="/admin/plugins/toggle?name=<?php echo urlencode($p); ?>">启用</a>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p><a href="/admin/plugins/market">进入插件市场</a></p>
        </div>
        <?php
        $html = ob_get_clean();
        return new Response($this->renderLayout($html));
    }

    public function togglePlugin(Request $request): Response
    {
        $this->ensureAuth();
        $name = (string)$request->query->get('name','');
        $enabled=PluginManager::enabled();
        if (in_array($name,$enabled,true)) $enabled=array_values(array_filter($enabled, fn($n)=>$n!==$name)); else $enabled[]=$name;
        PluginManager::setEnabled($enabled);
        return redirect('/admin/plugins');
    }

    public function pluginConfig(Request $request): Response
    {
        $this->ensureAuth();
        $name = (string)$request->query->get('name','');
        $class = "\\Plugins\\{$name}\\Plugin";
        if (!class_exists($class) || !method_exists($class, 'config')) return new Response('该插件不支持配置',404);
        if ($request->getMethod()==='POST') {
            $data=$request->request->all(); if (!verify_csrf($data['_token'] ?? '')) return new Response('Invalid CSRF token',400); unset($data['_token']);
            foreach ($data as $k=>$v) $this->setPluginOption($name,$k,(string)$v);
            return redirect('/admin/plugins/config?name='.urlencode($name));
        }
        $fields = $class::config();
        $values = $this->getPluginOptions($name);

        $token = csrf_token();
        ob_start(); ?>
        <div class="card">
            <h2>配置插件：<?php echo htmlspecialchars($name); ?></h2>
            <form method="post">
                <input type="hidden" name="_token" value="<?php echo htmlspecialchars($token); ?>">
                <?php foreach ($fields['fields'] ?? [] as $k=>$label): ?>
                    <label><?php echo htmlspecialchars($label); ?><br>
                        <input type="text" name="<?php echo htmlspecialchars($k); ?>" value="<?php echo htmlspecialchars($values[$k] ?? ''); ?>">
                    </label><br><br>
                <?php endforeach; ?>
                <button type="submit">保存</button>
            </form>
        </div>
        <?php
        $html = ob_get_clean();
        return new Response($this->renderLayout($html));
    }

    public function pluginMarket(Request $request): Response
    {
        $this->ensureAuth();
        $token = csrf_token();
        ob_start(); ?>
        <div class="card">
            <h2>插件市场</h2>
            <form method="post" action="/admin/plugins/market/install">
                <input type="hidden" name="_token" value="<?php echo htmlspecialchars($token); ?>">
                <label>索引地址：<input type="text" name="index_url" value="/market/index.json" style="width:100%;"></label><br><br>
                <label>插件包名：<input type="text" name="package" style="width:100%;"></label><br><br>
                <button type="submit">安装插件</button>
            </form>
        </div>
        <?php
        $html = ob_get_clean();
        return new Response($this->renderLayout($html));
    }

    public function pluginInstall(Request $request): Response
    {
        $this->ensureAuth();
        $data = $request->request->all();
        if (!verify_csrf($data['_token'] ?? '')) return new Response('Invalid CSRF token',400);
        $indexUrl = (string)($data['index_url'] ?? '');
        $pkgName = (string)($data['package'] ?? '');
        $verify = function(string $data, string $hex) { return hash_equals(strtolower($hex), strtolower(hash('sha256', $data))); };

        try {
            $index = $this->fetch($indexUrl);
            $obj = json_decode($index, true);
            $pkg = null;
            foreach ($obj['packages'] ?? [] as $p) if (($p['name'] ?? '')===$pkgName) { $pkg=$p; break; }
            if (!$pkg) return new Response('找不到插件包', 404);
            $zipData = $this->fetch((string)$pkg['zip_url']);
            if (!empty($pkg['sha256']) && !$verify($zipData, (string)$pkg['sha256'])) return new Response('签名校验失败', 400);
            $tmp = sys_get_temp_dir() . '/sparrow_pkg.zip';
            file_put_contents($tmp, $zipData);
            $zip = new \ZipArchive();
            if ($zip->open($tmp) !== true) return new Response('ZIP 打开失败', 400);
            $pluginsDir = dirname(__DIR__, 3) . '/plugins';
            $zip->extractTo($pluginsDir); $zip->close();
            return redirect('/admin/plugins');
        } catch (\Throwable $e) {
            return new Response('安装失败：'.$e->getMessage(), 500);
        }
    }

    public function customize(Request $request): Response
    {
        $this->ensureAuth();
        $theme = Config::get('theme');
        $schemaFile = dirname(__DIR__, 3).'/themes/'.$theme.'/theme.json';
        $schema = is_file($schemaFile) ? json_decode(file_get_contents($schemaFile), true) : ['fields'=>[]];
        $values = $this->getThemeOptions($theme);

        $token = csrf_token();
        ob_start(); ?>
        <div class="card">
            <h2>主题定制：<?php echo htmlspecialchars($theme); ?></h2>
            <form method="post" action="/admin/themes/customize">
                <input type="hidden" name="_token" value="<?php echo htmlspecialchars($token); ?>">
                <?php foreach ($schema['fields'] ?? [] as $k=>$label): ?>
                    <label><?php echo htmlspecialchars($label); ?><br>
                        <input type="text" name="<?php echo htmlspecialchars($k); ?>" value="<?php echo htmlspecialchars($values[$k] ?? ''); ?>">
                    </label><br><br>
                <?php endforeach; ?>
                <button type="submit">保存</button>
            </form>
        </div>
        <?php
        $html = ob_get_clean();
        return new Response($this->renderLayout($html));
    }

    public function customizeSave(Request $request): Response
    {
        $this->ensureAuth();
        $data = $request->request->all(); if (!verify_csrf($data['_token'] ?? '')) return new Response('Invalid CSRF token',400); unset($data['_token']);
        $theme = Config::get('theme');
        foreach ($data as $k=>$v) $this->setThemeOption($theme, $k, (string)$v);
        return redirect('/admin/themes/customize');
    }

    /** ✅ 站点设置页 */
    public function settings(Request $request): Response
    {
        $this->ensureAuth();
        $pdo = db();
        $errors = [];
        $message = null;

        if ($request->getMethod() === 'POST') {
            $siteName = trim((string)$request->request->get('site_name'));
            $siteUrl = trim((string)$request->request->get('site_url'));
            if (!$siteName) $errors[] = '站点名称不能为空';
            if (!$siteUrl) $errors[] = '站点URL不能为空';

            if (!$errors) {
                $pdo->prepare("INSERT OR REPLACE INTO options (k, v) VALUES (?, ?)")->execute(['site.name', $siteName]);
                $pdo->prepare("INSERT OR REPLACE INTO options (k, v) VALUES (?, ?)")->execute(['site.url', $siteUrl]);
                $message = "保存成功";
            }
        }

        $stmt = $pdo->prepare("SELECT v FROM options WHERE k=?");
        $stmt->execute(['site.name']);
        $siteName = $stmt->fetchColumn() ?: Config::get('SITE_NAME','SparrowCMS');

        $stmt = $pdo->prepare("SELECT v FROM options WHERE k=?");
        $stmt->execute(['site.url']);
        $siteUrl = $stmt->fetchColumn() ?: Config::get('SITE_URL','http://localhost:8000');

        ob_start(); ?>
        <div class="card">
            <h2>站点设置</h2>
            <?php if ($errors): ?><div style="color:red;"><?php echo implode('<br>', $errors); ?></div><?php endif; ?>
            <?php if ($message): ?><div style="color:green;"><?php echo $message; ?></div><?php endif; ?>
            <form method="post">
                <label>站点名称<br><input type="text" name="site_name" value="<?php echo htmlspecialchars($siteName); ?>" style="width:100%;"></label><br><br>
                <label>站点URL<br><input type="text" name="site_url" value="<?php echo htmlspecialchars($siteUrl); ?>" style="width:100%;"></label><br><br>
                <button type="submit">保存</button>
            </form>
        </div>
        <?php
        $html = ob_get_clean();
        return new Response($this->renderLayout($html));
    }

    /** ✅ 分类 & 标签页（只读列表版） */
    public function categories(Request $request): Response
    {
        $this->ensureAuth();
        $rows = db()->query("SELECT * FROM metas WHERE type IN ('category','tag') ORDER BY id DESC")->fetchAll();

        ob_start(); ?>
        <div class="card">
            <h2>分类 & 标签</h2>
            <ul>
                <?php foreach ($rows as $row): ?>
                    <li><?php echo htmlspecialchars($row['name']); ?> (<?php echo htmlspecialchars($row['type']); ?>) · 使用次数：<?php echo (int)$row['count']; ?></li>
                <?php endforeach; ?>
            </ul>
            <p style="margin-top:10px;color:#666;">（如需新增/编辑/删除，我可以继续给你加完整的 CRUD 表单与路由。）</p>
        </div>
        <?php
        $html = ob_get_clean();
        return new Response($this->renderLayout($html));
    }

    /** ✅ 评论管理页（只读列表版） */
    public function comments(Request $request): Response
    {
        $this->ensureAuth();
        $rows = db()->query("SELECT * FROM comments ORDER BY id DESC LIMIT 50")->fetchAll();

        ob_start(); ?>
        <div class="card">
            <h2>评论管理</h2>
            <ul>
                <?php foreach ($rows as $row): ?>
                    <li>
                        <strong><?php echo htmlspecialchars($row['author']); ?></strong>
                        （<?php echo htmlspecialchars($row['email'] ?? ''); ?>）：
                        <?php echo htmlspecialchars(mb_substr($row['content'],0,50)); ?>
                        <em style="color:#999;">@ <?php echo htmlspecialchars($row['created_at'] ?? ''); ?></em>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php
        $html = ob_get_clean();
        return new Response($this->renderLayout($html));
    }

    private function ensureAuth(): void
    {
        if (empty($_SESSION['uid'])) { header('Location: /admin/login', true, 302); exit; }
    }

    private function assignMetas(int $postId, string $csv, string $type): void
    {
        $pdo = db(); $items = array_filter(array_map('trim', preg_split('/[,，]/u', $csv)));
        foreach ($items as $name) {
            $slug = slugify($name);
            $m = $pdo->prepare("SELECT * FROM metas WHERE slug=? AND type=?"); $m->execute([$slug,$type]); $meta=$m->fetch();
            $mid = $meta ? (int)$meta['id'] : (function() use ($pdo,$name,$slug,$type){ $pdo->prepare("INSERT INTO metas (name, slug, type, count) VALUES (?,?,?,0)")->execute([$name,$slug,$type]); return (int)$pdo->lastInsertId(); })();
            $pdo->prepare("INSERT OR IGNORE INTO relationships (cid, mid) VALUES (?, ?)")->execute([$postId, $mid]);
            $pdo->prepare("UPDATE metas SET count=count+1 WHERE id=?")->execute([$mid]);
        }
    }

    private function getPluginOptions(string $plugin): array
    {
        $pdo = db(); $stmt=$pdo->prepare("SELECT k,v FROM options WHERE k LIKE ?"); $stmt->execute(["plugin:{$plugin}:%"]);
        $out=[]; foreach ($stmt->fetchAll() as $row) $out[substr($row['k'], strlen("plugin:{$plugin}:"))]=$row['v']; return $out;
    }
    private function setPluginOption(string $plugin, string $key, string $value): void
    {
        $pdo = db();
        try {
            $pdo->prepare("INSERT INTO options (k,v) VALUES (?,?) ON DUPLICATE KEY UPDATE v=VALUES(v)")
                ->execute(["plugin:{$plugin}:{$key}", $value]);
        } catch (\Throwable $e) {
            $pdo->prepare("INSERT INTO options (k,v) VALUES (?,?) ON CONFLICT(k) DO UPDATE SET v=excluded.v")
                ->execute(["plugin:{$plugin}:{$key}", $value]);
        }
    }

    private function getThemeOptions(string $theme): array
    {
        $pdo = db(); $stmt=$pdo->prepare("SELECT k,v FROM options WHERE k LIKE ?"); $stmt->execute(["theme:{$theme}:%"]);
        $out=[]; foreach ($stmt->fetchAll() as $row) $out[substr($row['k'], strlen("theme:{$theme}:"))]=$row['v']; return $out;
    }
    private function setThemeOption(string $theme, string $key, string $value): void
    {
        $pdo = db();
        try {
            $pdo->prepare("INSERT INTO options (k,v) VALUES (?,?) ON DUPLICATE KEY UPDATE v=VALUES(v)")
                ->execute(["theme:{$theme}:{$key}", $value]);
        } catch (\Throwable $e) {
            $pdo->prepare("INSERT INTO options (k,v) VALUES (?,?) ON CONFLICT(k) DO UPDATE SET v=excluded.v")
                ->execute(["theme:{$theme}:{$key}", $value]);
        }
    }

    private function fetch(string $url): string
    {
        if (stripos($url, 'http')===0) {
            if (function_exists('curl_init')) {
                $ch=curl_init($url);
                curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true, CURLOPT_FOLLOWLOCATION=>true, CURLOPT_CONNECTTIMEOUT=>10, CURLOPT_TIMEOUT=>30]);
                $data=curl_exec($ch);
                if ($data===false) throw new \RuntimeException('cURL 请求失败: '.curl_error($ch));
                curl_close($ch); return (string)$data;
            }
            $data = @file_get_contents($url);
            if ($data===false) throw new \RuntimeException('无法下载: '.$url);
            return (string)$data;
        } else {
            if (!is_file($url)) throw new \RuntimeException('索引文件不存在');
            return (string)file_get_contents($url);
        }
    }

    /** ✅ 渲染后台统一布局（themes/admin/layout.php） */
    private function renderLayout(string $content): string
    {
        ob_start();
        include dirname(__DIR__, 3) . '/themes/admin/layout.php';
        return (string)ob_get_clean();
    }
}
