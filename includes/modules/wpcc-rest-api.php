<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once dirname( dirname( __FILE__ ) ) . '/core/abstract-module.php';
require_once dirname( dirname( __FILE__ ) ) . '/core/class-converter-factory.php';

class WPCC_Rest_Api extends WPCC_Abstract_Module {
	
	private $namespace = 'wpcc/v1';
	
	public function init() {
		$this->name = 'REST API';
		$this->version = '1.0.0';
		$this->description = 'REST API 接口模块，提供转换服务的 API 端点';
		$this->dependencies = array(
			array( 'type' => 'class', 'name' => 'WP_REST_Server' ),
			array( 'type' => 'function', 'name' => 'rest_ensure_response' )
		);
		
		if ( $this->is_enabled() && $this->check_rest_api_availability() ) {
			add_action( 'rest_api_init', array( $this, 'register_routes' ) );
			add_action( 'init', array( $this, 'ensure_api_key_exists' ) );
		}
	}
	
	private function check_rest_api_availability() {
		return class_exists( 'WP_REST_Server' ) && function_exists( 'rest_ensure_response' );
	}

	/**
	 * 确保API密钥存在
	 */
	public function ensure_api_key_exists() {
		// 这个方法可以用来初始化API相关的设置
		// 目前为空实现，保持兼容性
	}
	
	public function register_routes() {
		register_rest_route( $this->namespace, '/convert', array(
			'methods' => WP_REST_Server::CREATABLE,
			'callback' => array( $this, 'convert_text' ),
			'permission_callback' => array( $this, 'check_permission' ),
			'args' => array(
				'text' => array(
					'required' => true,
					'type' => 'string',
					'description' => '需要转换的文本'
				),
				'target' => array(
					'required' => true,
					'type' => 'string',
					'enum' => array( 'zh-cn', 'zh-tw', 'zh-hk', 'zh-sg', 'zh-hans', 'zh-hant', 'zh-jp' ),
					'description' => '目标语言变体'
				),
				'engine' => array(
					'required' => false,
					'type' => 'string',
					'enum' => array( 'mediawiki', 'opencc' ),
					'default' => null,
					'description' => '转换引擎'
				)
			)
		) );
		
		register_rest_route( $this->namespace, '/batch-convert', array(
			'methods' => WP_REST_Server::CREATABLE,
			'callback' => array( $this, 'batch_convert_text' ),
			'permission_callback' => array( $this, 'check_permission' ),
			'args' => array(
				'texts' => array(
					'required' => true,
					'type' => 'array',
					'description' => '需要转换的文本数组'
				),
				'target' => array(
					'required' => true,
					'type' => 'string',
					'enum' => array( 'zh-cn', 'zh-tw', 'zh-hk', 'zh-sg', 'zh-hans', 'zh-hant', 'zh-jp' ),
					'description' => '目标语言变体'
				),
				'engine' => array(
					'required' => false,
					'type' => 'string',
					'enum' => array( 'mediawiki', 'opencc' ),
					'default' => null,
					'description' => '转换引擎'
				)
			)
		) );
		
		register_rest_route( $this->namespace, '/engines', array(
			'methods' => WP_REST_Server::READABLE,
			'callback' => array( $this, 'get_engines' ),
			'permission_callback' => array( $this, 'check_permission' )
		) );
		
		register_rest_route( $this->namespace, '/languages', array(
			'methods' => WP_REST_Server::READABLE,
			'callback' => array( $this, 'get_languages' ),
			'permission_callback' => array( $this, 'check_permission' )
		) );
		
		register_rest_route( $this->namespace, '/status', array(
			'methods' => WP_REST_Server::READABLE,
			'callback' => array( $this, 'get_api_status' ),
			'permission_callback' => array( $this, 'check_permission' )
		) );
	}
	
	public function check_permission( $request ) {
		$public_endpoints = array( 'convert', 'batch-convert', 'engines', 'languages' );
		$endpoint = $request->get_route();
		
		foreach ( $public_endpoints as $public ) {
			if ( strpos( $endpoint, $public ) !== false ) {
				return $this->check_api_key( $request ) || current_user_can( 'read' );
			}
		}
		
		return current_user_can( 'manage_options' );
	}
	
	private function check_api_key( $request ) {
		return $this->check_application_password( $request );
	}
	
	private function check_application_password( $request ) {
		if ( ! function_exists( 'wp_authenticate_application_password' ) ) {
			return false;
		}
		
		$authorization = $request->get_header( 'Authorization' );
		if ( empty( $authorization ) ) {
			return false;
		}
		
		if ( strpos( $authorization, 'Basic ' ) !== 0 ) {
			return false;
		}
		
		$credentials = base64_decode( substr( $authorization, 6 ) );
		if ( ! $credentials ) {
			return false;
		}
		
		list( $username, $password ) = explode( ':', $credentials, 2 );
		
		$user = wp_authenticate_application_password( null, $username, $password );
		
		if ( is_wp_error( $user ) ) {
			return false;
		}
		
		if ( ! $user || ! user_can( $user, 'read' ) ) {
			return false;
		}
		
		wp_set_current_user( $user->ID );
		return true;
	}
	
	public function convert_text( $request ) {
		$text = $request->get_param( 'text' );
		$target = $request->get_param( 'target' );
		$engine = $request->get_param( 'engine' );
		
		try {
			$converter = WPCC_Converter_Factory::get_converter( $engine );
			$converted_text = $converter->convert( $text, $target );
			
			return rest_ensure_response( array(
				'success' => true,
				'data' => array(
					'original' => $text,
					'converted' => $converted_text,
					'target' => $target,
					'engine' => $converter->get_engine_name()
				)
			) );
			
		} catch ( Exception $e ) {
			return new WP_Error( 'conversion_failed', $e->getMessage(), array( 'status' => 500 ) );
		}
	}
	
	public function batch_convert_text( $request ) {
		$texts = $request->get_param( 'texts' );
		$target = $request->get_param( 'target' );
		$engine = $request->get_param( 'engine' );
		
		if ( count( $texts ) > 100 ) {
			return new WP_Error( 'too_many_texts', '批量转换最多支持100个文本', array( 'status' => 400 ) );
		}
		
		try {
			$converter = WPCC_Converter_Factory::get_converter( $engine );
			$results = array();
			
			foreach ( $texts as $index => $text ) {
				$converted_text = $converter->convert( $text, $target );
				$results[] = array(
					'index' => $index,
					'original' => $text,
					'converted' => $converted_text
				);
			}
			
			return rest_ensure_response( array(
				'success' => true,
				'data' => array(
					'results' => $results,
					'target' => $target,
					'engine' => $converter->get_engine_name(),
					'total' => count( $results )
				)
			) );
			
		} catch ( Exception $e ) {
			return new WP_Error( 'batch_conversion_failed', $e->getMessage(), array( 'status' => 500 ) );
		}
	}
	
	public function get_engines( $request ) {
		$engines = WPCC_Converter_Factory::get_available_engines();
		
		return rest_ensure_response( array(
			'success' => true,
			'data' => $engines
		) );
	}
	
	public function get_languages( $request ) {
		$languages = array(
			'zh-cn' => array(
				'name' => '简体中文',
				'native_name' => '简体中文',
				'code' => 'zh-cn'
			),
			'zh-tw' => array(
				'name' => '台湾正体',
				'native_name' => '台灣正體',
				'code' => 'zh-tw'
			),
			'zh-hk' => array(
				'name' => '香港繁体',
				'native_name' => '香港繁體',
				'code' => 'zh-hk'
			),
			'zh-sg' => array(
				'name' => '新加坡简体',
				'native_name' => '新加坡简体',
				'code' => 'zh-sg'
			),
			'zh-hans' => array(
				'name' => '简体中文',
				'native_name' => '简体中文',
				'code' => 'zh-hans'
			),
			'zh-hant' => array(
				'name' => '繁体中文',
				'native_name' => '繁體中文',
				'code' => 'zh-hant'
			),
			'zh-jp' => array(
				'name' => '日本新字体',
				'native_name' => '日本新字体',
				'code' => 'zh-jp'
			)
		);
		
		return rest_ensure_response( array(
			'success' => true,
			'data' => $languages
		) );
	}
	
	public function get_api_status( $request ) {
		global $wpcc_options;
		
		$status = array(
			'plugin_version' => defined( 'wpcc_VERSION' ) ? wpcc_VERSION : '未知',
			'current_engine' => $wpcc_options['wpcc_engine'] ?? 'mediawiki',
			'enabled_languages' => $wpcc_options['wpcc_used_langs'] ?? array(),
			'application_passwords_supported' => function_exists( 'wp_authenticate_application_password' ),
			'authentication_method' => 'application_password',
			'engines' => WPCC_Converter_Factory::get_available_engines()
		);
		
		return rest_ensure_response( array(
			'success' => true,
			'data' => $status
		) );
	}
}