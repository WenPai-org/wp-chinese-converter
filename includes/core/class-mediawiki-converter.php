<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once dirname( __FILE__ ) . '/interface-converter.php';

class WPCC_MediaWiki_Converter implements WPCC_Converter_Interface {
	
	private $conversion_tables_loaded = false;
	
	public function convert( $text, $target_variant ) {
		if ( empty( $text ) ) {
			return $text;
		}
		
		$this->ensure_conversion_tables_loaded();
		
		switch ( $target_variant ) {
			case 'zh-hans':
				return $this->convert_to_hans( $text );
			case 'zh-hant':
				return $this->convert_to_hant( $text );
			case 'zh-cn':
				return $this->convert_to_cn( $text );
			case 'zh-tw':
				return $this->convert_to_tw( $text );
			case 'zh-hk':
				return $this->convert_to_hk( $text );
			case 'zh-sg':
				return $this->convert_to_sg( $text );
			default:
				return $text;
		}
	}
	
	public function get_supported_variants() {
		return array( 'zh-hans', 'zh-hant', 'zh-cn', 'zh-tw', 'zh-hk', 'zh-sg' );
	}
	
	public function get_engine_name() {
		return 'mediawiki';
	}
	
	public function get_engine_info() {
		return array(
			'name' => 'MediaWiki',
			'version' => '1.23.5',
			'description' => '基于 MediaWiki 的字符映射转换引擎',
			'features' => array(
				'字符级精确映射',
				'快速转换速度',
				'良好的兼容性',
				'支持多地区变体'
			),
			'memory_usage' => '约 1.5MB (转换表加载后)',
			'conversion_type' => 'character_mapping'
		);
	}
	
	public function is_available() {
		$conversion_file = dirname( dirname( __FILE__ ) ) . '/core/ZhConversion.php';
		return file_exists( $conversion_file );
	}
	
	private function ensure_conversion_tables_loaded() {
		if ( $this->conversion_tables_loaded ) {
			return;
		}
		
		global $zh2Hans, $zh2Hant, $zh2CN, $zh2TW, $zh2HK, $zh2SG;
		
		if ( ! isset( $zh2Hans ) ) {
			require_once dirname( dirname( __FILE__ ) ) . '/core/ZhConversion.php';
			
			$extra_conversion_file = WP_CONTENT_DIR . '/extra_zhconversion.php';
			if ( file_exists( $extra_conversion_file ) ) {
				require_once $extra_conversion_file;
			}
		}
		
		$this->conversion_tables_loaded = true;
	}
	
	private function convert_to_hans( $text ) {
		global $zh2Hans;
		return strtr( $text, $zh2Hans );
	}
	
	private function convert_to_hant( $text ) {
		global $zh2Hant;
		return strtr( $text, $zh2Hant );
	}
	
	private function convert_to_cn( $text ) {
		global $zh2Hans, $zh2CN;
		return strtr( strtr( $text, $zh2CN ), $zh2Hans );
	}
	
	private function convert_to_tw( $text ) {
		global $zh2Hant, $zh2TW;
		return strtr( strtr( $text, $zh2TW ), $zh2Hant );
	}
	
	private function convert_to_hk( $text ) {
		global $zh2Hant, $zh2HK;
		return strtr( strtr( $text, $zh2HK ), $zh2Hant );
	}
	
	private function convert_to_sg( $text ) {
		global $zh2Hans, $zh2SG;
		return strtr( strtr( $text, $zh2SG ), $zh2Hans );
	}
}