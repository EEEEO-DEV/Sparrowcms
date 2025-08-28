<?php
declare(strict_types=1);
namespace Sparrow\Compat\Typecho\Widget;
use function url; use function db;

final class Archive extends Base
{
    private array $list; private bool $isSingle; private int $i=-1; public array $context=[];
    public function __construct(string $themePath, array $list, bool $isSingle=false, array $context=[]) { parent::__construct($themePath,$context); $this->list=$list; $this->isSingle=$isSingle; }
    public function have(): bool { return $this->i + 1 < count($this->list); }
    public function next(): void { $this->i++; $this->context = $this->cur(); }
    private function cur(): array { return $this->list[$this->i] ?? ($this->list[0] ?? []); }
    public function id(): int { return (int)($this->cur()['id'] ?? 0); }
    public function title(): void { echo htmlspecialchars((string)($this->cur()['title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
    public function content(): void { echo (string)($this->cur()['content'] ?? ''); }
    public function date(string $fmt='Y-m-d H:i'): void { $d=strtotime((string)($this->cur()['created_at'] ?? 'now')); echo date($fmt,$d?:time()); }
    public function permalink(): void { echo (string)($this->cur()['permalink'] ?? url('/')); }
    public function excerpt(int $len=200): void { $raw=strip_tags((string)($this->cur()['content'] ?? '')); $s=mb_substr($raw,0,$len); echo htmlspecialchars($s.(mb_strlen($raw)>$len?'...':''), ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
    public function categories(string $glue=', '): void {
        $id=$this->id(); if(!$id){echo '' ;return;} $pdo=db(); $stmt=$pdo->prepare("SELECT m.name,m.slug FROM metas m JOIN relationships r ON r.mid=m.id WHERE r.cid=? AND m.type='category' ORDER BY m.name ASC"); $stmt->execute([$id]);
        $names=array_map(fn($row)=>'<a href="'.url('/category/'.$row['slug']).'">'.htmlspecialchars($row['name']).'</a>',$stmt->fetchAll()); echo implode($glue,$names);
    }
    public function tags(string $glue=', '): void {
        $id=$this->id(); if(!$id){echo '' ;return;} $pdo=db(); $stmt=$pdo->prepare("SELECT m.name,m.slug FROM metas m JOIN relationships r ON r.mid=m.id WHERE r.cid=? AND m.type='tag' ORDER BY m.name ASC"); $stmt->execute([$id]);
        $names=array_map(fn($row)=>'<a href="'.url('/tag/'.$row['slug']).'">'.htmlspecialchars($row['name']).'</a>',$stmt->fetchAll()); echo implode($glue,$names);
    }
    public function isSingle(): bool { return $this->isSingle; }
}
