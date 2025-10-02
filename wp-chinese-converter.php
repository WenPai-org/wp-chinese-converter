<?php
/*
 * Plugin Name: WP Chinese Converter
 * Description: Adds the language conversion function between Chinese Simplified and Chinese Traditional to your WP Website.
 * Author: WPCC.NET
 * Author URI: https://wpcc.net
 * Text Domain: wp-chinese-converter
 * Domain Path: /languages
 * Version: 1.3.0
 * License: GPLv3 or later
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

/**
 * WP Chinese Converter
 *
 * 为Wordpress增加中文繁简转换功能. 转换过程在服务器端完成. 使用的繁简字符映射表来源于Mediawiki.
 * 本插件比较耗费资源. 因为对页面内容繁简转换时载入了一个几百KB的转换表(ZhConversion.php), 编译后占用内存超过1.5MB
 * 如果可能, 建议安装xcache/ eAccelerator之类PHP缓存扩展. 可以有效提高速度并降低CPU使用,在生产环境下测试效果非常显着.
 *
 * @package WPChineseConverter
 * @version see wpcc_VERSION constant below
 *
 */

// 基础常量定义
define("wpcc_DEBUG", false);
define("wpcc_VERSION", "1.3.0");

// 插件URL常量
if (defined("WP_PLUGIN_URL")) {
    define(
        "wpcc_DIR_URL",
        WP_PLUGIN_URL .
            "/" .
            str_replace(basename(__FILE__), "", plugin_basename(__FILE__)),
    );
} elseif (function_exists("plugins_url")) {
    define("wpcc_DIR_URL", plugins_url("", __FILE__) . "/");
} else {
    // 测试环境的fallback
    define("wpcc_DIR_URL", "/wp-content/plugins/wp-chinese-converter/");
}

// 全局变量初始化
$wpcc_debug_data = [];

// 初始化全局变量
$wpcc_admin = false;
$wpcc_noconversion_url = false;
$wpcc_redirect_to = false;
$wpcc_direct_conversion_flag = false;
$wpcc_langs_urls = [];
$wpcc_target_lang = false;

// 载入OpenCC库
require __DIR__ . "/vendor/autoload.php";

use Overtrue\PHPOpenCC\OpenCC;
use Overtrue\PHPOpenCC\Strategy;

// 载入核心依赖
require_once dirname(__FILE__) . "/includes/core/class-converter-factory.php";
require_once dirname(__FILE__) . "/includes/core/class-module-manager.php";
require_once dirname(__FILE__) . "/includes/blocks/blocks-init.php";

// 加载JS和CSS资源
function wpcc_add_global_js()
{
    global $wpcc_options;

    wp_register_script(
        "wpcc-variant",
        plugins_url("/assets/dist/wpcc-variant.umd.js", __FILE__),
        [],
        "1.1.0",
    );
    wp_register_script(
        "wpcc-block-script-ok",
        plugins_url("/assets/js/wpcc-block-script-ok.js", __FILE__),
        ["wp-blocks", "wp-element", "wpcc-variant"],
        "1.3.0",
    );

    wp_enqueue_script(["wpcc-variant", "wpcc-block-script-ok"]);
    wp_localize_script("wpcc-block-script-ok", "wpc_switcher_use_permalink", [
        "type" => $wpcc_options["wpcc_use_permalink"],
    ]);
}

// 获取插件配置
$wpcc_options = get_wpcc_option("wpcc_options");
if (empty($wpcc_options)) {
    $wpcc_options = [
        "wpcc_search_conversion" => 1,
        "wpcc_used_langs" => [
            "zh-hans",
            "zh-hant",
            "zh-cn",
            "zh-hk",
            "zh-sg",
            "zh-tw",
        ],
        "wpcc_browser_redirect" => 0,
        "wpcc_auto_language_recong" => 0,
        "wpcc_flag_option" => 1,
        "wpcc_use_cookie_variant" => 0,
        "wpcc_use_fullpage_conversion" => 1,
        "wpcco_use_sitemap" => 1,
        "wpcc_trackback_plugin_author" => 0,
        "wpcc_add_author_link" => 0,
        "wpcc_use_permalink" => 0,
        "wpcc_no_conversion_tag" => "",
        "wpcc_no_conversion_ja" => 0,
        "wpcc_no_conversion_qtag" => 0,
        "wpcc_enable_post_conversion" => 0,
        "wpcc_post_conversion_target" => "zh-cn",
        "wpcc_engine" => "opencc", // alternative: mediawiki
        "nctip" => "",
    ];
}

// 加载站点地图模块
$modules_dir = __DIR__ . "/includes/modules/";
if (file_exists($modules_dir . "wpcc-sitemap.php")) {
    require_once $modules_dir . "wpcc-sitemap.php";
}

// 容错处理 - 只有配置正确时才加载功能
if (
    $wpcc_options != false &&
    is_array($wpcc_options) &&
    is_array($wpcc_options["wpcc_used_langs"])
) {
    // 加载核心功能模块
    require_once dirname(__FILE__) . "/includes/wpcc-core.php";

    // 加载管理后台模块（仅在后台时加载）
    if (is_admin()) {
        require_once dirname(__FILE__) . "/includes/wpcc-admin.php";
    }

    // 注册基础钩子
    add_action("wp_enqueue_scripts", "wpcc_add_global_js");
    add_filter("query_vars", "wpcc_insert_query_vars");
    add_action("init", "wpcc_init");

    // 调试模式下的重写规则刷新
    if (WP_DEBUG || (defined("wpcc_DEBUG") && wpcc_DEBUG == true)) {
        add_action("init", function () {
            global $wp_rewrite;
            $wp_rewrite->flush_rules();
        });
        add_action("wp_footer", "wpcc_debug");
    }
}

/**
 * 检查模块是否存在
 */
function wpcc_mobile_exist($name)
{
    $modules_dir = __DIR__ . "/includes/modules/";
    return file_exists($modules_dir . "wpcc-$name.php");
}

/**
 * 封装获取配置的函数
 */
function get_wpcc_option($option_name, $default = false)
{
    if (is_multisite() && wpcc_mobile_exist("network")) {
        return get_site_option($option_name, $default);
    } else {
        return get_option($option_name, $default);
    }
}

/**
 * 封装更新配置的函数
 */
function update_wpcc_option($option_name, $option_value)
{
    if (is_multisite() && wpcc_mobile_exist("network")) {
        return update_site_option($option_name, $option_value);
    } else {
        return update_option($option_name, $option_value);
    }
}
