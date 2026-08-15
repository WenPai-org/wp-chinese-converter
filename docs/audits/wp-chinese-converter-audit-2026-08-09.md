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
## 8. [CX] 第三轮整治（2026-08-09）

### 8.1 修复与复核

- “不转换”下拉不再只移除 `variant`：Classic 与 Block 路径都优先使用服务端 `zh` 哨兵 URL；服务端在未启用持久语言 Cookie 时会读取 `wpcc_is_redirect_*` 会话 Cookie，阻止浏览器语言识别在 302 后立即再次转换。
- 第二轮记录的 multisite pretty permalink `/wp-sitemap.xml` 404 被证实为测试环境假失败：disposable wp-env 缺少 multisite `.htaccess`，请求直接由 Apache 返回 404；主站同时启用 Yoast，Yoast 合法关闭 WordPress core sitemap。补齐标准 multisite rewrite、隔离停用 Yoast 后，主站和 `tradtest` 的 core/WPCC sitemap 均为 HTTP 200 XML；没有为测试环境错误修改插件 rewrite。
- Plugin Check first-party 修复按上下文进行，没有全仓机械格式化：清零 `UnsafePrintingFunction` 167 项、`SafeRedirect` 4 项和 SQL error；设置页、网络页、admin 输出按 text/attribute/URL/JS/可信 HTML 分开处理。
- 网络 JSON 导入增加权限/nonce 既有门禁之外的 1 MiB 限制、JSON 扩展名、键白名单和值消毒；导出/导入字段闭合，正确覆盖 quicktag、自动语系、发表转换目标，并兼容旧 `wpcc_default_enable_quicktags` 键。
- PHPStan level 1 正式扩展到主入口、modern main、legacy core、admin/controller/settings include 上下文；移除 legacy 内层命名函数和 `extract()` 未定义变量，修正 widget void callback、评论字段转义与 WordPress URL/AJAX 符号建模。仅对设置模板 include 产生的精确 `$this` 未定义消息做路径级忽略，不使用全局 class/function ignore。
- 新增 fail-closed runtime runner：只允许指定的 disposable wp-env 容器；覆盖 exact ZIP、主/子站、REST/XML-RPC/WP-CLI、备份幂等/回滚、core/WPCC sitemap、Yoast、标准切换 E2E 和 `cookie=0 + browser_redirect=1` 会话哨兵 E2E。

### 8.2 最终 HEAD 验证

- PHPUnit：13 tests / 33 assertions，exit 0。
- 非 vendor PHP lint：41 个 PHP 文件，exit 0；Composer validate/audit 均 exit 0。
- PHPStan 扩展后的完整 `phpstan.neon.dist`：`[OK] No errors`，exit 0。
- JS/runtime runner 静态检查：Node check、JS reset test、`bash -n`、ShellCheck、Python compile、`git diff --check` 均 exit 0。
- WordPress 7.0.3 / PHP 8.3 multisite exact ZIP：
  - 主站 matrix exit 0；`tradtest` 子站 matrix exit 0。
  - 主站与子站的 WP-CLI、REST、XML-RPC 保存均转换成功，备份数保持 1，重复保存幂等，恢复回原始简体。
  - REST 匿名请求实际 401；管理员转换 200；REST 文章创建 201；XML-RPC 200，子站使用动态 blog ID 2。
  - 主站 core/WPCC sitemap 为 200/200（527/418 bytes）；子站为 200/200（572/445 bytes）。
  - 主站与子站 Yoast 组合均为单一 canonical，title/description 转换和 hreflang 通过。
  - 标准浏览器 E2E 验证“原文 → zh-tw → 不转换精确恢复原 DOM → zh-tw → zh-cn”；会话 E2E 验证 `wpcc_use_cookie_variant=0`、`wpcc_browser_redirect=1` 时 reload 仍保持原文。两组 Node 退出码均为 0。
- 发布 ZIP：401 entries / 1,313,107 bytes；包含 `assets/dist/wpcc-variant.umd.js`，不含 dev dependencies，`unzip -t` exit 0。最终磁盘文件 SHA-256 为 `80e8d419ddbf5fe4e5df56c839cc1a74f6ad51d58c46f4d530e2366654bf70a6`。
- exact final ZIP Plugin Check：进程 exit 0，但报告 54 errors / 208 warnings，按 findings 判定 FAIL。errors 为 51 `OutputNotEscaped`、2 个最低 WordPress 版本兼容、1 个 sitemap heredoc；warnings 仍含 nonce/input/naming/debug 等历史债。

### 8.3 剩余发布阻断项

1. Plugin Check 仍为 54 errors / 208 warnings，不满足发布门禁；51 个输出问题分布在 settings、legacy core、sitemap/XML 和 bundled third-party，必须继续按上下文审查，不能批量 `esc_html()`。
2. 两个 `wp_cache_flush_group()` 兼容错误需要在“提高 Requires at least 到 WordPress 6.1”与“为 WordPress 6.0 保留兼容实现”之间做明确版本决策。
3. sitemap heredoc 及完整 XML/HTML 输出需要人工确认后定点抑制或改为结构化生成，不能为过检查破坏 XML。
4. Plugin Check warnings 中仍有 nonce recommended/missing、输入 unslash/sanitize、debug 输出和历史全局命名；零 error 前不能把安全审计写成 PASS。
5. PHPCS 仍沿用第二轮的 17,266 errors / 1,541 warnings（exit 2）失败基线；第三轮没有用自动格式化掩盖该债务。
6. 保存转换虽有受保护快照和程序化恢复，但仍缺管理员恢复 UI、dry-run、逐字段 diff 和多版本历史；该不可逆功能不应在缺少显式操作界面时作为默认启用能力发布。
7. Gutenberg 构建源仍不完整（缺 `package.json`/block 原始源码），现有构建产物能打包但前端 release 不完全可复现。

### 8.4 [CX] 追加边界

- 版本元数据仍冲突：插件头和根 `composer.json` 声明 PHP 7.4，当前 lock/vendor 生成的 `platform_check.php` 要求 PHP >= 8.1；必须在发布前选择提高 `Requires PHP`，或重新约束并验证兼容 PHP 7.4 的依赖闭包。
- 第三轮 exact-ZIP 真实运行覆盖 multisite 主站与子站；没有另建独立 single-site 拓扑，因此不能把该轮结果表述为独立单站环境 PASS。

## 9. [CX] 发布收口第三批（2026-08-11）

### 9.1 已实现

- 版本契约统一为 WordPress 6.3、PHP 8.1、插件 1.5.1。最低 WordPress 选择 6.3，因为现有三个区块使用 Block API v3；两处缓存组清理仍先检查 `wp_cache_supports( 'flush_group' )`，对象缓存不支持时不退化为全站 `wp_cache_flush()`。
- 发表转换历史升级为 `_wpcc_conversion_history_v2`：单值 schema 2，默认保留 10 条、硬上限 50；旧 `_wpcc_conversion_backup` 只迁移复制、不删除。记录 target、engine、用户、时间、来源通道和 original/converted hashes；重复输入不追加。
- 管理员恢复入口在自动转换关闭时仍可见。恢复 UI 使用独立 footer form、`edit_post`、文章专用 nonce、严格输入清洗和 safe redirect；支持选定历史、dry-run、标题/内容逐字段 diff、stale hash 冲突拒绝、后续编辑拒绝和重复恢复 no-op。
- 修复 WordPress slashing contract：filter 边界内部统一使用 unslashed 值，返回 filter、post meta 和 `wp_update_post()` 写入按各自契约 slash；引号、反斜杠、JSON 和 HTML attribute 可精确备份/恢复。
- multisite 普通站点管理员恢复历史 HTML 时，只在已通过权限、nonce 和 expected-hash 的同步写入窗口临时移除 post KSES filters，并在嵌套 `finally` 恢复；未授予 `unfiltered_html`，后续普通保存继续按原 capability 执行 KSES。
- Plugin Check 原 54 errors 已按输出上下文处理为 0：文本、属性、URL、inline JSON 分开转义；完整 XML、主题提供的 widget wrapper 和已逐节点转义的导航仅使用最窄单行说明性 ignore；第三方 `simple_html_dom` 只对未被插件调用的 debug dump 定点处理。
- Gutenberg 方案选择“重建”而不是删除功能：补回三个区块源码、variant 源、`package.json` 和确定性构建器；修复语言切换器 `edit`/`save` 中会触发 `ReferenceError` 的 `returncreateElement`，行为测试实际调用生成区块的 `edit()` 和 `save()`。
- 发布脚本固定排序、权限、mtime 和 ZIP extra fields；源码保留在仓库，release ZIP 排除 `node_modules`、构建源码、tests/docs 和 dev vendor。新增的 Forgejo CI 候选覆盖 WP 6.3/PHP 8.1 与 latest/PHP 8.3、PHPUnit、PHPStan、Gutenberg build check、双 ZIP 比对、exact ZIP runtime 和 Plugin Check；没有触发该 CI。
- legacy URL filter 对非字符串/空值原样返回，消除 PHP 8.3 下 `strpos( null )` deprecation。

### 9.2 验证证据

- PHPUnit：22 tests / 76 assertions，exit 0；含历史上限、legacy 保留、任意旧版本、dry-run、stale/later-edit、slashing 特殊字符、KSES 恢复与异常复位。
- PHPStan：完整 `phpstan.neon.dist`、2 GiB，`[OK] No errors`，exit 0。
- Composer validate/audit、41 个非 vendor PHP lint、YAML parse、`bash -n`、ShellCheck、JS syntax、language-switcher block behavior、variant reset 和 `git diff --check` 均 exit 0。
- exact fixed-epoch ZIP Plugin Check：进程 exit 0，**0 errors / 207 warnings**。warnings 主要为历史全局命名 115、nonce 37、输入 unslash/sanitize/validate 25、development debug 19；命令成功与 0 error 分开记录，warnings 未写成清零。
- 可复现构建：`SOURCE_DATE_EPOCH=1786400000` 连续两次构建 byte-identical，`cmp` 和 `unzip -t` exit 0。最终候选 `dist/wp-chinese-converter-1.5.1.zip` 为 1,293,281 bytes / 401 entries，SHA-256 `b2034115344de2834d7dd65a0abb3c67b7c5ecd6a249727a16a79f961914dc9a`。
- WordPress 7.0.3 / PHP 8.3.33 独立 standalone：完整进程 `/tmp/wpcc-r4-final-single-v3.rc` 为 1，唯一失败是后来纠正的测试假断言（错误要求具 `unfiltered_html` 的 standalone administrator 后续保存也必须剥离 script），不能记整进程 PASS。该进程在失败前已通过 exact ZIP 的 CLI、REST、XML-RPC history/幂等/dry-run/stale/later-edit/选版恢复、history cap 10、sitemap 200/200、Yoast 和两类前端 E2E；纠正 capability 判定后的定向管理员流程 `/tmp/wpcc-r4-final-single-admin-v7.rc` 为 0，dry-run、diff、恢复、重复恢复通过，并明确输出 `not-applicable-unfiltered-html`。
- WordPress 7.0.3 / PHP 8.3.33 multisite network-active：主站同一 exact 业务内容的 transport/history/sitemap/Yoast/两类前端 E2E通过，普通站点管理员 HTML/JSON 恢复、重复恢复和后续 KSES保护通过；`tradtest` 子站完整 site matrix exit 0，动态 blog ID 的 XML-RPC、peer postmeta隔离、sitemap 200/200、Yoast、两类前端 E2E和管理员恢复均通过。
- 运行期安装 ZIP 的 SHA 为 `162b1d9d998a5276c657f0408e07ae22cac699f35392216c06b1259fd8934bbd`；它与最终 `b203...` 的文件内容相同，仅构建 epoch/ZIP mtime不同。最终 `b203...` 另行完成 byte-identical 双构建和 exact ZIP Plugin Check。

### 9.3 仍未通过的发布门

1. FeiCode 为 P0、`write_policy=board_required`。本轮没有推送、审批/触发 CI、合并、tag/release、发布或部署；本地 `.forgejo/workflows/ci.yml` 不能算远端 CI PASS。
2. 最低版本 WP 6.3/PHP 8.1 的真实容器样本因 WenPai 缺镜像且拉取超过 30 分钟仍未完成，记 **BLOCKED**，不是 PASS。必须由 Board change 对 exact final HEAD 跑最低版本 CI。
3. Plugin Check 虽为 0 errors，仍有 207 warnings；nonce/input warnings 需要按实际写操作继续逐项审计，历史命名和 debug 债不能冒充已清零。
4. 全仓 PHPCS 沿用第三轮 17,266 errors / 1,541 warnings 的失败基线；本轮只对改动/安全输出做定向验证，没有机械格式化全仓，也不能写 PHPCS PASS。
5. 管理恢复使用 WordPress 钩子和乐观 expected-hash，hash 检查到 `wp_update_post()` 之间仍非数据库 CAS；并发窗口为已知 P2，不在本版绕过 WordPress hooks 直写数据库。

### 9.4 Board 人工动作

- 已生成 `docs/ai-context/board-change-wpcc-pr4.md`，但没有发布 Board 任务。
- devops 审核后只能普通、非 force push 本地 exact HEAD 到 PR #4 既有非默认候选分支，并只运行绑定该 SHA 的 CI；默认分支、merge、tag/release、发布资产、fei-publish/Woo/mall、部署和生产权限均禁止改变。

## 10. [CX] 发布门补测（2026-08-11）

### 10.1 Plugin Check warnings 定点整治

- 对 exact ZIP 的 207 warnings 重新分桶，只修改有明确安全或审核依据的项目：输入 unslash/sanitize/type guard、network settings 的 `manage_network_options` 与 nonce、license AJAX 的 `manage_options`、脚本显式 header/footer 参数、debug JSON 输出和发布包 Composer 元数据。没有批量重命名历史全局符号、第三方类/函数、外部集成 hook 或商标文本。
- 源码修复提交为 `9918065da0f29c2301332f34437d8108c0c14d67`。最终 exact ZIP Plugin Check 2.0.0 strict-csv 进程 exit 0，findings 为 **0 errors / 170 warnings**。170 项分桶：历史全局命名 115（变量 68、函数 33、hook 7、常量 4、类 3），nonce 35（Recommended 29、Missing 6），有意异常日志 17，商标文本 3。输入 unslash/sanitize/validate 和脚本 footer warnings 已清零。
- E2E harness 后续提交 `5fbee3beddf36d53205409df7dbf0a22988dddab`：每次创建唯一页面 slug，切换时显式等待目标 URL；multisite capability 不再从 REST user payload 推断，而是对临时用户调用 `current_user_can( 'unfiltered_html' )` 后传入浏览器断言。该提交不进入 release ZIP。

### 10.2 最终候选与回归

- 可复现构建固定 `SOURCE_DATE_EPOCH=1786437235` 连续两次 exit 0，`cmp` exit 0。候选 `dist/wp-chinese-converter-1.5.1.zip`：1,294,645 bytes / 402 entries，SHA-256 `f7ba5f7df7c3494cdcad301b475b7170d655f6062b793e854f409c7686ea3d9a`；Plugin Check 使用该 exact ZIP。
- standalone WordPress 7.0.3 / PHP 8.3.33 的单一完整 `runtime-matrix.sh all` 进程 exit 0：CLI、REST、XML-RPC 的两版本 history、dry-run/diff、stale/later-edit 冲突、选定旧版本恢复、重复恢复 no-op、history cap、core/variant sitemap、Yoast canonical/hreflang、两类前端 E2E 和管理员恢复均通过；具 `unfiltered_html` 的单站管理员明确记 KSES 检查不适用。证据 `/tmp/wpcc-r5a-final4-all.log`。
- multisite WordPress 7.0.3 / PHP 8.3.33 network-active：主站完整 `all` exit 0，修正 capability 探针后的 main admin 定向 exit 0；子站单一完整 `all` exit 0，包含三 transport、主子站隔离、sitemap、Yoast、两类 E2E、普通站点管理员历史恢复和后续 KSES 保护。证据 `/tmp/wpcc-r5m-final-main-all-v2.log`、`/tmp/wpcc-r5m-final-main-admin.log`、`/tmp/wpcc-r5m-final-child-all-v2.log`。
- 当前源码静态门：PHPUnit 22 tests / 76 assertions、full PHPStan 0 errors、Composer validate/audit、非 vendor PHP lint、Gutenberg reproducible build check、block behavior、variant reset、harness syntax 与 `git diff --check` 均 exit 0。

### 10.3 最低版本样本与边界

- WP 6.3/PHP 8.1 仍为 **BLOCKED**，零样本未写 PASS。官方 Docker Hub `wordpress:6.3-php8.1-apache` arm64/v8 manifest 为 `sha256:0f7b87ee7c96f0b234d75c1dd2d9a11bafac7b91464ef8c55253405e39ce0307`，multi-arch index 为 `sha256:2e29efb1c94a2602aa597a9fb98d8f9adce1328b9bcc617f495cd394264c89a4`。
- 对该精确 digest 的两次 pull 各有 900 秒硬上限，均 exit 124（16:10:31–16:25:31、16:26:17–16:41:18）。DNS、TLS 1.3、证书和 registry 预期 401 均正常；Docker daemon 使用 `https://docker.1ms.run/`，阻塞发生在 layer 传输。没有改用未校验第三方镜像拼凑样本。
- 备用官方 WordPress 6.3 tar 已下载并与官方校验端点一致：23,445,410 bytes，SHA-1 `5ae2e02020004f7a848fc4015d309a0479f7c261`，MD5 `096a73a915b72e51aca7dbe94b7f2219`；因未取得官方 PHP 8.1 runtime，未启动伪最低版本环境。
- 本轮未 push、未触发/审批 FeiCode CI、未合并、未发布、未部署。所有自建 standalone/multisite 容器、网络和卷已 `down -v` 清理，exit 0。
