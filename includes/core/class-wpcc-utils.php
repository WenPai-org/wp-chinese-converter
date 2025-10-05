<?php

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WPCC 工具类
 * 
 * 提供各种实用工具方法，使用现代PHP语法和最佳实践
 */
final class WPCC_Utils {
	
	/**
	 * 验证语言代码格式
	 */
	public static function is_valid_language_code( string $lang_code ): bool {
		$valid_codes = [
			'zh-cn', 'zh-tw', 'zh-hk', 'zh-sg', 
			'zh-hans', 'zh-hant', 'zh-jp'
		];
		
		return in_array( $lang_code, $valid_codes, true );
	}
	
	/**
	 * 安全地获取配置值
	 */
	public static function get_safe_option( string $key, $default = null ) {
		global $wpcc_options;
		
		return $wpcc_options[ $key ] ?? $default;
	}
	
	/**
	 * 检查字符串是否包含中文字符
	 */
	public static function contains_chinese_chars( string $text ): bool {
		return preg_match( '/[\x{4e00}-\x{9fff}]+/u', $text ) === 1;
	}
	
	/**
	 * 获取文本的语言类型（简体/繁体）
	 */
	public static function detect_text_type( string $text ): string {
		if ( ! self::contains_chinese_chars( $text ) ) {
			return 'unknown';
		}
		
		// 简单的启发式检测
		$simplified_chars = ['的', '了', '会', '这', '那', '个', '说'];
		$traditional_chars = ['的', '了', '會', '這', '那', '個', '說'];
		
		$simplified_count = 0;
		$traditional_count = 0;
		
		foreach ( $simplified_chars as $char ) {
			if ( strpos( $text, $char ) !== false ) {
				$simplified_count++;
			}
		}
		
		foreach ( $traditional_chars as $char ) {
			if ( strpos( $text, $char ) !== false ) {
				$traditional_count++;
			}
		}
		
		if ( $simplified_count > $traditional_count ) {
			return 'simplified';
		} elseif ( $traditional_count > $simplified_count ) {
			return 'traditional';
		}
		
		return 'mixed';
	}
	
	/**
	 * 格式化内存使用量
	 */
	public static function format_memory_usage( int $bytes ): string {
		$units = ['B', 'KB', 'MB', 'GB'];
		$factor = floor( ( strlen( (string) $bytes ) - 1 ) / 3 );
		
		return sprintf( "%.1f%s", $bytes / pow( 1024, $factor ), $units[ $factor ] ?? 'TB' );
	}
	
	/**
	 * 生成缓存键
	 */
	public static function generate_cache_key( string ...$parts ): string {
		$key = implode( '|', array_filter( $parts ) );
		return 'wpcc_' . md5( $key );
	}
	
	/**
	 * 安全的字符串截取（支持多字节字符）
	 */
	public static function safe_substr( string $str, int $start, ?int $length = null ): string {
		return mb_substr( $str, $start, $length, 'UTF-8' );
	}
	
	/**
	 * 清理和验证用户输入
	 */
	public static function sanitize_language_input( $input ): array {
		if ( ! is_array( $input ) ) {
			return [];
		}
		
		return array_filter( 
			array_map( 'sanitize_text_field', $input ),
			[ self::class, 'is_valid_language_code' ]
		);
	}
	
	/**
	 * 获取当前请求的语言偏好
	 */
	public static function get_request_language_preference(): ?string {
		// 从 URL 参数获取
		$url_lang = $_GET['variant'] ?? null;
		if ( $url_lang && self::is_valid_language_code( $url_lang ) ) {
			return $url_lang;
		}
		
		// 从 Cookie 获取
		$cookie_lang = $_COOKIE[ 'wpcc_variant_' . COOKIEHASH ] ?? null;
		if ( $cookie_lang && self::is_valid_language_code( $cookie_lang ) ) {
			return $cookie_lang;
		}
		
		// 从浏览器 Accept-Language 头获取
		$accept_lang = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
		if ( $accept_lang ) {
			return self::parse_accept_language( $accept_lang );
		}
		
		return null;
	}
	
	/**
	 * 解析 Accept-Language 头
	 */
	private static function parse_accept_language( string $accept_language ): ?string {
		$supported_langs = [
			'zh-cn' => ['zh-cn', 'zh-hans', 'zh'],
			'zh-tw' => ['zh-tw', 'zh-hant'],
			'zh-hk' => ['zh-hk'],
			'zh-sg' => ['zh-sg']
		];
		
		// 解析语言偏好
		preg_match_all( '/([a-z]{2,3}(?:-[a-z]{2,4})?)\s*(?:;\s*q\s*=\s*([\d.]+))?/i', 
			$accept_language, $matches, PREG_SET_ORDER );
		
		$preferences = [];
		foreach ( $matches as $match ) {
			$lang = strtolower( $match[1] );
			$quality = isset( $match[2] ) ? (float) $match[2] : 1.0;
			$preferences[ $lang ] = $quality;
		}
		
		// 按质量值排序
		arsort( $preferences );
		
		// 匹配支持的语言
		foreach ( $preferences as $lang => $quality ) {
			foreach ( $supported_langs as $supported => $aliases ) {
				if ( in_array( $lang, $aliases, true ) ) {
					return $supported;
				}
			}
		}
		
		return null;
	}
	
	/**
	 * 记录性能指标
	 */
	public static function log_performance_metric( string $operation, float $duration, array $context = [] ): void {
		if ( ! WP_DEBUG || ! defined( 'WPCC_LOG_PERFORMANCE' ) || ! WPCC_LOG_PERFORMANCE ) {
			return;
		}
		
		$log_entry = sprintf(
			'WPCC Performance: %s took %.4fs %s',
			$operation,
			$duration,
			$context ? '(' . json_encode( $context ) . ')' : ''
		);
		
		error_log( $log_entry );
	}
	
	/**
	 * 执行带性能监控的操作
	 */
	public static function with_performance_monitoring( string $operation, callable $callback, array $context = [] ) {
		$start_time = microtime( true );
		
		try {
			$result = $callback();
			return $result;
		} finally {
			$duration = microtime( true ) - $start_time;
			self::log_performance_metric( $operation, $duration, $context );
		}
	}
}