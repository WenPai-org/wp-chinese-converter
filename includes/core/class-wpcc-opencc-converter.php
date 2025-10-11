<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once dirname( __FILE__ ) . '/interface-converter.php';

use Overtrue\PHPOpenCC\OpenCC;
use Overtrue\PHPOpenCC\Strategy;
use Overtrue\PHPOpenCC\Dictionary;

class WPCC_OpenCC_Converter implements WPCC_Converter_Interface {
    /**
     * 预处理并排序后的字典缓存（按策略）
     * @var array<string, array<array<string,string>>> 每个策略对应若干个字典映射，保持原有顺序
     */
    private $prepared = [];

    /**
     * 是否包含中文字符的快速检测（避免不必要的 OpenCC 调用）
     */
    private function contains_chinese($text) {
        return is_string($text) && preg_match('/[\x{4e00}-\x{9fff}]/u', $text) === 1;
    }

    /**
     * 获取预处理后的字典（已展开并按键长度降序排序），仅在本请求内执行一次
     */
    private function get_prepared_dictionaries(string $strategy): array {
        if (isset($this->prepared[$strategy])) {
            return $this->prepared[$strategy];
        }

        $sets = Dictionary::get($strategy); // 与 upstream 一致的字典结构
        $prepared = [];

        foreach ($sets as $dictionary) {
            // 可能是分组数组（数组的数组），需要先展平
            if (is_array(reset($dictionary))) {
                $flat = [];
                foreach ($dictionary as $dict) {
                    $flat = array_merge($flat, $dict);
                }
                // 按键长度降序排序，优先匹配长词
                uksort($flat, function ($a, $b) {
                    return mb_strlen($b) <=> mb_strlen($a);
                });
                $prepared[] = $flat;
                continue;
            }

            // 普通单字典
            $flat = $dictionary;
            uksort($flat, function ($a, $b) {
                return mb_strlen($b) <=> mb_strlen($a);
            });
            $prepared[] = $flat;
        }

        $this->prepared[$strategy] = $prepared;
        return $prepared;
    }

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
		
        // 快速路径：非中文内容不做转换
        if ( ! $this->contains_chinese( $text ) ) {
            return $text;
        }

        if ( ! $this->is_available() ) {
            throw new Exception( 'OpenCC library is not available' );
        }
		
		if ( ! isset( $this->strategy_map[ $target_variant ] ) ) {
			return $text;
		}
		
        try {
            // 使用预处理字典避免每次调用时重复排序
            $strategy = $this->strategy_map[ $target_variant ];
            $dictionaries = $this->get_prepared_dictionaries($strategy);
            $output = $text;
            foreach ($dictionaries as $dict) {
                $output = strtr($output, $dict);
            }
            return $output;
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
		
		if ( ! is_array( $texts ) ) {
			return $texts;
		}
		
		// 检查缓存
		$cached_results = array();
		$uncached_texts = array();
		$uncached_indices = array();
		
        foreach ( $texts as $index => $text ) {
            $cached_result = WPCC_Conversion_Cache::get_cached_conversion( $text, $target_variant );
            if ( $cached_result !== null ) {
                $cached_results[ $index ] = $cached_result;
            } else if ( ! $this->contains_chinese( $text ) ) {
                // 非中文内容：无需调用 OpenCC，直接作为“已处理”内容返回
                $cached_results[ $index ] = $text;
            } else {
                $uncached_texts[] = $text;
                $uncached_indices[] = $index;
            }
        }
		
		// 如果所有内容都在缓存中，直接返回
		if ( empty( $uncached_texts ) ) {
			$results = array();
			foreach ( $texts as $index => $text ) {
				$results[] = $cached_results[ $index ];
			}
			return $results;
		}
		
        try {
            // 使用预处理字典一次性转换，避免每次排序
            $strategy = $this->strategy_map[ $target_variant ];
            $dictionaries = $this->get_prepared_dictionaries($strategy);

            $converted_texts = $uncached_texts;
            foreach ($dictionaries as $dict) {
                $converted_texts = array_map(function ($str) use ($dict) {
                    return strtr($str, $dict);
                }, $converted_texts);
            }
            
            // 合并结果并缓存新转换的内容
			$results = array();
			$uncached_index = 0;
			
			foreach ( $texts as $index => $original_text ) {
				if ( isset( $cached_results[ $index ] ) ) {
					$results[] = $cached_results[ $index ];
				} else {
					$converted_text = is_array( $converted_texts ) ? $converted_texts[ $uncached_index ] : $converted_texts;
					$results[] = $converted_text;
					
					// 缓存转换结果
					if ( $converted_text !== $original_text ) {
						WPCC_Conversion_Cache::set_cached_conversion( $original_text, $target_variant, $converted_text );
					}
					
					$uncached_index++;
				}
			}
			
			return $results;
			
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