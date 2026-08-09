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

## 7. [CX] 第二轮整治（2026-08-09）

### 7.1 已修复

- 正确发布包（插件目录名 `wp-chinese-converter`）的 Plugin Check 基线为 332 errors / 229 warnings，不再使用隔离目录名造成 text-domain 误报的 556/260 数字。元数据、block API v3、直接访问保护、`wp_parse_url()`、站点时区日期、翻译占位符、数据库表标识符、跨插件目录写入等确定性问题已修复。剩余主体是设置模板的上下文相关输出转义和历史命名/格式债；第三方 `simple_html_dom.php` 单列，不做批量格式化。
- 移除向 WP Super Cache 插件目录写入/删除 PHP 文件的旧安装器；缓存兼容改为切换内置 `WPCC_Cache_Addon` 配置，不覆盖其他插件文件。
- “发表时转换”改成命名两阶段流程：保存前转换并生成请求指纹，`wp_after_insert_post` 写受保护的原文快照；相同输入不重复备份；恢复要求 `edit_post`，恢复期间绕过转换，且当前文章已被后续编辑时拒绝覆盖。该钩子从 admin-only 移到公共入口，REST、XML-RPC、WP-CLI 保存也可获得相同保护。
- 网络受控配置不再依赖尚未加载的 `WPCC_Network_Settings` 类；真实子站点验证 `local=mediawiki`、网络默认 `opencc` 时解析结果为 `opencc`。
- SEO 模块不再在已经执行中的 `init` 上延迟注册集成过滤器；Yoast title、description、Open Graph 转换已生效。检测到 Yoast/Rank Math/AIOSEO 时不再额外输出 WPCC canonical；无 SEO 插件时 canonical 保留当前语言变体。
- OpenCC vendor 锁定到 PHP 8.1 平台可安装的 Symfony 6.4 LTS；PHP 7.4/8.0 不加载可选 OpenCC vendor，并安全回退 MediaWiki 引擎，避免 Composer platform check 让整个插件启动 fatal。
- 发布脚本的 `--exclude=dist` 原来误排除 `assets/dist/`，导致运行时 JS 丢失；已改为只排除根 `/dist/`。同时仅从 staging 去除 vendor 测试、benchmark、README/CHANGELOG/composer 元数据和 OpenCC 未使用的原始 dictionary，保留 parsed 字典、运行时代码和许可证。

### 7.2 第二轮验证事实

- PHPUnit：13 tests / 33 assertions，exit 0；新增保存备份、权限、幂等、后续编辑冲突和批量缓存跨引擎隔离。
- 真实 WordPress 内容保存：`网络软件` 保存为 `網路軟體`，备份数 1；再次保存备份仍为 1；恢复后回到原始简体，exit 0。
- 真实 multisite：转换网络、创建 `tradtest` 子站、network activate 均成功；网络受控 `wpcc_engine` 覆盖子站本地值成功。首次同时激活相同插件的两个 slug 会因全局函数重复 fatal，这是测试布置冲突，不是允许并存的产品场景。
- 真实 Yoast 组合：变体页 canonical 只剩 1 条且含 `variant=zh-tw`；Yoast title、description、Open Graph 从 `网络软件` 转为 `網路軟體`。hreflang 输出 zh-CN、zh-TW、x-default。自定义多语言 sitemap URL 返回 200 XML；当前 multisite/permalink 组合的核心 `/wp-sitemap.xml` 仍有 404，需要单独修 rewrite 兼容。
- 浏览器 E2E：WenPai ARM Chromium + Playwright 实际选择 `#wpcc_translate_type`；zh-tw 页面包含 `網路軟體和後臺印表機`，再切 zh-cn 恢复 `网络软件和后台打印机`，exit 0。直接选择“不转换”选项未恢复内容，单独记录为未修 P2。
- uninstall：隔离 multisite 中 network deactivate 后运行 `wp plugin uninstall --skip-delete` exit 0，站点与网络探针选项都保留；当前政策是默认保留历史数据，不把“删除所有数据”冒充完成。
- WordPress 7.0.3 / PHP 8.3 multisite：network active，`网络软件和后台打印机 → 網路軟體和後臺印表機`，exit 0；因此 readme `Tested up to: 7.0` 有真实运行样本。
- 发布包从 469 ZIP entries / 1,813,851 bytes 降至 401 entries / 1,311,078 bytes；包含 `assets/dist/wpcc-variant.umd.js`，不含 dev dependencies，ZIP 完整性通过。
- PHPStan level 1 的绿色门扩大到 conversion/cache/REST/SEO/sitemap/network/post-backup 12 个 first-party 文件；主入口、现代主类、legacy core、admin 另行跑过并保留真实债务，不用全局 ignore 掩盖缺符号、inner function、`extract()` 与错误 void callback。
- Plugin Check 正确包第二轮最后一次完整结果为 296 errors / 225 warnings；相对正确基线减少 36 errors / 4 warnings。剩余错误主体是 first-party 输出转义，不能对 XML、完整 HTML 片段和动态 attributes 机械 `esc_html()`；继续按输出上下文分批整治。PHPCS 第一轮 WordPress-Core 全仓历史结果为 16,422 errors / 1,401 warnings；第二轮 WordPress 标准排除 vendor、dist、tests、`simple_html_dom.php`、`ZhConversion.php` 后仍为 17,266 errors / 1,541 warnings（exit 2）。两种标准不可直接横比，均明确为 FAIL。

### 7.3 仍未关闭

1. 设置页、网络页与 legacy core 的输出转义债仍大；应按 text/attribute/URL/JS/XML context 分批修，不接受全文件自动格式化。
2. “不转换”下拉项从 zh-tw 返回原文的浏览器路径失败；zh-cn/zh-tw 双向切换通过。
3. multisite + pretty permalink 下核心 sitemap/rewrite 有 404；WPCC 自定义 sitemap 可用。
4. 保存转换已有最近快照恢复，但没有管理员 UI、逐字段 diff、dry-run 或多版本选择；仍建议 1.6 拆成显式迁移工具。
5. vendor 还能通过 fork OpenCC runtime 包去除 Symfony Console/Process，但当前不破坏上游 Composer contract；不把理论 0.95 MB 当成已交付。
