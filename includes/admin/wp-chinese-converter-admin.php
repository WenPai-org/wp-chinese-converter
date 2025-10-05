<?php

class wpcc_Admin
{
    private string $base = "";
    private bool $is_submitted = false;
    private bool $is_success = false;
    private bool $is_error = false;
    private string $message = "";
    private $options = false;
    private $langs = false;
    private string $url = "";
    private $admin_lang = false;

    // 移除旧式构造函数，只使用 __construct

    private function clean_invalid_langs(): void
    {
        global $wpcc_langs;
        if (
            isset($this->options["wpcc_used_langs"]) &&
            is_array($this->options["wpcc_used_langs"])
        ) {
            $valid_langs = [];
            foreach ($this->options["wpcc_used_langs"] as $lang) {
                if (isset($wpcc_langs[$lang])) {
                    $valid_langs[] = $lang;
                }
            }
            $this->options["wpcc_used_langs"] = $valid_langs;
        }
    }

    public function __construct()
    {
        global $wpcc_options, $wpcc_langs, $wpcc_modules;
        if (function_exists("wpcc_init_languages")) {
            wpcc_init_languages();
        }
        $locale = str_replace("_", "-", strtolower(get_locale()));
        if ($wpcc_options === false) {
            $wpcc_options = get_wpcc_option("wpcc_options");
        }
        $this->langs = &$wpcc_langs;
        $this->options = $wpcc_options;
        if (empty($this->options)) {
            $this->options = [
                // 语言与标签
                "wpcc_used_langs" => ["zh-cn", "zh-tw"],
                "cntip" => "简体",
                "twtip" => "繁体",
                "hktip" => "港澳",
                "hanstip" => "简体",
                "hanttip" => "繁体",
                "sgtip" => "马新",
                "jptip" => "日式",

                // 引擎与转换
                "wpcc_engine" => "mediawiki",
                "wpcc_search_conversion" => 1,
                "wpcc_use_fullpage_conversion" => 0,

                // 浏览器与 Cookie
                "wpcc_browser_redirect" => 0,
                "wpcc_auto_language_recong" => 0,
                "wpcc_use_cookie_variant" => 1,

                // 不转换
"wpcc_no_conversion_tag" => "pre,code,pre.wp-block-code,pre.wp-block-preformatted,script,noscript,style,kbd,samp",
                "wpcc_no_conversion_ja" => 0,
                "wpcc_no_conversion_qtag" => 0,

                // 发表时转换
                "wpcc_enable_post_conversion" => 0,
                "wpcc_post_conversion_target" => "zh-cn",

                // URL 与站点地图
                "wpcc_use_permalink" => 0,
                "wpcco_use_sitemap" => 0,
                "wpcco_sitemap_post_type" => "post,page",

                // SEO
                "wpcc_enable_hreflang_tags" => 1,
                "wpcc_hreflang_x_default" => "zh-cn",
                "wpcc_enable_schema_conversion" => 1,
                "wpcc_enable_meta_conversion" => 1,

                // 其他
                "wpcc_flag_option" => 1,
                "wpcc_trackback_plugin_author" => 0,
                "wpcc_add_author_link" => 0,
                "wpcc_translate_type" => 0,
                "nctip" => "",
                "wpcc_enable_cache_addon" => 1,
                "wpcc_enable_network_module" => 0,
            ];
        }

        $this->clean_invalid_langs();
        update_wpcc_option("wpcc_options", $this->options);

        $this->base = plugin_basename(dirname(__FILE__)) . "/";
        $page_slug = "wp-chinese-converter";

        if (is_network_admin() && wpcc_mobile_exist("network")) {
            $this->url = network_admin_url("settings.php?page=" . $page_slug);
        } else {
            $this->url = admin_url("options-general.php?page=" . $page_slug);
        }

        if (is_multisite() && wpcc_mobile_exist("network")) {
            add_submenu_page(
                "settings.php",
                "WP Chinese Converter",
                "WP Chinese Converter",
                "manage_network_options",
                $page_slug,
                [&$this, "display_options"],
            );
        } else {
            add_options_page(
                "WP Chinese Converter",
                "WP Chinese Converter",
                "manage_options",
                $page_slug,
                [&$this, "display_options"],
            );
        }

        wp_enqueue_script("jquery");

        add_action("wp_ajax_wpcc_clear_cache", [&$this, "ajax_clear_cache"]);
        add_action("wp_ajax_wpcc_preload_conversions", [
            &$this,
            "ajax_preload_conversions",
        ]);
        add_action("wp_ajax_wpcc_optimize_database", [
            &$this,
            "ajax_optimize_database",
        ]);
    }

    public function action_links(array $links, string $file): array
    {
        if ($file == $this->base . "wp-chinese-converter.php") {
            $links[] =
                '<a href="options-general.php?page=wp-chinese-converter" title="Change Settings">Settings</a>';
        }

        return $links;
    }

    public function is_gutenberg_active(): bool
    {
        // 检查是否启用了Gutenberg编辑器
        if (function_exists("use_block_editor_for_post_type")) {
            // 检查默认文章类型是否使用区块编辑器
            return use_block_editor_for_post_type("post");
        }

        // 备用检测：检查是否存在Gutenberg相关函数
        return function_exists("register_block_type") &&
            function_exists("wp_enqueue_block_editor_assets");
    }

    public function install_cache_module(): bool
    {
        global $wpcc_options;

        $ret = true;

        $file = file_get_contents(
            dirname(__FILE__) . "/../modules/wpcc-cache-addon.php",
        );

        $used_langs = "Array()";
        if (count($wpcc_options["wpcc_used_langs"]) > 0) {
            $used_langs =
                "Array('" .
                implode("', '", $wpcc_options["wpcc_used_langs"]) .
                "')";
        }
        $file = str_replace(
            "##wpcc_auto_language_recong##",
            $wpcc_options["wpcc_auto_language_recong"],
            $file,
        );
        $file = str_replace("##wpcc_used_langs##", $used_langs, $file);

        $fp = @fopen(
            WP_PLUGIN_DIR .
                "/wp-super-cache/plugins/wpcc-wp-super-cache-plugin.php",
            "w",
        );
        if ($fp) {
            @fwrite($fp, $file);
            @fclose($fp);
        } else {
            $ret = false;
        }

        return $ret;
    }

    public function uninstall_cache_module(): bool
    {
        return unlink(
            WP_PLUGIN_DIR .
                "/wp-super-cache/plugins/wpcc-wp-super-cache-plugin.php",
        );
    }

    public function get_cache_status(): int
    {
        if (!function_exists("wp_cache_is_enabled")) {
            return 0;
        }
        if (
            !file_exists(
                WP_PLUGIN_DIR .
                    "/wp-super-cache/plugins/wpcc-wp-super-cache-plugin.php",
            )
        ) {
            return 1;
        }

        return 2;
    }

    function display_options()
    {
        global $wp_rewrite;

        wp_enqueue_style(
            "wpcc-admin",
            plugin_dir_url(
                dirname(dirname(__DIR__)) . "/wp-chinese-converter.php",
            ) . "assets/admin/admin.css",
            [],
            "2.0.0",
        );

        wp_enqueue_script(
            "wpcc-admin",
            plugin_dir_url(
                dirname(dirname(__DIR__)) . "/wp-chinese-converter.php",
            ) . "assets/admin/admin.js",
            ["jquery"],
            "2.0.0",
            true,
        );

        // 传递Gutenberg检测数据给JavaScript
        wp_localize_script("wpcc-admin", "wpccAdminData", [
            "isGutenbergActive" => $this->is_gutenberg_active(),
        ]);

        if (!empty($_POST["wpcco_uninstall_nonce"])) {
            delete_option("wpcc_options");
            update_option("rewrite_rules", "");
            echo '<div class="wrap"><h2>WP Chinese Converter Setting</h2><div class="updated">Uninstall Successfully. 卸载成功, 现在您可以到<a href="plugins.php">插件菜单</a>里禁用本插件.</div></div>';
            return;
        } elseif ($this->options === false) {
            echo '<div class="wrap"><h2>WP Chinese Converter Setting</h2><div class="error">错误: 没有找到配置信息, 可能由于WordPress系统错误或者您已经卸载了本插件. 您可以<a href="plugins.php">尝试</a>禁用本插件后再重新激活.</div></div>';
            return;
        }

        // 处理“重置为默认设置”
        if (!empty($_POST['wpcc_reset_defaults']) && current_user_can('manage_options')) {
            if (check_admin_referer('wpcc_reset_defaults', 'wpcc_reset_nonce')) {
                $ok = false;
                if (class_exists('WPCC_Presets')) {
                    // 利用工厂默认预设恢复默认
                    $ok = WPCC_Presets::apply_preset('factory_default');
                }
                if ($ok) {
                    $this->options = get_wpcc_option('wpcc_options', []);
                    $this->is_submitted = true;
                    $this->is_success = true;
                    $this->message .= __('已重置为默认设置。', 'wp-chinese-converter');
                } else {
                    $this->is_submitted = true;
                    $this->is_error = true;
                    $this->message .= __('重置失败。', 'wp-chinese-converter');
                }
            } else {
                $this->is_submitted = true;
                $this->is_error = true;
                $this->message .= __('安全验证失败。', 'wp-chinese-converter');
            }
        }

        if (!empty($_POST["toggle_cache"])) {
            if ($this->get_cache_status() == 1) {
                $result = $this->install_cache_module();
                if ($result) {
                    echo '<div class="updated fade" style=""><p>' .
                        _e(
                            "安装WP Super Cache 兼容成功.",
                            "wp-chinese-converter",
                        ) .
                        "</p></div>";
                } else {
                    echo '<div class="error" style=""><p>' .
                        _e(
                            "错误: 安装WP Super Cache 兼容失败",
                            "wp-chinese-converter",
                        ) .
                        ".</p></div>";
                }
            } elseif ($this->get_cache_status() == 2) {
                $result = $this->uninstall_cache_module();
                if ($result) {
                    echo '<div class="updated fade" style=""><p>' .
                        _e(
                            "卸载WP Super Cache 兼容成功",
                            "wp-chinese-converter",
                        ) .
                        "</p></div>";
                } else {
                    echo '<div class="error" style=""><p>' .
                        _e(
                            "错误: 卸载WP Super Cache 兼容失败",
                            "wp-chinese-converter",
                        ) .
                        ".</p></div>";
                }
            }
        }

        if (!empty($_POST["wpcco_submitted"]) && empty($_POST['wpcc_reset_defaults'])) {
            $this->is_submitted = true;
            $this->process();

            if ($this->get_cache_status() == 2) {
                $this->install_cache_module();
            }
        }

        ob_start();
        include dirname(__FILE__) . "/../wpcc-settings-page.php";
        $o = ob_get_clean();

        if ($this->admin_lang) {
            wpcc_load_conversion_table();
            $o = limit_zhconversion($o, $this->langs[$this->admin_lang][0]);
        }

        echo $o;
    }

    function navi()
    {
        $variant =
            isset($_GET["variant"]) && !empty($_GET["variant"])
                ? $_GET["variant"]
                : "";
        $str =
            '<span><a title="' .
            __("默认", "wp-chinese-converter") .
            '" href="' .
            $this->url .
            '" ' .
            (!$variant
                ? 'style="color: #464646; text-decoration: none !important;"'
                : "") .
            " >" .
            __("默认", "wp-chinese-converter") .
            "</a></span>&nbsp;";
        if (!$this->options["wpcc_used_langs"]) {
            return $str;
        }
        if (is_array($this->langs)) {
            foreach ($this->langs as $key => $value) {
                $str .=
                    '<span><a href="' .
                    $this->url .
                    "&variant=" .
                    $key .
                    '" title="' .
                    $value[2] .
                    '" ' .
                    ($variant == $key
                        ? 'style="color: #464646; text-decoration: none !important;"'
                        : "") .
                    ">" .
                    $value[2] .
                    "</a>&nbsp;</span>";
            }
        }

        return $str;
    }

    function process()
    {
        global $wp_rewrite, $wpcc_options;

        // 获取当前设置作为基础
        $options = $this->options;

        // 只更新表单中实际提交的字段

        // 语言设置
        if (
            isset($_POST["wpcco_variant_zh-cn"]) ||
            isset($_POST["wpcco_variant_zh-tw"]) ||
            isset($_POST["wpcco_variant_zh-hk"])
        ) {
            $langs = [];
            if (is_array($this->langs)) {
                foreach ($this->langs as $key => $value) {
                    if (isset($_POST["wpcco_variant_" . $key])) {
                        $langs[] = $key;
                    }
                }
            }
            $options["wpcc_used_langs"] = $langs;
        }

        // 复选框字段（未选中时不会在POST中出现）
        $checkbox_fields = [
            "wpcco_use_fullpage_conversion" => "wpcc_use_fullpage_conversion",
            "wpcco_use_sitemap" => "wpcco_use_sitemap",
            "wpcco_auto_language_recong" => "wpcc_auto_language_recong",
            "wpcc_enable_cache_addon" => "wpcc_enable_cache_addon",
            "wpcc_enable_network_module" => "wpcc_enable_network_module",
            "wpcc_enable_hreflang_tags" => "wpcc_enable_hreflang_tags",
            "wpcc_enable_schema_conversion" => "wpcc_enable_schema_conversion",
            "wpcc_enable_meta_conversion" => "wpcc_enable_meta_conversion",
            "wpcc_show_more_langs" => "wpcc_show_more_langs",
            "wpcc_no_conversion_qtag" => "wpcc_no_conversion_qtag",
            "wpcc_enable_post_conversion" => "wpcc_enable_post_conversion",
        ];

        foreach ($checkbox_fields as $post_field => $option_field) {
            $options[$option_field] = isset($_POST[$post_field]) ? 1 : 0;
        }

        // 文本字段和下拉选择框
        $text_fields = [
            "wpcc_translate_type" => "wpcc_translate_type",
            "wpcco_no_conversion_tag" => "wpcc_no_conversion_tag",
            "wpcco_no_conversion_tip" => "nctip",
            "wpcc_engine" => "wpcc_engine",
            "wpcco_search_conversion" => "wpcc_search_conversion",
            "wpcco_browser_redirect" => "wpcc_browser_redirect",
            "wpcco_use_cookie_variant" => "wpcc_use_cookie_variant",
            "wpcco_use_permalink" => "wpcc_use_permalink",
            "wpcco_sitemap_post_type" => "wpcco_sitemap_post_type",
            "wpcc_hreflang_x_default" => "wpcc_hreflang_x_default",
            "wpcc_post_conversion_target" => "wpcc_post_conversion_target",
        ];

        foreach ($text_fields as $post_field => $option_field) {
            if (isset($_POST[$post_field])) {
                if ($post_field === "wpcco_no_conversion_tag") {
                    $options[$option_field] = trim(
                        $_POST[$post_field],
                        " \t\n\r\0\x0B,|",
                    );
                } elseif ($post_field === "wpcco_no_conversion_tip") {
                    $options[$option_field] = trim($_POST[$post_field]);
                } elseif ($post_field === "wpcco_sitemap_post_type") {
                    $options[$option_field] = trim($_POST[$post_field]);
                } elseif (
                    in_array($post_field, [
                        "wpcc_translate_type",
                        "wpcco_search_conversion",
                        "wpcco_browser_redirect",
                        "wpcco_use_cookie_variant",
                        "wpcco_use_permalink",
                    ])
                ) {
                    $options[$option_field] = intval($_POST[$post_field]);
                } else {
                    $options[$option_field] = sanitize_text_field(
                        $_POST[$post_field],
                    );
                }
            }
        }

        if (is_array($this->langs)) {
            foreach ($this->langs as $lang => $value) {
                if (
                    is_array($value) &&
                    isset($value[1]) &&
                    isset($_POST[$value[1]]) &&
                    !empty($_POST[$value[1]])
                ) {
                    $options[$value[1]] = trim($_POST[$value[1]]);
                }
            }
        }

        if (
            $this->get_cache_status() == 2 &&
            empty($options["wpcc_browser_redirect"]) &&
            empty($options["wpcc_use_cookie_variant"])
        ) {
            $this->uninstall_cache_module();
        }

        $wpcc_options = $options;
        $need_flush_rules = false;

        if (
            $this->options["wpcc_use_permalink"] !=
                $options["wpcc_use_permalink"] ||
            ($this->options["wpcc_use_permalink"] != 0 &&
                $this->options["wpcc_used_langs"] !=
                    $options["wpcc_used_langs"])
        ) {
            if (!has_filter("rewrite_rules_array", "wpcc_rewrite_rules")) {
                add_filter("rewrite_rules_array", "wpcc_rewrite_rules");
            }
            $need_flush_rules = true;
        }

        // 检查站点地图设置是否有变化
        if (
            isset($this->options["wpcco_use_sitemap"]) &&
            isset($options["wpcco_use_sitemap"]) &&
            $this->options["wpcco_use_sitemap"] != $options["wpcco_use_sitemap"]
        ) {
            $need_flush_rules = true;
            // 重置站点地图重写规则刷新标记
            delete_option("wpcc_sitemap_rewrite_rules_flushed");
        }

        if ($need_flush_rules) {
            $wp_rewrite->flush_rules();
        }

        update_wpcc_option("wpcc_options", $options);

        $this->options = $options;
        $this->is_success = true;
        $this->message .= "设置已更新。";
    }

    public function ajax_clear_cache()
    {
        if (!check_ajax_referer("wpcc_tools", "nonce", false)) {
            wp_send_json_error("安全验证失败");
            return;
        }

        if (!current_user_can("manage_options")) {
            wp_send_json_error("权限不足");
            return;
        }

        if (class_exists("WPCC_Modern_Cache")) {
            $cache_module = new WPCC_Modern_Cache();
            $cache_module->clear_all_cache();

            wp_send_json_success(["message" => "缓存已清除"]);
        } else {
            wp_send_json_error("缓存模块未加载");
        }
    }

    public function ajax_preload_conversions()
    {
        if (!check_ajax_referer("wpcc_tools", "nonce", false)) {
            wp_send_json_error("安全验证失败");
            return;
        }

        if (!current_user_can("manage_options")) {
            wp_send_json_error("权限不足");
            return;
        }

        if (class_exists("WPCC_Modern_Cache")) {
            $cache_module = new WPCC_Modern_Cache();
            $cache_module->preload_common_conversions();

            wp_send_json_success(["message" => "常用转换预加载完成"]);
        } else {
            wp_send_json_error("缓存模块未加载");
        }
    }

    public function ajax_optimize_database()
    {
        if (!check_ajax_referer("wpcc_tools", "nonce", false)) {
            wp_send_json_error("安全验证失败");
            return;
        }

        if (!current_user_can("manage_options")) {
            wp_send_json_error("权限不足");
            return;
        }

        global $wpdb;

        $tables = [$wpdb->options];
        $optimized = 0;

        foreach ($tables as $table) {
            $result = $wpdb->query("OPTIMIZE TABLE {$table}");
            if ($result !== false) {
                $optimized++;
            }
        }

        if (function_exists("wp_cache_flush")) {
            wp_cache_flush();
        }

        wp_send_json_success(["message" => "已优化 {$optimized} 个数据表"]);
    }
}
