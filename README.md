# WP Chinese Converter (WPCC)

文派简繁转换（WP Chinese Converter），简称 WPCC，是完全符合阅读习惯和 SEO 优化的 WordPress 网站中文繁简转换解决方案。

![avatar](https://img.feibisi.com/2021/03/wpchinese-switcher-banner-1544x500-1.png)

## 核心特性

### 现代化架构 (2025重构)
- **面向对象设计**: 采用单例模式和依赖注入的现代PHP架构
- **模块化系统**: 支持功能模块的动态加载和管理
- **向后兼容**: 保持与旧版本配置和功能的兼容性

### 多语言转换支持
- **转换引擎**:
  - 基于 PHP OpenCC 1.2.1 版本的高精度转换
  - MediaWiki 转换引擎支持
- **支持地区**:
  - `zh-cn` / `zh-hans`: 简体中文（中国大陆）
  - `zh-tw` / `zh-hant`: 繁体中文（台湾）
  - `zh-hk`: 港澳繁体中文
  - `zh-sg`: 马新简体中文
  - `zh-jp`: 日式汉字
- **转换策略**: 词汇级别转换、异体字转换、地区习惯用词转换

### Gutenberg 区块支持
- **语言切换器区块**: 可视化语言切换按钮
- **转换状态区块**: 显示当前语言状态
- **不转换区块**: 指定不需要转换的内容区域
- **现代编辑器体验**: 完全兼容WordPress 5.0+区块编辑器

### 性能优化
- **智能缓存系统**: 支持多种缓存策略（WordPress原生缓存、现代缓存系统）
- **内存优化**: 字典预加载和按需加载机制
- **转换缓存**: 避免重复转换相同内容
- **全页面转换**: 可选的服务端全页面转换模式

### SEO 优化功能
- **Canonical URL**: 自动生成规范链接，避免重复内容问题
- **Hreflang 支持**: 自动添加语言版本链接标记
- **URL 结构优化**: 支持伪静态和查询参数两种URL模式
- **搜索增强**: 支持搜索关键词的简繁转换
- **站点地图兼容**: 自动生成多语言版本的站点地图

### 高级功能
- **浏览器语言检测**: 根据访客浏览器语言自动切换
- **Cookie 记忆**: 记住用户的语言偏好设置
- **多站点支持**: 完全兼容 WordPress 多站点模式
- **REST API**: 提供完整的 REST API 接口
- **AJAX 转换**: 支持动态内容的实时转换

### 兼容性
- **WordPress 版本**: WordPress 5.0+ （推荐 6.0+）
- **PHP 版本**: PHP 7.4+ （推荐 8.0+）
- **数据库**: MySQL 5.7+ 或 MariaDB 10.2+
- **缓存插件**: 兼容 WP Super Cache, W3 Total Cache 等

## 系统要求

- WordPress 5.0 或更高版本
- PHP 7.4 或更高版本（推荐 PHP 8.0+）
- MySQL 5.7+ 或 MariaDB 10.2+
- 服务器内存至少 128MB（用于加载转换字典）

## 安装配置

### 自动安装
1. 在 WordPress 后台导航到 `插件 > 安装插件`
2. 搜索 "WP Chinese Converter" 或 "WPCC"
3. 点击 `现在安装`，然后激活插件
4. 在 `设置 > WP Chinese Converter` 中配置转换选项

### 手动安装
1. 下载插件压缩包
2. 解压到 `/wp-content/plugins/wp-chinese-converter/` 目录
3. 在 WordPress 后台激活插件
4. 配置插件设置

## 主要设置选项

### 基本设置
- **启用语言**: 选择要启用的语言版本
- **转换模式**: 选择使用的转换引擎（OpenCC 或 MediaWiki）
- **URL 模式**: 选择伪静态或查询参数模式
- **默认语言**: 设置网站的默认语言版本

### 高级设置
- **缓存设置**: 配置转换缓存策略
- **SEO 优化**: 启用 canonical URL 和 hreflang 支持
- **浏览器检测**: 启用自动语言检测
- **Cookie 记忆**: 启用用户语言偏好记忆

## 使用方法

### Gutenberg 区块使用
1. 在页面编辑器中点击 `+` 添加区块
2. 搜索 "WP Chinese Converter" 相关区块
3. 选择所需区块并配置选项：
   - **语言切换器**: 设置按钮样式和显示语言
   - **转换状态**: 显示当前页面语言状态
   - **不转换**: 指定不需要转换的内容区域

### 短代码支持
- 语言切换器（老短代码，平铺/下拉由“展示形式”设置控制）
```
[wp-chinese-converter]
```
- 不转换内容（编辑器无法保留注释时作为稳健占位）：
```
[wpcc_nc]不转换的内容[/wpcc_nc]
[wpcs_nc]不转换的内容[/wpcs_nc]
```

### PHP 函数调用
```php
// 获取转换后的内容
<?php echo wpcc_convert_text('要转换的文本', 'zh-tw'); ?>

// 获取当前语言
<?php $current_lang = wpcc_get_current_language(); ?>

// 获取语言切换链接
<?php $switch_url = wpcc_get_switch_url('zh-cn'); ?>
```

## 开发者接口

### 过滤器钩子
```php
// 自定义转换文本
add_filter('wpcc_convert_text', 'custom_conversion', 10, 3);

// 修改语言标签
add_filter('wpcc_language_labels', 'custom_labels');

// 自定义缓存时间
add_filter('wpcc_cache_expire', 'custom_cache_time');
```

### 动作钩子
```php
// 转换前执行
add_action('wpcc_before_conversion', 'before_convert');

// 转换后执行
add_action('wpcc_after_conversion', 'after_convert');
```

## 链接与重写规则行为说明（重要）

- 链接格式与固定链接的关系
  - 当 WordPress 启用了固定链接，且“URL 链接格式”选择了“前缀/后缀”时：生成 /zh-xx/ 样式链接（或 …/zh-xx/）
  - 当 WordPress 未启用固定链接（或环境未正确应用 rewrite 规则）时：自动回退为 ?variant=zh-xx，避免 404
- 首页根级变体访问行为
  - 访问 /zh/ 或 /zh-reset/：作为“哨兵”回到不转换首页（https://example.com/），并设置 zh 偏好以覆盖浏览器/Cookie 策略
  - 访问 /zh-xx/（如 /zh-tw/、/zh-hk/）：统一 302 跳转到首页，避免首页重复内容与 404
- zh 哨兵与 rel="nofollow"
  - 仅当“不转换”链接为覆盖策略而携带 zh 哨兵（URL 含 /zh/ 或 ?variant=zh）时自动添加 rel="nofollow"
  - 当不需要哨兵（直接是原始 URL）时，不加 nofollow，避免影响站内权重传递

## 兼容层与新版内核

- 新内核（WPCC_Main / WPCC_Config 等）统一管理重写、变体解析、链接构造、注入脚本等；
- 为了兼容历史主题/插件/短代码调用，保留了 includes/wpcc-core.php 中的“老函数”（如 wpcc_link_conversion、set_wpcc_langs_urls、wpcc_output_navi、短代码 [wp-chinese-converter] 等）；
- 老函数的行为已与新内核对齐（例如固定链接未启用时自动降级为 ?variant=xx），确保前后端一致；
- 建议新项目优先使用区块与新内核能力；对生态依赖的老接口，后续会以 @deprecated 标注与迁移指引逐步过渡。

## 故障排除

### 常见问题

**Q: 插件激活后页面显示空白？**
A: 请检查 PHP 内存限制，建议至少 128MB。可以在 `wp-config.php` 中添加 `define('WP_MEMORY_LIMIT', '256M');`

**Q: 转换后的内容不正确？**
A: 请检查选择的转换引擎和目标语言是否正确。OpenCC 引擎适合日常使用，MediaWiki 引擎更严格。

**Q: 缓存不生效？**
A: 请检查 WordPress 缓存配置，或尝试清除转换缓存：`设置 > WP Chinese Converter > 高级设置 > 清除缓存`

**Q: URL 重写不工作？**
A: 请确保 WordPress 固定链接已启用并“保存更改”一次；服务器需正确支持 rewrite（如 Nginx/Apache 规则）。插件在未启用固定链接时会自动回退为 ?variant=xx。

## 性能优化建议

1. **启用缓存**: 使用 WordPress 缓存插件（如 WP Super Cache）
2. **PHP OPcache**: 启用 PHP OPcache 以提高字典加载速度
3. **CDN 集成**: 使用 CDN 加速静态资源加载
4. **按需加载**: 仅在需要的页面启用转换功能

## 贡献指南

欢迎提交 Issue 和 Pull Request！

1. Fork 本仓库
2. 创建功能分支 (`git checkout -b feature/AmazingFeature`)
3. 提交更改 (`git commit -m 'Add some AmazingFeature'`)
4. 推送到分支 (`git push origin feature/AmazingFeature`)
5. 开启 Pull Request

## 许可证

本插件采用 GPLv3 或更高版本许可证。详见 [LICENSE](LICENSE) 文件。

## 支持与反馈

- **官方网站**: https://wpcc.net
- **GitHub 仓库**: https://github.com/wpcc-net/wp-chinese-converter
- **技术支持**: https://wpcc.net/support
- **Bug 报告**: https://github.com/wpcc-net/wp-chinese-converter/issues

---

## 版本历史

### v1.4.x (2025年稳定版补丁)
- 统一“展示形式”数值语义：1=平铺，0=下拉；修复短代码展示反向问题
- 单站模式：当 WordPress 未启用固定链接时，链接自动降级为 ?variant=xx，避免 /zh-xx/ 404
- 首页根级变体：/zh/ 与 /zh-xx/ 统一 302 到首页；/zh/ 同时设置 zh 偏好覆盖浏览器/Cookie 策略
- 前端切换器：仅在携带 zh 哨兵时为“不转换”链接添加 rel="nofollow"，并与新窗口 noopener noreferrer 兼容
- 统一文件命名风格：核心类重命名为 class-wpcc-*.php；保留兼容层但与新内核对齐

### v1.3.0 (2025年重构版本)
- 完全重写插件架构，采用现代化 OOP 设计
- 新增 Gutenberg 区块支持
- 优化性能和内存使用
- 增强 SEO 优化功能
- 完善多站点支持
- 新增 REST API 接口

### v1.2.x (历史版本)
- 基于原 WP Chinese Conversion 的改进版本
- 修复 PHP 8.x 兼容性问题
- 基础的简繁转换功能

> **历史说明**: 此项目分叉于原 WP Chinese Conversion 中文简繁转换器免费插件，感谢原作者 Ono Oogami 提供的基础框架。由于原插件多年未更新，文派开源团队于 2025 年进行完全重写和品牌重塑，为 WordPress 中文用户提供长期更新和技术支持。

---

**Copyright © 2025 · WPCC.NET , All Rights Reserved. 文派 （广州） 科技有限公司**
