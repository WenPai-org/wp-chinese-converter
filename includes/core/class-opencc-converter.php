<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once dirname( __FILE__ ) . '/interface-converter.php';

use Overtrue\PHPOpenCC\OpenCC;
use Overtrue\PHPOpenCC\Strategy;

class WPCC_OpenCC_Converter implements WPCC_Converter_Interface {
	
	private $strategy_map = array(
		'zh-hans' => Strategy::TRADITIONAL_TO_SIMPLIFIED,
		'zh-hant' => Strategy::SIMPLIFIED_TO_TRADITIONAL,
		'zh-cn'   => Strategy::TRADITIONAL_TO_SIMPLIFIED,
		'zh-tw'   => Strategy::SIMPLIFIED_TO_TAIWAN_WITH_PHRASE,
		'zh-hk'   => Strategy::SIMPLIFIED_TO_HONGKONG,
		'zh-sg'   => Strategy::TRADITIONAL_TO_SIMPLIFIED,
		'zh-jp'   => Strategy::SIMPLIFIED_TO_JAPANESE,
	);
	
	public function convert( $text, $target_variant ) {
		if ( empty( $text ) ) {
			return $text;
		}
		
		if ( ! $this->is_available() ) {
			throw new Exception( 'OpenCC library is not available' );
		}
		
		if ( ! isset( $this->strategy_map[ $target_variant ] ) ) {
			return $text;
		}
		
		try {
			return OpenCC::convert( $text, $this->strategy_map[ $target_variant ] );
		} catch ( Exception $e ) {
			error_log( 'WPCC OpenCC Conversion Error: ' . $e->getMessage() );
			return $text;
		}
	}
	
	public function get_supported_variants() {
		return array_keys( $this->strategy_map );
	}
	
	public function get_engine_name() {
		return 'opencc';
	}
	
	public function get_engine_info() {
		return array(
			'name' => 'OpenCC',
			'version' => '1.2.1',
			'description' => '基于 OpenCC 的智能词汇级转换引擎',
			'features' => array(
				'词汇级别转换',
				'异体字转换',
				'地区习惯用词转换',
				'智能语境分析',
				'支持批量转换'
			),
			'memory_usage' => '按需加载，内存占用较低',
			'conversion_type' => 'vocabulary_based'
		);
	}
	
	public function is_available() {
		return class_exists( 'Overtrue\PHPOpenCC\OpenCC' );
	}
	
	public function batch_convert( $texts, $target_variant ) {
		if ( ! $this->is_available() ) {
			return $texts;
		}
		
		if ( ! isset( $this->strategy_map[ $target_variant ] ) ) {
			return $texts;
		}
		
		try {
			return OpenCC::convert( $texts, $this->strategy_map[ $target_variant ] );
		} catch ( Exception $e ) {
			error_log( 'WPCC OpenCC Batch Conversion Error: ' . $e->getMessage() );
			return $texts;
		}
	}
	
	public function get_available_strategies() {
		return $this->strategy_map;
	}
	
	public function convert_with_strategy( $text, $strategy ) {
		if ( empty( $text ) || ! $this->is_available() ) {
			return $text;
		}
		
		try {
			return OpenCC::convert( $text, $strategy );
		} catch ( Exception $e ) {
			error_log( 'WPCC OpenCC Strategy Conversion Error: ' . $e->getMessage() );
			return $text;
		}
	}
}