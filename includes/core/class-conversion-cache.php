<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WPCC转换缓存管理器
 * 
 * 提供转换结果缓存功能，避免重复转换相同内容
 * 支持内存缓存和持久化缓存两种模式
 */
class WPCC_Conversion_Cache {
	
	/**
	 * 内存缓存数组
	 * 
	 * @var array
	 */
	private static $memory_cache = array();
	
	/**
	 * 缓存统计信息
	 * 
	 * @var array
	 */
	private static $cache_stats = array(
		'hits' => 0,
		'misses' => 0,
		'sets' => 0
	);
	
	/**
	 * 最大内存缓存条目数
	 * 
	 * @var int
	 */
	private static $max_memory_cache_size = 1000;
	
	/**
	 * 缓存过期时间（秒）
	 * 
	 * @var int
	 */
	private static $cache_expire_time = 3600; // 1小时
	
	/**
	 * 获取缓存的转换结果
	 * 
	 * @param string $text 原始文本
	 * @param string $variant 目标语言变体
	 * @return string|null 缓存的转换结果，如果不存在返回null
	 */
	public static function get_cached_conversion( $text, $variant ) {
		if ( empty( $text ) || empty( $variant ) ) {
			return null;
		}
		
		$cache_key = self::generate_cache_key( $text, $variant );
		
		// 先检查内存缓存
		if ( isset( self::$memory_cache[ $cache_key ] ) ) {
			$cached_data = self::$memory_cache[ $cache_key ];
			if ( $cached_data['expires'] > time() ) {
				self::$cache_stats['hits']++;
				return $cached_data['result'];
			} else {
				// 清理过期的内存缓存
				unset( self::$memory_cache[ $cache_key ] );
			}
		}
		
		// 检查WordPress对象缓存
		$cached_result = wp_cache_get( $cache_key, 'wpcc_conversion' );
		if ( $cached_result !== false ) {
			// 同时存入内存缓存以加速后续访问
			self::set_memory_cache( $cache_key, $cached_result );
			self::$cache_stats['hits']++;
			return $cached_result;
		}
		
		self::$cache_stats['misses']++;
		return null;
	}
	
	/**
	 * 设置转换结果缓存
	 * 
	 * @param string $text 原始文本
	 * @param string $variant 目标语言变体
	 * @param string $result 转换结果
	 * @return bool 是否设置成功
	 */
	public static function set_cached_conversion( $text, $variant, $result ) {
		if ( empty( $text ) || empty( $variant ) || empty( $result ) ) {
			return false;
		}
		
		$cache_key = self::generate_cache_key( $text, $variant );
		
		// 设置内存缓存
		self::set_memory_cache( $cache_key, $result );
		
		// 设置WordPress对象缓存
		wp_cache_set( $cache_key, $result, 'wpcc_conversion', self::$cache_expire_time );
		
		self::$cache_stats['sets']++;
		return true;
	}
	
	/**
	 * 批量获取转换缓存
	 * 
	 * @param array $texts_variants 文本和变体的数组 [[text, variant], ...]
	 * @return array 缓存结果数组，键为cache_key，值为转换结果
	 */
	public static function get_cached_conversions_batch( $texts_variants ) {
		$results = array();
		
		foreach ( $texts_variants as $item ) {
			if ( ! is_array( $item ) || count( $item ) < 2 ) {
				continue;
			}
			
			list( $text, $variant ) = $item;
			$cache_key = self::generate_cache_key( $text, $variant );
			$cached_result = self::get_cached_conversion( $text, $variant );
			
			if ( $cached_result !== null ) {
				$results[ $cache_key ] = $cached_result;
			}
		}
		
		return $results;
	}
	
	/**
	 * 批量设置转换缓存
	 * 
	 * @param array $conversions 转换结果数组 [[text, variant, result], ...]
	 * @return int 成功设置的缓存数量
	 */
	public static function set_cached_conversions_batch( $conversions ) {
		$count = 0;
		
		foreach ( $conversions as $conversion ) {
			if ( ! is_array( $conversion ) || count( $conversion ) < 3 ) {
				continue;
			}
			
			list( $text, $variant, $result ) = $conversion;
			if ( self::set_cached_conversion( $text, $variant, $result ) ) {
				$count++;
			}
		}
		
		return $count;
	}
	
	/**
	 * 生成缓存键
	 * 
	 * @param string $text 原始文本
	 * @param string $variant 目标语言变体
	 * @return string 缓存键
	 */
	private static function generate_cache_key( $text, $variant ) {
		// 使用文本和变体的哈希值作为缓存键，确保唯一性
		return 'wpcc_' . md5( $text . '|' . $variant );
	}
	
	/**
	 * 设置内存缓存
	 * 
	 * @param string $cache_key 缓存键
	 * @param string $result 转换结果
	 */
	private static function set_memory_cache( $cache_key, $result ) {
		// 如果内存缓存已满，移除最旧的条目
		if ( count( self::$memory_cache ) >= self::$max_memory_cache_size ) {
			$oldest_key = array_key_first( self::$memory_cache );
			unset( self::$memory_cache[ $oldest_key ] );
		}
		
		self::$memory_cache[ $cache_key ] = array(
			'result' => $result,
			'expires' => time() + self::$cache_expire_time
		);
	}
	
	/**
	 * 清理过期的内存缓存
	 */
	public static function cleanup_expired_memory_cache() {
		$current_time = time();
		$expired_keys = array();
		
		foreach ( self::$memory_cache as $key => $data ) {
			if ( $data['expires'] <= $current_time ) {
				$expired_keys[] = $key;
			}
		}
		
		foreach ( $expired_keys as $key ) {
			unset( self::$memory_cache[ $key ] );
		}
		
		return count( $expired_keys );
	}
	
	/**
	 * 清空所有缓存
	 */
	public static function clear_all_cache() {
		// 清空内存缓存
		self::$memory_cache = array();
		
		// 清空WordPress对象缓存
		wp_cache_flush_group( 'wpcc_conversion' );
		
		// 重置统计信息
		self::$cache_stats = array(
			'hits' => 0,
			'misses' => 0,
			'sets' => 0
		);
	}
	
	/**
	 * 获取缓存统计信息
	 * 
	 * @return array 缓存统计信息
	 */
	public static function get_cache_stats() {
		$stats = self::$cache_stats;
		$stats['memory_cache_size'] = count( self::$memory_cache );
		$stats['hit_rate'] = $stats['hits'] + $stats['misses'] > 0 
			? round( $stats['hits'] / ( $stats['hits'] + $stats['misses'] ) * 100, 2 ) 
			: 0;
		
		return $stats;
	}
	
	/**
	 * 设置缓存配置
	 * 
	 * @param array $config 配置数组
	 */
	public static function set_cache_config( $config ) {
		if ( isset( $config['max_memory_cache_size'] ) ) {
			self::$max_memory_cache_size = (int) $config['max_memory_cache_size'];
		}
		
		if ( isset( $config['cache_expire_time'] ) ) {
			self::$cache_expire_time = (int) $config['cache_expire_time'];
		}
	}
	
	/**
	 * 获取缓存配置
	 * 
	 * @return array 当前缓存配置
	 */
	public static function get_cache_config() {
		return array(
			'max_memory_cache_size' => self::$max_memory_cache_size,
			'cache_expire_time' => self::$cache_expire_time
		);
	}
}