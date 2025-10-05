<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * WPCC Presets
 * 
 * 提供一组可快速应用的预设配置，便于用户初始化或重置插件设置
 */
final class WPCC_Presets {

    /**
     * 获取所有预设
     */
    public static function get_presets(): array {
        // 统一的通用键，避免遗漏
        $base = [
            'wpcc_used_langs'               => ['zh-cn','zh-tw'],
            'cntip'                         => '简体',
            'twtip'                         => '繁体',
            'hktip'                         => '港澳',
            'hanstip'                       => '简体',
            'hanttip'                       => '繁体',
            'sgtip'                         => '马新',
            'jptip'                         => '日式',
            'wpcc_translate_type'           => 0,
            'wpcc_engine'                   => 'opencc',
            'wpcc_search_conversion'        => 1,
            'wpcc_use_fullpage_conversion'  => 0,
            'wpcc_no_conversion_qtag'       => 0,
            'wpcc_enable_post_conversion'   => 0,
            'wpcc_post_conversion_target'   => 'zh-cn',
            'wpcc_no_conversion_tag'        => 'pre,code,wp-block-code',
            'wpcc_no_conversion_ja'         => 1,
            'nctip'                         => '',
            'wpcc_browser_redirect'         => 0,
            'wpcc_auto_language_recong'     => 0,
            'wpcc_use_cookie_variant'       => 2,
            'wpcc_use_permalink'            => 0,
            'wpcco_use_sitemap'             => 0,
            'wpcco_sitemap_post_type'       => 'post,page',
            'wpcc_enable_cache_addon'       => 1,
            'wpcc_enable_network_module'    => 0,
            'wpcc_enable_hreflang_tags'     => 1,
            'wpcc_hreflang_x_default'       => 'zh-cn',
            'wpcc_enable_schema_conversion'  => 1,
            'wpcc_enable_meta_conversion'    => 1,
        ];

        return [
            // 恢复到插件的“工厂默认值”（更广覆盖的默认语言集与基础选项）
'factory_default' => [
                'label'   => __( '恢复默认', 'wp-chinese-converter' ),
                'options' => [
                    'wpcc_used_langs'               => ['zh-cn','zh-tw'],
                    'cntip'                         => '简体',
                    'twtip'                         => '繁体',
                    'hktip'                         => '港澳',
                    'hanstip'                       => '简体',
                    'hanttip'                       => '繁体',
                    'sgtip'                         => '马新',
                    'jptip'                         => '日式',

                    'wpcc_engine'                   => 'mediawiki',
                    'wpcc_search_conversion'        => 1,

                    'wpcc_browser_redirect'         => 0,
                    'wpcc_auto_language_recong'     => 0,
                    'wpcc_use_cookie_variant'       => 1,

                    'wpcc_use_fullpage_conversion'  => 0,

'wpcc_no_conversion_tag'        => '',
                    'wpcc_no_conversion_ja'         => 0,
                    'wpcc_no_conversion_qtag'       => 0,

                    'wpcc_enable_post_conversion'   => 0,
                    'wpcc_post_conversion_target'   => 'zh-cn',

                    'wpcc_use_permalink'            => 0,
                    'wpcco_use_sitemap'             => 0,
                    'wpcco_sitemap_post_type'       => 'post,page',

                    'wpcc_enable_hreflang_tags'     => 1,
                    'wpcc_hreflang_x_default'       => 'zh-cn',
                    'wpcc_enable_schema_conversion'  => 1,
                    'wpcc_enable_meta_conversion'    => 1,

                    'wpcc_enable_cache_addon'       => 1,
                    'wpcc_enable_network_module'    => 0,

                    'wpcc_translate_type'           => 0,
                    'nctip'                         => '',
                    'wpcc_flag_option'              => 1,
                    'wpcc_trackback_plugin_author'  => 0,
                    'wpcc_add_author_link'          => 0,
                ],
            ],

            // 内容/资讯站
            'content_site' => [
                'label'   => __( '内容站', 'wp-chinese-converter' ),
                'options' => array_merge( $base, [
                    'wpcc_engine'                  => 'opencc',
                    'wpcc_search_conversion'       => 2,
                    'wpcco_use_sitemap'            => 1,
                    'wpcc_use_fullpage_conversion' => 0,
                    'wpcc_used_langs'              => ['zh-cn','zh-tw'],
                ]),
            ],

            // 企业站
            'corporate' => [
                'label'   => __( '企业站', 'wp-chinese-converter' ),
                'options' => array_merge( $base, [
                    'wpcc_engine'                  => 'mediawiki',
                    'wpcc_search_conversion'       => 0,
                    'wpcco_use_sitemap'            => 1,
                    'wpcc_used_langs'              => ['zh-cn','zh-tw'],
                ]),
            ],

            // 技术文档/知识库
            'docs' => [
                'label'   => __( '技术文档', 'wp-chinese-converter' ),
                'options' => array_merge( $base, [
                    'wpcc_engine'                  => 'opencc',
                    'wpcc_search_conversion'       => 1,
                    'wpcc_use_fullpage_conversion' => 0,
                    'wpcc_no_conversion_tag'       => 'pre,code,wp-block-code,kbd,samp',
                    'wpcc_no_conversion_ja'        => 1,
                    'wpcc_used_langs'              => ['zh-cn','zh-tw'],
                ]),
            ],

            // 高性能/兼容优先
            'performance' => [
                'label'   => __( '高性能', 'wp-chinese-converter' ),
                'options' => array_merge( $base, [
                    'wpcc_engine'                  => 'mediawiki',
                    'wpcc_search_conversion'       => 0,
                    'wpcc_use_fullpage_conversion' => 0,
                    'wpcc_used_langs'              => ['zh-cn','zh-tw'],
                ]),
            ],
        ];
    }

    /**
     * 应用预设
     */
    public static function apply_preset( string $slug ): bool {
        $presets = self::get_presets();
        if ( ! isset( $presets[ $slug ] ) ) {
            return false;
        }

        $new = $presets[ $slug ]['options'];
        $current = get_wpcc_option( 'wpcc_options', [] );
        if ( ! is_array( $current ) ) {
            $current = [];
        }

        // 合并：仅覆盖预设中出现的键，其他用户自定义保持不变
        $merged = array_merge( $current, $new );

        $before_permalink = isset( $current['wpcc_use_permalink'] ) ? intval( $current['wpcc_use_permalink'] ) : 0;
        $after_permalink  = isset( $merged['wpcc_use_permalink'] ) ? intval( $merged['wpcc_use_permalink'] ) : 0;

        $ok = update_wpcc_option( 'wpcc_options', $merged );

        // 若固定链接设定发生变化，则刷新重写规则
        if ( $before_permalink !== $after_permalink ) {
            if ( function_exists( 'flush_rewrite_rules' ) ) {
                flush_rewrite_rules();
            }
        }

        return (bool) $ok;
    }

    /**
     * 获取可用于UI的预设清单（slug => label）
     */
    public static function get_preset_labels(): array {
        $labels = [];
        foreach ( self::get_presets() as $slug => $preset ) {
            $labels[ $slug ] = $preset['label'] ?? $slug;
        }
        return $labels;
    }
}
