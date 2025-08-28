<?php
namespace Plugins\HelloFooter;

use Sparrow\Core\EventManager;

class HelloFooter
{
    /**
     * 插件初始化
     */
    public function register(): void
    {
        // 给页面输出追加一个 footer
        EventManager::addFilter('response_content', function ($content) {
            return $content . "\n<footer style='text-align:center; margin:20px 0; color:#666;'>"
                 . "HelloFooter 插件生效了"
                 . "</footer>";
        });
    }
}
