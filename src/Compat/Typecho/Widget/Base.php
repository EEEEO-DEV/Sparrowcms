<?php
declare(strict_types=1);
namespace Sparrow\Compat\Typecho\Widget;
use Sparrow\Compat\Typecho\Plugin;

abstract class Base
{
    public string $themePath;
    public array $context;
    public function __construct(string $themePath, array $context = []) { $this->themePath = rtrim($themePath, '/'); $this->context = $context; }
    public function need(string $file): void { $p = $this->themePath . '/' . ltrim($file, '/'); if (is_file($p)) include $p; }
    public function header(): void { Plugin::call('Widget_Archive', 'header', $this); }
    public function footer(): void { Plugin::call('Widget_Archive', 'footer', $this); }
}
