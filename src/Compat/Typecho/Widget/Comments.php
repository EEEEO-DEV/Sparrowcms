<?php
declare(strict_types=1);
namespace Sparrow\Compat\Typecho\Widget;
use function db;
final class Comments extends Base
{
    private array $list; private int $i=-1;
    public function __construct(string $themePath, int $postId) {
        parent::__construct($themePath); $stmt=db()->prepare("SELECT * FROM comments WHERE post_id=? AND status='approved' ORDER BY created_at ASC"); $stmt->execute([$postId]); $this->list=$stmt->fetchAll();
    }
    public function have(): bool { return $this->i + 1 < count($this->list); }
    public function next(): void { $this->i++; }
    private function cur(): array { return $this->list[$this->i] ?? []; }
    public function count(): int { return count($this->list); }
    public function theId(): void { echo (int)($this->cur()['id'] ?? 0); }
    public function permalink(): void { echo '#comment-'.(int)($this->cur()['id'] ?? 0); }
    public function author(): void { echo htmlspecialchars((string)($this->cur()['author'] ?? ''), ENT_QUOTES); }
    public function authorLink(): void { $a=(string)($this->cur()['author'] ?? ''); $mail=(string)($this->cur()['email'] ?? ''); $url=$mail?'mailto:'.$mail:'#'; echo '<a href="'.htmlspecialchars($url,ENT_QUOTES).'">'.htmlspecialchars($a,ENT_QUOTES).'</a>'; }
    public function date(string $fmt='Y-m-d H:i'): void { $d=strtotime((string)($this->cur()['created_at'] ?? 'now')); echo date($fmt,$d?:time()); }
    public function content(): void { echo htmlspecialchars((string)($this->cur()['content'] ?? ''), ENT_QUOTES); }
    public function avatar(int $size=40): void { $mail=strtolower(trim((string)($this->cur()['email'] ?? ''))); $hash=md5($mail); $src='https://www.gravatar.com/avatar/'.$hash.'?s='.$size.'&d=identicon'; echo '<img src="'.htmlspecialchars($src,ENT_QUOTES).'" width="'.$size.'" height="'.$size.'" style="border-radius:50%">'; }
}
