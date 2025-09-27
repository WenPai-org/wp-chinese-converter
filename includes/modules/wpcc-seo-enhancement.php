<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once dirname( dirname( __FILE__ ) ) . '/core/abstract-module.php';

class WPCC_SEO_Enhancement extends WPCC_Abstract_Module {
	
	private $seo_plugins = array();
	private $active_integrations = array();
	
	public function init() {
		$this->name = 'SEO Enhancement';
		$this->version = '1.0.0';
		$this->description = 'SEO插件增强模块，支持Yoast SEO、RankMath、AIOSEO等主流SEO插件';
		$this->dependencies = array();
		
		$this->settings = array(
			'enable_yoast_integration' => true,
			'enable_rankmath_integration' => true,
			'enable_aioseo_integration' => true,
			'enable_hreflang_tags' => true,
			'enable_schema_conversion' => true,
			'enable_meta_conversion' => true,
			'hreflang_x_default' => 'zh-cn'
		);
		
		if ( $this->is_enabled() ) {
			add_action( 'init', array( $this, 'detect_seo_plugins' ), 5 );
			add_action( 'wp_head', array( $this, 'output_hreflang_tags' ), 1 );
			add_action( 'wp_head', array( $this, 'setup_seo_integrations' ), 2 );
		}
	}
	
	public function detect_seo_plugins() {
		$yoast_active = class_exists( 'WPSEO_Options' ) || class_exists( 'WPSEO_Frontend' );
		$rankmath_active = class_exists( 'RankMath' ) || function_exists( 'rank_math' );
		$aioseo_active = class_exists( 'AIOSEO\Plugin\AIOSEO' ) || function_exists( 'aioseo' );
		
		$this->seo_plugins = array(
			'yoast' => array(
				'active' => $yoast_active,
				'name' => 'Yoast SEO',
				'version' => $this->get_yoast_version()
			),
			'rankmath' => array(
				'active' => $rankmath_active,
				'name' => 'RankMath',
				'version' => $this->get_rankmath_version()
			),
			'aioseo' => array(
				'active' => $aioseo_active,
				'name' => 'All in One SEO',
				'version' => $this->get_aioseo_version()
			)
		);
		
		do_action( 'wpcc_seo_plugins_detected', $this->seo_plugins );
	}
	
	public function setup_seo_integrations() {
		global $wpcc_options;
		
		foreach ( $this->seo_plugins as $plugin => $info ) {
			if ( ! $info['active'] ) {
				continue;
			}
			
			$setting_key = 'wpcc_enable_' . $plugin . '_integration';
			$enabled = isset( $wpcc_options[ $setting_key ] ) ? $wpcc_options[ $setting_key ] : 1;
			
			if ( ! $enabled ) {
				continue;
			}
			
			switch ( $plugin ) {
				case 'yoast':
					$this->setup_yoast_integration();
					break;
				case 'rankmath':
					$this->setup_rankmath_integration();
					break;
				case 'aioseo':
					$this->setup_aioseo_integration();
					break;
			}
			
			$this->active_integrations[] = $plugin;
		}
		
		$this->sync_global_settings();
	}
	
	private function sync_global_settings() {
		global $wpcc_options;
		
		if ( ! empty( $wpcc_options ) ) {
			$this->settings['enable_hreflang_tags'] = isset( $wpcc_options['wpcc_enable_hreflang_tags'] ) ? $wpcc_options['wpcc_enable_hreflang_tags'] : 1;
			$this->settings['enable_schema_conversion'] = isset( $wpcc_options['wpcc_enable_schema_conversion'] ) ? $wpcc_options['wpcc_enable_schema_conversion'] : 1;
			$this->settings['enable_meta_conversion'] = isset( $wpcc_options['wpcc_enable_meta_conversion'] ) ? $wpcc_options['wpcc_enable_meta_conversion'] : 1;
			$this->settings['hreflang_x_default'] = isset( $wpcc_options['wpcc_hreflang_x_default'] ) ? $wpcc_options['wpcc_hreflang_x_default'] : 'zh-cn';
		}
	}
	
	private function setup_yoast_integration() {
		add_filter( 'wpseo_title', array( $this, 'convert_seo_title' ), 10, 1 );
		add_filter( 'wpseo_metadesc', array( $this, 'convert_meta_description' ), 10, 1 );
		add_filter( 'wpseo_opengraph_title', array( $this, 'convert_seo_title' ), 10, 1 );
		add_filter( 'wpseo_opengraph_desc', array( $this, 'convert_meta_description' ), 10, 1 );
		add_filter( 'wpseo_twitter_title', array( $this, 'convert_seo_title' ), 10, 1 );
		add_filter( 'wpseo_twitter_description', array( $this, 'convert_meta_description' ), 10, 1 );
		
		if ( $this->settings['enable_schema_conversion'] ) {
			add_filter( 'wpseo_schema_graph', array( $this, 'convert_schema_data' ), 10, 1 );
		}
	}
	
	private function setup_rankmath_integration() {
		add_filter( 'rank_math/frontend/title', array( $this, 'convert_seo_title' ), 10, 1 );
		add_filter( 'rank_math/frontend/description', array( $this, 'convert_meta_description' ), 10, 1 );
		add_filter( 'rank_math/opengraph/facebook/title', array( $this, 'convert_seo_title' ), 10, 1 );
		add_filter( 'rank_math/opengraph/facebook/description', array( $this, 'convert_meta_description' ), 10, 1 );
		add_filter( 'rank_math/opengraph/twitter/title', array( $this, 'convert_seo_title' ), 10, 1 );
		add_filter( 'rank_math/opengraph/twitter/description', array( $this, 'convert_meta_description' ), 10, 1 );
		
		if ( $this->settings['enable_schema_conversion'] ) {
			add_filter( 'rank_math/json_ld', array( $this, 'convert_schema_data' ), 10, 1 );
		}
	}
	
	private function setup_aioseo_integration() {
		add_filter( 'aioseo_title', array( $this, 'convert_seo_title' ), 10, 1 );
		add_filter( 'aioseo_description', array( $this, 'convert_meta_description' ), 10, 1 );
		add_filter( 'aioseo_facebook_title', array( $this, 'convert_seo_title' ), 10, 1 );
		add_filter( 'aioseo_facebook_description', array( $this, 'convert_meta_description' ), 10, 1 );
		add_filter( 'aioseo_twitter_title', array( $this, 'convert_seo_title' ), 10, 1 );
		add_filter( 'aioseo_twitter_description', array( $this, 'convert_meta_description' ), 10, 1 );
	}
	
	public function convert_seo_title( $title ) {
		if ( ! $this->settings['enable_meta_conversion'] || empty( $title ) ) {
			return $title;
		}
		
		return $this->convert_text( $title );
	}
	
	public function convert_meta_description( $description ) {
		if ( ! $this->settings['enable_meta_conversion'] || empty( $description ) ) {
			return $description;
		}
		
		return $this->convert_text( $description );
	}
	
	public function convert_schema_data( $data ) {
		if ( ! $this->settings['enable_schema_conversion'] || empty( $data ) ) {
			return $data;
		}
		
		return $this->convert_schema_recursive( $data );
	}
	
	private function convert_schema_recursive( $data ) {
		if ( is_string( $data ) ) {
			return $this->convert_text( $data );
		}
		
		if ( is_array( $data ) ) {
			foreach ( $data as $key => $value ) {
				$data[ $key ] = $this->convert_schema_recursive( $value );
			}
		}
		
		if ( is_object( $data ) ) {
			foreach ( $data as $key => $value ) {
				$data->$key = $this->convert_schema_recursive( $value );
			}
		}
		
		return $data;
	}
	
	private function convert_text( $text ) {
		if ( empty( $text ) || ! function_exists( 'zhconversion' ) ) {
			return $text;
		}
		
		global $wpcc_target_lang;
		
		if ( empty( $wpcc_target_lang ) ) {
			$wpcc_target_lang = $this->get_current_language();
		}
		
		if ( $wpcc_target_lang === 'zh-cn' || $wpcc_target_lang === 'zh' ) {
			return $text;
		}
		
		return zhconversion( $text, $wpcc_target_lang );
	}
	
	private function get_current_language() {
		if ( isset( $_GET['variant'] ) ) {
			return sanitize_text_field( $_GET['variant'] );
		}
		
		global $wpcc_options;
		if ( ! empty( $wpcc_options['wpcc_used_langs'] ) ) {
			return $wpcc_options['wpcc_used_langs'][0];
		}
		
		return 'zh-cn';
	}
	
	public function output_hreflang_tags() {
		if ( ! $this->settings['enable_hreflang_tags'] ) {
			return;
		}
		
		global $wpcc_options;
		
		if ( empty( $wpcc_options['wpcc_used_langs'] ) || ! is_array( $wpcc_options['wpcc_used_langs'] ) ) {
			return;
		}
		
		if ( count( $wpcc_options['wpcc_used_langs'] ) < 2 ) {
			return;
		}
		
		$current_url = $this->get_current_url();
		if ( empty( $current_url ) ) {
			return;
		}
		
		$languages = $wpcc_options['wpcc_used_langs'];
		
		foreach ( $languages as $lang ) {
			if ( empty( $lang ) ) {
				continue;
			}
			
			$lang_url = $this->get_language_url( $current_url, $lang );
			$hreflang = $this->get_hreflang_code( $lang );
			
			if ( ! empty( $lang_url ) && ! empty( $hreflang ) ) {
				echo '<link rel="alternate" hreflang="' . esc_attr( $hreflang ) . '" href="' . esc_url( $lang_url ) . '" />' . "\n";
			}
		}
		
		$default_lang = $this->settings['hreflang_x_default'];
		if ( ! empty( $default_lang ) ) {
			$default_url = $this->get_language_url( $current_url, $default_lang );
			if ( ! empty( $default_url ) ) {
				echo '<link rel="alternate" hreflang="x-default" href="' . esc_url( $default_url ) . '" />' . "\n";
			}
		}
	}
	
	private function get_current_url() {
		if ( is_admin() ) {
			return home_url();
		}
		
		global $wp;
		$current_url = home_url( $wp->request );
		
		if ( ! empty( $wp->query_string ) ) {
			$current_url .= '?' . $wp->query_string;
		}
		
		return trailingslashit( $current_url );
	}
	
	private function get_language_url( $url, $lang ) {
		global $wpcc_options;
		
		$clean_url = remove_query_arg( 'variant', $url );
		
		if ( empty( $wpcc_options['wpcc_use_permalink'] ) ) {
			return add_query_arg( 'variant', $lang, $clean_url );
		}
		
		$permalink_type = intval( $wpcc_options['wpcc_use_permalink'] );
		
		switch ( $permalink_type ) {
			case 1:
				$clean_url = rtrim( $clean_url, '/' );
				return $clean_url . '/' . $lang . '/';
			case 2:
				$parsed_url = parse_url( $clean_url );
				$base_url = $parsed_url['scheme'] . '://' . $parsed_url['host'];
				$path = isset( $parsed_url['path'] ) ? $parsed_url['path'] : '/';
				$query = isset( $parsed_url['query'] ) ? '?' . $parsed_url['query'] : '';
				return $base_url . '/' . $lang . $path . $query;
			default:
				return add_query_arg( 'variant', $lang, $clean_url );
		}
	}
	
	private function get_hreflang_code( $lang ) {
		$hreflang_map = array(
			'zh-cn' => 'zh-CN',
			'zh-tw' => 'zh-TW',
			'zh-hk' => 'zh-HK',
			'zh-sg' => 'zh-SG',
			'zh-hans' => 'zh-Hans',
			'zh-hant' => 'zh-Hant',
			'zh-jp' => 'zh'
		);
		
		return isset( $hreflang_map[ $lang ] ) ? $hreflang_map[ $lang ] : $lang;
	}
	
	public function get_seo_plugins_status() {
		return $this->seo_plugins;
	}
	
	public function get_active_integrations() {
		return $this->active_integrations;
	}
	
	public function get_seo_statistics() {
		$stats = array(
			'detected_plugins' => count( array_filter( $this->seo_plugins, function( $plugin ) {
				return $plugin['active'];
			} ) ),
			'active_integrations' => count( $this->active_integrations ),
			'hreflang_enabled' => $this->settings['enable_hreflang_tags'],
			'schema_conversion_enabled' => $this->settings['enable_schema_conversion'],
			'meta_conversion_enabled' => $this->settings['enable_meta_conversion']
		);
		
		return $stats;
	}
	
	private function get_yoast_version() {
		if ( defined( 'WPSEO_VERSION' ) ) {
			return constant( 'WPSEO_VERSION' );
		}
		return 'unknown';
	}
	
	private function get_rankmath_version() {
		if ( defined( 'RANK_MATH_VERSION' ) ) {
			return constant( 'RANK_MATH_VERSION' );
		}
		return 'unknown';
	}
	
	private function get_aioseo_version() {
		if ( defined( 'AIOSEO_VERSION' ) ) {
			return constant( 'AIOSEO_VERSION' );
		}
		return 'unknown';
	}
}