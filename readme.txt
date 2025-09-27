=== WP Chinese Converter ===
Contributors: wpfanyi
Tags: chinese, 中文, 繁体, 简体, 繁简转换, Chinese Simplified, Chinese Traditional, widget, sidebar
Requires at least: 4.5
Tested up to: 8.2
Stable tag: 1.2.0
License: GPLv2
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Adds the language conversion function between Chinese Simplified and Chinese Traditional to your WP Blog. Released under GPL license.

== Description ==

这个插件为中文博客设计, 提供完整的基于服务器端的中文繁简转换解决方案. 相比用Javascript进行客户端繁简转换, 本插件提供的转换功能更为专业和可靠, 支持三种中文语言: 简体中文(zh-hans), 台湾正体(zh-tw), 港澳繁体(zh-hk); 并且提供更多其它特性. 插件使用的繁简转换表和核心转换技术均来源于 opencc。

使用方法: 可通过古腾堡区块“繁简转换”或简码 [wp-chinese-converter] 来向页面添加翻译切换按钮，插件的设置页可以在后台“设置”菜单下的"WP Chinese Converter"子菜单中找到。

特性(Features):

*  繁简转换页面URL支持<code>/permalink/to/your/original/post/?variant=zh-xx</code>, <code>/permalink/to/your/original/post/zh-xx/</code>和<code>/zh-xx/permalink/to/your/original/post/</code>三种形式.(zh-xx为语言代码)
*  基于词语繁简转换. 如 "网络" 在台湾正体(zh-tw)里会被转换为 "網路".
*  自动在转换后版本页面加上noindex的meta标签, 避免搜索引擎重复索引.
*  使用Cookie保存访客语言偏好. 插件将记录浏览者最后一次访问时的页面语言, 在浏览者再次访问显示对应版本. (此功能默认不开启)
*  自动识别浏览器语言. 插件将识别用户浏览器首选中文语言, 并显示对应版本页面. (此功能默认不开启)
*  中文搜索关键词简繁体通用. 这将增强Wordpress的搜索功能, 使其同时在数据库里搜索关键词的简繁体版本. 例如, 假如访客在浏览您博客页面时通过表单提交了关键词为 "網路" 的搜索, 如果您的文章里有 "网络" 这个词, 则这篇文章也会被放到搜索结果里返回. (此功能默认不开启)
*  后台可设置不转换部分HTML标签里中文.
*  后台可设置翻译按钮展现形式

如果使用缓存插件 (WP Super cache, Hyper Cache, etc), 上面部分功能(识别浏览器语言, 使用Cookie保存访客语言偏好)可能存在兼容性问题。

== Installation ==

上传插件并激活即可。

== Changelog ==

## 1.1.0 ###

* 改为使用 opencc 翻译库
* 兼容 PHP 8.2
* 增加古腾堡区块
* 增加一个名为 [wp-chinese-converter] 的简码
* 新增支持通过管理后台设置翻译按钮展现样式
* 新增支持港澳繁体转换
* 支持国际化翻译
* 优化管理后台设置界面

## 1.0.0 ###

* 删除/隐藏多种不常用的中文转换版本
* 修正多处新版本 PHP 报错
* 初始版本发布
