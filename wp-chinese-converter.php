<?php
/*
 * Plugin Name: WP Chinese Converter
 * Description: Adds the language conversion function between Chinese Simplified and Chinese Traditional to your WP Website.
 * Author: WPCC.NET
 * Author URI: https://wpcc.net
 * Text Domain: wp-chinese-converter
 * Domain Path: /languages
* Version: 1.5
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
define("wpcc_VERSION", "1.5");

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
require_once dirname(__FILE__) .
    "/includes/core/class-wpcc-exception-handler.php";
require_once dirname(__FILE__) . "/includes/core/class-wpcc-utils.php";
require_once dirname(__FILE__) .
    "/includes/core/class-wpcc-language-config.php";
require_once dirname(__FILE__) . "/includes/core/class-wpcc-converter-factory.php";
require_once dirname(__FILE__) . "/includes/core/class-wpcc-module-manager.php";
require_once dirname(__FILE__) . "/includes/core/class-wpcc-conversion-cache.php";
require_once dirname(__FILE__) . "/includes/core/class-wpcc-config.php";
require_once dirname(__FILE__) . "/includes/core/class-wpcc-presets.php";
require_once dirname(__FILE__) . "/includes/core/class-wpcc-main.php";
require_once dirname(__FILE__) . "/includes/blocks/blocks-init.php";

// 加载JS和CSS资源
function wpcc_add_global_js()
{
    global $wpcc_options;

    wp_register_script(
        "wpcc-variant",
        plugins_url("/assets/dist/wpcc-variant.umd.js", __FILE__),
        [],
        wpcc_VERSION,
    );
    wp_register_script(
        "wpcc-block-script-ok",
        plugins_url("/assets/js/wpcc-block-script-ok.js", __FILE__),
        ["wp-blocks", "wp-element", "wpcc-variant"],
        wpcc_VERSION,
    );

    wp_enqueue_script(["wpcc-variant", "wpcc-block-script-ok"]);
    $use_permalink_type = 0;
    if (is_array($wpcc_options) && isset($wpcc_options["wpcc_use_permalink"])) {
        $use_permalink_type = (int) $wpcc_options["wpcc_use_permalink"];
    }
    wp_localize_script("wpcc-block-script-ok", "wpc_switcher_use_permalink", [
        "type" => $use_permalink_type,
    ]);
}

// 初始化现代化架构
$wpcc_main = WPCC_Main::get_instance();
$wpcc_config = $wpcc_main->get_config();

// 保持向后兼容性
$wpcc_options = $wpcc_config->get_all_options();

// 加载站点地图模块
$modules_dir = __DIR__ . "/includes/modules/";
if (file_exists($modules_dir . "wpcc-sitemap.php")) {
    require_once $modules_dir . "wpcc-sitemap.php";
}

// 加载网络设置模块
if (
    is_multisite() &&
    file_exists(__DIR__ . "/includes/network/wpcc-network-settings.php")
) {
    require_once __DIR__ . "/includes/network/wpcc-network-settings.php";
    add_action("init", ["WPCC_Network_Settings", "init"]);
}


// 容错处理 - 只有配置正确时才加载功能
if (
    $wpcc_options != false &&
    is_array($wpcc_options) &&
    is_array($wpcc_options["wpcc_used_langs"])
) {
    // 加载遗留的核心功能模块（为了向后兼容）
    require_once dirname(__FILE__) . "/includes/wpcc-core.php";

    // 确保核心初始化与查询变量已注册（保证变体参数与重写规则生效）
    if (!has_action("init", "wpcc_init")) {
        add_action("init", "wpcc_init");
    }
    if ( ! class_exists( 'WPCC_Main' ) ) {
        add_filter("query_vars", "wpcc_insert_query_vars");
    }

    // 加载管理后台模块（仅在后台时加载）
    if (is_admin()) {
        require_once dirname(__FILE__) . "/includes/wpcc-admin.php";
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
    // 使用现代化的配置管理器
    global $wpcc_main;
    if ($wpcc_main && method_exists($wpcc_main, "get_config")) {
        $config = $wpcc_main->get_config();
        if ($option_name === "wpcc_options") {
            return $config->get_all_options();
        }
        return $config->get_option($option_name, $default);
    }

    // 向后兼容
    return get_option($option_name, $default);
}

/**
 * 封装更新配置的函数
 */
function update_wpcc_option($option_name, $option_value)
{
    // 始终将站点级设置保存到当前站点的 options；
    // 网络级设置在网络设置模块中使用 update_site_option 专门处理。
    return update_option($option_name, $option_value);
}
