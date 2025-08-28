<?php
declare(strict_types=1);
namespace Sparrow\Compat\Typecho;
use Sparrow\Core\ThemeManager;
use Sparrow\Compat\Typecho\Widget\Archive as ArchiveWidget;

final class Renderer
{
    public static function themePath(?string $theme=null): string { return ThemeManager::path($theme); }
    public static function renderArchive(array $posts, bool $isSingle=false): string
    {
        $theme = self::themePath();
        $widget = new ArchiveWidget($theme, $posts, $isSingle);
        ob_start();
        $file = $isSingle ? ($theme.'/post.php') : ($theme.'/index.php');
        if (!is_file($file)) $file = $theme.'/index.php';
        include $file;
        return (string)ob_get_clean();
    }
}
