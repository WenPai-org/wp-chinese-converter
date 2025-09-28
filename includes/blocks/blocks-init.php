<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPCC_Blocks {
    
    public function __construct() {
        add_action( 'init', array( $this, 'register_blocks' ) );
        add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_block_editor_assets' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
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
        
        if ( file_exists( $block_path . '/index.js' ) ) {
            register_block_type( $block_path );
        }
    }
    
    public function enqueue_block_editor_assets() {
        wp_enqueue_style(
            'wpcc-blocks-editor',
            plugins_url( 'assets/css/blocks-editor.css', dirname( dirname( __FILE__ ) ) ),
            array(),
            '1.0.0'
        );
        
        global $wpcc_options;
        $enabled_languages = $wpcc_options['wpcc_used_langs'] ?? array();
        
        wp_localize_script(
            'wp-blocks',
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
                'noConversionLabel' => $wpcc_options['nctip'] ?? '不转换'
            )
        );
    }
    
    public function enqueue_frontend_assets() {
        wp_enqueue_style(
            'wpcc-blocks-frontend',
            plugins_url( 'assets/css/blocks-frontend.css', dirname( dirname( __FILE__ ) ) ),
            array(),
            '1.0.0'
        );
        
        wp_enqueue_script(
            'wpcc-blocks-frontend',
            plugins_url( 'assets/js/blocks-frontend.js', dirname( dirname( __FILE__ ) ) ),
            array( 'wpcc-variant' ),
            '1.0.0',
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
                'noConversionLabel' => $wpcc_options['nctip'] ?? '不转换'
            )
        );
    }
}

new WPCC_Blocks();