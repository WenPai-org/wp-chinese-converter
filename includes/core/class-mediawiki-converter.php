<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once dirname( __FILE__ ) . '/interface-converter.php';

class WPCC_MediaWiki_Converter implements WPCC_Converter_Interface {
	
	private $conversion_tables_loaded = false;
	private static $loaded_tables = array();
	private $lazy_load_enabled = true;
	
	public function convert( $text, $target_variant ) {
		if ( empty( $text ) ) {
			return $text;
		}
		
		// 检查缓存
		$cached_result = WPCC_Conversion_Cache::get_cached_conversion( $text, $target_variant );
		if ( $cached_result !== null ) {
			return $cached_result;
		}
		
		$this->ensure_conversion_tables_loaded( $target_variant );
		
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
		$memory_usage = $this->get_memory_usage_info();
		
		return array(
			'name' => 'MediaWiki',
			'version' => '1.23.5',
			'description' => '基于MediaWiki的字符映射转换引擎',
			'features' => array(
				'字符级精确映射',
				'快速转换速度',
				'良好的兼容性',
				'支持多地区变体',
				'按需加载优化'
			),
			'memory_usage' => $memory_usage,
			'conversion_type' => 'character_mapping',
			'lazy_load_enabled' => $this->lazy_load_enabled,
			'loaded_tables' => array_keys( array_filter( self::$loaded_tables ) )
		);
	}
	
	public function get_memory_usage_info() {
		$loaded_count = count( array_filter( self::$loaded_tables ) );
		$total_count = count( $this->get_supported_variants() );
		
		if ( $this->lazy_load_enabled ) {
			$estimated_usage = $loaded_count * 0.25; // MB per table approximately
			return sprintf( '约 %.1fMB (%d/%d 表已加载, 按需加载开启)', $estimated_usage, $loaded_count, $total_count );
		} else {
			return '约 1.5MB (所有转换表已加载)';
		}
	}
	
	public function enable_lazy_loading( $enable = true ) {
		$this->lazy_load_enabled = $enable;
	}
	
	public function is_lazy_loading_enabled() {
		return $this->lazy_load_enabled;
	}
	
	public static function get_loaded_tables_status() {
		return self::$loaded_tables;
	}
	
	public static function unload_table( $variant ) {
		if ( isset( self::$loaded_tables[ $variant ] ) ) {
			unset( self::$loaded_tables[ $variant ] );
			
			// 清理对应的全局变量（这里只能标记，PHP不能真正释放全局变量内存）
			return true;
		}
		return false;
	}
	
	public function is_available() {
		$conversion_file = dirname( dirname( __FILE__ ) ) . '/core/ZhConversion.php';
		return file_exists( $conversion_file );
	}
	
	private function ensure_conversion_tables_loaded( $target_variant = null ) {
		if ( $this->lazy_load_enabled && $target_variant ) {
			// 按需加载特定语言变体的转换表
			$this->ensure_specific_table_loaded( $target_variant );
		} else {
			// 传统方式：加载所有转换表
			$this->load_all_conversion_tables();
		}
	}
	
	private function ensure_specific_table_loaded( $target_variant ) {
		if ( isset( self::$loaded_tables[ $target_variant ] ) ) {
			return; // 已经加载
		}
		
		$conversion_file = dirname( dirname( __FILE__ ) ) . '/core/ZhConversion.php';
		if ( ! file_exists( $conversion_file ) ) {
			return;
		}
		
		// 只加载需要的转换表
		global $zh2Hans, $zh2Hant, $zh2CN, $zh2TW, $zh2HK, $zh2SG;
		
		switch ( $target_variant ) {
			case 'zh-hans':
				if ( ! isset( $zh2Hans ) ) {
					$this->load_conversion_table_partial( 'zh2Hans' );
				}
				self::$loaded_tables['zh-hans'] = true;
				break;
				
			case 'zh-hant':
				if ( ! isset( $zh2Hant ) ) {
					$this->load_conversion_table_partial( 'zh2Hant' );
				}
				self::$loaded_tables['zh-hant'] = true;
				break;
				
			case 'zh-cn':
				if ( ! isset( $zh2CN ) || ! isset( $zh2Hans ) ) {
					$this->load_conversion_table_partial( array( 'zh2CN', 'zh2Hans' ) );
				}
				self::$loaded_tables['zh-cn'] = true;
				break;
				
			case 'zh-tw':
				if ( ! isset( $zh2TW ) || ! isset( $zh2Hant ) ) {
					$this->load_conversion_table_partial( array( 'zh2TW', 'zh2Hant' ) );
				}
				self::$loaded_tables['zh-tw'] = true;
				break;
				
			case 'zh-hk':
				if ( ! isset( $zh2HK ) || ! isset( $zh2Hant ) ) {
					$this->load_conversion_table_partial( array( 'zh2HK', 'zh2Hant' ) );
				}
				self::$loaded_tables['zh-hk'] = true;
				break;
				
			case 'zh-sg':
				if ( ! isset( $zh2SG ) || ! isset( $zh2Hans ) ) {
					$this->load_conversion_table_partial( array( 'zh2SG', 'zh2Hans' ) );
				}
				self::$loaded_tables['zh-sg'] = true;
				break;
				
			default:
				$this->load_all_conversion_tables();
				break;
		}
	}
	
	private function load_conversion_table_partial( $table_names ) {
		$conversion_file = dirname( dirname( __FILE__ ) ) . '/core/ZhConversion.php';
		
		if ( ! is_array( $table_names ) ) {
			$table_names = array( $table_names );
		}
		
		// 读取文件内容并解析指定的转换表
		$file_content = file_get_contents( $conversion_file );
		
		foreach ( $table_names as $table_name ) {
			$pattern = '/\$' . preg_quote( $table_name, '/' ) . '\s*=\s*array\s*\((.*?)\);/s';
			if ( preg_match( $pattern, $file_content, $matches ) ) {
				$table_code = '$' . $table_name . ' = array(' . $matches[1] . ');';
				eval( $table_code );
				
				// 将变量设置为全局
				$GLOBALS[ $table_name ] = ${$table_name};
			}
		}
		
		// 加载额外转换文件
		$extra_conversion_file = WP_CONTENT_DIR . '/extra_zhconversion.php';
		if ( file_exists( $extra_conversion_file ) ) {
			require_once $extra_conversion_file;
		}
	}
	
	private function load_all_conversion_tables() {
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
		
		// 标记所有表为已加载
		self::$loaded_tables = array(
			'zh-hans' => true,
			'zh-hant' => true,
			'zh-cn' => true,
			'zh-tw' => true,
			'zh-hk' => true,
			'zh-sg' => true
		);
		
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