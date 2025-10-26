<?php

if (!defined("ABSPATH")) {
    exit();
}

class WPCC_Network_Settings
{
    private static $default_controlled_options = [
        // 语言与界面
        "wpcc_used_langs",
        "cntip",
        "twtip",
        "hktip",
        "hanstip",
        "hanttip",
        "sgtip",
        "jptip",
        "nctip",
        "wpcc_flag_option",
        "wpcc_show_more_langs",
        // 引擎与核心
        "wpcc_engine",
        "wpcc_search_conversion",
        "wpcc_use_fullpage_conversion",
        // 编辑器
        "wpcc_no_conversion_qtag",
        "wpcc_enable_post_conversion",
        "wpcc_post_conversion_target",
        // URL 与站点地图
        "wpcc_use_permalink",
        "wpcco_sitemap_post_type",
        "wpcco_use_sitemap",
        // 智能检测
        "wpcc_browser_redirect",
        "wpcc_auto_language_recong",
        "wpcc_use_cookie_variant",
        "wpcc_first_visit_default",
        // 过滤
        "wpcc_no_conversion_tag",
        "wpcc_no_conversion_ja",
        // SEO
        "wpcc_hreflang_x_default",
        "wpcc_enable_hreflang_tags",
        "wpcc_enable_hreflang_x_default",
        "wpcc_enable_schema_conversion",
        "wpcc_enable_meta_conversion",
        // 兼容性
        "wpcc_enable_cache_addon",
        "wpcc_enable_network_module",
    ];

    public static function init()
    {
        if (!is_multisite()) {
            return;
        }

        add_action("network_admin_menu", [__CLASS__, "add_network_menu"]);
        add_action("admin_action_wpcc_network_settings", [
            __CLASS__,
            "save_network_settings",
        ]);
        add_action("admin_action_wpcc_export_settings", [
            __CLASS__,
            "export_settings",
        ]);
        add_action("admin_action_wpcc_import_settings", [
            __CLASS__,
            "import_settings",
        ]);
        add_action("admin_action_wpcc_reset_network_settings", [
            __CLASS__,
            "reset_network_settings",
        ]);
        add_action("admin_action_wpcc_bulk_reset_sites", [
            __CLASS__,
            "bulk_reset_sites",
        ]);
        add_action("admin_notices", [__CLASS__, "network_managed_notice"]);
        add_action("network_admin_notices", [
            __CLASS__,
            "network_admin_notices",
        ]);

        if (self::is_network_enforced()) {
            add_action(
                "admin_menu",
                [__CLASS__, "maybe_remove_site_menu"],
                999,
            );
        }
    }

    public static function add_network_menu()
    {
        add_submenu_page(
            "settings.php",
            "文派译词 网络设置",
            "文派译词",
            "manage_network_options",
            "wpcc-network",
            [__CLASS__, "render_network_page"],
        );
    }

    public static function maybe_remove_site_menu()
    {
        if (self::is_network_enforced()) {
            remove_submenu_page("options-general.php", "wp-chinese-converter");
        }
    }

    public static function network_managed_notice()
    {
        if (!is_multisite() || is_network_admin()) {
            return;
        }

        $screen = get_current_screen();
        if (!$screen || $screen->id !== "settings_page_wp-chinese-converter") {
            return;
        }

        $network_controlled_options = get_site_option(
            "wpcc_network_controlled_options",
            self::$default_controlled_options,
        );
        if (!is_array($network_controlled_options)) {
            $network_controlled_options = explode(
                ",",
                $network_controlled_options,
            );
        }

        $controlled_count = count($network_controlled_options);

        if (get_site_option("wpcc_network_enforce", 0)) {
            echo '<div class="notice notice-warning"><p>' .
                "WP Chinese Converter 插件正由网络管理员强制管理。所有设置将使用网络级别配置，任何更改将被忽略。如有疑问请联系网络管理员。" .
                "</p></div>";
        } elseif ($controlled_count > 0) {
            echo '<div class="notice notice-info"><p>' .
                sprintf(
                    "WP Chinese Converter 插件的 %d 项设置由网络管理员控制。这些设置的更改将不会生效。",
                    $controlled_count,
                ) .
                "</p></div>";
        }
    }

    public static function network_admin_notices()
    {
        if (isset($_GET["updated"]) && $_GET["updated"] === "true") {
            echo '<div class="notice notice-success is-dismissible"><p>网络设置已更新。</p></div>';
        }
        if (isset($_GET["imported"]) && $_GET["imported"] === "true") {
            echo '<div class="notice notice-success is-dismissible"><p>设置导入成功。</p></div>';
        }
        if (isset($_GET["reset"]) && $_GET["reset"] === "true") {
            echo '<div class="notice notice-success is-dismissible"><p>网络设置已重置为默认值。</p></div>';
        }
        if (isset($_GET["bulk_reset"]) && $_GET["bulk_reset"] === "true") {
            $reset_count = isset($_GET["reset_count"])
                ? intval($_GET["reset_count"])
                : 0;
            echo '<div class="notice notice-success is-dismissible"><p>已成功重置 ' .
                $reset_count .
                " 个站点的设置。</p></div>";
        }
    }

    public static function is_network_enforced()
    {
        return get_site_option("wpcc_network_enforce", 0);
    }

    public static function is_option_controlled($option_name)
    {
        if (!is_multisite()) {
            return false;
        }

        $controlled_options = get_site_option(
            "wpcc_network_controlled_options",
            self::$default_controlled_options,
        );
        return in_array($option_name, $controlled_options);
    }

    public static function get_network_option($option_name, $default = null)
    {
        if (self::is_option_controlled($option_name)) {
            return get_site_option($option_name, $default);
        }
        return $default;
    }

    public static function render_network_page()
    {
        $active_tab = isset($_GET["tab"])
            ? sanitize_text_field($_GET["tab"])
            : "network";

        wp_enqueue_style(
            "wpcc-admin",
            plugin_dir_url(
                dirname(dirname(__DIR__)) . "/wp-chinese-converter.php",
            ) . "assets/admin/admin.css",
            [],
            wpcc_VERSION,
        );

        wp_enqueue_script(
            "wpcc-admin",
            plugin_dir_url(
                dirname(dirname(__DIR__)) . "/wp-chinese-converter.php",
            ) . "assets/admin/admin.js",
            ["jquery"],
            wpcc_VERSION,
            true,
        );
        ?>
        <div class="wrap wpcc-settings">
            <h1>
                <?php esc_html_e("文派译词网络设置", "wp-chinese-converter"); ?>
                <span style="font-size: 13px; padding-left: 10px; color: #666;">
                    <?php printf(
                        esc_html__("版本: %s", "wp-chinese-converter"),
                        esc_html(wpcc_VERSION),
                    ); ?>
                </span>
                <a href="https://wpcc.net/document/" target="_blank" class="button button-secondary" style="margin-left: 10px;">
                    <?php esc_html_e("文档", "wp-chinese-converter"); ?>
                </a>
                <a href="https://wpcc.net/support/" target="_blank" class="button button-secondary">
                    <?php esc_html_e("支持", "wp-chinese-converter"); ?>
                </a>
            </h1>

            <div id="wpcc-status" class="notice" style="display:none; margin-top: 10px; padding: 8px 12px;"></div>

            <div class="wpcc-card">
                <div class="wpcc-tabs-wrapper">
                    <div class="wpcc-sync-tabs">
                        <button type="button" class="wpcc-tab <?php echo $active_tab ===
                        "network"
                            ? "active"
                            : ""; ?>" data-tab="network">
                            网络管理
                        </button>
                        <button type="button" class="wpcc-tab <?php echo $active_tab ===
                        "basic"
                            ? "active"
                            : ""; ?>" data-tab="basic">
                            基础设置
                        </button>
                        <button type="button" class="wpcc-tab <?php echo $active_tab ===
                        "import_export"
                            ? "active"
                            : ""; ?>" data-tab="import_export">
                            导入导出
                        </button>
                        <button type="button" class="wpcc-tab <?php echo $active_tab ===
                        "tools"
                            ? "active"
                            : ""; ?>" data-tab="tools">
                            工具维护
                        </button>
                    </div>
                </div>

                <!-- 网络管理选项卡 -->
                <div class="wpcc-section" id="wpcc-section-network" style="<?php echo $active_tab !==
                "network"
                    ? "display: none;"
                    : ""; ?>">
                    <h2><?php _e(
                        "网络管理设置",
                        "wp-chinese-converter",
                    ); ?></h2>
                    <p class="wpcc-section-desc"><?php _e(
                        "配置多站点网络的中文转换管理方式，控制子站点的设置权限。",
                        "wp-chinese-converter",
                    ); ?></p>

                    <form method="post" action="edit.php?action=wpcc_network_settings" id="wpcc-network-form">
                        <?php wp_nonce_field("wpcc_network_settings"); ?>
                        <input type="hidden" name="tab" value="network">

                        <table class="form-table">
                            <tr>
                                <th>启用网络级管理</th>
                                <td>
                                    <label class="wpcc-switch">
                                        <input type="checkbox" name="wpcc_network_enabled" value="1" <?php checked(
                                            get_site_option(
                                                "wpcc_network_enabled",
                                                1,
                                            ),
                                        ); ?>>
                                        <span class="wpcc-slider"></span>
                                        <span class="wpcc-switch-label">在所有站点使用网络设置</span>
                                    </label>
                                    <p class="description">启用后，所有子站点将使用选定的网络设置，子站点的对应设置将被覆盖</p>
                                </td>
                            </tr>
                            <tr>
                                <th>强制使用网络设置</th>
                                <td>
                                    <label class="wpcc-switch">
                                        <input type="checkbox" name="wpcc_network_enforce" value="1" <?php checked(
                                            get_site_option(
                                                "wpcc_network_enforce",
                                                0,
                                            ),
                                        ); ?>>
                                        <span class="wpcc-slider"></span>
                                        <span class="wpcc-switch-label">完全禁用子站点设置页面</span>
                                    </label>
                                    <p class="description">启用后，子站点将无法访问WP Chinese Converter设置页面，所有设置完全由网络管理员控制</p>
                                </td>
                            </tr>
                            <tr>
                                <th>网络控制选项</th>
                                <td>
                                    <?php
                                    $controlled_options = get_site_option(
                                        "wpcc_network_controlled_options",
                                        self::$default_controlled_options,
                                    );
                                    if (!is_array($controlled_options)) {
                                        $controlled_options = explode(
                                            ",",
                                            $controlled_options,
                                        );
                                    }

                                    $all_options = [
                                        "wpcc_used_langs" => "语言模块",
                                        "cntip" => "简体中文显示名",
                                        "twtip" => "台湾正体显示名",
                                        "hktip" => "港澳繁体显示名",
                                        "hanstip" => "简体中文(国际)显示名",
                                        "hanttip" => "繁体中文(国际)显示名",
                                        "sgtip" => "马新简体显示名",
                                        "jptip" => "日式汉字显示名",
                                        "nctip" => '"不转换"标签显示名',
                                        "wpcc_flag_option" => "展示形式",
                                        // Harmonize key with single-site UI
                                        "wpcc_show_more_langs" =>
                                            "扩展语言模块",
                                        "wpcc_engine" => "转换引擎",
                                        "wpcc_search_conversion" => "搜索转换",
                                        "wpcc_use_fullpage_conversion" =>
                                            "全页面转换",
                                        // Harmonize quicktags key with single-site
                                        "wpcc_no_conversion_qtag" =>
                                            "编辑器快速标签",
                                        "wpcc_enable_post_conversion" =>
                                            "发表时转换",
                                        "wpcc_post_conversion_target" =>
                                            "发表时转换目标语言",
                                        "wpcc_use_permalink" => "URL链接格式",
                                        "wpcco_sitemap_post_type" =>
                                            "站点地图内容类型",
                                        "wpcco_use_sitemap" => "多语言站点地图",
                                        "wpcc_browser_redirect" =>
                                            "浏览器语言检测",
                                        "wpcc_auto_language_recong" =>
                                            "语系内通用",
                                        "wpcc_use_cookie_variant" =>
                                            "Cookie偏好记忆",
                                        "wpcc_first_visit_default" =>
                                            "首次访问不转换",
                                        "wpcc_no_conversion_tag" => "标签排除",
                                        "wpcc_no_conversion_ja" =>
                                            "日语内容排除",
                                        "wpcc_hreflang_x_default" =>
                                            "SEO默认语言",
                                        "wpcc_enable_hreflang_tags" =>
                                            "hreflang标签",
                                        "wpcc_enable_hreflang_x_default" =>
                                            "x-default 标签",
                                        "wpcc_enable_schema_conversion" =>
                                            "Schema数据转换",
                                        "wpcc_enable_meta_conversion" =>
                                            "SEO元数据转换",
                                        "wpcc_enable_cache_addon" =>
                                            "缓存插件兼容",
                                        "wpcc_allow_uninstall" =>
                                            "允许插件卸载",
                                    ];

                                    $option_groups = [
                                        "language" => [
                                            "title" => "语言设置",
                                            "options" => [
                                                "wpcc_used_langs",
                                                "cntip",
                                                "twtip",
                                                "hktip",
                                                "hanstip",
                                                "hanttip",
                                                "sgtip",
                                                "jptip",
                                                "nctip",
                                                "wpcc_flag_option",
                                                "wpcc_show_more_langs",
                                            ],
                                        ],
                                        "engine" => [
                                            "title" => "核心功能",
                                            "options" => [
                                                "wpcc_engine",
                                                "wpcc_search_conversion",
                                                "wpcc_use_fullpage_conversion",
                                            ],
                                        ],
                                        "editor" => [
                                            "title" => "编辑器增强",
                                            "options" => [
                                                "wpcc_no_conversion_qtag",
                                                "wpcc_enable_post_conversion",
                                                "wpcc_post_conversion_target",
                                            ],
                                        ],
                                        "url_sitemap" => [
                                            "title" => "站点地图",
                                            "options" => [
                                                "wpcc_use_permalink",
                                                "wpcco_sitemap_post_type",
                                                "wpcco_use_sitemap",
                                            ],
                                        ],
                                        "detection" => [
                                            "title" => "智能检测",
                                        "options" => [
                                                "wpcc_browser_redirect",
                                                "wpcc_auto_language_recong",
                                                "wpcc_use_cookie_variant",
                                                "wpcc_first_visit_default",
                                            ],
                                        ],
                                        "filter" => [
                                            "title" => "内容过滤",
                                            "options" => [
                                                "wpcc_no_conversion_tag",
                                                "wpcc_no_conversion_ja",
                                            ],
                                        ],
                                        "seo" => [
                                            "title" => "SEO优化",
                                            "options" => [
                                                "wpcc_hreflang_x_default",
                                                "wpcc_enable_hreflang_tags",
                                                "wpcc_enable_hreflang_x_default",
                                                "wpcc_enable_schema_conversion",
                                                "wpcc_enable_meta_conversion",
                                            ],
                                        ],
                                        "compatibility" => [
                                            "title" => "缓存兼容",
                                            "options" => [
                                                "wpcc_enable_cache_addon",
                                                "wpcc_allow_uninstall",
                                            ],
                                        ],
                                    ];

                                    echo '<div class="network-control-options">';
                                    foreach (
                                        $option_groups
                                        as $group => $group_data
                                    ) {
                                        echo '<div class="option-group">';
                                        echo "<h4>" .
                                            esc_html($group_data["title"]) .
                                            "</h4>";
                                        if (isset($group_data["description"])) {
                                            echo '<p class="group-description">' .
                                                esc_html(
                                                    $group_data["description"],
                                                ) .
                                                "</p>";
                                        }

                                        foreach (
                                            $group_data["options"]
                                            as $option
                                        ) {
                                            if (isset($all_options[$option])) {
                                                echo '<label class="wpcc-checkbox">';
                                                echo '<input type="checkbox" name="wpcc_network_controlled_options[]" value="' .
                                                    esc_attr($option) .
                                                    '" ' .
                                                    (in_array(
                                                        $option,
                                                        $controlled_options,
                                                    )
                                                        ? "checked"
                                                        : "") .
                                                    ">";
                                                echo '<span class="wpcc-checkbox-label">' .
                                                    esc_html(
                                                        $all_options[$option],
                                                    ) .
                                                    "</span>";
                                                echo "</label>";
                                            }
                                        }

                                        echo "</div>";
                                    }
                                    echo "</div>";
                                    ?>
                                    <p class="description">选中的选项将在所有站点使用网络设置，未选中的选项可由站点管理员自定义。</p>
                                    <div class="wpcc-action-buttons" style="margin-top:10px;">
                                        <button type="button" id="select-all-options" class="button button-secondary"><?php _e(
                                            "全选",
                                            "wp-chinese-converter",
                                        ); ?></button>
                                        <button type="button" id="deselect-all-options" class="button button-secondary"><?php _e(
                                            "取消全选",
                                            "wp-chinese-converter",
                                        ); ?></button>
                                        <button type="button" id="reset-default-options" class="button button-secondary"><?php _e(
                                            "恢复默认",
                                            "wp-chinese-converter",
                                        ); ?></button>
                                    </div>
                                </td>
                            </tr>
                        </table>

                        <div class="wpcc-submit-wrapper">
                            <button type="submit" class="button button-primary">保存设置</button>
                        </div>
                    </form>
                </div>

                <!-- 基础设置选项卡 -->
                <div class="wpcc-section" id="wpcc-section-basic" style="<?php echo $active_tab !==
                "basic"
                    ? "display: none;"
                    : ""; ?>">
                    <h2><?php _e("基础设置", "wp-chinese-converter"); ?></h2>
                    <p class="wpcc-section-desc"><?php _e(
                        "配置网络默认设置，新建站点将自动应用这些设置。",
                        "wp-chinese-converter",
                    ); ?></p>

                    <form method="post" action="edit.php?action=wpcc_network_settings" id="wpcc-basic-form">
                        <?php wp_nonce_field("wpcc_network_settings"); ?>
                        <input type="hidden" name="tab" value="basic">

                        <h3>语言和界面设置</h3>
                        <table class="form-table">
                            <tr>
                                <th>语言模块 <a href="https://wpcc.net/document/language-modules" target="_blank" class="wpcc-doc-link" title="查看详细说明">↗</a></th>
                                <td>
                                    <div class="wpcc-language-section">
                                        <div style="margin-bottom: 10px;">
                                            <label class="wpcc-switch">
                                                <input type="checkbox" name="wpcc_default_variant_zh-cn" value="1" <?php checked(
                                                    in_array(
                                                        "zh-cn",
                                                        get_site_option(
                                                            "wpcc_default_used_langs",
                                                            ["zh-cn", "zh-tw"],
                                                        ),
                                                    ),
                                                ); ?>>
                                                <span class="wpcc-slider"></span>
                                                <span class="wpcc-switch-label">中国大陆 (zh-cn)</span>
                                            </label>
                                            <input type="text" name="wpcc_default_cntip" value="<?php echo esc_attr(
                                                get_site_option(
                                                    "wpcc_default_cntip",
                                                    "简体",
                                                ),
                                            ); ?>" placeholder="中国大陆" class="regular-text wpcc-input" style="margin-left: 20px;" />
                                        </div>
                                        <div style="margin-bottom: 10px;">
                                            <label class="wpcc-switch">
                                                <input type="checkbox" name="wpcc_default_variant_zh-tw" value="1" <?php checked(
                                                    in_array(
                                                        "zh-tw",
                                                        get_site_option(
                                                            "wpcc_default_used_langs",
                                                            ["zh-cn", "zh-tw"],
                                                        ),
                                                    ),
                                                ); ?>>
                                                <span class="wpcc-slider"></span>
                                                <span class="wpcc-switch-label">台灣正體 (zh-tw)</span>
                                            </label>
                                            <input type="text" name="wpcc_default_twtip" value="<?php echo esc_attr(
                                                get_site_option(
                                                    "wpcc_default_twtip",
                                                    "繁体",
                                                ),
                                            ); ?>" placeholder="台灣正體" class="regular-text wpcc-input" style="margin-left: 20px;" />
                                        </div>
                                        <div style="margin-bottom: 10px;">
                                            <label class="wpcc-switch">
                                                <input type="checkbox" name="wpcc_default_variant_zh-hk" value="1" <?php checked(
                                                    in_array(
                                                        "zh-hk",
                                                        get_site_option(
                                                            "wpcc_default_used_langs",
                                                            ["zh-cn", "zh-tw"],
                                                        ),
                                                    ),
                                                ); ?>>
                                                <span class="wpcc-slider"></span>
                                                <span class="wpcc-switch-label">港澳繁體 (zh-hk)</span>
                                            </label>
                                            <input type="text" name="wpcc_default_hktip" value="<?php echo esc_attr(
                                                get_site_option(
                                                    "wpcc_default_hktip",
                                                    "港澳",
                                                ),
                                            ); ?>" placeholder="港澳繁體" class="regular-text wpcc-input" style="margin-left: 20px;" />
                                        </div>
                                    </div>

<div class="wpcc-extended-languages" style="margin-top: 15px; <?php echo get_site_option(
    "wpcc_default_enable_extended_langs",
    1,
)
    ? ""
    : "display: none;"; ?>">
                                        <h4>扩展语言模块（国际）</h4>
                                        <div style="margin-bottom: 10px;">
                                            <label class="wpcc-switch">
                                                <input type="checkbox" name="wpcc_default_variant_zh-hans" value="1" <?php checked(
                                                    in_array(
                                                        "zh-hans",
                                                        get_site_option(
                                                            "wpcc_default_used_langs",
                                                            ["zh-cn", "zh-tw"],
                                                        ),
                                                    ),
                                                ); ?>>
                                                <span class="wpcc-slider"></span>
                                                <span class="wpcc-switch-label">简体中文 (zh-hans)</span>
                                            </label>
                                            <input type="text" name="wpcc_default_hanstip" value="<?php echo esc_attr(
                                                get_site_option(
                                                    "wpcc_default_hanstip",
                                                    "简体",
                                                ),
                                            ); ?>" placeholder="简体中文" class="regular-text wpcc-input" style="margin-left: 20px;" />
                                        </div>
                                        <div style="margin-bottom: 10px;">
                                            <label class="wpcc-switch">
                                                <input type="checkbox" name="wpcc_default_variant_zh-hant" value="1" <?php checked(
                                                    in_array(
                                                        "zh-hant",
                                                        get_site_option(
                                                            "wpcc_default_used_langs",
                                                            ["zh-cn", "zh-tw"],
                                                        ),
                                                    ),
                                                ); ?>>
                                                <span class="wpcc-slider"></span>
                                                <span class="wpcc-switch-label">繁体中文 (zh-hant)</span>
                                            </label>
                                            <input type="text" name="wpcc_default_hanttip" value="<?php echo esc_attr(
                                                get_site_option(
                                                    "wpcc_default_hanttip",
                                                    "繁体",
                                                ),
                                            ); ?>" placeholder="繁体中文" class="regular-text wpcc-input" style="margin-left: 20px;" />
                                        </div>
                                        <div style="margin-bottom: 10px;">
                                            <label class="wpcc-switch">
                                                <input type="checkbox" name="wpcc_default_variant_zh-sg" value="1" <?php checked(
                                                    in_array(
                                                        "zh-sg",
                                                        get_site_option(
                                                            "wpcc_default_used_langs",
                                                            ["zh-cn", "zh-tw"],
                                                        ),
                                                    ),
                                                ); ?>>
                                                <span class="wpcc-slider"></span>
                                                <span class="wpcc-switch-label">马新简体 (zh-sg)</span>
                                            </label>
                                            <input type="text" name="wpcc_default_sgtip" value="<?php echo esc_attr(
                                                get_site_option(
                                                    "wpcc_default_sgtip",
                                                    "马新",
                                                ),
                                            ); ?>" placeholder="马新简体" class="regular-text wpcc-input" style="margin-left: 20px;" />
                                        </div>
                                        <div style="margin-bottom: 10px;">
                                            <label class="wpcc-switch">
                                                <input type="checkbox" name="wpcc_default_variant_zh-jp" value="1" <?php checked(
                                                    in_array(
                                                        "zh-jp",
                                                        get_site_option(
                                                            "wpcc_default_used_langs",
                                                            ["zh-cn", "zh-tw"],
                                                        ),
                                                    ),
                                                ); ?>>
                                                <span class="wpcc-slider"></span>
                                                <span class="wpcc-switch-label">日式汉字 (zh-jp) (仅 OpenCC 引擎)</span>
                                            </label>
                                            <input type="text" name="wpcc_default_jptip" value="<?php echo esc_attr(
                                                get_site_option(
                                                    "wpcc_default_jptip",
                                                    "日式",
                                                ),
                                            ); ?>" placeholder="日式汉字" class="regular-text wpcc-input" style="margin-left: 20px;" />
                                        </div>
                                    </div>
                                    <p class="description">请至少勾选一种中文语言，否则插件无法正常运行。支持自定义名称，留空为默认值。</p>
                                </td>
                            </tr>
                            <tr>
                                <th>"不转换"标签 <a href="https://wpcc.net/document/no-conversion-tag" target="_blank" class="wpcc-doc-link" title="查看详细说明">↗</a></th>
                                <td>
                                    <input type="text" name="wpcc_default_nctip" value="<?php echo esc_attr(
                                        get_site_option(
                                            "wpcc_default_nctip",
                                            "简体",
                                        ),
                                    ); ?>" placeholder="请输入显示名（默认值如左）" class="regular-text" />
                                    <p class="description">自定义小工具中"不转换"链接的显示名称。</p>
                                </td>
                            </tr>
                            <tr>
                                <th>展示形式 <a href="https://wpcc.net/document/display-format" target="_blank" class="wpcc-doc-link" title="查看详细说明">↗</a></th>
                                <td>
                                    <label><input type="radio" name="wpcc_default_flag_option" value="1" <?php checked(
                                        get_site_option(
                                            "wpcc_default_flag_option",
                                            1,
                                        ),
                                        1,
                                    ); ?>> 平铺</label><br>
                                    <label><input type="radio" name="wpcc_default_flag_option" value="0" <?php checked(
                                        get_site_option(
                                            "wpcc_default_flag_option",
                                            1,
                                        ),
                                        0,
                                    ); ?>> 下拉列表</label>
                                    <p class="description">选择语言切换按钮的展现方式。</p>
                                </td>
                            </tr>
                            <tr>
                                <th>显示更多语言 <a href="https://wpcc.net/document/extended-languages" target="_blank" class="wpcc-doc-link" title="查看详细说明">↗</a></th>
                                <td>
                                    <label class="wpcc-switch">
<input type="checkbox" id="wpcc_default_enable_extended_langs" name="wpcc_default_enable_extended_langs" value="1" <?php checked(
    get_site_option("wpcc_default_enable_extended_langs", 1),
); ?>>
                                        <span class="wpcc-slider"></span>
                                        <span class="wpcc-switch-label">启用扩展语言模块</span>
                                    </label>
                                    <p class="description">启用后将显示更多中文语言变体选项，包括 zh-hans、zh-hant、zh-sg、zh-jp 等。</p>
                                </td>
                            </tr>
                        </table>

                        <h3>转换引擎和核心功能</h3>
                        <table class="form-table">
                            <tr>
                                <th>转换引擎 <a href="https://wpcc.net/document/conversion-engine" target="_blank" class="wpcc-doc-link" title="查看详细说明">↗</a></th>
                                <td>
<select name="wpcc_default_engine" id="wpcc_default_engine" class="regular-text">
                                        <option value="opencc" <?php selected(
                                            get_site_option(
                                                "wpcc_default_engine",
                                                "opencc",
                                            ),
                                            "opencc",
                                        ); ?>>OpenCC - 智能词汇级别转换</option>
                                        <option value="mediawiki" <?php selected(
                                            get_site_option(
                                                "wpcc_default_engine",
                                                "opencc",
                                            ),
                                            "mediawiki",
                                        ); ?>>MediaWiki - 快速字符映射转换</option>
                                    </select>
                                    <p class="description">词汇级转换, 地区习惯用词, 异体字转换</p>
                                </td>
                            </tr>
                            <tr>
                                <th>搜索转换 <a href="https://wpcc.net/document/search-conversion" target="_blank" class="wpcc-doc-link" title="查看详细说明">↗</a></th>
                                <td>
                                    <select name="wpcc_default_search_conversion" class="regular-text">
                                        <option value="2" <?php selected(
                                            get_site_option(
                                                "wpcc_default_search_conversion",
                                                1,
                                            ),
                                            2,
                                        ); ?>>开启</option>
                                        <option value="0" <?php selected(
                                            get_site_option(
                                                "wpcc_default_search_conversion",
                                                1,
                                            ),
                                            0,
                                        ); ?>>关闭</option>
                                        <option value="1" <?php selected(
                                            get_site_option(
                                                "wpcc_default_search_conversion",
                                                1,
                                            ),
                                            1,
                                        ); ?>>仅语言非"不转换"时开启（默认）</option>
                                    </select>
                                    <p class="description">此功能将增加搜索时数据库负担，低配服务器建议关闭。</p>
                                </td>
                            </tr>
                            <tr>
                                <th>全页面转换 <a href="https://wpcc.net/document/fullpage-conversion" target="_blank" class="wpcc-doc-link" title="查看详细说明">↗</a></th>
                                <td>
                                    <label class="wpcc-switch">
                                        <input type="checkbox" name="wpcc_default_use_fullpage_conversion" value="1" <?php checked(
                                            get_site_option(
                                                "wpcc_default_use_fullpage_conversion",
                                                1,
                                            ),
                                        ); ?>>
                                        <span class="wpcc-slider"></span>
                                        <span class="wpcc-switch-label">启用全页面转换</span>
                                    </label>
                                    <p class="description">如果遇到异常（包括中文转换错误，HTML页面错误或PHP错误等），请关闭此选项。</p>
                                </td>
                            </tr>
                        </table>

                        <h3>编辑器增强</h3>
                        <table class="form-table">
                            <tr>
                                <th>快速标签 <a href="https://wpcc.net/document/quick-tags" target="_blank" class="wpcc-doc-link" title="查看详细说明">↗</a></th>
                                <td>
                                    <label class="wpcc-switch">
                                        <input type="checkbox" name="wpcc_default_no_conversion_qtag" value="1" <?php checked(
                                            get_site_option(
                                                "wpcc_default_no_conversion_qtag",
                                                1,
                                            ),
                                        ); ?>>
                                        <span class="wpcc-slider"></span>
                                        <span class="wpcc-switch-label">启用编辑器快速标签</span>
                                    </label>
                                    <p class="description">在经典编辑器工具栏中添加"wpcc_NC"按钮。区块编辑器可直接用 [wpcc_nc] 简码，示例：[wpcc_nc]文派墨图，文风笔笙[/wpcc_nc]</p>
                                </td>
                            </tr>
                            <tr>
                                <th>发表时转换 <a href="https://wpcc.net/document/post-conversion" target="_blank" class="wpcc-doc-link" title="查看详细说明">↗</a></th>
                                <td>
                                    <label class="wpcc-switch">
                                        <input type="checkbox" name="wpcc_default_enable_post_conversion" value="1" <?php checked(
                                            get_site_option(
                                                "wpcc_default_enable_post_conversion",
                                                0,
                                            ),
                                        ); ?>>
                                        <span class="wpcc-slider"></span>
                                        <span class="wpcc-switch-label">启用发表时自动转换</span>
                                    </label>
                                    <div id="wpcc-network-post-conv-options" style="margin-top:10px; <?php echo get_site_option(
                                        "wpcc_default_enable_post_conversion",
                                        0,
                                    )
                                        ? ""
                                        : "display:none;"; ?>">
                                        <label for="wpcc_default_post_conversion_target">转换目标语言：</label>
                                        <select name="wpcc_default_post_conversion_target" id="wpcc_default_post_conversion_target" class="regular-text" style="margin-left:10px;">
                                            <?php
                                            $default_used_langs = get_site_option(
                                                "wpcc_default_used_langs",
                                                ["zh-cn", "zh-tw"],
                                            );
                                            $names = [
                                                "zh-cn" => get_site_option(
                                                    "wpcc_default_cntip",
                                                    "简体",
                                                ),
                                                "zh-tw" => get_site_option(
                                                    "wpcc_default_twtip",
                                                    "繁体",
                                                ),
                                                "zh-hk" => get_site_option(
                                                    "wpcc_default_hktip",
                                                    "港澳",
                                                ),
                                                "zh-hans" => get_site_option(
                                                    "wpcc_default_hanstip",
                                                    "简体",
                                                ),
                                                "zh-hant" => get_site_option(
                                                    "wpcc_default_hanttip",
                                                    "繁体",
                                                ),
                                                "zh-sg" => get_site_option(
                                                    "wpcc_default_sgtip",
                                                    "马新",
                                                ),
                                                "zh-jp" => get_site_option(
                                                    "wpcc_default_jptip",
                                                    "日式",
                                                ),
                                            ];
                                            $current_target = get_site_option(
                                                "wpcc_default_post_conversion_target",
                                                "zh-cn",
                                            );
                                            foreach (
                                                $default_used_langs
                                                as $code
                                            ) {
                                                if (isset($names[$code])) {
                                                    echo '<option value="' .
                                                        esc_attr($code) .
                                                        '"' .
                                                        selected(
                                                            $current_target,
                                                            $code,
                                                            false,
                                                        ) .
                                                        ">" .
                                                        esc_html(
                                                            $names[$code],
                                                        ) .
                                                        " (" .
                                                        esc_html($code) .
                                                        ")</option>";
                                                }
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <p class="description">启用后，在发布或更新文章时自动转换内容。</p>
                                </td>
                            </tr>
                        </table>

                        <h3>URL 和站点地图设置</h3>
                        <table class="form-table">
                            <tr>
                                <th>链接格式 <a href="https://wpcc.net/document/permalink-format" target="_blank" class="wpcc-doc-link" title="查看详细说明">↗</a></th>
                                <td>
                                    <?php $wpcc_network_base = esc_html( rtrim( network_home_url(), '/' ) ); ?>
                                    <label><input type="radio" name="wpcc_default_use_permalink" value="0" <?php checked(
                                        get_site_option(
                                            "wpcc_default_use_permalink",
                                            0,
                                        ),
                                        0,
                                    ); ?>> <?php echo $wpcc_network_base; ?>/blog/%year%/%monthnum%/%day%/%postname%/?variant=zh-tw (默认)</label><br>
                                    <label><input type="radio" name="wpcc_default_use_permalink" value="1" <?php checked(
                                        get_site_option(
                                            "wpcc_default_use_permalink",
                                            0,
                                        ),
                                        1,
                                    ); ?>> <?php echo $wpcc_network_base; ?>/blog/%year%/%monthnum%/%day%/%postname%/zh-tw/</label><br>
                                    <label><input type="radio" name="wpcc_default_use_permalink" value="2" <?php checked(
                                        get_site_option(
                                            "wpcc_default_use_permalink",
                                            0,
                                        ),
                                        2,
                                    ); ?>> <?php echo $wpcc_network_base; ?>/zh-tw/blog/%year%/%monthnum%/%day%/%postname%/</label>
                                    <p class="description">注意：此选项影响插件生成的转换页面链接。提示：若未开启固定链接，则只能选第一种默认URL形式。</p>
                                </td>
                            </tr>
                            <tr>
                                <th>地图内容类型 <a href="https://wpcc.net/document/sitemap-post-types" target="_blank" class="wpcc-doc-link" title="查看详细说明">↗</a></th>
                                <td>
                                    <input type="text" name="wpcc_default_sitemap_post_type" value="<?php echo esc_attr(
                                        get_site_option(
                                            "wpcc_default_sitemap_post_type",
                                            "post,page",
                                        ),
                                    ); ?>" placeholder="默认为:post,page" class="regular-text" />
                                    <p class="description">默认为 post 和 page 生成地图，如需添加自定义 post_type 请用逗号分隔。</p>
                                </td>
                            </tr>
                            <tr>
                                <th>多语言地图 <a href="https://wpcc.net/document/multilingual-sitemap" target="_blank" class="wpcc-doc-link" title="查看详细说明">↗</a></th>
                                <td>
                                    <label class="wpcc-switch">
                                        <input type="checkbox" name="wpcc_default_use_sitemap" value="1" <?php checked(
                                            get_site_option(
                                                "wpcc_default_use_sitemap",
                                                1,
                                            ),
                                        ); ?>>
                                        <span class="wpcc-slider"></span>
                                        <span class="wpcc-switch-label">启用多语言网站地图</span>
                                    </label>
                                    <?php $wpcc_network_base2 = esc_html( rtrim( network_home_url(), '/' ) ); ?>
<?php 
                                        $default_langs = get_site_option('wpcc_default_used_langs', array('zh-cn','zh-tw'));
                                        if (!is_array($default_langs)) { $default_langs = array('zh-cn','zh-tw'); }
                                        $sample_lang = !empty($default_langs) ? $default_langs[0] : 'zh-cn';
                                    ?>
                                    <p class="description">网站地图访问地址：<?php echo $wpcc_network_base2; ?>/<?php echo esc_html($sample_lang); ?>/sitemap.xml/</p>
                                </td>
                            </tr>
                        </table>

                        <h3>智能检测功能</h3>
                        <table class="form-table">
                            <tr>
                                <th>浏览器语言 <a href="https://wpcc.net/document/browser-language" target="_blank" class="wpcc-doc-link" title="查看详细说明">↗</a></th>
                                <td>
                                    <select name="wpcc_default_browser_redirect" class="regular-text">
                                        <option value="2" <?php selected(
                                            get_site_option(
                                                "wpcc_default_browser_redirect",
                                                0,
                                            ),
                                            1,
                                        ); ?>>显示为对应繁简版本</option>
                                        <option value="1" <?php selected(
                                            get_site_option(
                                                "wpcc_default_browser_redirect",
                                                0,
                                            ),
                                            2,
                                        ); ?>>跳转至对应繁简页面</option>
                                        <option value="0" <?php selected(
                                            get_site_option(
                                                "wpcc_default_browser_redirect",
                                                0,
                                            ),
                                            0,
                                        ); ?>>关闭（默认值）</option>
                                    </select>
                                    <p class="description">注意：此项设置不会应用于搜索引擎。</p>
                                </td>
                            </tr>
                            <tr>
                                <th>首次访问</th>
                                <td>
                                    <label class="wpcc-switch">
                                        <input type="checkbox" name="wpcc_default_first_visit_default" value="1" <?php checked(
                                            get_site_option('wpcc_default_first_visit_default', 0),
                                            1
                                        ); ?> />
                                        <span class="wpcc-slider"></span>
                                        <span class="wpcc-switch-label">首次访问不转换（保持站点默认语言）</span>
                                    </label>
                                    <p class="description">开启后，首次访问根路径时不根据浏览器语言自动转换，避免首页内容与默认语言不一致；用户选择语言后仍按其它策略生效。</p>
                                </td>
                            </tr>
                            <tr>
                                <th>Cookie偏好 <a href="https://wpcc.net/document/cookie-preference" target="_blank" class="wpcc-doc-link" title="查看详细说明">↗</a></th>
                                <td>
                                    <select name="wpcc_default_use_cookie_variant" class="regular-text">
                                        <option value="2" <?php selected(
                                            get_site_option(
                                                "wpcc_default_use_cookie_variant",
                                                0,
                                            ),
                                            1,
                                        ); ?>>显示为对应繁简版本</option>
                                        <option value="1" <?php selected(
                                            get_site_option(
                                                "wpcc_default_use_cookie_variant",
                                                0,
                                            ),
                                            2,
                                        ); ?>>跳转至对应繁简页面</option>
                                        <option value="0" <?php selected(
                                            get_site_option(
                                                "wpcc_default_use_cookie_variant",
                                                0,
                                            ),
                                            0,
                                        ); ?>>关闭（默认值）</option>
                                    </select>
                                    <p class="description">记住用户的语言选择偏好，下次访问时自动应用。</p>
                                </td>
                            </tr>
                            <tr>
                                <th>语系内通用</th>
                                <td>
                                    <label class="wpcc-switch">
                                        <input type="checkbox" name="wpcc_default_auto_language_recong" value="1" <?php checked(
                                            get_site_option(
                                                "wpcc_default_auto_language_recong",
                                                0,
                                            ),
                                        ); ?>>
                                        <span class="wpcc-slider"></span>
                                        <span class="wpcc-switch-label">允许不同语系内通用</span>
                                    </label>
                                </td>
                            </tr>
                        </table>

                        <h3>内容过滤设置</h3>
                        <table class="form-table">
                            <tr>
                                <th>标签排除 <a href="https://wpcc.net/document/tag-exclusion" target="_blank" class="wpcc-doc-link" title="查看详细说明">↗</a></th>
                                <td>
                                    <textarea name="wpcc_default_no_conversion_tag" rows="3" class="large-text" placeholder="推荐默认值: pre,code,pre.wp-block-code,pre.wp-block-preformatted"><?php echo esc_textarea(
                                        get_site_option(
                                            "wpcc_default_no_conversion_tag",
                                            "",
                                        ),
                                    ); ?></textarea>
                                    <p class="description">这里输入的HTML标签或选择器内内容将不进行中文繁简转换，推荐设置 <strong>pre,code,pre.wp-block-code,pre.wp-block-preformatted</strong> 来排除区块编辑器的代码块与预格式化内容。</p>
                                </td>
                            </tr>
                            <tr>
                                <th>日语内容排除 <a href="https://wpcc.net/document/japanese-exclusion" target="_blank" class="wpcc-doc-link" title="查看详细说明">↗</a></th>
                                <td>
                                    <label class="wpcc-switch">
                                        <input type="checkbox" name="wpcc_default_no_conversion_ja" value="1" <?php checked(
                                            get_site_option(
                                                "wpcc_default_no_conversion_ja",
                                                0,
                                            ),
                                        ); ?>>
                                        <span class="wpcc-slider"></span>
                                        <span class="wpcc-switch-label">排除日语内容转换</span>
                                    </label>
                                    <p class="description">启用后，标记为日语（lang="ja"）的内容将不进行中文繁简转换，避免日语汉字被错误转换。</p>
                                </td>
                            </tr>
                        </table>

                        <h3>SEO 优化功能</h3>
                        <table class="form-table">
                            <tr>
                                <th>hreflang 标签 <a href="https://wpcc.net/document/hreflang-tags" target="_blank" class="wpcc-doc-link" title="查看详细说明">↗</a></th>
                                <td>
                                    <label class="wpcc-switch">
                                        <input type="checkbox" name="wpcc_default_enable_hreflang_tags" value="1" <?php checked(
                                            get_site_option(
                                                "wpcc_default_enable_hreflang_tags",
                                                1,
                                            ),
                                        ); ?>>
                                        <span class="wpcc-slider"></span>
                                        <span class="wpcc-switch-label">启用 hreflang 标签生成</span>
                                    </label>
                                    <p class="description">自动为每个语言版本生成 hreflang 标签，提升多语言 SEO 效果。</p>
                                </td>
                            </tr>
                            <tr>
                                <th>x-default 标签</th>
                                <td>
                                    <label class="wpcc-switch">
                                        <input type="checkbox" name="wpcc_default_enable_hreflang_x_default" value="1" <?php checked(get_site_option('wpcc_default_enable_hreflang_x_default', 1)); ?>>
                                        <span class="wpcc-slider"></span>
                                        <span class="wpcc-switch-label">启用 x-default hreflang 标签</span>
                                    </label>
                                </td>
                            </tr>
                            <tr id="wpcc-network-x-default-options" style="<?php echo get_site_option('wpcc_default_enable_hreflang_x_default', 1) ? '' : 'display:none;'; ?>">
                                <th>默认语言 <a href="https://wpcc.net/document/default-language" target ="_blank" class="wpcc-doc-link" title="查看详细说明">↗</a></th>
                                <td>
                                    <select name="wpcc_default_hreflang_x_default" class="regular-text">
                                        <option value="zh-cn" <?php selected(
                                            get_site_option(
                                                "wpcc_default_hreflang_x_default",
                                                "zh-cn",
                                            ),
                                            "zh-cn",
                                        ); ?>>简体中文 (zh-cn)</option>
                                        <option value="zh-tw" <?php selected(
                                            get_site_option(
                                                "wpcc_default_hreflang_x_default",
                                                "zh-cn",
                                            ),
                                            "zh-tw",
                                        ); ?>>台湾正体 (zh-tw)</option>
                                        <option value="zh-hk" <?php selected(
                                            get_site_option(
                                                "wpcc_default_hreflang_x_default",
                                                "zh-cn",
                                            ),
                                            "zh-hk",
                                        ); ?>>香港繁体 (zh-hk)</option>
                                        <option value="zh-hans" <?php selected(
                                            get_site_option(
                                                "wpcc_default_hreflang_x_default",
                                                "zh-cn",
                                            ),
                                            "zh-hans",
                                        ); ?>>简体中文 (zh-hans)</option>
                                        <option value="zh-hant" <?php selected(
                                            get_site_option(
                                                "wpcc_default_hreflang_x_default",
                                                "zh-cn",
                                            ),
                                            "zh-hant",
                                        ); ?>>繁体中文 (zh-hant)</option>
                                        <option value="zh-sg" <?php selected(
                                            get_site_option(
                                                "wpcc_default_hreflang_x_default",
                                                "zh-cn",
                                            ),
                                            "zh-sg",
                                        ); ?>>马新简体 (zh-sg)</option>
                                        <option value="zh-jp" <?php selected(
                                            get_site_option(
                                                "wpcc_default_hreflang_x_default",
                                                "zh-cn",
                                            ),
                                            "zh-jp",
                                        ); ?>>日式汉字 (zh-jp)</option>
                                    </select>
                                    <p class="description">设置 x-default hreflang 标签指向的默认语言版本。</p>
                                </td>
                            </tr>
                            <tr>
                                <th>Schema 数据 <a href="https://wpcc.net/document/schema-conversion" target="_blank" class="wpcc-doc-link" title="查看详细说明">↗</a></th>
                                <td>
                                    <label class="wpcc-switch">
                                        <input type="checkbox" name="wpcc_default_enable_schema_conversion" value="1" <?php checked(
                                            get_site_option(
                                                "wpcc_default_enable_schema_conversion",
                                                1,
                                            ),
                                        ); ?>>
                                        <span class="wpcc-slider"></span>
                                        <span class="wpcc-switch-label">启用 Schema 结构化数据转换</span>
                                    </label>
                                    <p class="description">自动转换 JSON-LD 结构化数据中的文本内容，支持 Article、Product、Organization 等类型。</p>
                                </td>
                            </tr>
                            <tr>
                                <th>元数据转换 <a href="https://wpcc.net/document/meta-conversion" target="_blank" class="wpcc-doc-link" title="查看详细说明">↗</a></th>
                                <td>
                                    <label class="wpcc-switch">
                                        <input type="checkbox" name="wpcc_default_enable_meta_conversion" value="1" <?php checked(
                                            get_site_option(
                                                "wpcc_default_enable_meta_conversion",
                                                1,
                                            ),
                                        ); ?>>
                                        <span class="wpcc-slider"></span>
                                        <span class="wpcc-switch-label">启用 SEO 元数据转换</span>
                                    </label>
                                    <p class="description">转换页面标题、描述、Open Graph 和 Twitter Card 等元数据。</p>
                                </td>
                            </tr>
                        </table>

                        <h3>缓存和兼容性</h3>
                        <table class="form-table">
                            <tr>
                                <th>缓存兼容 <a href="https://wpcc.net/document/cache-compatibility" target="_blank" class="wpcc-doc-link" title="查看详细说明">↗</a></th>
                                <td>
                                    <label class="wpcc-switch">
                                        <input type="checkbox" name="wpcc_default_enable_cache_addon" value="1" <?php checked(
                                            get_site_option(
                                                "wpcc_default_enable_cache_addon",
                                                1,
                                            ),
                                        ); ?>>
                                        <span class="wpcc-slider"></span>
                                        <span class="wpcc-switch-label">启用缓存插件兼容</span>
                                    </label>
                                    <p class="description">支持 WP Super Cache、WP Rocket、LiteSpeed Cache、W3 Total Cache 等流行的缓存插件。</p>
                                </td>
                            </tr>

                        </table>



                        <div class="wpcc-submit-wrapper">
                            <button type="submit" class="button button-primary">保存设置</button>
                            <button type="button" class="button button-secondary" onclick="location.reload()">重置选项</button>
                        </div>
                    </form>
                </div>



                <!-- 导入导出选项卡 -->
                <div class="wpcc-section" id="wpcc-section-import_export" style="<?php echo $active_tab !==
                "import_export"
                    ? "display: none;"
                    : ""; ?>">
                    <h2><?php _e("导入导出", "wp-chinese-converter"); ?></h2>
                    <p class="wpcc-section-desc"><?php _e(
                        "导入或导出网络设置，便于备份和迁移配置。",
                        "wp-chinese-converter",
                    ); ?></p>

                    <div class="wpcc-import-export-wrapper">
                        <div class="wpcc-export-section">
                            <h3><?php _e(
                                "导出设置",
                                "wp-chinese-converter",
                            ); ?></h3>
                            <p><?php _e(
                                "导出当前网络设置为JSON文件，包含所有网络级别的配置。",
                                "wp-chinese-converter",
                            ); ?></p>
                            <form method="post" action="edit.php?action=wpcc_export_settings">
                                <?php wp_nonce_field("wpcc_export_settings"); ?>
                                <div class="wpcc-export-options">
                                    <label><input type="checkbox" name="export_network_settings" value="1" checked> 网络管理设置</label><br>
                                    <label><input type="checkbox" name="export_basic_settings" value="1" checked> 基础设置</label><br>
                                    <label><input type="checkbox" name="export_controlled_options" value="1" checked> 网络控制选项</label>
                                </div>
                                <p>
                                    <button type="submit" class="button button-primary">导出设置</button>
                                </p>
                            </form>
                        </div>

                        <div class="wpcc-import-section">
                            <h3><?php _e(
                                "导入设置",
                                "wp-chinese-converter",
                            ); ?></h3>
                            <p><?php _e(
                                "从JSON文件导入网络设置。",
                                "wp-chinese-converter",
                            ); ?><strong><?php _e(
    "注意：这将覆盖当前设置。",
    "wp-chinese-converter",
); ?></strong></p>
                            <form method="post" action="edit.php?action=wpcc_import_settings" enctype="multipart/form-data">
                                <?php wp_nonce_field("wpcc_import_settings"); ?>
                                <table class="form-table">
                                    <tr>
                                        <th>选择文件</th>
                                        <td>
                                            <input type="file" name="import_file" accept=".json" required>
                                            <p class="description">请选择之前导出的JSON设置文件</p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>导入选项</th>
                                        <td>
                                            <label><input type="checkbox" name="import_network_settings" value="1" checked> 网络管理设置</label><br>
                                            <label><input type="checkbox" name="import_basic_settings" value="1" checked> 基础设置</label><br>
                                            <label><input type="checkbox" name="import_controlled_options" value="1" checked> 网络控制选项</label><br>
                                            <label><input type="checkbox" name="apply_to_existing_sites" value="1"> 应用到现有站点</label>
                                            <p class="description">勾选后将把导入的设置应用到所有现有站点</p>
                                        </td>
                                    </tr>
                                </table>
                                <p>
                                    <button type="submit" class="button button-primary" onclick="return confirm('确定要导入设置吗？这将覆盖当前配置。')">导入设置</button>
                                </p>
                            </form>
                        </div>


                    </div>
                </div>

                <!-- 工具维护选项卡 -->
                <div class="wpcc-section" id="wpcc-section-tools" style="<?php echo $active_tab !==
                "tools"
                    ? "display: none;"
                    : ""; ?>">
                    <h2><?php _e("工具维护", "wp-chinese-converter"); ?></h2>
                    <p class="wpcc-section-desc"><?php _e(
                        "网络级别的插件管理和维护工具。",
                        "wp-chinese-converter",
                    ); ?></p>

                    <form method="post" action="edit.php?action=wpcc_network_settings" id="wpcc-tools-form">
                        <?php wp_nonce_field("wpcc_network_settings"); ?>
                        <input type="hidden" name="tab" value="tools">

                        <h3><?php _e(
                            "网络控制选项",
                            "wp-chinese-converter",
                        ); ?></h3>
                        <table class="form-table">
                            <tr>
                                <th>启用工具维护功能</th>
                                <td>
                                    <label class="wpcc-switch">
                                        <input type="checkbox" name="wpcc_enable_network_tools" value="1" <?php checked(
                                            get_site_option(
                                                "wpcc_enable_network_tools",
                                                1,
                                            ),
                                        ); ?>>
                                        <span class="wpcc-slider"></span>
                                        <span class="wpcc-switch-label">启用网络工具维护功能</span>
                                    </label>
                                    <p class="description">启用后，网络管理员可以使用批量管理、统计监控等高级功能</p>
                                </td>
                            </tr>

                            <tr>
                                <th>启用批量操作</th>
                                <td>
                                    <label class="wpcc-switch">
                                        <input type="checkbox" name="wpcc_enable_bulk_operations" value="1" <?php checked(
                                            get_site_option(
                                                "wpcc_enable_bulk_operations",
                                                0,
                                            ),
                                        ); ?>>
                                        <span class="wpcc-slider"></span>
                                        <span class="wpcc-switch-label">启用批量重置等危险操作</span>
                                    </label>
                                    <p class="description">启用后，可以执行批量重置站点设置等操作。建议谨慎启用</p>
                                </td>
                            </tr>
                        </table>

                        <div class="wpcc-submit-wrapper">
                            <button type="submit" class="button button-primary">保存工具设置</button>
                        </div>
                    </form>

                    <?php if (
                        get_site_option("wpcc_enable_network_tools", 1)
                    ): ?>
                    <h3><?php _e(
                        "网络统计概览",
                        "wp-chinese-converter",
                    ); ?></h3>
                    <table class="form-table">
                        <tr>
                            <th>
                                <?php _e("站点统计", "wp-chinese-converter"); ?>
                                <a href="https://wpcc.net/document/network-statistics" target="_blank" class="wpcc-doc-link" title="<?php _e(
                                    "查看详细说明",
                                    "wp-chinese-converter",
                                ); ?>">↗</a>
                            </th>
                            <td>
                                <?php
                                $sites = get_sites();
                                $total_sites = count($sites);
                                $active_sites = 0;
                                $engine_stats = [
                                    "mediawiki" => 0,
                                    "opencc" => 0,
                                    "other" => 0,
                                ];
                                $language_stats = [];

                                foreach ($sites as $site) {
                                    switch_to_blog($site->blog_id);
                                    $options = get_option("wpcc_options", []);

                                    if (!empty($options)) {
                                        $active_sites++;

                                        // 统计引擎使用情况
                                        $engine =
                                            $options["wpcc_engine"] ??
                                            "mediawiki";
                                        if (isset($engine_stats[$engine])) {
                                            $engine_stats[$engine]++;
                                        } else {
                                            $engine_stats["other"]++;
                                        }

                                        // 统计语言使用情况
                                        $used_langs =
                                            $options["wpcc_used_langs"] ?? [];
                                        foreach ($used_langs as $lang) {
                                            if (
                                                !isset($language_stats[$lang])
                                            ) {
                                                $language_stats[$lang] = 0;
                                            }
                                            $language_stats[$lang]++;
                                        }
                                    }

                                    restore_current_blog();
                                }
                                ?>

                                <div class="wpcc-stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-bottom: 20px;">
                                    <div class="wpcc-stat-card" style="padding: 15px; background: #f0f6fc; border-left: 4px solid #0073aa; border-radius: 4px;">
                                        <div style="font-size: 24px; font-weight: 600; color: #0073aa;"><?php echo esc_html(
                                            $total_sites,
                                        ); ?></div>
                                        <div style="color: #666; font-size: 14px;">总站点数</div>
                                    </div>
                                    <div class="wpcc-stat-card" style="padding: 15px; background: #f0f9f0; border-left: 4px solid #00a32a; border-radius: 4px;">
                                        <div style="font-size: 24px; font-weight: 600; color: #00a32a;"><?php echo esc_html(
                                            $active_sites,
                                        ); ?></div>
                                        <div style="color: #666; font-size: 14px;">已激活站点</div>
                                    </div>
                                    <div class="wpcc-stat-card" style="padding: 15px; background: #fdf2f2; border-left: 4px solid #d63638; border-radius: 4px;">
                                        <div style="font-size: 24px; font-weight: 600; color: #d63638;"><?php echo esc_html(
                                            $total_sites - $active_sites,
                                        ); ?></div>
                                        <div style="color: #666; font-size: 14px;">未激活站点</div>
                                    </div>
                                </div>

                                <?php if ($active_sites > 0): ?>
                                <div style="margin-top: 20px;">
                                    <h4 style="margin-bottom: 10px;">转换引擎分布</h4>
                                    <div style="display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 15px;">
                                        <?php foreach (
                                            $engine_stats
                                            as $engine => $count
                                        ): ?>
                                            <?php if ($count > 0): ?>
                                                <span style="padding: 4px 12px; background: #e7f3ff; border: 1px solid #0073aa; border-radius: 20px; font-size: 12px; color: #0073aa;">
                                                    <?php echo esc_html(
                                                        ucfirst($engine),
                                                    ); ?>: <?php echo esc_html(
    $count,
); ?>
                                                </span>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>

                                    <?php if (!empty($language_stats)): ?>
                                    <h4 style="margin-bottom: 10px;">语言模块使用情况</h4>
                                    <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                                        <?php
                                        $lang_names = [
                                            "zh-cn" => "简体中文",
                                            "zh-tw" => "台湾正体",
                                            "zh-hk" => "港澳繁体",
                                            "zh-hans" => "简体中文(国际)",
                                            "zh-hant" => "繁体中文(国际)",
                                            "zh-sg" => "马新简体",
                                            "zh-jp" => "日式汉字",
                                        ];
                                        foreach (
                                            $language_stats
                                            as $lang => $count
                                        ):
                                            $lang_name = isset(
                                                $lang_names[$lang],
                                            )
                                                ? $lang_names[$lang]
                                                : $lang; ?>
                                            <span style="padding: 4px 12px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 20px; font-size: 12px; color: #856404;">
                                                <?php echo esc_html(
                                                    $lang_name,
                                                ); ?>: <?php echo esc_html(
    $count,
); ?>
                                            </span>
                                        <?php
                                        endforeach;
                                        ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                    <?php endif; ?>

                    <?php if (get_site_option("wpcc_allow_site_details", 1)): ?>
                    <h3><?php _e("站点管理", "wp-chinese-converter"); ?></h3>
                    <table class="form-table">
                        <tr>
                            <th>
                                <?php _e("站点列表", "wp-chinese-converter"); ?>
                                <a href="https://wpcc.net/document/network-site-management" target="_blank" class="wpcc-doc-link" title="<?php _e(
                                    "查看详细说明",
                                    "wp-chinese-converter",
                                ); ?>">↗</a>
                            </th>
                            <td>
                                <div class="wpcc-sites-container" style="border: 1px solid #ddd; border-radius: 4px; max-height: 400px; overflow-y: auto;">
                                    <table class="wp-list-table widefat fixed striped" style="margin: 0; border: none;">
                                        <thead>
                                            <tr style="background: #f9f9f9;">
                                                <th style="padding: 12px; border-bottom: 1px solid #ddd; font-weight: 600;">站点信息</th>
                                                <th style="padding: 12px; border-bottom: 1px solid #ddd; font-weight: 600;">状态</th>
                                                <th style="padding: 12px; border-bottom: 1px solid #ddd; font-weight: 600;">配置</th>
                                                <th style="padding: 12px; border-bottom: 1px solid #ddd; font-weight: 600;">操作</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $lang_names = [
                                                "zh-cn" => "简体",
                                                "zh-tw" => "繁体",
                                                "zh-hk" => "港澳",
                                                "zh-hans" => "简体(国际)",
                                                "zh-hant" => "繁体(国际)",
                                                "zh-sg" => "马新",
                                                "zh-jp" => "日式",
                                            ];

                                            foreach ($sites as $site) {
                                                switch_to_blog($site->blog_id);
                                                $options = get_option(
                                                    "wpcc_options",
                                                    [],
                                                );
                                                $site_details = get_blog_details(
                                                    $site->blog_id,
                                                );

                                                echo "<tr>";

                                                // 站点信息
                                                echo '<td style="padding: 12px; border-bottom: 1px solid #eee;">';
                                                echo '<div style="font-weight: 600; margin-bottom: 4px;">' .
                                                    esc_html(
                                                        $site_details->blogname,
                                                    ) .
                                                    "</div>";
                                                echo '<div style="font-size: 12px; color: #666;">' .
                                                    esc_html(
                                                        $site_details->domain .
                                                            $site_details->path,
                                                    ) .
                                                    "</div>";
                                                echo "</td>";

                                                // 状态
                                                echo '<td style="padding: 12px; border-bottom: 1px solid #eee;">';
                                                if (!empty($options)) {
                                                    echo '<span style="display: inline-block; padding: 2px 8px; background: #d1e7dd; color: #0f5132; border-radius: 12px; font-size: 11px; font-weight: 500;">✓ 已激活</span>';
                                                } else {
                                                    echo '<span style="display: inline-block; padding: 2px 8px; background: #f8d7da; color: #721c24; border-radius: 12px; font-size: 11px; font-weight: 500;">✗ 未激活</span>';
                                                }
                                                echo "</td>";

                                                // 配置信息
                                                echo '<td style="padding: 12px; border-bottom: 1px solid #eee;">';
                                                if (!empty($options)) {
                                                    $engine =
                                                        $options[
                                                            "wpcc_engine"
                                                        ] ?? "mediawiki";
                                                    echo '<div style="margin-bottom: 4px;">';
                                                    echo '<span style="font-size: 11px; color: #666;">引擎:</span> ';
                                                    echo '<span style="padding: 1px 6px; background: #e7f3ff; color: #0073aa; border-radius: 8px; font-size: 10px;">' .
                                                        esc_html(
                                                            ucfirst($engine),
                                                        ) .
                                                        "</span>";
                                                    echo "</div>";

                                                    if (
                                                        isset(
                                                            $options[
                                                                "wpcc_used_langs"
                                                            ],
                                                        ) &&
                                                        !empty(
                                                            $options[
                                                                "wpcc_used_langs"
                                                            ]
                                                        )
                                                    ) {
                                                        echo "<div>";
                                                        echo '<span style="font-size: 11px; color: #666;">语言:</span> ';
                                                        $lang_tags = [];
                                                        foreach (
                                                            $options[
                                                                "wpcc_used_langs"
                                                            ]
                                                            as $lang
                                                        ) {
                                                            $lang_name = isset(
                                                                $lang_names[
                                                                    $lang
                                                                ],
                                                            )
                                                                ? $lang_names[
                                                                    $lang
                                                                ]
                                                                : $lang;
                                                            $lang_tags[] =
                                                                '<span style="padding: 1px 4px; background: #fff3cd; color: #856404; border-radius: 6px; font-size: 9px;">' .
                                                                esc_html(
                                                                    $lang_name,
                                                                ) .
                                                                "</span>";
                                                        }
                                                        echo implode(
                                                            " ",
                                                            $lang_tags,
                                                        );
                                                        echo "</div>";
                                                    }
                                                } else {
                                                    echo '<span style="color: #999; font-size: 12px;">未配置</span>';
                                                }
                                                echo "</td>";

                                                // 操作
                                                echo '<td style="padding: 12px; border-bottom: 1px solid #eee;">';
                                                $site_admin_url = get_admin_url(
                                                    $site->blog_id,
                                                    "options-general.php?page=wp-chinese-converter",
                                                );
                                                echo '<a href="' .
                                                    esc_url($site_admin_url) .
                                                    '" target="_blank" class="button button-small" style="font-size: 11px; padding: 4px 8px; text-decoration: none;">管理设置</a>';
                                                echo "</td>";

                                                echo "</tr>";

                                                restore_current_blog();
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                                <p class="description" style="margin-top: 10px;">
                                    <?php _e(
                                        '显示网络中所有站点的插件状态和配置信息。点击"管理设置"可直接访问对应站点的设置页面。',
                                        "wp-chinese-converter",
                                    ); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                    <?php endif; ?>

                    <h3><?php _e("缓存兼容性", "wp-chinese-converter"); ?></h3>
                    <table class="form-table">
                        <tr>
                            <th>
                                <?php _e(
                                    "缓存插件状态",
                                    "wp-chinese-converter",
                                ); ?>
                                <a href="https://wpcc.net/document/cache-compatibility" target="_blank" class="wpcc-doc-link" title="<?php _e(
                                    "查看详细说明",
                                    "wp-chinese-converter",
                                ); ?>">↗</a>
                            </th>
                            <td>
                                <?php
                                $cache_plugins = [
                                    "wp_super_cache" => [
                                        "name" => "WP Super Cache",
                                        "check" =>
                                            function_exists(
                                                "wp_cache_is_enabled",
                                            ) ||
                                            function_exists(
                                                "wp_super_cache_init",
                                            ),
                                    ],
                                    "wp_rocket" => [
                                        "name" => "WP Rocket",
                                        "check" => function_exists(
                                            "rocket_clean_domain",
                                        ),
                                    ],
                                    "litespeed_cache" => [
                                        "name" => "LiteSpeed Cache",
                                        "check" => class_exists(
                                            "LiteSpeed\Core",
                                        ),
                                    ],
                                    "w3_total_cache" => [
                                        "name" => "W3 Total Cache",
                                        "check" => function_exists(
                                            "w3tc_flush_all",
                                        ),
                                    ],
                                    "wp_fastest_cache" => [
                                        "name" => "WP Fastest Cache",
                                        "check" => class_exists(
                                            "WpFastestCache",
                                        ),
                                    ],
                                    "autoptimize" => [
                                        "name" => "Autoptimize",
                                        "check" =>
                                            class_exists("autoptimizeMain") ||
                                            function_exists(
                                                "autoptimize_autoload",
                                            ),
                                    ],
                                    "jetpack_boost" => [
                                        "name" => "Jetpack Boost",
                                        "check" =>
                                            defined("JETPACK_BOOST_VERSION") ||
                                            class_exists(
                                                "Automattic\Jetpack_Boost\Jetpack_Boost",
                                            ),
                                    ],
                                ];

                                $active_cache_plugins = [];
                                foreach ($cache_plugins as $key => $plugin) {
                                    if ($plugin["check"]) {
                                        $active_cache_plugins[$key] =
                                            $plugin["name"];
                                    }
                                }

                                if (!empty($active_cache_plugins)) {
                                    echo '<div style="margin-bottom: 15px; padding: 12px; background: #e7f3ff; border-left: 4px solid #0073aa; border-radius: 4px;">';
                                    echo '<div style="font-weight: 600; color: #0073aa; margin-bottom: 8px;">检测到的缓存插件:</div>';
                                    echo '<div style="display: flex; flex-wrap: wrap; gap: 8px;">';
                                    foreach (
                                        $active_cache_plugins
                                        as $plugin_name
                                    ) {
                                        echo '<span style="padding: 4px 10px; background: #fff; border: 1px solid #0073aa; border-radius: 16px; font-size: 12px; color: #0073aa;">' .
                                            esc_html($plugin_name) .
                                            "</span>";
                                    }
                                    echo "</div>";
                                    echo "</div>";

                                    // 检查缓存兼容设置
                                    $cache_controlled = in_array(
                                        "wpcc_enable_cache_addon",
                                        get_site_option(
                                            "wpcc_network_controlled_options",
                                            [],
                                        ),
                                    );
                                    if ($cache_controlled) {
                                        $cache_enabled = get_site_option(
                                            "wpcc_default_enable_cache_addon",
                                            1,
                                        );
                                        if ($cache_enabled) {
                                            echo '<div style="padding: 10px; background: #d1e7dd; border-left: 4px solid #0f5132; border-radius: 4px; margin-bottom: 10px;">';
                                            echo '<span style="color: #0f5132; font-weight: 500;">网络级缓存兼容已启用</span><br>';
                                            echo '<span style="color: #0f5132; font-size: 12px;">所有子站点将自动使用缓存兼容功能</span>';
                                            echo "</div>";
                                        } else {
                                            echo '<div style="padding: 10px; background: #f8d7da; border-left: 4px solid #721c24; border-radius: 4px; margin-bottom: 10px;">';
                                            echo '<span style="color: #721c24; font-weight: 500;">网络级缓存兼容已禁用</span><br>';
                                            echo '<span style="color: #721c24; font-size: 12px;">子站点的缓存兼容功能将不可用</span>';
                                            echo "</div>";
                                        }
                                    } else {
                                        echo '<div style="padding: 10px; background: #fff3cd; border-left: 4px solid #856404; border-radius: 4px; margin-bottom: 10px;">';
                                        echo '<span style="color: #856404; font-weight: 500;">缓存兼容选项未被网络控制</span><br>';
                                        echo '<span style="color: #856404; font-size: 12px;">各子站点可自行配置缓存兼容功能</span>';
                                        echo "</div>";
                                    }
                                } else {
                                    echo '<div style="padding: 12px; background: #f9f9f9; border-radius: 4px; color: #666; text-align: center;">';
                                    echo "未检测到活跃的缓存插件";
                                    echo "</div>";
                                }
                                ?>
                            </td>
                        </tr>
                    </table>

                    <?php if (
                        get_site_option("wpcc_enable_bulk_operations", 0)
                    ): ?>
                    <h3 style="color: #d63638;"><?php _e(
                        "危险操作区域",
                        "wp-chinese-converter",
                    ); ?></h3>
                    <table class="form-table">
                        <tr>
                            <th style="color: #d63638;">
                                <?php _e(
                                    "批量重置站点设置",
                                    "wp-chinese-converter",
                                ); ?>
                                <a href="https://wpcc.net/document/bulk-operations" target="_blank" class="wpcc-doc-link" title="<?php _e(
                                    "查看详细说明",
                                    "wp-chinese-converter",
                                ); ?>">↗</a>
                            </th>
                            <td>
                                <div style="padding: 15px; background: #fdf2f2; border: 1px solid #f5c2c7; border-radius: 4px; margin-bottom: 15px;">
                                    <div style="color: #721c24; font-weight: 600; margin-bottom: 8px;">危险操作警告</div>
                                    <div style="color: #721c24; font-size: 14px; line-height: 1.5;">
                                        此操作将重置网络中所有站点的 WP Chinese Converter 设置为默认值。<br>
                                        <strong>此操作不可逆，请谨慎使用！</strong>
                                    </div>
                                </div>

                                <form method="post" action="edit.php?action=wpcc_bulk_reset_sites" style="border: 2px dashed #d63638; padding: 15px; border-radius: 4px;">
                                    <?php wp_nonce_field(
                                        "wpcc_bulk_reset_sites",
                                    ); ?>

                                    <div style="margin-bottom: 15px;">
                                        <label class="wpcc-switch">
                                            <input type="checkbox" name="wpcc_bulk_reset_confirm" required />
                                            <span class="wpcc-slider"></span>
                                            <span class="wpcc-switch-label" style="color: #d63638; font-weight: 500;">我确认要批量重置所有站点设置 (此操作不可逆)</span>
                                        </label>
                                    </div>

                                    <div>
                                        <button type="submit" class="button button-secondary" style="background: #d63638; border-color: #d63638; color: #fff;"
                                               onclick="return confirm('最后确认：您确定要重置网络中所有 <?php echo esc_js(
                                                   count(get_sites()),
                                               ); ?> 个站点的设置吗？\n\n此操作将：\n- 删除所有站点的 WPCC 配置\n- 无法恢复已删除的设置\n- 需要重新配置所有站点\n\n请输入 RESET 确认：') && prompt('请输入 RESET 确认此操作：') === 'RESET'">
                                            批量重置所有站点设置
                                        </button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    </table>
                    <?php else: ?>
                    <div style="padding: 15px; background: #f0f6fc; border-left: 4px solid #0073aa; border-radius: 4px; margin-top: 20px;">
                        <div style="color: #0073aa; font-weight: 600; margin-bottom: 5px;">批量操作已禁用</div>
                        <div style="color: #0073aa; font-size: 14px;">
                            为了安全考虑，批量重置等危险操作已被禁用。如需启用，请在上方的"网络控制选项"中开启"启用批量操作"。
                        </div>
                    </div>
                    <?php endif; ?>
                </div>




                <div class="wpcc-clearfix" style="clear: both;"></div>
                </div>
            </div>
        </div>

        <style>
            /* Keep WP admin footer at the bottom on this page (no overlay) */
            body.network-admin.settings_page_wpcc-network #wpfooter {
                position: static !important;
                left: auto !important;
                bottom: auto !important;
                right: auto !important;
                width: auto !important;
            }
            body.network-admin.settings_page_wpcc-network #wpbody-content {
                padding-bottom: 0 !important;
            }

            /* Ensure admin footer stays below our content on this page */
            .wpcc-settings:after,
            .wpcc-card:after {
                content: "";
                display: block;
                clear: both;
            }

            .wpcc-card {
                background: #fff;
                border: 1px solid #ccd0d4;
                border-radius: 4px;
                margin-top: 20px;
                padding: 20px;
                box-shadow: 0 1px 1px rgba(0, 0, 0, 0.04);
            }

            .wpcc-sync-tabs {
                display: flex;
                flex-wrap: wrap;
                gap: 5px;
                border-bottom: 1px solid #c3c4c7;
                margin-bottom: 20px;
            }

            .wpcc-section-desc {
                color: #666;
                margin-bottom: 20px;
                font-size: 14px;
            }

            .network-control-options {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
                gap: 15px;
                margin: 15px 0;
            }

            .option-group {
                padding: 30px;
                border-radius: 6px;
                background: #fafafa;
            }

            .option-group h4 {
                margin-top: 0;
                margin-bottom: 10px;
                padding-bottom: 5px;
                color: #1d2327;
                font-size: 14px;
                font-weight: 600;
                border-bottom: 1px solid #eee;
            }

            .group-description {
                margin: 0 0 12px 0;
                font-size: 12px;
                color: #646970;
                font-style: italic;
            }

            .wpcc-checkbox {
                display: block;
                margin-bottom: 10px;
                padding: 2px 0;
            }

            .wpcc-checkbox-label {
                margin-left: 8px;
                font-size: 13px;
                color: #1d2327;
            }

            .wpcc-info-box {
                border-radius: 4px;
                padding: 12px;
                margin-bottom: 15px;
                border-left: 4px solid #2271b1;
            }

            .wpcc-import-export-wrapper {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 20px;
                margin-top: 20px;
            }

            .wpcc-export-section,
            .wpcc-import-section {
                border: 1px solid #e0e0e0;
                padding: 20px;
                border-radius: 6px;
                background: #fafafa;
            }

            .wpcc-export-section h3,
            .wpcc-import-section h3 {
                margin-top: 0;
                margin-bottom: 15px;
                color: #1d2327;
                font-weight: 600;
            }
        </style>

        <script>
            jQuery(document).ready(function($) {
                $('.wpcc-tab').click(function() {
                    var tab = $(this).data('tab');
                    $('.wpcc-tab').removeClass('active');
                    $(this).addClass('active');
                    $('.wpcc-section').hide();
                    $('#wpcc-section-' + tab).show();

                    var url = new URL(window.location);
                    url.searchParams.set('tab', tab);
                    window.history.pushState({}, '', url);
                });

                $('#select-all-options').click(function() {
                    $('input[name="wpcc_network_controlled_options[]"]').prop('checked', true);
                });

                $('#deselect-all-options').click(function() {
                    $('input[name="wpcc_network_controlled_options[]"]').prop('checked', false);
                });

                $('#reset-default-options').click(function() {
                    $('input[name="wpcc_network_controlled_options[]"]').prop('checked', false);
                    $('input[value="wpcc_engine"], input[value="wpcc_used_langs"], input[value="wpcc_search_conversion"]').prop('checked', true);
                });

                $('#preview-control-effect').click(function() {
                    var controlledOptions = [];
                    $('input[name="wpcc_network_controlled_options[]"]:checked').each(function() {
                        controlledOptions.push($(this).val());
                    });

                    var previewContent = '<h5>被网络控制的选项：</h5>';
                    if (controlledOptions.length === 0) {
                        previewContent += '<p style="color: #666;">没有选择任何控制选项，子站点可以自由修改所有设置。</p>';
                    } else {
                        previewContent += '<ul style="margin: 10px 0; padding-left: 20px;">';
                        controlledOptions.forEach(function(option) {
                            var optionText = $('input[value="' + option + '"]').siblings('.wpcc-checkbox-label').text();
                            previewContent += '<li style="margin-bottom: 5px; color: #d63638;"><strong>' + optionText + '</strong> - 将在子站点显示为禁用状态</li>';
                        });
                        previewContent += '</ul>';
                        previewContent += '<p style="color: #0073aa; font-weight: 500;">✓ 子站点管理员将看到这些选项被禁用，并显示"由网络管理员控制"的提示。</p>';
                    }

                    $('#preview-content').html(previewContent);
                    $('#control-preview').slideDown();
                });

                // 联动：显示更多语言 开关控制扩展语言模块显示
                function toggleExtendedLanguages() {
                    var enabled = $('#wpcc_default_enable_extended_langs').is(':checked');
                    $('.wpcc-extended-languages').toggle(enabled);
                }
                $('#wpcc_default_enable_extended_langs').on('change', toggleExtendedLanguages);
                toggleExtendedLanguages();

                // 回滚：不在网络 UI 上根据引擎禁用语言，改由管理员自主勾选

                // 联动：发表时转换 -> 目标语言显示/隐藏
                function toggleNetPostConv() {
                    var enabled = $('input[name="wpcc_default_enable_post_conversion"]').is(':checked');
                    $('#wpcc-network-post-conv-options').toggle(enabled);
                }
                $('input[name="wpcc_default_enable_post_conversion"]').on('change', toggleNetPostConv);
                toggleNetPostConv();

                // 联动：x-default 标签 -> 默认语言行显示/隐藏
                function toggleNetXDefault() {
                    var enabled = $('input[name="wpcc_default_enable_hreflang_x_default"]').is(':checked');
                    $('#wpcc-network-x-default-options').toggle(enabled);
                }
                $('input[name="wpcc_default_enable_hreflang_x_default"]').on('change', toggleNetXDefault);
                toggleNetXDefault();

            });
        </script>
<?php
    }

    public static function save_network_settings()
    {
        if (!current_user_can("manage_network_options")) {
            wp_die("您没有足够权限修改这些设置。");
            return;
        }

        check_admin_referer("wpcc_network_settings");

        $current_tab = isset($_POST["tab"])
            ? sanitize_text_field($_POST["tab"])
            : "";

        if ($current_tab === "network") {
            update_site_option(
                "wpcc_network_enabled",
                isset($_POST["wpcc_network_enabled"]) ? 1 : 0,
            );
            update_site_option(
                "wpcc_network_enforce",
                isset($_POST["wpcc_network_enforce"]) ? 1 : 0,
            );

            $controlled_options = isset(
                $_POST["wpcc_network_controlled_options"],
            )
                ? $_POST["wpcc_network_controlled_options"]
                : [];
            update_site_option(
                "wpcc_network_controlled_options",
                $controlled_options,
            );
        }

        if ($current_tab === "tools") {
            update_site_option(
                "wpcc_enable_network_tools",
                isset($_POST["wpcc_enable_network_tools"]) ? 1 : 0,
            );
            update_site_option(
                "wpcc_allow_site_details",
                isset($_POST["wpcc_allow_site_details"]) ? 1 : 0,
            );
            update_site_option(
                "wpcc_enable_bulk_operations",
                isset($_POST["wpcc_enable_bulk_operations"]) ? 1 : 0,
            );
        }

        if ($current_tab === "basic") {
            $default_used_langs = [];
            if (isset($_POST["wpcc_default_variant_zh-cn"])) {
                $default_used_langs[] = "zh-cn";
            }
            if (isset($_POST["wpcc_default_variant_zh-tw"])) {
                $default_used_langs[] = "zh-tw";
            }
            if (isset($_POST["wpcc_default_variant_zh-hk"])) {
                $default_used_langs[] = "zh-hk";
            }
            if (isset($_POST["wpcc_default_variant_zh-hans"])) {
                $default_used_langs[] = "zh-hans";
            }
            if (isset($_POST["wpcc_default_variant_zh-hant"])) {
                $default_used_langs[] = "zh-hant";
            }
            if (isset($_POST["wpcc_default_variant_zh-sg"])) {
                $default_used_langs[] = "zh-sg";
            }
            if (isset($_POST["wpcc_default_variant_zh-jp"])) {
                $default_used_langs[] = "zh-jp";
            }

            // 按网络策略裁剪默认启用语言集
            $ext_enabled = isset($_POST["wpcc_default_enable_extended_langs"])
                ? 1
                : 0;
            $engine = isset($_POST["wpcc_default_engine"])
                ? sanitize_text_field($_POST["wpcc_default_engine"])
                : get_site_option("wpcc_default_engine", "opencc");

            // 1) 扩展语言未启用时，仅保留基础三种
            if (!$ext_enabled) {
                $base_langs = ["zh-cn", "zh-tw", "zh-hk"];
                $default_used_langs = array_values(
                    array_intersect($default_used_langs, $base_langs),
                );
            }
            // 2) 非 OpenCC 引擎时移除 zh-jp
            if ($engine !== "opencc") {
                $default_used_langs = array_values(
                    array_diff($default_used_langs, ["zh-jp"]),
                );
            }
            // 3) 兜底：至少保留一种
            if (empty($default_used_langs)) {
                $default_used_langs = ["zh-cn"];
            }

            update_site_option("wpcc_default_used_langs", $default_used_langs);
            update_site_option(
                "wpcc_default_cntip",
                sanitize_text_field($_POST["wpcc_default_cntip"] ?? "简体"),
            );
            update_site_option(
                "wpcc_default_twtip",
                sanitize_text_field($_POST["wpcc_default_twtip"] ?? "繁体"),
            );
            update_site_option(
                "wpcc_default_hktip",
                sanitize_text_field($_POST["wpcc_default_hktip"] ?? "港澳"),
            );
            update_site_option(
                "wpcc_default_hanstip",
                sanitize_text_field($_POST["wpcc_default_hanstip"] ?? "简体"),
            );
            update_site_option(
                "wpcc_default_hanttip",
                sanitize_text_field($_POST["wpcc_default_hanttip"] ?? "繁体"),
            );
            update_site_option(
                "wpcc_default_sgtip",
                sanitize_text_field($_POST["wpcc_default_sgtip"] ?? "马新"),
            );
            update_site_option(
                "wpcc_default_jptip",
                sanitize_text_field($_POST["wpcc_default_jptip"] ?? "日式"),
            );

            update_site_option(
                "wpcc_default_nctip",
                sanitize_text_field($_POST["wpcc_default_nctip"] ?? "简体"),
            );
            update_site_option(
                "wpcc_default_flag_option",
                intval($_POST["wpcc_default_flag_option"] ?? 1),
            );
            update_site_option(
                "wpcc_default_enable_extended_langs",
                $ext_enabled,
            );

            update_site_option("wpcc_default_engine", $engine);
            update_site_option(
                "wpcc_default_search_conversion",
                intval($_POST["wpcc_default_search_conversion"] ?? 1),
            );
            update_site_option(
                "wpcc_default_use_fullpage_conversion",
                isset($_POST["wpcc_default_use_fullpage_conversion"]) ? 1 : 0,
            );

            update_site_option(
                "wpcc_default_no_conversion_qtag",
                isset($_POST["wpcc_default_no_conversion_qtag"]) ? 1 : 0,
            );
            update_site_option(
                "wpcc_default_enable_post_conversion",
                isset($_POST["wpcc_default_enable_post_conversion"]) ? 1 : 0,
            );
            if (isset($_POST["wpcc_default_post_conversion_target"])) {
                update_site_option(
                    "wpcc_default_post_conversion_target",
                    sanitize_text_field(
                        $_POST["wpcc_default_post_conversion_target"],
                    ),
                );
            }

            update_site_option(
                "wpcc_default_use_permalink",
                intval($_POST["wpcc_default_use_permalink"] ?? 0),
            );
            update_site_option(
                "wpcc_default_sitemap_post_type",
                sanitize_text_field(
                    $_POST["wpcc_default_sitemap_post_type"] ?? "post,page",
                ),
            );
            update_site_option(
                "wpcc_default_use_sitemap",
                isset($_POST["wpcc_default_use_sitemap"]) ? 1 : 0,
            );

            update_site_option(
                "wpcc_default_browser_redirect",
                intval($_POST["wpcc_default_browser_redirect"] ?? 0),
            );
            update_site_option(
                "wpcc_default_use_cookie_variant",
                intval($_POST["wpcc_default_use_cookie_variant"] ?? 0),
            );
            update_site_option(
                "wpcc_default_first_visit_default",
                isset($_POST["wpcc_default_first_visit_default"]) ? 1 : 0,
            );
            update_site_option(
                "wpcc_default_auto_language_recong",
                isset($_POST["wpcc_default_auto_language_recong"]) ? 1 : 0,
            );

            update_site_option(
                "wpcc_default_no_conversion_tag",
                sanitize_textarea_field(
                    $_POST["wpcc_default_no_conversion_tag"] ?? "",
                ),
            );
            update_site_option(
                "wpcc_default_no_conversion_ja",
                isset($_POST["wpcc_default_no_conversion_ja"]) ? 1 : 0,
            );

            update_site_option(
                "wpcc_default_enable_hreflang_x_default",
                isset($_POST["wpcc_default_enable_hreflang_x_default"]) ? 1 : 0,
            );
            update_site_option(
                "wpcc_default_hreflang_x_default",
                sanitize_text_field(
                    $_POST["wpcc_default_hreflang_x_default"] ?? "zh-cn",
                ),
            );
            update_site_option(
                "wpcc_default_enable_hreflang_tags",
                isset($_POST["wpcc_default_enable_hreflang_tags"]) ? 1 : 0,
            );
            update_site_option(
                "wpcc_default_enable_schema_conversion",
                isset($_POST["wpcc_default_enable_schema_conversion"]) ? 1 : 0,
            );
            update_site_option(
                "wpcc_default_enable_meta_conversion",
                isset($_POST["wpcc_default_enable_meta_conversion"]) ? 1 : 0,
            );

            update_site_option(
                "wpcc_default_enable_cache_addon",
                isset($_POST["wpcc_default_enable_cache_addon"]) ? 1 : 0,
            );
            update_site_option(
                "wpcc_default_enable_network_module",
                isset($_POST["wpcc_default_enable_network_module"]) ? 1 : 0,
            );

            // 自动同步：如果启用了网络级管理，则将被网络控制的基础设置同步到所有子站点
            $network_enabled = get_site_option("wpcc_network_enabled", 1);
            if ($network_enabled) {
                $controlled_options = get_site_option(
                    "wpcc_network_controlled_options",
                    self::$default_controlled_options,
                );
                if (!is_array($controlled_options)) {
                    $controlled_options = explode(
                        ",",
                        (string) $controlled_options,
                    );
                }
                $sync_settings = [];

                // 构建 从受控的站点级键 -> 网络默认键 的映射
                $map = [
                    "wpcc_used_langs" => "wpcc_default_used_langs",
                    "cntip" => "wpcc_default_cntip",
                    "twtip" => "wpcc_default_twtip",
                    "hktip" => "wpcc_default_hktip",
                    "hanstip" => "wpcc_default_hanstip",
                    "hanttip" => "wpcc_default_hanttip",
                    "sgtip" => "wpcc_default_sgtip",
                    "jptip" => "wpcc_default_jptip",
                    "nctip" => "wpcc_default_nctip",
                    "wpcc_flag_option" => "wpcc_default_flag_option",
                    // 扩展语言模块（单站点键为 wpcc_show_more_langs）
                    "wpcc_show_more_langs" =>
                        "wpcc_default_enable_extended_langs",
                    "wpcc_engine" => "wpcc_default_engine",
                    "wpcc_search_conversion" =>
                        "wpcc_default_search_conversion",
                    "wpcc_use_fullpage_conversion" =>
                        "wpcc_default_use_fullpage_conversion",
                    // 快速标签（单站点键为 wpcc_no_conversion_qtag）
                    "wpcc_no_conversion_qtag" =>
                        "wpcc_default_no_conversion_qtag",
                    "wpcc_enable_post_conversion" =>
                        "wpcc_default_enable_post_conversion",
                    "wpcc_post_conversion_target" =>
                        "wpcc_default_post_conversion_target",
                    "wpcc_use_permalink" => "wpcc_default_use_permalink",
                    "wpcco_sitemap_post_type" =>
                        "wpcc_default_sitemap_post_type",
                    "wpcco_use_sitemap" => "wpcc_default_use_sitemap",
                    "wpcc_browser_redirect" => "wpcc_default_browser_redirect",
                    "wpcc_auto_language_recong" =>
                        "wpcc_default_auto_language_recong",
                    "wpcc_use_cookie_variant" =>
                        "wpcc_default_use_cookie_variant",
                    "wpcc_first_visit_default" =>
                        "wpcc_default_first_visit_default",
                    "wpcc_no_conversion_tag" =>
                        "wpcc_default_no_conversion_tag",
                    "wpcc_no_conversion_ja" => "wpcc_default_no_conversion_ja",
                    "wpcc_hreflang_x_default" =>
                        "wpcc_default_hreflang_x_default",
                    "wpcc_enable_hreflang_tags" =>
                        "wpcc_default_enable_hreflang_tags",
                    "wpcc_enable_hreflang_x_default" =>
                        "wpcc_default_enable_hreflang_x_default",
                    "wpcc_enable_schema_conversion" =>
                        "wpcc_default_enable_schema_conversion",
                    "wpcc_enable_meta_conversion" =>
                        "wpcc_default_enable_meta_conversion",
                    "wpcc_enable_cache_addon" =>
                        "wpcc_default_enable_cache_addon",
                    "wpcc_enable_network_module" =>
                        "wpcc_default_enable_network_module",
                ];

                foreach ($controlled_options as $local_key) {
                    if (isset($map[$local_key])) {
                        $network_key = $map[$local_key];
                        $sync_settings[$network_key] = get_site_option(
                            $network_key,
                        );
                    }
                }

                // 仅当有需要同步的键时才应用
                if (!empty($sync_settings)) {
                    self::apply_settings_to_all_sites($sync_settings);
                }
            }
        }

        $redirect_url = add_query_arg(
            [
                "page" => "wpcc-network",
                "updated" => "true",
            ],
            network_admin_url("settings.php"),
        );

        if (!empty($current_tab)) {
            $redirect_url = add_query_arg("tab", $current_tab, $redirect_url);
        }

        wp_redirect($redirect_url);
        exit();
    }

    public static function display_sites_status()
    {
        $sites = get_sites(["number" => 50]);

        if (empty($sites)) {
            echo "<p>没有找到站点。</p>";
            return;
        }

        echo '<table class="wp-list-table widefat fixed striped">';
        echo "<thead><tr><th>站点</th><th>转换引擎</th><th>启用语言</th><th>状态</th></tr></thead>";
        echo "<tbody>";

        foreach ($sites as $site) {
            switch_to_blog($site->blog_id);

            $site_options = get_option("wpcc_options", []);
            $engine = $site_options["wpcc_engine"] ?? "mediawiki";
            $languages = $site_options["wpcc_used_langs"] ?? [];
            $active = is_plugin_active(
                "wp-chinese-converter/wp-chinese-converter.php",
            );

            echo "<tr>";
            echo '<td><a href="' .
                esc_url(get_home_url()) .
                '">' .
                esc_html(get_bloginfo("name")) .
                "</a></td>";
            echo "<td>" . esc_html($engine) . "</td>";
            echo "<td>" . esc_html(implode(", ", $languages)) . "</td>";
            echo "<td>" . ($active ? "✅ 激活" : "❌ 未激活") . "</td>";
            echo "</tr>";

            restore_current_blog();
        }

        echo "</tbody></table>";
    }

    public static function export_settings()
    {
        if (!current_user_can("manage_network_options")) {
            wp_die("您没有足够权限执行此操作。");
            return;
        }

        check_admin_referer("wpcc_export_settings");

        $export_data = [];

        if (isset($_POST["export_network_settings"])) {
            $export_data["network_settings"] = [
                "wpcc_network_enabled" => get_site_option(
                    "wpcc_network_enabled",
                    0,
                ),
                "wpcc_network_enforce" => get_site_option(
                    "wpcc_network_enforce",
                    0,
                ),
            ];
        }

        if (isset($_POST["export_basic_settings"])) {
                $export_data["basic_settings"] = [
                    "wpcc_default_used_langs" => get_site_option(
                    "wpcc_default_used_langs",
                    ["zh-cn", "zh-tw"],
                ),
                "wpcc_default_cntip" => get_site_option(
                    "wpcc_default_cntip",
                    "简体",
                ),
                "wpcc_default_twtip" => get_site_option(
                    "wpcc_default_twtip",
                    "繁体",
                ),
                "wpcc_default_hktip" => get_site_option(
                    "wpcc_default_hktip",
                    "港澳",
                ),
                "wpcc_default_hanstip" => get_site_option(
                    "wpcc_default_hanstip",
                    "简体",
                ),
                "wpcc_default_hanttip" => get_site_option(
                    "wpcc_default_hanttip",
                    "繁体",
                ),
                "wpcc_default_sgtip" => get_site_option(
                    "wpcc_default_sgtip",
                    "马新",
                ),
                "wpcc_default_jptip" => get_site_option(
                    "wpcc_default_jptip",
                    "日式",
                ),
                "wpcc_default_nctip" => get_site_option(
                    "wpcc_default_nctip",
                    "简体",
                ),
                "wpcc_default_flag_option" => get_site_option(
                    "wpcc_default_flag_option",
                    1,
                ),
                "wpcc_default_enable_extended_langs" => get_site_option(
                    "wpcc_default_enable_extended_langs",
                    1,
                ),
                "wpcc_default_engine" => get_site_option(
                    "wpcc_default_engine",
                    "opencc",
                ),
                "wpcc_default_search_conversion" => get_site_option(
                    "wpcc_default_search_conversion",
                    1,
                ),
                "wpcc_default_use_fullpage_conversion" => get_site_option(
                    "wpcc_default_use_fullpage_conversion",
                    1,
                ),
                "wpcc_default_enable_quicktags" => get_site_option(
                    "wpcc_default_enable_quicktags",
                    1,
                ),
                "wpcc_default_enable_post_conversion" => get_site_option(
                    "wpcc_default_enable_post_conversion",
                    0,
                ),
                "wpcc_default_use_permalink" => get_site_option(
                    "wpcc_default_use_permalink",
                    0,
                ),
                "wpcc_default_sitemap_post_type" => get_site_option(
                    "wpcc_default_sitemap_post_type",
                    "post,page",
                ),
                "wpcc_default_use_sitemap" => get_site_option(
                    "wpcc_default_use_sitemap",
                    1,
                ),
                "wpcc_default_browser_redirect" => get_site_option(
                    "wpcc_default_browser_redirect",
                    0,
                ),
                    "wpcc_default_use_cookie_variant" => get_site_option(
                        "wpcc_default_use_cookie_variant",
                        0,
                    ),
                    "wpcc_default_first_visit_default" => get_site_option(
                        "wpcc_default_first_visit_default",
                        0,
                    ),
                "wpcc_default_no_conversion_tag" => get_site_option(
                    "wpcc_default_no_conversion_tag",
                    "",
                ),
                "wpcc_default_no_conversion_ja" => get_site_option(
                    "wpcc_default_no_conversion_ja",
                    0,
                ),
                "wpcc_default_hreflang_x_default" => get_site_option(
                    "wpcc_default_hreflang_x_default",
                    "zh-cn",
                ),
                "wpcc_default_enable_hreflang_x_default" => get_site_option(
                    "wpcc_default_enable_hreflang_x_default",
                    1,
                ),
                "wpcc_default_enable_hreflang_tags" => get_site_option(
                    "wpcc_default_enable_hreflang_tags",
                    1,
                ),
                "wpcc_default_enable_schema_conversion" => get_site_option(
                    "wpcc_default_enable_schema_conversion",
                    1,
                ),
                "wpcc_default_enable_meta_conversion" => get_site_option(
                    "wpcc_default_enable_meta_conversion",
                    1,
                ),
                "wpcc_default_enable_cache_addon" => get_site_option(
                    "wpcc_default_enable_cache_addon",
                    1,
                ),
                "wpcc_default_enable_network_module" => get_site_option(
                    "wpcc_default_enable_network_module",
                    1,
                ),
            ];
        }

        if (isset($_POST["export_controlled_options"])) {
            $export_data["controlled_options"] = [
                "wpcc_network_controlled_options" => get_site_option(
                    "wpcc_network_controlled_options",
                    self::$default_controlled_options,
                ),
            ];
        }

        $export_data["export_info"] = [
            "plugin_version" => wpcc_VERSION,
            "export_date" => current_time("mysql"),
            "site_url" => network_site_url(),
        ];

        $filename = "wpcc-network-settings-" . date("Y-m-d-H-i-s") . ".json";

        header("Content-Type: application/json");
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header("Cache-Control: no-cache, must-revalidate");
        header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

        echo json_encode(
            $export_data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE,
        );
        exit();
    }

    public static function import_settings()
    {
        if (!current_user_can("manage_network_options")) {
            wp_die("您没有足够权限执行此操作。");
            return;
        }

        check_admin_referer("wpcc_import_settings");

        if (
            !isset($_FILES["import_file"]) ||
            $_FILES["import_file"]["error"] !== UPLOAD_ERR_OK
        ) {
            wp_die("文件上传失败，请重试。");
            return;
        }

        $file_content = file_get_contents($_FILES["import_file"]["tmp_name"]);
        $import_data = json_decode($file_content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_die("无效的JSON文件，请检查文件格式。");
            return;
        }

        if (
            isset($_POST["import_network_settings"]) &&
            isset($import_data["network_settings"])
        ) {
            foreach ($import_data["network_settings"] as $key => $value) {
                update_site_option($key, $value);
            }
        }

        if (
            isset($_POST["import_basic_settings"]) &&
            isset($import_data["basic_settings"])
        ) {
            foreach ($import_data["basic_settings"] as $key => $value) {
                update_site_option($key, $value);
            }
        }

        if (
            isset($_POST["import_controlled_options"]) &&
            isset($import_data["controlled_options"])
        ) {
            foreach ($import_data["controlled_options"] as $key => $value) {
                update_site_option($key, $value);
            }
        }

        if (
            isset($_POST["apply_to_existing_sites"]) &&
            isset($import_data["basic_settings"])
        ) {
            self::apply_settings_to_all_sites($import_data["basic_settings"]);
        }

        $redirect_url = add_query_arg(
            [
                "page" => "wpcc-network",
                "tab" => "import_export",
                "imported" => "true",
            ],
            network_admin_url("settings.php"),
        );

        wp_redirect($redirect_url);
        exit();
    }

    public static function reset_network_settings()
    {
        if (!current_user_can("manage_network_options")) {
            wp_die("您没有足够权限执行此操作。");
            return;
        }

        check_admin_referer("wpcc_reset_network_settings");

        delete_site_option("wpcc_network_enabled");
        delete_site_option("wpcc_network_enforce");
        delete_site_option("wpcc_default_engine");
        delete_site_option("wpcc_allow_site_override");
        delete_site_option("wpcc_default_languages");
        delete_site_option("wpcc_default_search_conversion");
        delete_site_option("wpcc_default_use_fullpage_conversion");
        delete_site_option("wpcc_default_enable_hreflang_tags");
        delete_site_option("wpcc_default_enable_schema_conversion");
        delete_site_option("wpcc_default_enable_meta_conversion");
        delete_site_option("wpcc_network_controlled_options");

        $redirect_url = add_query_arg(
            [
                "page" => "wpcc-network",
                "tab" => "import_export",
                "reset" => "true",
            ],
            network_admin_url("settings.php"),
        );

        wp_redirect($redirect_url);
        exit();
    }

    private static function apply_settings_to_all_sites($settings)
    {
        $sites = get_sites();

        // 获取网络主站的固定链接结构，作为必要时的回填模板
        $main_site_id = function_exists('get_main_site_id') ? get_main_site_id() : (is_multisite() ? get_network()->site_id : get_current_blog_id());
        $network_permalink_structure = '';
        if ($main_site_id) {
            switch_to_blog($main_site_id);
            $network_permalink_structure = (string) get_option('permalink_structure', '');
            restore_current_blog();
        }

        foreach ($sites as $site) {
            switch_to_blog($site->blog_id);

            $current_options = get_option('wpcc_options', array());

            if (isset($settings["wpcc_default_used_langs"])) {
                $current_options["wpcc_used_langs"] =
                    $settings["wpcc_default_used_langs"];
            }
            if (isset($settings["wpcc_default_cntip"])) {
                $current_options["cntip"] = $settings["wpcc_default_cntip"];
            }
            if (isset($settings["wpcc_default_twtip"])) {
                $current_options["twtip"] = $settings["wpcc_default_twtip"];
            }
            if (isset($settings["wpcc_default_hktip"])) {
                $current_options["hktip"] = $settings["wpcc_default_hktip"];
            }
            if (isset($settings["wpcc_default_hanstip"])) {
                $current_options["hanstip"] = $settings["wpcc_default_hanstip"];
            }
            if (isset($settings["wpcc_default_hanttip"])) {
                $current_options["hanttip"] = $settings["wpcc_default_hanttip"];
            }
            if (isset($settings["wpcc_default_sgtip"])) {
                $current_options["sgtip"] = $settings["wpcc_default_sgtip"];
            }
            if (isset($settings["wpcc_default_jptip"])) {
                $current_options["jptip"] = $settings["wpcc_default_jptip"];
            }
            if (isset($settings["wpcc_default_nctip"])) {
                $current_options["nctip"] = $settings["wpcc_default_nctip"];
            }
            if (isset($settings["wpcc_default_flag_option"])) {
                $current_options["wpcc_flag_option"] =
                    $settings["wpcc_default_flag_option"];
            }
            if (isset($settings["wpcc_default_enable_extended_langs"])) {
                $current_options["wpcc_show_more_langs"] =
                    $settings["wpcc_default_enable_extended_langs"];
            }
            if (isset($settings["wpcc_default_engine"])) {
                $current_options["wpcc_engine"] =
                    $settings["wpcc_default_engine"];
            }
            if (isset($settings["wpcc_default_search_conversion"])) {
                $current_options["wpcc_search_conversion"] =
                    $settings["wpcc_default_search_conversion"];
            }
            if (isset($settings["wpcc_default_use_fullpage_conversion"])) {
                $current_options["wpcc_use_fullpage_conversion"] =
                    $settings["wpcc_default_use_fullpage_conversion"];
            }
            if (isset($settings["wpcc_default_enable_hreflang_tags"])) {
                $current_options["wpcc_enable_hreflang_tags"] =
                    $settings["wpcc_default_enable_hreflang_tags"];
            }
            if (isset($settings["wpcc_default_enable_schema_conversion"])) {
                $current_options["wpcc_enable_schema_conversion"] =
                    $settings["wpcc_default_enable_schema_conversion"];
            }
            if (isset($settings["wpcc_default_enable_meta_conversion"])) {
                $current_options["wpcc_enable_meta_conversion"] =
                    $settings["wpcc_default_enable_meta_conversion"];
            }
            if (isset($settings["wpcc_default_no_conversion_qtag"])) {
                $current_options["wpcc_no_conversion_qtag"] =
                    $settings["wpcc_default_no_conversion_qtag"];
            }
            if (isset($settings["wpcc_default_enable_post_conversion"])) {
                $current_options["wpcc_enable_post_conversion"] =
                    $settings["wpcc_default_enable_post_conversion"];
            }
            if (isset($settings["wpcc_default_post_conversion_target"])) {
                $current_options["wpcc_post_conversion_target"] =
                    $settings["wpcc_default_post_conversion_target"];
            }
            if (isset($settings["wpcc_default_use_permalink"])) {
                $current_options["wpcc_use_permalink"] =
                    $settings["wpcc_default_use_permalink"];
            }
            if (isset($settings["wpcc_default_sitemap_post_type"])) {
                $current_options["wpcco_sitemap_post_type"] =
                    $settings["wpcc_default_sitemap_post_type"];
            }
            if (isset($settings["wpcc_default_use_sitemap"])) {
                $current_options["wpcco_use_sitemap"] =
                    $settings["wpcc_default_use_sitemap"];
            }
            if (isset($settings["wpcc_default_browser_redirect"])) {
                $current_options["wpcc_browser_redirect"] =
                    $settings["wpcc_default_browser_redirect"];
            }
            if (isset($settings["wpcc_default_use_cookie_variant"])) {
                $current_options["wpcc_use_cookie_variant"] =
                    $settings["wpcc_default_use_cookie_variant"];
            }
            if (isset($settings["wpcc_default_first_visit_default"])) {
                $current_options["wpcc_first_visit_default"] =
                    $settings["wpcc_default_first_visit_default"] ? 1 : 0;
            }
            if (isset($settings["wpcc_default_auto_language_recong"])) {
                $current_options["wpcc_auto_language_recong"] =
                    $settings["wpcc_default_auto_language_recong"];
            }
            if (isset($settings["wpcc_default_no_conversion_tag"])) {
                $current_options["wpcc_no_conversion_tag"] =
                    $settings["wpcc_default_no_conversion_tag"];
            }
            if (isset($settings["wpcc_default_no_conversion_ja"])) {
                $current_options["wpcc_no_conversion_ja"] =
                    $settings["wpcc_default_no_conversion_ja"];
            }
            if (isset($settings["wpcc_default_hreflang_x_default"])) {
                $current_options["wpcc_hreflang_x_default"] =
                    $settings["wpcc_default_hreflang_x_default"];
            }
            if (isset($settings["wpcc_default_enable_cache_addon"])) {
                $current_options["wpcc_enable_cache_addon"] =
                    $settings["wpcc_default_enable_cache_addon"];
            }
            if (isset($settings["wpcc_default_enable_network_module"])) {
                $current_options["wpcc_enable_network_module"] =
                    $settings["wpcc_default_enable_network_module"];
            }

            update_option('wpcc_options', $current_options);

            // 若子站启用了插件的固定链接模式，但 WP 仍是默认朴素结构，则回填网络主站的固定链接结构，避免 404
            $site_permastruct = (string) get_option('permalink_structure', '');
            $wpcc_use_permalink = isset($current_options['wpcc_use_permalink']) ? (int) $current_options['wpcc_use_permalink'] : 0;
            if ($wpcc_use_permalink !== 0 && empty($site_permastruct) && !empty($network_permalink_structure)) {
                update_option('permalink_structure', $network_permalink_structure);
            }

            // 确保在网络同步后，为每个子站自动刷新重写规则，避免新语言路径 404
            if (function_exists('flush_rewrite_rules')) {
                // 将子站的最新选项注入全局，供重写规则过滤器使用
                global $wpcc_options, $wp_rewrite;
                $wpcc_options = $current_options;

                if (!has_filter('rewrite_rules_array', 'wpcc_rewrite_rules')) {
                    add_filter('rewrite_rules_array', 'wpcc_rewrite_rules');
                }

                // 重新初始化并强制刷新（hard）
                if (isset($wp_rewrite) && is_object($wp_rewrite)) {
                    $wp_rewrite->init();
                }
                flush_rewrite_rules(true);
            }

            restore_current_blog();
        }
    }

    public static function bulk_reset_sites()
    {
        if (!current_user_can("manage_network_options")) {
            wp_die("您没有足够权限执行此操作。");
            return;
        }

        check_admin_referer("wpcc_bulk_reset_sites");

        if (!isset($_POST["wpcc_bulk_reset_confirm"])) {
            wp_die("请确认批量重置操作。");
            return;
        }

        $sites = get_sites();
        $reset_count = 0;

        foreach ($sites as $site) {
            switch_to_blog($site->blog_id);

            // 删除站点的 WPCC 设置
            $deleted = delete_option("wpcc_options");
            if ($deleted) {
                $reset_count++;
            }

            restore_current_blog();
        }

        $redirect_url = add_query_arg(
            [
                "page" => "wpcc-network",
                "tab" => "tools",
                "bulk_reset" => "true",
                "reset_count" => $reset_count,
            ],
            network_admin_url("settings.php"),
        );

        wp_redirect($redirect_url);
        exit();
    }
}
