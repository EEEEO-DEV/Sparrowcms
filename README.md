# SparrowCMS  风格轻博客（支持安装向导 / 主题 / 插件 / 市场 / 兼容层）

- 运行环境：**PHP 8.0+**，**MySQL/MariaDB 10.0+**（或 SQLite），**Nginx 任意版本**（兼容宝塔面板）。
- 生态特性：主题（Twig + Typecho 兼容 PHP 模板）、插件（Sparrow/Typecho 双风格）、插件市场（JSON 索引 + SHA256 校验）、Typecho 数据导入（含附件记录）、固定链接规则。
- 内容功能：分页、上一篇/下一篇、归档页、独立页面（page）。

## 快速开始（宝塔/Nginx）

1. 面板中新建网站，PHP 版本选择 8.0 或以上，创建站点目录并将本项目上传解压到站点根。
2. 运行 `composer install`（或在本地装好 vendor 再上传）。
3. Nginx 伪静态（示例）：
   
   ```nginx
   location / {
       try_files $uri /index.php?$query_string;
   }
   
   ```
4. 访问站点首页，会自动进入 `/install` 安装引导。按提示完成数据库与站点初始化（可选：一键导入 Typecho 数据）。

## 固定链接

在 `.env` 设置 `PERMALINK_STRUCTURE`：

- `id-slug` → `/post/{id}-{slug}`（默认）
- `slug` → `/{slug}`（注意避免与 `/admin`、`/tag` 等冲突）
- `id-html` → `/archives/{id}.html`

## 主题配置与前端自定义器

- 每个主题目录可放置 `theme.json`，定义 Schema：
  
  ```json
  { "fields": [
    {"key":"primary_color","label":"主色","type":"text","default":"#111"},
    {"key":"hero_html","label":"头部 HTML","type":"textarea"}
  ]}
  ```
- 后台：**主题定制** 可编辑并保存到 `options`（键名 `theme:<theme>:<key>`）；模板中使用 `{{ theme_option('primary_color','#111') }}` 读取。

## 插件市场（在线安装/升级）

- 市场索引为 JSON：
  
  ```json
  {"packages":[{"name":"HelloFooter","version":"1.0.0","zip_url":"https://.../HelloFooter.zip","sha256":"<hex>"}]}
  ```
- 后台「插件市场」填入索引 URL，输入包名安装。安装过程会校验 `sha256`，解压到 `plugins/`。

## Typecho 导入

- 安装向导或后台工具可从 Typecho MySQL 导入：用户/文章/页面/分类/标签/评论/附件记录（附件文件路径保留）。
- 缩略图：本项目提供附件记录表，可在主题或插件侧根据 `attachments` 生成缩略图（GD/Imagick）。

## 开发说明

- 路由：FastRoute；视图：Twig（Typecho 兼容模式使用 PHP 模板）；数据库：PDO。
- 目录：
  - `public/`：入口（index.php）
  - `src/`：内核与控制器
  - `themes/`：主题（默认 Twig；`typecho_demo` 为 PHP 模板）
  - `plugins/`：插件（示例 `HelloFooter`、`TypechoHello`）
  - `market/`：示例市场索引与包（离线体验）
  - `storage/`：数据存储
