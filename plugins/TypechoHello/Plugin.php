<?php
declare(strict_types=1);
namespace Plugins\TypechoHello;
use Sparrow\Compat\Typecho\Plugin as TPlugin;
class Plugin
{
    public static function activate(): void
    {
        TPlugin::factory('Widget_Archive')->header = [self::class, 'onHeader'];
        TPlugin::factory('Widget_Archive')->footer = [self::class, 'onFooter'];
    }
    public static function onHeader($widget): void { echo "<!-- TypechoHello header hook -->"; }
    public static function onFooter($widget): void { echo "<!-- TypechoHello footer hook -->"; }

    public static function config(): array
    {
        return [
            'fields' => [
                ['key'=>'note','label'=>'页脚注释','type'=>'text','default'=>'Hello!','help'=>'页脚追加文本'],
                ['key'=>'position','label'=>'插入位置','type'=>'select','options'=>[
                    ['label'=>'仅页脚','value'=>'footer'],['label'=>'仅页头','value'=>'header'],['label'=>'两者','value'=>'both']
                ],'default'=>'both'],
            ],
        ];
    }
}
