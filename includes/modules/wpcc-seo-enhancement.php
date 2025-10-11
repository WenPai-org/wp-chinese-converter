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
$this->version = '1.5';
		$this->description = 'SEO插件增强模块，支持Yoast SEO、RankMath、AIOSEO等主流SEO插件';
		$this->dependencies = array();
		
        $this->settings = array(
            'enable_yoast_integration' => true,
            'enable_rankmath_integration' => true,
            'enable_aioseo_integration' => true,
            'enable_hreflang_tags' => true,
            'enable_hreflang_x_default' => true,
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
            $this->settings['enable_hreflang_x_default'] = isset( $wpcc_options['wpcc_enable_hreflang_x_default'] ) ? $wpcc_options['wpcc_enable_hreflang_x_default'] : 1;
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
		// 防抖：确保仅输出一次，避免重复标签
		static $wpcc_hreflang_printed = false;
		if ( $wpcc_hreflang_printed ) {
			return;
		}
		$wpcc_hreflang_printed = true;
		
		// 确保在输出前同步一次全局设置（避免执行顺序导致读取到默认值）
		$this->sync_global_settings();
		
		if ( ! $this->settings['enable_hreflang_tags'] ) {
			return;
		}
		
		global $wpcc_options;
		
		if ( empty( $wpcc_options['wpcc_used_langs'] ) || ! is_array( $wpcc_options['wpcc_used_langs'] ) ) {
			return;
		}
		
		// 需要至少两个语言版本才有意义
		$languages = array_values( array_unique( array_filter( $wpcc_options['wpcc_used_langs'] ) ) );
		if ( count( $languages ) < 2 ) {
			return;
		}
		
		$current_url = $this->get_current_url();
		if ( empty( $current_url ) ) {
			return;
		}
		
		$printed = [];
		foreach ( $languages as $lang ) {
			$lang_url = $this->get_language_url( $current_url, $lang );
			$hreflang = $this->get_hreflang_code( $lang );
			if ( $lang_url && $hreflang && empty( $printed[ $hreflang ] ) ) {
				$printed[ $hreflang ] = $lang_url;
				echo '<link rel="alternate" hreflang="' . esc_attr( $hreflang ) . '" href="' . esc_url( $lang_url ) . '" />' . "\n";
			}
		}
		
        $default_lang = $this->settings['hreflang_x_default'];
        if ( $this->settings['enable_hreflang_x_default'] && ! empty( $default_lang ) ) {
            $default_url = $this->get_language_url( $current_url, $default_lang );
            if ( ! empty( $default_url ) ) {
                echo '<link rel="alternate" hreflang="x-default" href="' . esc_url( $default_url ) . '" />' . "\n";
            }
        }
	}
	
	private function get_current_url() {
		if ( is_admin() ) {
			return home_url( '/' );
		}
		
		// 优先使用规范链接，避免把内部查询变量（year/monthnum/day/name 等）拼进 URL
		if ( is_singular() ) {
			$object_id = get_queried_object_id();
			if ( $object_id ) {
				$url = get_permalink( $object_id );
				return trailingslashit( remove_query_arg( 'variant', $url ) );
			}
		}
		
		// 搜索页保留搜索参数，其它归一化为当前路径，无附加查询串
		if ( is_search() ) {
			$url = get_search_link();
			return trailingslashit( remove_query_arg( 'variant', $url ) );
		}
		
		global $wp;
		$url = home_url( $wp->request );
		return trailingslashit( remove_query_arg( 'variant', $url ) );
	}
	
	private function get_language_url( $url, $lang ) {
		global $wpcc_options;
		
		$clean_url = remove_query_arg( 'variant', $url );
		
		// 允许的语言前缀集合（用于剥离已有语言段）
		$valid_codes = method_exists( 'WPCC_Language_Config', 'get_valid_language_codes' )
			? WPCC_Language_Config::get_valid_language_codes()
			: array( 'zh-cn','zh-tw','zh-hk','zh-hans','zh-hant','zh-sg','zh-jp','zh','zh-reset' );
		$valid_regex = implode( '|', array_map( 'preg_quote', $valid_codes ) );
		
		// 仅 query 形式（模式 0）
		if ( empty( $wpcc_options['wpcc_use_permalink'] ) ) {
			return add_query_arg( 'variant', $lang, $clean_url );
		}
		
		$permalink_type = intval( $wpcc_options['wpcc_use_permalink'] );
		$parsed_url = parse_url( $clean_url );
		$scheme = isset( $parsed_url['scheme'] ) ? $parsed_url['scheme'] : ( is_ssl() ? 'https' : 'http' );
		$host   = isset( $parsed_url['host'] ) ? $parsed_url['host'] : '';
		$base   = $scheme . '://' . $host;
		$path   = isset( $parsed_url['path'] ) ? $parsed_url['path'] : '/';
		$query  = isset( $parsed_url['query'] ) && $parsed_url['query'] !== '' ? '?' . $parsed_url['query'] : '';
		
		// 统一剥离开头的语言前缀
		$path = preg_replace( '#^/(?:' . $valid_regex . ')(/|$)#i', '/$1', $path );
		if ( $path === '' ) { $path = '/'; }
		
		switch ( $permalink_type ) {
			case 1:
				// 语言段后缀：去掉末尾已存在的语言段再追加
				$path = rtrim( $path, '/' );
				$path = preg_replace( '#/(?:' . $valid_regex . ')/?$#i', '', $path );
				return $base . rtrim( $path, '/' ) . '/' . $lang . '/' . $query;
			case 2:
				// 语言段前缀：在剥离后的路径前面加上新语言前缀
				$path = '/' . ltrim( $path, '/' );
				// 确保不会出现双斜杠
				$final = rtrim( $base, '/' ) . '/' . $lang . rtrim( $path, '/' ) . '/';
				return $query ? ( $final . $query ) : $final;
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
			'zh-jp' => 'zh-JP'
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