<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPCC_Blocks {
    
    public function __construct() {
        add_action( 'init', array( $this, 'register_blocks' ) );
        add_action( 'init', array( $this, 'register_block_styles' ) );
        add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_block_editor_assets' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
    }

    public function register_block_styles() {
        // 在 init 阶段注册样式句柄，供 block.json 中的 editorStyle 和 style 字段使用
        wp_register_style(
            'wpcc-blocks-editor',
            plugins_url( 'assets/css/blocks-editor.css', dirname( dirname( __FILE__ ) ) ),
            array(),
            wpcc_VERSION
        );

        wp_register_style(
            'wpcc-blocks-frontend',
            plugins_url( 'assets/css/blocks-frontend.css', dirname( dirname( __FILE__ ) ) ),
            array(),
            wpcc_VERSION
        );
    }

    public function register_blocks() {
        $blocks = array(
            'language-switcher',
            'conversion-status',
            'no-conversion'
        );

        foreach ( $blocks as $block ) {
            $this->register_single_block( $block );
        }
    }

    private function register_single_block( $block_name ) {
        $block_path = plugin_dir_path( __FILE__ ) . 'build/' . $block_name;

        // 更符合规范的做法：检测 block.json 是否存在
        if ( file_exists( $block_path . '/block.json' ) ) {
            register_block_type( $block_path );
        }
    }
    
    public function enqueue_block_editor_assets() {
        // 确保wpcc-variant脚本已加载
        if ( ! wp_script_is( 'wpcc-variant', 'registered' ) ) {
            wp_register_script( 'wpcc-variant', plugins_url( 'assets/dist/wpcc-variant.umd.js', dirname( dirname( __FILE__ ) ) ), array(), wpcc_VERSION );
        }

        // 样式已在 init 阶段注册，这里只需要加载即可
        wp_enqueue_style( 'wpcc-blocks-editor' );

        // 兼容层：为编辑器注册旧占位文本的 deprecated 版本，避免校验失败
        wp_enqueue_script(
            'wpcc-block-compat',
            plugins_url( 'assets/js/wpcc-block-compat.js', dirname( dirname( __FILE__ ) ) ),
            array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-hooks' ),
wpcc_VERSION,
            true
        );

        global $wpcc_options;
        $enabled_languages = $wpcc_options['wpcc_used_langs'] ?? array();

        wp_localize_script(
            'wpcc-block-compat',
            'wpccBlockSettings',
            array(
                'enabledLanguages' => $enabled_languages,
                'languageLabels' => array(
                    'zh-cn' => $wpcc_options['cntip'] ?? '简体',
                    'zh-tw' => $wpcc_options['twtip'] ?? '繁体',
                    'zh-hk' => $wpcc_options['hktip'] ?? '港澳',
                    'zh-hans' => $wpcc_options['hanstip'] ?? '简体',
                    'zh-hant' => $wpcc_options['hanttip'] ?? '繁体',
                    'zh-sg' => $wpcc_options['sgtip'] ?? '马新',
                    'zh-jp' => $wpcc_options['jptip'] ?? '日式'
                ),
                'noConversionLabel' => $wpcc_options['nctip'] ?? '不转换',
                'strings' => array(
                    'noConversionLabel' => $wpcc_options['nctip'] ?? __('不转换', 'wp-chinese-converter'),
                    'currentLanguagePrefix' => __('当前语言：', 'wp-chinese-converter'),
                    'languageSelectLabel' => __('选择语言', 'wp-chinese-converter')
                )
            )
        );
    }
    
    public function enqueue_frontend_assets() {
        // 确保wpcc-variant脚本已加载
        if ( ! wp_script_is( 'wpcc-variant', 'registered' ) ) {
            wp_register_script( 'wpcc-variant', plugins_url( 'assets/dist/wpcc-variant.umd.js', dirname( dirname( __FILE__ ) ) ), array(), wpcc_VERSION );
        }

        // 为前端状态指示器的 dashicons 图标提供样式支持
        wp_enqueue_style( 'dashicons' );

        // 样式已在 init 阶段注册，这里只需要加载即可
        wp_enqueue_style( 'wpcc-blocks-frontend' );

        // 使用文件修改时间作为版本号，避免浏览器缓存导致逻辑不更新
        $blocks_front_js = plugin_dir_path( dirname( __FILE__ ) ) . 'assets/js/blocks-frontend.js';
        $blocks_front_ver = file_exists( $blocks_front_js ) ? filemtime( $blocks_front_js ) : wpcc_VERSION;
        wp_enqueue_script(
            'wpcc-blocks-frontend',
            plugins_url( 'assets/js/blocks-frontend.js', dirname( dirname( __FILE__ ) ) ),
            array( 'wpcc-variant' ),
            $blocks_front_ver,
            true
        );
        
        global $wpcc_options;
        $enabled_languages = $wpcc_options['wpcc_used_langs'] ?? array();
        
        wp_localize_script(
            'wpcc-blocks-frontend',
            'wpccFrontendSettings',
            array(
                'enabledLanguages' => $enabled_languages,
                'languageLabels' => array(
                    'zh-cn' => $wpcc_options['cntip'] ?? '简体',
                    'zh-tw' => $wpcc_options['twtip'] ?? '繁体',
                    'zh-hk' => $wpcc_options['hktip'] ?? '港澳',
                    'zh-hans' => $wpcc_options['hanstip'] ?? '简体',
                    'zh-hant' => $wpcc_options['hanttip'] ?? '繁体',
                    'zh-sg' => $wpcc_options['sgtip'] ?? '马新',
                    'zh-jp' => $wpcc_options['jptip'] ?? '日式'
                ),
                'noConversionLabel' => $wpcc_options['nctip'] ?? '不转换',
                'strings' => array(
                    'noConversionLabel' => $wpcc_options['nctip'] ?? __('不转换', 'wp-chinese-converter'),
                    'currentLanguagePrefix' => __('当前语言：', 'wp-chinese-converter'),
                    'languageSelectLabel' => __('选择语言', 'wp-chinese-converter')
                )
            )
        );
    }
}

new WPCC_Blocks();