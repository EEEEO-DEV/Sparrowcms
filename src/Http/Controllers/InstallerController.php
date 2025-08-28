<?php
declare(strict_types=1);
namespace Sparrow\Http\Controllers;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class InstallerController
{
    public function index(Request $request): Response
    {
        if ($request->getMethod()==='POST') {
            $d = $request->request->all();
            $site = trim((string)($d['site_name'] ?? 'SparrowCMS'));
            $url  = trim((string)($d['site_url'] ?? 'http://localhost:8000'));
            $mode = (string)($d['theme_mode'] ?? 'twig');
            $theme= (string)($d['theme'] ?? 'default');
            $permalink = (string)($d['permalink'] ?? 'id-slug');

            $driver=(string)($d['db_driver'] ?? 'sqlite');
            if ($driver==='sqlite') {
                $dsn = 'sqlite:' . dirname(__DIR__, 3) . '/storage/sparrow.sqlite';
                $user = null; $pass = null;
            } else {
                $host = (string)($d['db_host'] ?? '127.0.0.1');
                $port = (string)($d['db_port'] ?? '3306');
                $name = (string)($d['db_name'] ?? 'sparrow');
                $user = (string)($d['db_user'] ?? '');
                $pass = (string)($d['db_pass'] ?? '');
                $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
            }

            $admin_user = (string)($d['admin_user'] ?? 'admin');
            $admin_pass = (string)($d['admin_pass'] ?? 'admin');

            $ty_dsn = (string)($d['ty_dsn'] ?? '');
            $ty_user= (string)($d['ty_user'] ?? '');
            $ty_pass= (string)($d['ty_pass'] ?? '');
            $ty_prefix = (string)($d['ty_prefix'] ?? 'typecho_');

            $errors = $this->checkEnv();
            if ($errors) return $this->view(['errors'=>$errors,'prefill'=>$d]);

            try {
                // write .env
                $env = "APP_ENV=production\nAPP_INSTALLED=1\nAPP_KEY=" . bin2hex(random_bytes(16)) . "\nSITE_URL={$url}\nSITE_NAME={$site}\nDEFAULT_THEME={$theme}\nTHEME_MODE={$mode}\nDB_DSN={$dsn}\nDB_USER={$user}\nDB_PASS={$pass}\nPERMALINK_STRUCTURE={$permalink}\n";
                file_put_contents(dirname(__DIR__, 3) . '/.env', $env);

                // init DB & schema
                $pdo = new \PDO($dsn, $user?:null, $pass?:null, [\PDO::ATTR_ERRMODE=>\PDO::ERRMODE_EXCEPTION, \PDO::ATTR_DEFAULT_FETCH_MODE=>\PDO::FETCH_ASSOC]);
                foreach (array_filter(array_map('trim', explode(';', $this->schemaSql($dsn)))) as $stmt) { if ($stmt) $pdo->exec($stmt); }

                // seed admin
                $exists = $pdo->query("SELECT COUNT(*) AS c FROM users")->fetch()['c'] ?? 0;
                if ((int)$exists===0) {
                    $hash = password_hash($admin_pass, PASSWORD_DEFAULT);
                    $pdo->prepare("INSERT INTO users (username, password_hash, created_at) VALUES (?, ?, ?)")
                        ->execute([$admin_user, $hash, date('Y-m-d H:i:s')]);
                }

                // optional: import typecho
                if ($ty_dsn) $this->importTypecho($pdo, $ty_dsn, $ty_user, $ty_pass, $ty_prefix);

                return new Response('<meta http-equiv="refresh" content="0;url=/" />安装完成，正在跳转首页...', 200, ['Content-Type'=>'text/html; charset=utf-8']);
            } catch (\Throwable $e) {
                return $this->view(['errors'=>['安装失败：'.$e->getMessage()],'prefill'=>$d]);
            }
        }
        return $this->view();
    }

    private function view(array $data=[]): Response
    {
        $errors = $data['errors'] ?? [];
        $prefill = $data['prefill'] ?? [];
        ob_start(); include dirname(__DIR__, 3) . '/themes/default/installer.php'; $html=(string)ob_get_clean();
        return new Response($html);
    }

    private function checkEnv(): array
    {
        $errors=[];
        if (version_compare(PHP_VERSION,'8.0.0','<')) $errors[]='需要 PHP >= 8.0';
        foreach (['pdo','mbstring'] as $ext) if (!extension_loaded($ext)) $errors[]='缺少 PHP 扩展：'.$ext;
        $storage = dirname(__DIR__, 3) . '/storage';
        if (!is_dir($storage)) @mkdir($storage, 0777, true);
        if (!is_writable($storage)) $errors[]='storage 目录不可写';
        return $errors;
    }

    private function schemaSql(string $dsn): string
    {
        $isMysql = strpos($dsn, 'mysql:') === 0;
        if ($isMysql) {
            return <<<SQL
CREATE TABLE IF NOT EXISTS options (k VARCHAR(190) PRIMARY KEY, v LONGTEXT NOT NULL);
CREATE TABLE IF NOT EXISTS users (id INT AUTO_INCREMENT PRIMARY KEY, username VARCHAR(190) UNIQUE NOT NULL, password_hash VARCHAR(255) NOT NULL, role VARCHAR(32) NOT NULL DEFAULT 'admin', created_at DATETIME NOT NULL);
CREATE TABLE IF NOT EXISTS posts (id INT AUTO_INCREMENT PRIMARY KEY, type VARCHAR(16) NOT NULL DEFAULT 'post', title VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, content LONGTEXT NOT NULL, status VARCHAR(16) NOT NULL DEFAULT 'publish', author_id INT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NULL, INDEX idx_posts_slug(slug));
CREATE TABLE IF NOT EXISTS comments (id INT AUTO_INCREMENT PRIMARY KEY, post_id INT NOT NULL, author VARCHAR(191) NOT NULL, email VARCHAR(191), content TEXT NOT NULL, status VARCHAR(16) NOT NULL DEFAULT 'approved', ip VARCHAR(64), created_at DATETIME NOT NULL, INDEX idx_comments_post(post_id));
CREATE TABLE IF NOT EXISTS metas (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(191) NOT NULL, slug VARCHAR(191) NOT NULL, type ENUM('category','tag') NOT NULL, description TEXT DEFAULT '', count INT NOT NULL DEFAULT 0, UNIQUE KEY idx_metas_slug_type(slug, type));
CREATE TABLE IF NOT EXISTS relationships (cid INT NOT NULL, mid INT NOT NULL, PRIMARY KEY (cid, mid));
CREATE TABLE IF NOT EXISTS attachments (id INT AUTO_INCREMENT PRIMARY KEY, post_id INT NOT NULL, url TEXT NOT NULL, mime VARCHAR(64), created_at DATETIME NOT NULL);
SQL;
        } else {
            return <<<SQL
CREATE TABLE IF NOT EXISTS options (k TEXT PRIMARY KEY, v TEXT NOT NULL);
CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT UNIQUE NOT NULL, password_hash TEXT NOT NULL, role TEXT NOT NULL DEFAULT 'admin', created_at TEXT NOT NULL);
CREATE TABLE IF NOT EXISTS posts (id INTEGER PRIMARY KEY AUTOINCREMENT, type TEXT NOT NULL DEFAULT 'post', title TEXT NOT NULL, slug TEXT NOT NULL, content TEXT NOT NULL, status TEXT NOT NULL DEFAULT 'publish', author_id INTEGER NOT NULL, created_at TEXT NOT NULL, updated_at TEXT);
CREATE INDEX IF NOT EXISTS idx_posts_slug ON posts(slug);
CREATE TABLE IF NOT EXISTS comments (id INTEGER PRIMARY KEY AUTOINCREMENT, post_id INTEGER NOT NULL, author TEXT NOT NULL, email TEXT, content TEXT NOT NULL, status TEXT NOT NULL DEFAULT 'approved', ip TEXT, created_at TEXT NOT NULL);
CREATE TABLE IF NOT EXISTS metas (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, slug TEXT NOT NULL, type TEXT NOT NULL, description TEXT DEFAULT '', count INTEGER NOT NULL DEFAULT 0);
CREATE UNIQUE INDEX IF NOT EXISTS idx_metas_slug_type ON metas(slug, type);
CREATE TABLE IF NOT EXISTS relationships (cid INTEGER NOT NULL, mid INTEGER NOT NULL, PRIMARY KEY (cid, mid));
CREATE TABLE IF NOT EXISTS attachments (id INTEGER PRIMARY KEY AUTOINCREMENT, post_id INTEGER NOT NULL, url TEXT NOT NULL, mime TEXT, created_at TEXT NOT NULL);
SQL;
        }
    }

    private function importTypecho(\PDO $dst, string $dsn, string $user, string $pass, string $prefix): void
    {
        $src = new \PDO($dsn, $user?:null, $pass?:null, [\PDO::ATTR_ERRMODE=>\PDO::ERRMODE_EXCEPTION, \PDO::ATTR_DEFAULT_FETCH_MODE=>\PDO::FETCH_ASSOC]);

        // metas
        $midMap=[];
        foreach ($src->query("SELECT * FROM {$prefix}metas WHERE type IN ('category','tag')") as $m) {
            $slug = $m['slug'] ?: $m['name'];
            $slug = strtolower($slug);
            $row = $dst->prepare("SELECT id FROM metas WHERE slug=? AND type=?"); $row->execute([$slug, $m['type']]); $meta = $row->fetch();
            if (!$meta) { $dst->prepare("INSERT INTO metas (name, slug, type, description, count) VALUES (?,?,?,?,0)")->execute([$m['name'], $slug, $m['type'], $m['description'] ?? '']); $mid = (int)$dst->lastInsertId(); } else { $mid=(int)$meta['id']; }
            $midMap[$m['mid']] = $mid;
        }

        // posts/pages
        $cidMap=[];
        $q = $src->query("SELECT * FROM {$prefix}contents WHERE type IN ('post','page') ORDER BY created DESC");
        while ($p = $q->fetch()) {
            $type = $p['type'];
            $title = $p['title'];
            $slug = $p['slug'] ?: $title;
            $slug = preg_replace('/[^a-z0-9\-]+/i', '-', strtolower($slug));
            $content = $p['text'];
            $status = $p['status'] === 'publish' ? 'publish' : 'draft';
            $author = 1;
            $stmt = $dst->prepare("INSERT INTO posts (type, title, slug, content, status, author_id, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?)");
            $stmt->execute([$type, $title, $slug, $content, $status, $author, date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]);
            $cidMap[$p['cid']] = (int)$dst->lastInsertId();
        }

        // relationships
        foreach ($src->query("SELECT * FROM {$prefix}relationships") as $r) {
            $cid = $cidMap[$r['cid']] ?? null; $mid = $midMap[$r['mid']] ?? null;
            if ($cid && $mid) { $dst->prepare("INSERT OR IGNORE INTO relationships (cid, mid) VALUES (?, ?)")->execute([$cid, $mid]); $dst->prepare("UPDATE metas SET count=count+1 WHERE id=?")->execute([$mid]); }
        }

        // comments
        foreach ($src->query("SELECT * FROM {$prefix}comments") as $c) {
            $cid = $cidMap[$c['cid']] ?? null; if (!$cid) continue;
            $status = $c['status'] == 'approved' ? 'approved' : 'pending';
            $dst->prepare("INSERT INTO comments (post_id, author, email, content, status, ip, created_at) VALUES (?,?,?,?,?,?,?)")
                ->execute([$cid, $c['author'], $c['mail'], $c['text'], $status, $c['ip'], date('Y-m-d H:i:s')]);
        }

        // attachments
        foreach ($src->query("SELECT * FROM {$prefix}contents WHERE type='attachment'") as $a) {
            $cid = $a['cid'];
            $path = (string)$a['text'];
            $postId = $cidMap[$cid] ?? null; if (!$postId) continue;
            $dst->prepare("INSERT INTO attachments (post_id, url, mime, created_at) VALUES (?,?,?,?)")
                ->execute([$postId, $path, $a['mime'] ?? '', date('Y-m-d H:i:s')]);
        }
    }
}
