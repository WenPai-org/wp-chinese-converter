# wp-chinese-converter 全面审计与整治（2026-08-09）

> [CX] 本文记录 FeiCode 真源仓库的隔离审计事实；不是发布批准，也不是生产部署记录。

## 1. 范围与代码真源

- 真源：`https://feicode.com/WenPai-org/wp-chinese-converter.git`
- 基线：`a7a6b1bd1858e4dbc34c7207791a77567e6051d8`（`main`）
- 隔离分支：`codex/wp-chinese-converter-audit-20260809`
- WenPai 工作区：`/home/parallels/Projects/wp-chinese-converter-codex-audit`
- 未部署、未发布、未合并默认分支；测试只使用 WenPai 上的隔离 WordPress 6.8.3 / PHP 8.3 环境。

## 2. 功能、入口、数据流和依赖

| 功能 | 入口 | 数据流/持久化 | 外部依赖与边界 |
|---|---|---|---|
| 繁简转换 | `zhconversion()`、内容/标题/评论等过滤器 | 原文本 → 工厂 → OpenCC/MediaWiki → 对象缓存 → 输出 | `overtrue/php-opencc` 1.3.1；内置 `ZhConversion.php` |
| 语言切换 | 短代码、Gutenberg 三个动态区块、链接过滤器 | 请求路径或 `variant` → 目标变体 → Cookie/链接 → 页面过滤 | WordPress rewrite、Cookie；支持查询参数/前缀/后缀 |
| SEO | `wp_head`、SEO 插件过滤器 | 当前 URL/变体 → canonical、hreflang、meta/schema 转换 | Yoast、Rank Math、AIOSEO 兼容钩子 |
| Sitemap | 自定义 rewrite 与模板重定向 | 公开文章类型/分页 → 语言变体 XML URL | 不再禁用 WordPress 核心 sitemap |
| REST 转换 | `/wpcc/v1/convert`、`batch-convert`、只读状态路由 | 认证管理员请求 → 引擎 → JSON 响应 | WordPress Cookie/Application Password；单条 100,000 字节、批量 100 条/500,000 字节 |
| 缓存 | `WPCC_Conversion_Cache`、Modern Cache 模块 | 文本+目标+引擎 → 内存/对象缓存或 transient | Redis/Memcached 由 WordPress 对象缓存承接 |
| 搜索转换 | `posts_where` / `posts_distinct` | 搜索词扩展为简繁候选 → SQL 查询 | 大数据集上可能扩大 SQL 成本 |
| 发表时转换 | `wp_insert_post_data`（opt-in） | 编辑内容 → 指定变体 → **覆盖保存后的正文/标题** | 不可逆；现限制文章/页面并跳过自动保存、修订 |
| 多站点 | Network Settings 管理页、站点初始化/批量应用 | 网络默认/受控字段 → 站点 `wpcc_options` | 仅多站点启用；批量写入需网络管理员 |
| 缓存插件兼容 | Cache Addon 模块 | 语言/设置变化 → 调用各缓存插件清理 API | WP Super Cache、WP Rocket、LiteSpeed、W3TC 等 |

主要持久化项包括站点级 `wpcc_options`、rewrite/cache 标记及 `wpcc_cache_keys`，以及多站点的 `wpcc_network_*`、`wpcc_default_*` 选项。当前没有 `uninstall.php` 或完整 uninstall hook。

## 3. 已确认并修复的问题

| 等级 | 问题 | 修复与验证 |
|---|---|---|
| P1 | 设置保存、缓存切换和“卸载”表单可被 CSRF 触发 | 添加 capability 与 nonce 校验，输入统一 unslash/sanitize；PHP lint、单元测试通过 |
| P1 | REST 转换默认允许任何有 `read` 权限的账号执行高成本转换，且请求体无限 | 默认改为 `manage_options`（可过滤），路由和回调双重校验单条/批量体积；真实 REST：匿名 401、管理员 200、超限 400 |
| P1 | MediaWiki 表在方法局部作用域加载，除 `zh2Hans` 外的表不可见，PHP 8.3 下 `zh-tw` 会 fatal | 声明全部表为 global 并验证存在；单元转换和真实 WordPress 转换通过 |
| P1 | 转换缓存键不含引擎，切换 OpenCC/MediaWiki 后可能复用另一引擎的结果 | 缓存键加入引擎标识，调用链显式传入引擎；增加引擎隔离测试 |
| P1 | Modern Cache 命中返回元数据数组而非转换字符串，且不登记键，无法可靠清理 | 返回 `converted`、登记键、限制键注册表并清理淘汰项 |
| P1 | 查询参数模式 canonical 会删除 `variant`，把语言页 canonical 回原页 | 保留已过滤 permalink 的语言参数并用 `esc_url()` 输出 |
| P1 | Sitemap 拼接文章类型 SQL、XML URL 转义不足，并全局禁用核心 sitemap | 仅接受公开文章类型，改用 `wp_count_posts()`，XML 转义；保留核心 sitemap |
| P1 | 激活默认值只在后台文件注册，WP-CLI 激活不写 `wpcc_options`；旧默认还启用全页转换和 sitemap | 激活钩子移到主文件，统一使用安全完整默认值；WP-CLI 删除选项后重新激活确认写入，full-page 默认 0 |
| P1 | 发表时转换作用于全局保存过滤器，可能改写自定义类型、自动保存和修订 | 改为 `wp_insert_post_data`，默认只处理 post/page，跳过 autosave/revision，仍保留 opt-in 行为 |
| P2 | 依赖锁过期且 `symfony/process` 命中 CVE-2026-24739 | 更新锁与生产 vendor：OpenCC 1.3.1、Symfony Process 7.4.13；`composer audit --locked` 无公告 |
| P2 | 对象缓存清理无条件调用新 API，旧 WordPress/缓存后端可能 fatal | `wp_cache_flush_group()` 加能力检测，绝不退化为全站缓存清空 |
| P2 | 重定向使用普通 `wp_redirect()` | 前台语言重定向改为 `wp_safe_redirect()` |
| P2 | 主文件/README/readme/changelog/锁文件版本不一致，存在 WordPress.org 更新器冲突风险 | 统一未发布版本 1.5.1、WP 6.0+/PHP 7.4+、GPLv3；增加 FeiCode `Update URI` |

## 4. 尚未关闭的问题与限制

1. **不可逆内容修改仍存在。** “发表时转换”没有预览、备份、逐项 diff 或撤销事务。此次仅收窄触发面；在产品决定前不能移除用户功能。建议拆成独立管理员迁移工具，要求 dry-run、备份和恢复点。
2. **全页面 HTML 字符串转换风险高。** 即便默认关闭，它仍可能转换脚本、JSON、结构化数据或第三方组件载荷。建议 1.6 标记 deprecated，2.0 移除，改为语义明确的 WordPress 字段/块过滤。
3. **新旧核心重复。** `WPCC_Main` 与 `includes/wpcc-core.php` 同时注册大量 URL、内容、SEO 钩子；存在重复执行、顺序依赖和性能成本。应先做钩子级回归矩阵，再逐步收口，不能在维护补丁中直接删除兼容层。
4. **Modern Cache 模块挂接的 `wpcc_before_conversion` / `wpcc_after_conversion` 在实际转换链没有发出。** 现有核心缓存有效，但该模块大部分是死路径。建议合并为一个缓存实现。
5. **多站点导入边界过宽。** 网络管理员导入 JSON 时按文件中的键写 `site_option`，没有逐字段白名单/类型 schema；虽要求 `manage_network_options` 和 nonce，仍可能误写任意 `wpcc_*` 配置。网络批量写入也未在本轮建立真实 multisite E2E。
6. **卸载不完整。** 设置页“卸载”只删除部分站点选项/重写规则；网络选项、transient、缓存键和其他标记可能残留。需要先定义“保留数据”产品政策，再实现 `uninstall.php`，不能默认删除历史数据。
7. **文档 API 漂移。** README 展示的 `wpcc_convert_text()`、部分 filters/actions 与真实调用链不一致；应在兼容 API 冻结后重写开发者接口章节。
8. **构建源不完整。** 仓库有 `package-lock.json` 与构建后的 Gutenberg 文件，但没有 `package.json` 和 block 源码，前端构建不可复现。发布前必须找回源文件或明确将构建产物设为真源。
9. **静态规范债务很大。** 全仓 WordPress-Core PHPCS 为 16,422 errors / 1,401 warnings，不能标 PASS；其中大量是历史格式和国际化规则。不可在本补丁里全量自动格式化，否则会覆盖共享审阅上下文。
10. **未使用的网络能力。** `simple_html_dom.php` 包含 URL `file_get_contents()`，扩展基类包含许可服务器 `wp_remote_post()`；当前主流程未实例化这些路径，但未来启用前需加 URL allowlist、超时和 SSRF 约束。
11. **缓存兼容安装器会写入另一个插件目录。** 旧功能直接创建/删除 WP Super Cache 的插件文件，升级时会丢失，也绕过 WordPress Filesystem API。功能涉及既有用户，当前不擅自删除；1.6 应改为官方 hook 集成或独立兼容扩展。
12. **没有完整浏览器 E2E 和真实 multisite E2E。** 本轮完成真实 WordPress CLI/HTTP/REST smoke；SEO 插件组合、不同 permalink、对象缓存后端与网络批量操作仍需矩阵测试。

## 5. 测试与工具证据

### 基线

- 非 vendor PHP lint：exit 0。
- `composer validate --strict`：exit 2（锁过期、缺少 license）。
- `composer audit --locked`：exit 1（`symfony/process` CVE-2026-24739）。
- 仓库无测试配置；不能把零样本写成 PASS。

### 修复后

- `composer validate --strict`：exit 0。
- `composer audit --locked`：exit 0，无已知公告。
- `vendor/bin/phpunit -c phpunit.xml.dist`：exit 0，8 tests / 21 assertions。
- `composer analyze`：exit 0；PHPStan level 1 **仅覆盖 7 个关键转换/缓存/REST/sitemap 文件**，不是全仓 PASS。
- PHPCS WordPress-Core 全仓报告：exit 2，16,422 errors / 1,401 warnings（明确 FAIL/技术债）。
- WordPress 6.8.3 + PHP 8.3.33：插件激活 exit 0；WP-CLI 重新激活可写完整默认值；OpenCC `网络软件 → 網路軟體`、反向转换成功；首页 HTTP 200。
- 真实 REST：匿名请求 401；管理员 Application Password 请求 200；100,001 字节单条和 101 条批量请求均 400。
- 发布包：`scripts/build-release.sh` exit 0；ZIP 约 1.8 MiB、469 个文件、展开约 5.8 MiB；不含根级 Composer/测试/静态分析/`.DS_Store` 文件，生产 vendor 保留。
- Plugin Check 2.0.0：命令 exit 0，但报告 **556 errors / 260 warnings**，因此判定 FAIL，不是通过；包含真实问题（跨插件目录写文件、输出转义、直接访问保护等）和隔离目录名导致的 text-domain 误报。

## 6. 版本方向

### 1.5.1（本维护分支）

- 保留：两种转换引擎、语言切换器、no-conversion 标记、查询参数与 permalink、hreflang、基础 sitemap、现有多站点兼容。
- 交付：安全/正确性修复、依赖更新、可复现的 PHP 测试与发布打包脚本。
- 不做：删除兼容 API、生产部署、正式发布、默认分支合并。

### 1.6

- 将转换流水线和缓存统一为一个入口，移除死 hook；为 URL/SEO/permalink 建立数据驱动集成测试矩阵。
- REST 改为显式开关，增加速率限制/审计日志；若公共 API 是产品需求，单独设计 token scope，而不是降低默认 capability。
- 找回 Gutenberg 源码和 `package.json`，建立 CI：PHP lint、PHPUnit、PHPStan、PHPCS 增量门、Plugin Check、发布包内容断言。
- 为 multisite 导入建立 JSON schema、白名单、dry-run 和真实网络测试。

### 2.0

- 拆出不可逆批量/发表时转换为带备份和撤销的迁移插件或工具。
- 移除全页面 HTML 字符串转换；旧全局函数进入明确 deprecation 周期。
- 评估 MediaWiki 引擎是保留为兼容 fallback 还是独立扩展；决定必须基于语料正确率、峰值内存和站点兼容数据，而不是只看包体积。
