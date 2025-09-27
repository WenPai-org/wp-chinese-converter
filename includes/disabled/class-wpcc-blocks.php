<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPCC_Blocks {

	public function __construct() {
		add_action( 'init', array( $this, 'register_blocks' ), 20 );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_block_editor_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
	}

	public function register_blocks() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		wp_register_script(
			'wpcc-blocks',
			plugin_dir_url( dirname( dirname( __DIR__ ) ) . '/wp-chinese-converter.php' ) . 'assets/js/gudengbao.js',
			array( 'wp-blocks', 'wp-element', 'wp-i18n' ),
			'2.0.0',
			true
		);

		register_block_type( 'wpcc/language-switcher', array(
			'editor_script' => 'wpcc-blocks',
			'render_callback' => array( $this, 'render_language_switcher' )
		) );

		register_block_type( 'wpcc/conversion-indicator', array(
			'editor_script' => 'wpcc-blocks',
			'render_callback' => array( $this, 'render_conversion_indicator' )
		) );
	}

	public function enqueue_block_editor_assets() {
		wp_set_script_translations( 'wpcc-blocks', 'wp-chinese-converter' );
	}

	public function enqueue_frontend_assets() {
		wp_enqueue_style(
			'wpcc-blocks-frontend',
			plugin_dir_url( dirname( dirname( __DIR__ ) ) . '/wp-chinese-converter.php' ) . 'assets/css/blocks.css',
			array(),
			'2.0.0'
		);
	}

	public function register_rest_routes() {
		register_rest_route( 'wpcc/v1', '/preview-switcher', array(
			'methods' => 'GET',
			'callback' => array( $this, 'get_switcher_preview' ),
			'permission_callback' => function() {
				return current_user_can( 'edit_posts' );
			}
		) );
	}

	public function get_switcher_preview( $request ) {
		$params = $request->get_params();
		
		$attributes = array(
			'displayStyle' => sanitize_text_field( $params['style'] ?? 'horizontal' ),
			'showFlags' => (bool) ( $params['show_flags'] ?? false ),
			'showLabels' => (bool) ( $params['show_labels'] ?? true ),
			'buttonStyle' => sanitize_text_field( $params['button_style'] ?? 'default' ),
			'fontSize' => intval( $params['font_size'] ?? 14 ),
			'textColor' => sanitize_hex_color( $params['text_color'] ?? '#333333' ),
			'backgroundColor' => sanitize_hex_color( $params['bg_color'] ?? '#ffffff' ),
			'borderRadius' => intval( $params['border_radius'] ?? 4 ),
			'spacing' => intval( $params['spacing'] ?? 8 ),
			'alignment' => sanitize_text_field( $params['alignment'] ?? 'left' )
		);

		return $this->render_language_switcher( $attributes );
	}

	public function render_language_switcher( $attributes ) {
		global $wpcc_options, $wpcc_langs, $wpcc_target_lang, $wpcc_langs_urls, $wpcc_noconversion_url;

		if ( ! function_exists( 'set_wpcc_langs_urls' ) ) {
			return '<div class="wpcc-error">语言切换功能未初始化</div>';
		}

		if ( function_exists( 'wpcc_init_languages' ) ) {
			wpcc_init_languages();
		}

		if ( empty( $wpcc_langs ) ) {
			return '<div class="wpcc-error">语言配置未加载</div>';
		}

		set_wpcc_langs_urls();

		$defaults = array(
			'displayStyle' => 'horizontal',
			'showFlags' => false,
			'showLabels' => true,
			'buttonStyle' => 'default',
			'fontSize' => 14,
			'textColor' => '#333333',
			'backgroundColor' => '#ffffff',
			'borderRadius' => 4,
			'spacing' => 8,
			'alignment' => 'left',
			'customCSS' => ''
		);

		$attributes = wp_parse_args( $attributes, $defaults );

		$wrapper_classes = array(
			'wpcc-language-switcher',
			'wpcc-style-' . $attributes['displayStyle'],
			'wpcc-button-' . $attributes['buttonStyle'],
			'wpcc-align-' . $attributes['alignment']
		);

		$wrapper_style = array(
			'font-size: ' . $attributes['fontSize'] . 'px',
			'color: ' . $attributes['textColor'],
			'gap: ' . $attributes['spacing'] . 'px'
		);

		if ( $attributes['displayStyle'] === 'dropdown' ) {
			return $this->render_dropdown_switcher( $attributes, $wrapper_classes, $wrapper_style );
		}

		$output = '<div class="' . esc_attr( implode( ' ', $wrapper_classes ) ) . '" style="' . esc_attr( implode( '; ', $wrapper_style ) ) . '">';

		if ( $attributes['customCSS'] ) {
			$output .= '<style>' . wp_strip_all_tags( $attributes['customCSS'] ) . '</style>';
		}

		$current_url = $wpcc_noconversion_url ?: home_url();
		$no_convert_tip = isset( $wpcc_options['nctip'] ) ? $wpcc_options['nctip'] : '不转换';

		$output .= '<a href="' . esc_url( $current_url ) . '" class="wpcc-lang-link' . ( ! $wpcc_target_lang ? ' wpcc-current' : '' ) . '" data-lang="">';
		if ( $attributes['showFlags'] ) {
			$output .= '<span class="wpcc-flag">🏳️</span>';
		}
		if ( $attributes['showLabels'] ) {
			$output .= '<span class="wpcc-label">' . esc_html( $no_convert_tip ) . '</span>';
		}
		$output .= '</a>';

		if ( isset( $wpcc_langs_urls ) && is_array( $wpcc_langs_urls ) ) {
			foreach ( $wpcc_langs_urls as $lang_code => $lang_url ) {
				if ( ! isset( $wpcc_langs[ $lang_code ] ) ) {
					continue;
				}

				$lang_info = $wpcc_langs[ $lang_code ];
				$lang_name = ( isset( $wpcc_options[ $lang_info[1] ] ) && ! empty( $wpcc_options[ $lang_info[1] ] ) ) ? $wpcc_options[ $lang_info[1] ] : $lang_info[2];

				$output .= '<a href="' . esc_url( $lang_url ) . '" class="wpcc-lang-link' . ( $wpcc_target_lang === $lang_code ? ' wpcc-current' : '' ) . '" data-lang="' . esc_attr( $lang_code ) . '">';
				
				if ( $attributes['showFlags'] ) {
					$flag = $this->get_language_flag( $lang_code );
					$output .= '<span class="wpcc-flag">' . $flag . '</span>';
				}
				
				if ( $attributes['showLabels'] ) {
					$output .= '<span class="wpcc-label">' . esc_html( $lang_name ) . '</span>';
				}
				
				$output .= '</a>';
			}
		}

		$output .= '</div>';

		return $output;
	}

	private function render_dropdown_switcher( $attributes, $wrapper_classes, $wrapper_style ) {
		global $wpcc_options, $wpcc_langs, $wpcc_target_lang, $wpcc_langs_urls, $wpcc_noconversion_url;

		$current_lang_name = '不转换';
		if ( $wpcc_target_lang && isset( $wpcc_langs[ $wpcc_target_lang ] ) ) {
			$lang_info = $wpcc_langs[ $wpcc_target_lang ];
			$current_lang_name = ( isset( $wpcc_options[ $lang_info[1] ] ) && ! empty( $wpcc_options[ $lang_info[1] ] ) ) ? $wpcc_options[ $lang_info[1] ] : $lang_info[2];
		}

		$output = '<div class="' . esc_attr( implode( ' ', $wrapper_classes ) ) . '" style="' . esc_attr( implode( '; ', $wrapper_style ) ) . '">';
		$output .= '<select class="wpcc-dropdown" onchange="window.location.href=this.value">';
		
		$output .= '<option value="' . esc_url( $wpcc_noconversion_url ) . '"' . ( ! $wpcc_target_lang ? ' selected' : '' ) . '>';
		$output .= esc_html( isset( $wpcc_options['nctip'] ) ? $wpcc_options['nctip'] : '不转换' );
		$output .= '</option>';

		if ( isset( $wpcc_langs_urls ) && is_array( $wpcc_langs_urls ) ) {
			foreach ( $wpcc_langs_urls as $lang_code => $lang_url ) {
				if ( ! isset( $wpcc_langs[ $lang_code ] ) ) {
					continue;
				}

				$lang_info = $wpcc_langs[ $lang_code ];
				$lang_name = ( isset( $wpcc_options[ $lang_info[1] ] ) && ! empty( $wpcc_options[ $lang_info[1] ] ) ) ? $wpcc_options[ $lang_info[1] ] : $lang_info[2];

				$output .= '<option value="' . esc_url( $lang_url ) . '"' . ( $wpcc_target_lang === $lang_code ? ' selected' : '' ) . '>';
				$output .= esc_html( $lang_name );
				$output .= '</option>';
			}
		}

		$output .= '</select></div>';

		return $output;
	}

	public function render_conversion_indicator( $attributes ) {
		global $wpcc_target_lang, $wpcc_langs, $wpcc_options;
		
		if ( function_exists( 'wpcc_init_languages' ) ) {
			wpcc_init_languages();
		}

		$defaults = array(
			'showIcon' => true,
			'showText' => true,
			'position' => 'inline'
		);

		$attributes = wp_parse_args( $attributes, $defaults );

		$wrapper_classes = array(
			'wpcc-conversion-indicator',
			'wpcc-position-' . $attributes['position']
		);

		$output = '<div class="' . esc_attr( implode( ' ', $wrapper_classes ) ) . '">';

		if ( $attributes['showIcon'] ) {
			$icon = $wpcc_target_lang ? '🔄' : '📝';
			$output .= '<span class="wpcc-indicator-icon">' . $icon . '</span>';
		}

		if ( $attributes['showText'] ) {
			$text = '不转换';
			if ( $wpcc_target_lang && isset( $wpcc_langs[ $wpcc_target_lang ] ) ) {
				$lang_info = $wpcc_langs[ $wpcc_target_lang ];
				$text = ( isset( $wpcc_options[ $lang_info[1] ] ) && ! empty( $wpcc_options[ $lang_info[1] ] ) ) ? $wpcc_options[ $lang_info[1] ] : $lang_info[2];
			}
			$output .= '<span class="wpcc-indicator-text">' . esc_html( $text ) . '</span>';
		}

		$output .= '</div>';

		return $output;
	}

	private function get_language_flag( $lang_code ) {
		$flags = array(
			'zh-cn' => '🇨🇳',
			'zh-tw' => '🇹🇼',
			'zh-hk' => '🇭🇰',
			'zh-sg' => '🇸🇬'
		);

		return $flags[ $lang_code ] ?? '🏳️';
	}
}