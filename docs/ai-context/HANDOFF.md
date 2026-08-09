# HANDOFF

## [CX] 2026-08-09 wp-chinese-converter 全面整治

- FeiCode 真源基线：`a7a6b1bd1858e4dbc34c7207791a77567e6051d8`。
- WenPai 隔离工作区：`/home/parallels/Projects/wp-chinese-converter-codex-audit`。
- 分支：`codex/wp-chinese-converter-audit-20260809`；未部署、未发布、未合并 `main`。
- 已修复：后台 CSRF/权限、REST 权限与体积限制、MediaWiki fatal、引擎缓存串用、Modern Cache、canonical、sitemap、WP-CLI 激活默认值、不可逆保存触发面、依赖 CVE 与元数据。
- 验证：PHPUnit 8 tests / 21 assertions；Composer validate/audit exit 0；关键 7 文件 PHPStan exit 0；真实 WP 6.8.3/PHP 8.3 激活、前台、转换和 REST smoke 通过。
- 非通过项：全仓 PHPCS 16,422 errors / 1,401 warnings；Plugin Check 2.0.0 报告 556 errors / 260 warnings（命令 exit 0 但判定 FAIL）；无真实 multisite、SEO 插件矩阵或完整浏览器 E2E；不可逆转换、全页转换、新旧核心重复、卸载残留、前端构建源缺失仍待产品/架构决策。
- 审计全文：`docs/audits/wp-chinese-converter-audit-2026-08-09.md`。

## [CX] 2026-08-09 wp-chinese-converter 第二轮

- 继续分支 `codex/wp-chinese-converter-audit-20260809`，只在 WenPai `/home/parallels/Projects/wp-chinese-converter-codex-audit` 工作；未部署、未发布、未合并默认分支。
- 第二轮已修：保存转换原文快照/幂等/权限/冲突保护，批量缓存跨引擎隔离，multisite 网络受控值，Yoast 过滤器时序与重复 canonical，WP Super Cache 跨插件写文件，OpenCC/Symfony 实际 PHP 平台，发布包误排除 `assets/dist` 与 staging 体积。
- 验证：PHPUnit 13/33；真实内容保存与恢复；multisite network activation/site override；Yoast canonical/title/description/OG/hreflang；ARM Chromium 简繁切换 E2E；默认保留数据的 uninstall；WordPress 7.0.3/PHP 8.3 network active 转换通过；发布包 1,311,078 bytes/401 entries。
- 未关闭：正确包 Plugin Check 最后一次完整值 296 errors/225 warnings（主要为 first-party 输出转义）；PHPCS WordPress 标准排除 vendor/历史 bundled 源后仍 17,266/1,541；“不转换”下拉回原文失败；multisite pretty permalink 的核心 sitemap 404；main/legacy/admin PHPStan 债未清零。
- 详细证据与边界：`docs/audits/wp-chinese-converter-audit-2026-08-09.md` 第 7 节。
