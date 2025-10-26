<?php

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WPCC异常基类
 */
class WPCC_Exception extends Exception {
	protected $error_code;
	protected $context;
	
	public function __construct( string $message = '', string $error_code = '', array $context = [], int $code = 0, ?Throwable $previous = null ) {
		parent::__construct( $message, $code, $previous );
		$this->error_code = $error_code;
		$this->context = $context;
	}
	
	public function get_error_code(): string {
		return $this->error_code;
	}
	
	public function get_context(): array {
		return $this->context;
	}
}

/**
 * 转换异常
 */
class WPCC_Conversion_Exception extends WPCC_Exception {
	public function __construct( string $message, string $variant = '', string $text_preview = '', ?Throwable $previous = null ) {
		$context = [
			'variant' => $variant,
			'text_preview' => mb_substr( $text_preview, 0, 50 ) . ( mb_strlen( $text_preview ) > 50 ? '...' : '' )
		];
		
		parent::__construct( $message, 'CONVERSION_ERROR', $context, 0, $previous );
	}
}

/**
 * 缓存异常
 */
class WPCC_Cache_Exception extends WPCC_Exception {
	public function __construct( string $message, string $cache_key = '', ?Throwable $previous = null ) {
		$context = [ 'cache_key' => $cache_key ];
		parent::__construct( $message, 'CACHE_ERROR', $context, 0, $previous );
	}
}

/**
 * 配置异常
 */
class WPCC_Config_Exception extends WPCC_Exception {
	public function __construct( string $message, string $config_key = '', $config_value = null, ?Throwable $previous = null ) {
		$context = [ 
			'config_key' => $config_key,
			'config_value' => is_scalar( $config_value ) ? $config_value : gettype( $config_value )
		];
		parent::__construct( $message, 'CONFIG_ERROR', $context, 0, $previous );
	}
}

/**
 * WPCC异常处理器
 */
final class WPCC_Exception_Handler {
	
	private static $error_counts = [];
	private static $max_errors_per_type = 10;
	private static $error_log_enabled = true;
	private static $fallback_strategies = [];
	
	/**
	 * 初始化异常处理器
	 */
	public static function init(): void {
		// 注册默认的降级策略
		self::register_fallback_strategy( 'CONVERSION_ERROR', [ self::class, 'conversion_fallback' ] );
		self::register_fallback_strategy( 'CACHE_ERROR', [ self::class, 'cache_fallback' ] );
		self::register_fallback_strategy( 'CONFIG_ERROR', [ self::class, 'config_fallback' ] );
	}
	
	/**
	 * 安全地执行操作，带异常处理
	 */
	public static function safe_execute( callable $operation, $fallback_value = null, string $operation_name = 'unknown' ) {
		try {
			return $operation();
		} catch ( WPCC_Exception $e ) {
			return self::handle_wpcc_exception( $e, $fallback_value, $operation_name );
		} catch ( Throwable $e ) {
			return self::handle_generic_exception( $e, $fallback_value, $operation_name );
		}
	}
	
	/**
	 * 处理WPCC特定异常
	 */
	public static function handle_wpcc_exception( WPCC_Exception $exception, $fallback_value = null, string $operation_name = 'unknown' ) {
		$error_code = $exception->get_error_code();
		
		// 记录错误
		self::log_error( $exception, $operation_name );
		
		// 检查错误频率限制
		if ( self::should_suppress_error( $error_code ) ) {
			return $fallback_value;
		}
		
		// 尝试执行降级策略
		if ( isset( self::$fallback_strategies[ $error_code ] ) ) {
			try {
				return call_user_func( self::$fallback_strategies[ $error_code ], $exception, $fallback_value );
			} catch ( Throwable $fallback_error ) {
				self::log_error( $fallback_error, "fallback_for_{$operation_name}" );
			}
		}
		
		return $fallback_value;
	}
	
	/**
	 * 处理通用异常
	 */
	public static function handle_generic_exception( Throwable $exception, $fallback_value = null, string $operation_name = 'unknown' ) {
		self::log_error( $exception, $operation_name );
		
		// 对于严重错误，可能需要通知管理员
		if ( $exception instanceof Error || $exception instanceof ParseError ) {
			self::notify_admin_of_critical_error( $exception, $operation_name );
		}
		
		return $fallback_value;
	}
	
	/**
	 * 注册降级策略
	 */
	public static function register_fallback_strategy( string $error_code, callable $strategy ): void {
		self::$fallback_strategies[ $error_code ] = $strategy;
	}
	
	/**
	 * 转换异常的降级策略
	 */
	public static function conversion_fallback( WPCC_Conversion_Exception $exception, $fallback_value = null ) {
		$context = $exception->get_context();
		$original_text = $context['text_preview'] ?? '';
		
		// 如果有原始文本，返回原文本而不是null
		if ( ! empty( $original_text ) && $original_text !== '...' ) {
			return $original_text;
		}
		
		return $fallback_value;
	}
	
	/**
	 * 缓存异常的降级策略
	 */
	public static function cache_fallback( WPCC_Cache_Exception $exception, $fallback_value = null ) {
		// 缓存问题时，继续执行但不使用缓存
		return $fallback_value;
	}
	
	/**
	 * 配置异常的降级策略
	 */
	public static function config_fallback( WPCC_Config_Exception $exception, $fallback_value = null ) {
		$context = $exception->get_context();
		$config_key = $context['config_key'] ?? '';
		
		// 返回默认配置值
		$defaults = [
			'wpcc_engine' => 'opencc',
			'wpcc_use_fullpage_conversion' => 1,
			'wpcc_search_conversion' => 1,
			'wpcc_used_langs' => [ 'zh-cn', 'zh-tw' ]
		];
		
		return $defaults[ $config_key ] ?? $fallback_value;
	}
	
	/**
	 * 记录错误
	 */
	private static function log_error( Throwable $exception, string $operation_name ): void {
		if ( ! self::$error_log_enabled ) {
			return;
		}
		
		// 统一异常计数键，保证与 should_suppress_error 使用的键一致
		if ( $exception instanceof WPCC_Exception ) {
			$ekey = 'wpcc_error_' . $exception->get_error_code();
			self::$error_counts[ $ekey ] = ( self::$error_counts[ $ekey ] ?? 0 ) + 1;
		} else {
			$ekey = get_class( $exception );
			self::$error_counts[ $ekey ] = ( self::$error_counts[ $ekey ] ?? 0 ) + 1;
		}
		
		$log_message = sprintf(
			'WPCC Error in %s: %s [%s:%d] Context: %s',
			$operation_name,
			$exception->getMessage(),
			basename( $exception->getFile() ),
			$exception->getLine(),
			$exception instanceof WPCC_Exception ? json_encode( $exception->get_context() ) : 'N/A'
		);
		
		error_log( $log_message );
		
		// 在调试模式下记录堆栈跟踪
		if ( WP_DEBUG && defined( 'WPCC_DEBUG_TRACE' ) && WPCC_DEBUG_TRACE ) {
			error_log( 'WPCC Error Stack Trace: ' . $exception->getTraceAsString() );
		}
	}
	
	/**
	 * 检查是否应该抑制错误（防止日志洪水）
	 */
	private static function should_suppress_error( string $error_code ): bool {
		$error_key = "wpcc_error_{$error_code}";
		$count = self::$error_counts[ $error_key ] ?? 0;
		
		return $count > self::$max_errors_per_type;
	}
	
	/**
	 * 通知管理员关键错误
	 */
	private static function notify_admin_of_critical_error( Throwable $exception, string $operation_name ): void {
		// 防止发送过多邮件
		$notification_key = 'wpcc_critical_error_' . md5( $exception->getMessage() );
		$last_notification = get_transient( $notification_key );
		
		if ( $last_notification ) {
			return;
		}
		
		// 设置12小时的冷却期
		set_transient( $notification_key, time(), 12 * HOUR_IN_SECONDS );
		
		$admin_email = get_option( 'admin_email' );
		if ( ! $admin_email ) {
			return;
		}
		
		$subject = sprintf( 'WP Chinese Converter Critical Error on %s', get_bloginfo( 'name' ) );
		$message = sprintf(
			"A critical error occurred in WP Chinese Converter:\n\nOperation: %s\nError: %s\nFile: %s:%d\n\nPlease check your error logs for more details.\n\nTime: %s",
			$operation_name,
			$exception->getMessage(),
			$exception->getFile(),
			$exception->getLine(),
			current_time( 'mysql' )
		);
		
		wp_mail( $admin_email, $subject, $message );
	}
	
	/**
	 * 获取错误统计
	 */
	public static function get_error_stats(): array {
		return [
			'error_counts' => self::$error_counts,
			'total_errors' => array_sum( self::$error_counts ),
			'error_log_enabled' => self::$error_log_enabled,
			'max_errors_per_type' => self::$max_errors_per_type
		];
	}
	
	/**
	 * 重置错误计数
	 */
	public static function reset_error_counts(): void {
		self::$error_counts = [];
	}
	
	/**
	 * 设置错误记录状态
	 */
	public static function set_error_logging( bool $enabled ): void {
		self::$error_log_enabled = $enabled;
	}
	
	/**
	 * 设置每种错误类型的最大记录数
	 */
	public static function set_max_errors_per_type( int $max ): void {
		self::$max_errors_per_type = max( 1, $max );
	}
}

// 初始化异常处理器
WPCC_Exception_Handler::init();