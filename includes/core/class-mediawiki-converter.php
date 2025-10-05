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

		// 检查缓存
		$cached_result = WPCC_Conversion_Cache::get_cached_conversion( $text, $target_variant );
		if ( $cached_result !== null ) {
			return $cached_result;
		}

		// 回滚：始终加载所有转换表
		$this->load_all_conversion_tables();

		$result = $text;
		switch ( $target_variant ) {
			case 'zh-hans':
				$result = $this->convert_to_hans( $text );
				break;
			case 'zh-hant':
				$result = $this->convert_to_hant( $text );
				break;
			case 'zh-cn':
				$result = $this->convert_to_cn( $text );
				break;
			case 'zh-tw':
				$result = $this->convert_to_tw( $text );
				break;
			case 'zh-hk':
				$result = $this->convert_to_hk( $text );
				break;
			case 'zh-sg':
				$result = $this->convert_to_sg( $text );
				break;
		}

		// 缓存转换结果
		if ( $result !== $text ) {
			WPCC_Conversion_Cache::set_cached_conversion( $text, $target_variant, $result );
		}

		return $result;
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
			'description' => '基于MediaWiki的字符映射转换引擎',
			'features' => array(
				'字符级精确映射',
				'快速转换速度',
				'良好的兼容性',
				'支持多地区变体'
			),
			'conversion_type' => 'character_mapping'
		);
	}

	public function is_available() {
		$conversion_file = dirname( dirname( __FILE__ ) ) . '/core/ZhConversion.php';
		return file_exists( $conversion_file );
	}

	private function load_all_conversion_tables() {
		if ( $this->conversion_tables_loaded ) {
			return;
		}

		global $zh2Hans;
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
