<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once dirname( __FILE__ ) . '/interface-converter.php';

class WPCC_Converter_Factory {
	
	private static $instances = array();
	
	public static function get_converter( $engine = null ) {
		global $wpcc_options;
		
		if ( $engine === null ) {
			$engine = isset( $wpcc_options['wpcc_engine'] ) ? $wpcc_options['wpcc_engine'] : 'mediawiki';
		}
		
		if ( ! isset( self::$instances[ $engine ] ) ) {
			self::$instances[ $engine ] = self::create_converter( $engine );
		}
		
		return self::$instances[ $engine ];
	}
	
	private static function create_converter( $engine ) {
			switch ( $engine ) {
				case 'opencc':
					require_once dirname( __FILE__ ) . '/class-wpcc-opencc-converter.php';
					return new WPCC_OpenCC_Converter();
					
				case 'mediawiki':
				default:
					require_once dirname( __FILE__ ) . '/class-wpcc-mediawiki-converter.php';
					return new WPCC_MediaWiki_Converter();
			}
	}
	
	public static function get_available_engines() {
		$engines = array();
		
		if ( class_exists( 'Overtrue\PHPOpenCC\OpenCC' ) ) {
			$engines['opencc'] = array(
				'name' => 'OpenCC',
				'description' => '智能词汇级别转换',
				'version' => '1.2.1',
				'features' => array( '词汇级转换', '地区习惯用词', '异体字转换' )
			);
		}
		
		$engines['mediawiki'] = array(
			'name' => 'MediaWiki',
			'description' => '快速字符映射转换',
			'version' => '1.23.5',
			'features' => array( '字符映射', '快速转换', '兼容性好' )
		);
		
		return $engines;
	}
	
	public static function clear_cache() {
		self::$instances = array();
	}
}