<?php

/**
 * WP Chinese Converter - Core Functions
 *
 * 包含所有前台转换相关功能
 *
 * @package WPChineseConverter
 * @version 1.2.0
 */

// 防止直接访问
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// 全局变量初始化
$wpcc_langs = array();

/**
 * 初始化语言配置
 */
function wpcc_init_languages(): void {
	global $wpcc_langs;

	if ( empty( $wpcc_langs ) ) {
		$wpcc_langs = [
			'zh-cn' => [ 'zhconversion_cn', 'cntip', __( '简体中文', 'wp-chinese-converter' ), 'zh-CN' ],
			'zh-tw' => [ 'zhconversion_tw', 'twtip', __( '台灣正體', 'wp-chinese-converter' ), 'zh-TW' ],
			'zh-hk' => [ 'zhconversion_hk', 'hktip', __( '港澳繁體', 'wp-chinese-converter' ), 'zh-HK' ],
			'zh-hans' => [ 'zhconversion_hans', 'hanstip', __( '简体中文', 'wp-chinese-converter' ), 'zh-Hans' ],
			'zh-hant' => [ 'zhconversion_hant', 'hanttip', __( '繁体中文', 'wp-chinese-converter' ), 'zh-Hant' ],
			'zh-sg' => [ 'zhconversion_sg', 'sgtip', __( '马新简体', 'wp-chinese-converter' ), 'zh-SG' ],
			'zh-jp' => [ 'zhconversion_jp', 'jptip', __( '日式汉字', 'wp-chinese-converter' ), 'zh-JP' ],
		];
	}
}

/**
 * 插件核心初始化
 */
function wpcc_init() {
	global $wpcc_options, $wp_rewrite;

	if ( isset( $wpcc_options['wpcc_use_permalink'] ) && $wpcc_options['wpcc_use_permalink'] != 0 && empty( $wp_rewrite->permalink_structure ) ) {
		$wpcc_options['wpcc_use_permalink'] = 0;
		update_wpcc_option( 'wpcc_options', $wpcc_options );
	}

	if ( $wpcc_options['wpcc_use_permalink'] != 0 ) {
		add_filter( 'rewrite_rules_array', 'wpcc_rewrite_rules' );
	}

	// 处理评论提交的转换
	if ( ( isset( $_SERVER['PHP_SELF'] ) && ( strpos( $_SERVER['PHP_SELF'], 'wp-comments-post.php' ) !== false
	       || strpos( $_SERVER['PHP_SELF'], 'ajax-comments.php' ) !== false
	       || strpos( $_SERVER['PHP_SELF'], 'comments-ajax.php' ) !== false )
	     ) &&
	     isset( $_SERVER["REQUEST_METHOD"] ) && $_SERVER["REQUEST_METHOD"] == "POST" &&
	     isset( $_POST['variant'] ) && ! empty( $_POST['variant'] ) && in_array( $_POST['variant'], $wpcc_options['wpcc_used_langs'] )
	) {
		global $wpcc_target_lang;
		$wpcc_target_lang = $_POST['variant'];
		wpcc_do_conversion();
		return;
	}

	// 修复首页显示Page时的问题
	if ( 'page' == get_option( 'show_on_front' ) && get_option( 'page_on_front' ) ) {
		add_action( 'parse_query', 'wpcc_parse_query_fix' );
	}

	add_action( 'parse_request', 'wpcc_parse_query' );
	add_action( 'template_redirect', 'wpcc_template_redirect', -100 );
	add_action( 'init', function() {
		wpcc_init_languages();
		wpcc_init_modules();
	}, 1 );

	add_filter( 'render_block', 'wpcc_render_no_conversion_block', 5, 2 );
}

/**
 * 向WordPress查询变量中添加variant参数
 */
function wpcc_insert_query_vars( $vars ) {
	array_push( $vars, 'variant' );
	return $vars;
}

/**
 * 修复首页显示Page时繁简转换页的问题
 */
function wpcc_parse_query_fix( $this_WP_Query ) {
	$qv = &$this_WP_Query->query_vars;

	if ( $this_WP_Query->is_home && 'page' == get_option( 'show_on_front' ) && get_option( 'page_on_front' ) ) {
		$_query = wp_parse_args( $this_WP_Query->query );
		if ( isset( $_query['pagename'] ) && '' == $_query['pagename'] ) {
			unset( $_query['pagename'] );
		}
		if ( empty( $_query ) || ! array_diff( array_keys( $_query ), array(
				'preview', 'page', 'paged', 'cpage', 'variant'
			) ) ) {
			$this_WP_Query->is_page = true;
			$this_WP_Query->is_home = false;
			$qv['page_id'] = get_option( 'page_on_front' );
			if ( ! empty( $qv['paged'] ) ) {
				$qv['page'] = $qv['paged'];
				unset( $qv['paged'] );
			}
		}
	}

	// 其他查询修复逻辑...
	return $this_WP_Query;
}

/**
 * 解析当前请求，获取目标语言
 */
function wpcc_parse_query( $query ) {
	if ( is_robots() ) {
		return;
	}

	global $wpcc_target_lang, $wpcc_redirect_to, $wpcc_noconversion_url, $wpcc_options, $wpcc_direct_conversion_flag;

	if ( ! is_404() ) {
		$wpcc_noconversion_url = wpcc_get_noconversion_url();
	} else {
		$wpcc_noconversion_url = get_option( 'home' ) . '/';
		$wpcc_target_lang = false;
		return;
	}

	$request_lang = isset( $query->query_vars['variant'] ) ? $query->query_vars['variant'] : '';
	$cookie_lang = isset( $_COOKIE[ 'wpcc_variant_' . COOKIEHASH ] ) ? $_COOKIE[ 'wpcc_variant_' . COOKIEHASH ] : '';

	if ( $request_lang && in_array( $request_lang, $wpcc_options['wpcc_used_langs'] ) ) {
		$wpcc_target_lang = $request_lang;
	} else {
		$wpcc_target_lang = false;
	}

	// 处理重定向逻辑
	if ( ! $wpcc_target_lang ) {
		if ( $request_lang == 'zh' && ! is_admin() ) {
			if ( $wpcc_options['wpcc_use_cookie_variant'] != 0 ) {
				setcookie( 'wpcc_variant_' . COOKIEHASH, 'zh', time() + 30000000, COOKIEPATH, COOKIE_DOMAIN );
			} else {
				setcookie( 'wpcc_is_redirect_' . COOKIEHASH, '1', 0, COOKIEPATH, COOKIE_DOMAIN );
			}
			header( 'Location: ' . $wpcc_noconversion_url );
			die();
		}

		// Cookie和浏览器语言检测逻辑...
	}

	// 搜索转换逻辑
	if ( $wpcc_options['wpcc_search_conversion'] == 2 ||
	     ( $wpcc_target_lang && $wpcc_options['wpcc_search_conversion'] == 1 )
	) {
		wpcc_apply_filter_search_rule();
	}

	// 设置Cookie
	if ( $wpcc_target_lang && $wpcc_options['wpcc_use_cookie_variant'] != 0 && $cookie_lang != $wpcc_target_lang ) {
		setcookie( 'wpcc_variant_' . COOKIEHASH, $wpcc_target_lang, time() + 30000000, COOKIEPATH, COOKIE_DOMAIN );
	}
}

/**
 * 模板重定向处理 - 核心转换逻辑
 */
function wpcc_template_redirect() {
	global $wpcc_noconversion_url, $wpcc_langs_urls, $wpcc_options, $wpcc_target_lang, $wpcc_redirect_to;

	set_wpcc_langs_urls();

	if ( ! is_404() && $wpcc_redirect_to && ! is_admin() ) {
		setcookie( 'wpcc_is_redirect_' . COOKIEHASH, '1', 0, COOKIEPATH, COOKIE_DOMAIN );
		wp_redirect( $wpcc_langs_urls[ $wpcc_redirect_to ], 302 );
	}

	if ( ! $wpcc_target_lang ) {
		return;
	}

	// 添加评论表单语言参数
	add_action( 'comment_form', 'wpcc_modify_comment_form' );
	function wpcc_modify_comment_form() {
		global $wpcc_target_lang;
		echo '<input type="hidden" name="variant" value="' . $wpcc_target_lang . '" />';
	}

	wpcc_do_conversion();
}

/**
 * 设置各语言版本的URL
 */
function set_wpcc_langs_urls() {
	global $wpcc_langs_urls, $wpcc_options, $wpcc_noconversion_url;

	if ( ! $wpcc_langs_urls ) {
		if ( $wpcc_noconversion_url == get_option( 'home' ) . '/' && $wpcc_options['wpcc_use_permalink'] ) {
			foreach ( $wpcc_options['wpcc_used_langs'] as $value ) {
				$wpcc_langs_urls[ $value ] = $wpcc_noconversion_url . $value . '/';
			}
		} else {
			foreach ( $wpcc_options['wpcc_used_langs'] as $value ) {
				$wpcc_langs_urls[ $value ] = wpcc_link_conversion( $wpcc_noconversion_url, $value );
			}
		}
	}
}

/**
 * 获取当前页面原始URL
 */
function wpcc_get_noconversion_url() {
	global $wpcc_options;
	$reg = implode( '|', $wpcc_options['wpcc_used_langs'] );
	$tmp = ( is_ssl() ? 'https://' : 'http://' ) .
	       ( isset( $_SERVER['HTTP_HOST'] ) ? $_SERVER['HTTP_HOST'] : '' ) .
	       ( isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '' );
	$tmp = trim( strtolower( remove_query_arg( 'variant', $tmp ) ) );

	if ( preg_match( '/^(.*)\/(' . $reg . '|zh|zh-reset)(\/.*)?$/', $tmp, $matches ) ) {
		$tmp = user_trailingslashit( trailingslashit( $matches[1] ) . ltrim( $matches[3] ?? '', '/' ) );
		if ( $tmp == get_option( 'home' ) ) {
			$tmp .= '/';
		}
	}

	return $tmp;
}

/**
 * 转换链接到指定语言版本
 */
function wpcc_link_conversion( $link, $variant = null ) {
	global $wpcc_options;

	static $wpcc_wp_home;
	if ( empty( $wpcc_wp_home ) ) {
		$wpcc_wp_home = home_url();
	}

	if ( $variant === null ) {
		$variant = $GLOBALS['wpcc_target_lang'];
	}
	if ( $variant == false ) {
		return $link;
	}
	if ( str_contains( $link, $variant ) ) {
		return $link;
	}

	if ( str_contains( $link, '?' ) || ! ($wpcc_options['wpcc_use_permalink'] ?? 0) ) {
		return add_query_arg( 'variant', $variant, $link );
	}

	if ( $wpcc_options['wpcc_use_permalink'] == 1 ) {
		return user_trailingslashit( trailingslashit( $link ) . $variant );
	}
	if ( is_multisite() && wpcc_mobile_exist( 'network' ) ) {
		$sites = get_sites();
		foreach ( $sites as $site ) {
			if ( '/' == $site->path ) {
				continue;
			}
			$path = str_replace( '/', '', $site->path );
			$sub_url = "$site->domain/$path";
			if ( str_contains( $link, $sub_url ) ) {
				return str_replace( $sub_url, "$sub_url/$variant", $link );
			}
		}
	}

	return str_replace( $wpcc_wp_home, "$wpcc_wp_home/$variant", $link );
}

/**
 * 修改重写规则
 */
function wpcc_rewrite_rules( $rules ) {
	global $wpcc_options;
	$reg = implode( '|', $wpcc_options['wpcc_used_langs'] );
	$rules2 = array();

	if ( $wpcc_options['wpcc_use_permalink'] == 1 ) {
		foreach ( $rules as $key => $value ) {
			if ( strpos( $key, 'trackback' ) !== false || strpos( $key, 'print' ) !== false || strpos( $value, 'lang=' ) !== false ) {
				continue;
			}
			if ( substr( $key, -3 ) == '/?$' ) {
				if ( ! preg_match_all( '/\$matches\[(\d+)\]/', $value, $matches, PREG_PATTERN_ORDER ) ) {
					continue;
				}
				$number = count( $matches[0] ) + 1;
				$rules2[ substr( $key, 0, -3 ) . '/(' . $reg . '|zh|zh-reset)/?$' ] = $value . '&variant=$matches[' . $number . ']';
			}
		}
	} else {
		foreach ( $rules as $key => $value ) {
			if ( strpos( $key, 'trackback' ) !== false || strpos( $key, 'print' ) !== false || strpos( $value, 'lang=' ) !== false ) {
				continue;
			}
			if ( substr( $key, -3 ) == '/?$' ) {
				$rules2[ '(' . $reg . '|zh|zh-reset)/' . $key ] = preg_replace_callback( '/\$matches\[(\d+)\]/', '_wpcc_permalink_preg_callback', $value ) . '&variant=$matches[1]';
			}
		}
	}

	$rules2[ '^(' . $reg . '|zh|zh-reset)/?$' ] = 'index.php?variant=$matches[1]';
	return array_merge( $rules2, $rules );
}

/**
 * URL重写回调函数
 */
function _wpcc_permalink_preg_callback( $matches ) {
	return '$matches[' . ( intval( $matches[1] ) + 1 ) . ']';
}

/**
 * 核心转换函数
 */
function zhconversion( ?string $str, ?string $variant = null ): string {
	global $wpcc_options, $wpcc_langs;
	wpcc_init_languages();

	if ( $str === null || $str === '' ) {
		return $str;
	}

	if ( $variant === null ) {
		$variant = $GLOBALS['wpcc_target_lang'];
	}

	if ( $variant == false ) {
		return $str;
	}

	if ( !isset( $wpcc_langs[ $variant ] ) ) {
		return $str;
	}

	// 检查缓存
	$cached_result = WPCC_Conversion_Cache::get_cached_conversion( $str, $variant );
	if ( $cached_result !== null ) {
		return $cached_result;
	}

	return WPCC_Exception_Handler::safe_execute(
		function() use ( $str, $variant ) {
			$converter = WPCC_Converter_Factory::get_converter();
			$result = $converter->convert( $str, $variant );
			
			// 将结果存入缓存
			if ( $result !== $str ) { // 只缓存真正有变化的转换
				WPCC_Conversion_Cache::set_cached_conversion( $str, $variant, $result );
			}
			
			return $result;
		},
		$str, // 降级值：返回原文本
		"zhconversion_{$variant}"
	);
}

/**
 * 带排除标记的转换函数
 */
function zhconversion2( $str, $variant = null ) {
	global $wpcc_options, $wpcc_langs;

	wpcc_init_languages();

	if ( $variant === null ) {
		$variant = $GLOBALS['wpcc_target_lang'];
	}
	if ( $variant == false ) {
		return $str;
	}

	if ( !isset( $wpcc_langs[ $variant ] ) || !isset( $wpcc_langs[ $variant ][0] ) || !is_callable( $wpcc_langs[ $variant ][0] ) ) {
		return $str;
	}

	return limit_zhconversion( $str, $wpcc_langs[ $variant ][0] );
}

/**
 * 各种特定语言的转换函数
 */
function zhconversion_hant( ?string $str ): string {
	if ( $str === null || $str === '' ) {
		return $str ?? '';
	}
	try {
		$converter = WPCC_Converter_Factory::get_converter();
		return $converter->convert( $str, 'zh-hant' );
	} catch ( Exception $e ) {
		error_log( 'WPCC zhconversion_hant Error: ' . $e->getMessage() );
		return $str;
	}
}

function zhconversion_hans( $str ) {
	if ( $str === null || $str === '' ) {
		return $str;
	}
	try {
		$converter = WPCC_Converter_Factory::get_converter();
		return $converter->convert( $str, 'zh-hans' );
	} catch ( Exception $e ) {
		error_log( 'WPCC zhconversion_hans Error: ' . $e->getMessage() );
		return $str;
	}
}

function zhconversion_cn( $str ) {
	if ( $str === null || $str === '' ) {
		return $str;
	}
	try {
		$converter = WPCC_Converter_Factory::get_converter();
		return $converter->convert( $str, 'zh-cn' );
	} catch ( Exception $e ) {
		error_log( 'WPCC zhconversion_cn Error: ' . $e->getMessage() );
		return $str;
	}
}

function zhconversion_tw( $str ) {
	if ( $str === null || $str === '' ) {
		return $str;
	}
	try {
		$converter = WPCC_Converter_Factory::get_converter();
		return $converter->convert( $str, 'zh-tw' );
	} catch ( Exception $e ) {
		error_log( 'WPCC zhconversion_tw Error: ' . $e->getMessage() );
		return $str;
	}
}

function zhconversion_jp( $str ) {
	if ( $str === null || $str === '' ) {
		return $str;
	}
	try {
		$converter = WPCC_Converter_Factory::get_converter();
		return $converter->convert( $str, 'zh-jp' );
	} catch ( Exception $e ) {
		error_log( 'WPCC zhconversion_jp Error: ' . $e->getMessage() );
		return $str;
	}
}

function zhconversion_hk( $str ) {
	if ( $str === null || $str === '' ) {
		return $str;
	}
	try {
		$converter = WPCC_Converter_Factory::get_converter();
		return $converter->convert( $str, 'zh-hk' );
	} catch ( Exception $e ) {
		error_log( 'WPCC zhconversion_hk Error: ' . $e->getMessage() );
		return $str;
	}
}

function zhconversion_sg( $str ) {
	if ( $str === null || $str === '' ) {
		return $str;
	}
	try {
		$converter = WPCC_Converter_Factory::get_converter();
		return $converter->convert( $str, 'zh-sg' );
	} catch ( Exception $e ) {
		error_log( 'WPCC zhconversion_sg Error: ' . $e->getMessage() );
		return $str;
	}
}

/**
 * 递归转换数组
 */
function zhconversion_deep( $value ) {
	$value = is_array( $value ) ? array_map( 'zhconversion_deep', $value ) : zhconversion( $value );
	return $value;
}

/**
 * 有限转换函数 - 不转换指定标签内的内容
 */
function limit_zhconversion( $str, $function ) {
	if ( $m = preg_split( '/(<!--wpcc_NC([a-zA-Z0-9]*)_START-->)(.*?)(<!--wpcc_NC\2_END-->)/s', $str, - 1, PREG_SPLIT_DELIM_CAPTURE ) ) {
		$r = '';
		$count = 0;
		foreach ( $m as $v ) {
			$count ++;
			if ( $count % 5 == 1 ) {
				$r .= $function ( $v );
			} else if ( $count % 5 == 4 ) {
				$r .= $v;
			}
		}
		return $r;
	} else {
		return $function( $str );
	}
}

/**
 * 转换到多种语言并返回数组
 */
function zhconversion_all( $str, $langs = array( 'zh-tw', 'zh-cn', 'zh-hk', 'zh-sg', 'zh-hans', 'zh-hant' ) ) {
	global $wpcc_langs;
	$return = array();
	foreach ( $langs as $value ) {
		if ( ! isset( $wpcc_langs[ $value ] ) ) {
			continue;
		}
		$tmp = $wpcc_langs[ $value ][0] ( $str );
		if ( $tmp != $str ) {
			$return[] = $tmp;
		}
	}
	return array_unique( $return );
}

/**
 * 输出导航选择器
 */
function wpcc_output_navi( $args = '', $isReturn = false ) {
	global $wpcc_target_lang, $wpcc_noconversion_url, $wpcc_langs_urls, $wpcc_langs, $wpcc_options;
	wpcc_init_languages();

	extract( wp_parse_args( $args, array( 'mode' => 'normal', 'echo' => 1 ) ) );
	if ( $mode == 'wrap' ) {
		wpcc_output_navi2();
		return;
	}

	if ( ! empty( $wpcc_options['nctip'] ) ) {
		$noconverttip = $wpcc_options['nctip'];
	} else {
		$locale = str_replace( '_', '-', strtolower( get_locale() ) );
		if ( in_array( $locale, array( 'zh-hant', 'zh-tw', 'zh-hk', 'zh-mo' ) ) ) {
			$noconverttip = '不转换';
		} else {
			$noconverttip = '不转换';
		}
	}
	if ( $wpcc_target_lang ) {
		$noconverttip = zhconversion( $noconverttip );
	}

	if ( ( $wpcc_options['wpcc_browser_redirect'] == 2 || $wpcc_options['wpcc_use_cookie_variant'] == 2 ) &&
	     $wpcc_target_lang
	) {
		$default_url = wpcc_link_conversion( $wpcc_noconversion_url, 'zh' );
		if ( $wpcc_options['wpcc_use_permalink'] != 0 && is_home() && ! is_paged() ) {
			$default_url = trailingslashit( $default_url );
		}
	} else {
		$default_url = $wpcc_noconversion_url;
	}

	$wpcc_translate_type = $wpcc_options['wpcc_translate_type'] ?? 0;

	$output = "\n" . '<div id="wpcc_widget_inner"><!--wpcc_NC_START-->' . "\n";
	if ( $wpcc_translate_type == 0 ) {
		$output .= '	<span id="wpcc_original_link" class="' . ( $wpcc_target_lang == false ? 'wpcc_current_lang' : 'wpcc_lang' ) . '" ><a class="wpcc_link" href="' . esc_url( $default_url ) . '" title="' . esc_html( $noconverttip ) . '" langvar="">' . esc_html( $noconverttip ) . '</a></span>' . "\n";

		foreach ( $wpcc_langs_urls as $key => $value ) {
			if ( !isset( $wpcc_langs[ $key ] ) || !isset( $wpcc_langs[ $key ][1] ) || !isset( $wpcc_langs[ $key ][2] ) ) {
				continue;
			}
			$tip = ! empty( $wpcc_options[ $wpcc_langs[ $key ][1] ] ) ? esc_html( $wpcc_options[ $wpcc_langs[ $key ][1] ] ) : $wpcc_langs[ $key ][2];
			if ( $wpcc_target_lang ) {
				$tip = zhconversion( $tip );
			}
			$safe_key = esc_attr( $key );
			$output .= '	<span id="wpcc_' . $safe_key . '_link" class="' . ( $wpcc_target_lang == $key ? 'wpcc_current_lang' : 'wpcc_lang' ) . '" ><a class="wpcc_link" rel="nofollow" href="' . esc_url( $value ) . '" title="' . esc_attr( $tip ) . '" langvar="' . $safe_key . '">' . esc_html( $tip ) . '</a></span>' . "\n";
		}
	} else if ( $wpcc_translate_type == 1 ) {
		$checkSelected = function ( $selected_lang ) use ( $wpcc_target_lang ) {
			return $selected_lang == $wpcc_target_lang ? "selected" : "";
		};
		$output .= sprintf( '<select id="wpcc_translate_type" value="%s" onchange="wpccRedirectToPage(this)">', $wpcc_translate_type );
		$output .= sprintf( '<option id="wpcc_original_link" value="" %s>%s</option>', $checkSelected( '' ), esc_html( $noconverttip ) );
		foreach ( $wpcc_langs_urls as $key => $value ) {
			if ( !isset( $wpcc_langs[ $key ] ) || !isset( $wpcc_langs[ $key ][1] ) || !isset( $wpcc_langs[ $key ][2] ) ) {
				continue;
			}
			$tip = ! empty( $wpcc_options[ $wpcc_langs[ $key ][1] ] ) ? esc_html( $wpcc_options[ $wpcc_langs[ $key ][1] ] ) : $wpcc_langs[ $key ][2];
			if ( $wpcc_target_lang ) {
				$tip = zhconversion( $tip );
			}
			$safe_key = esc_attr( $key );
			$output .= sprintf( '<option id="wpcc_%s_link" class="%s" value="%s" %s>%s</option>', $safe_key, esc_attr( $wpcc_target_lang == $key ? 'wpcc_current_lang' : 'wpcc_lang' ), $safe_key, $checkSelected( $key ), esc_html( $tip ) );
		}
		$output .= sprintf( '</select>' );
	}
	$output .= '<!--wpcc_NC_END--></div>' . "\n";

	if ( ! $echo || $isReturn ) {
		return $output;
	}
	echo $output;
}

/**
 * 另一种导航输出方式
 */
function wpcc_output_navi2() {
	global $wpcc_target_lang, $wpcc_noconversion_url, $wpcc_langs_urls, $wpcc_options;

	if ( ( $wpcc_options['wpcc_browser_redirect'] == 2 || $wpcc_options['wpcc_use_cookie_variant'] == 2 ) &&
	     $wpcc_target_lang
	) {
		$default_url = wpcc_link_conversion( $wpcc_noconversion_url, 'zh' );
		if ( $wpcc_options['wpcc_use_permalink'] != 0 && is_home() && ! is_paged() ) {
			$default_url = trailingslashit( $default_url );
		}
	} else {
		$default_url = $wpcc_noconversion_url;
	}

	$output = "\n" . '<div id="wpcc_widget_inner"><!--wpcc_NC_START-->' . "\n";
	$output .= '	<span id="wpcc_original_link" class="' . ( $wpcc_target_lang == false ? 'wpcc_current_lang' : 'wpcc_lang' ) . '" ><a class="wpcc_link" href="' . esc_url( $default_url ) . '" title="' . esc_html( '不转换' ) . '">' . esc_html( '不转换' ) . '</a></span>' . "\n";
	$output .= '	<span id="wpcc_cn_link" class="' . ( $wpcc_target_lang == 'zh-cn' ? 'wpcc_current_lang' : 'wpcc_lang' ) . '" ><a class="wpcc_link" rel="nofollow" href="' . esc_url( $wpcc_langs_urls['zh-cn'] ) . '" title="' . esc_html( '大陆简体' ) . '" >' . esc_html( '大陆简体' ) . '</a></span>' . "\n";
	$output .= '	<span id="wpcc_tw_link" class="' . ( $wpcc_target_lang == 'zh-tw' ? 'wpcc_current_lang' : 'wpcc_lang' ) . '"><a class="wpcc_link" rel="nofollow" href="' . esc_url( $wpcc_langs_urls['zh-tw'] ) . '" title="' . esc_html( '台湾正体' ) . '" >' . esc_html( '台湾正体' ) . '</a></span>' . "\n";
	$output .= '<!--wpcc_NC_END--></div>' . "\n";
	echo $output;
}

/**
 * 短码处理函数
 */
function wp_chinese_converter_shortcode(): string {
	set_wpcc_langs_urls();
	return wpcc_output_navi( '', true );
}

/**
 * 小部件类
 */
class wpcc_Widget extends WP_Widget {
	public function __construct() {
		parent::__construct( 'widget_wpcc', 'WP Chinese Converter', [
			'classname'   => 'widget_wpcc',
			'description' => 'WP Chinese Converter Widget'
		] );
	}

	public function widget( $args, $instance ): void {
		extract( $args );
		$title = apply_filters( 'widget_title', $instance['title'] ?? '' );
		echo $before_widget;
		if ( $title ) {
			echo $before_title . esc_html( $title ) . $after_title;
		}
		$widget_args = isset( $instance['args'] ) ? sanitize_text_field( $instance['args'] ) : '';
		wpcc_output_navi( $widget_args );
		echo $after_widget;
	}

	public function update( $new_instance, $old_instance ): array {
		return $new_instance;
	}

	public function form( $instance ): void {
		$title = isset( $instance['title'] ) ? esc_attr( $instance['title'] ) : '';
		$args = isset( $instance['args'] ) ? esc_attr( $instance['args'] ) : '';
		?>
        <p>
            <label for="<?php echo $this->get_field_id( 'title' ); ?>">Title: <input class="widefat"
                                                                                     id="<?php echo $this->get_field_id( 'title' ); ?>"
                                                                                     name="<?php echo $this->get_field_name( 'title' ); ?>"
                                                                                     type="text"
                                                                                     value="<?php echo $title; ?>"/></label>
            <label for="<?php echo $this->get_field_id( 'args' ); ?>">Args: <input class="widefat"
                                                                                   id="<?php echo $this->get_field_id( 'args' ); ?>"
                                                                                   name="<?php echo $this->get_field_name( 'args' ); ?>"
                                                                                   type="text"
                                                                                   value="<?php echo $args; ?>"/></label>
        </p>
		<?php
	}
}

/**
 * 获取浏览器首选语言
 */
function wpcc_get_prefered_language( string $accept_languages, array $target_langs, int $flag = 0 ): string|false {
	$langs = array();
	preg_match_all( '/([a-z]{1,8}(-[a-z]{1,8})?)\s*(;\s*q\s*=\s*(1|0\.[0-9]+))?/i', $accept_languages, $lang_parse );

	if ( count( $lang_parse[1] ) ) {
		$langs = array_combine( $lang_parse[1], $lang_parse[4] );
		foreach ( $langs as $lang => $val ) {
			if ( $val === '' ) {
				$langs[ $lang ] = '1';
			}
		}
		arsort( $langs, SORT_NUMERIC );
		$langs = array_keys( $langs );
		$langs = array_map( 'strtolower', $langs );

		foreach ( $langs as $val ) {
			if ( in_array( $val, $target_langs ) ) {
				return $val;
			}
		}

		if ( $flag ) {
			$array = array( 'zh-hans', 'zh-cn', 'zh-sg', 'zh-my' );
			$a = array_intersect( $array, $target_langs );
			if ( ! empty( $a ) ) {
				$b = array_intersect( $array, $langs );
				if ( ! empty( $b ) ) {
					return current( $a );
				}
			}

			$array = array( 'zh-hant', 'zh-tw', 'zh-hk', 'zh-mo' );
			$a = array_intersect( $array, $target_langs );
			if ( ! empty( $a ) ) {
				$b = array_intersect( $array, $langs );
				if ( ! empty( $b ) ) {
					return current( $a );
				}
			}
		}

		return false;
	}

	return false;
}

/**
 * 判断是否为搜索引擎访问
 */
function wpcc_is_robot(): bool {
	if ( empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
		return true;
	}
	$ua = strtoupper( $_SERVER['HTTP_USER_AGENT'] );

	$robots = array( 'bot', 'spider', 'crawler', 'dig', 'search', 'find' );

	foreach ( $robots as $key => $val ) {
		if ( strstr( $ua, strtoupper( $val ) ) ) {
			return true;
		}
	}

	$browsers = array(
		"compatible; MSIE", "UP.Browser", "Mozilla", "Opera", "NSPlayer", "Avant Browser", "Chrome", "Gecko", "Safari", "Lynx"
	);

	foreach ( $browsers as $key => $val ) {
		if ( strstr( $ua, strtoupper( $val ) ) ) {
			return false;
		}
	}

	return true;
}

/**
 * 应用搜索过滤规则
 */
function wpcc_apply_filter_search_rule() {
	add_filter( 'posts_where', 'wpcc_filter_search_rule', 100 );
	function search_distinct() {
		return "DISTINCT";
	}
	add_filter( 'posts_distinct', 'search_distinct' );
}

/**
 * 搜索过滤规则
 */
function wpcc_filter_search_rule( $where ) {
	global $wp_query, $wpdb;
	
	if ( empty( $wp_query->query_vars['s'] ) || empty( $wp_query->query_vars['search_terms'] ) ) {
		return $where;
	}
	
	// 检查是否包含中文字符
	if ( ! preg_match( '/[\x{4e00}-\x{9fff}]+/u', $wp_query->query_vars['s'] ) ) {
		return $where;
	}

	wpcc_load_conversion_table();

	$sql_parts = array();
	$original_parts = array();
	
	foreach ( $wp_query->query_vars['search_terms'] as $term ) {
		// 安全处理搜索词
		$safe_term = sanitize_text_field( $term );
		if ( empty( $safe_term ) ) {
			continue;
		}
		
		// 构建原始搜索条件（用于替换）
		$original_condition = $wpdb->prepare(
			"(({$wpdb->posts}.post_title LIKE %s) OR ({$wpdb->posts}.post_excerpt LIKE %s) OR ({$wpdb->posts}.post_content LIKE %s))",
			'%' . $wpdb->esc_like( $safe_term ) . '%',
			'%' . $wpdb->esc_like( $safe_term ) . '%',
			'%' . $wpdb->esc_like( $safe_term ) . '%'
		);
		$original_parts[] = $original_condition;
		
		// 获取转换后的变体
		$variants = zhconversion_all( $safe_term );
		$variants[] = $safe_term; // 包含原始词
		$variants = array_unique( array_filter( $variants ) );
		
		$variant_conditions = array();
		foreach ( $variants as $variant ) {
			$safe_variant = sanitize_text_field( $variant );
			if ( empty( $safe_variant ) ) {
				continue;
			}
			
			$variant_conditions[] = $wpdb->prepare(
				"({$wpdb->posts}.post_title LIKE %s OR {$wpdb->posts}.post_content LIKE %s OR {$wpdb->posts}.post_excerpt LIKE %s)",
				'%' . $wpdb->esc_like( $safe_variant ) . '%',
				'%' . $wpdb->esc_like( $safe_variant ) . '%',
				'%' . $wpdb->esc_like( $safe_variant ) . '%'
			);
		}
		
		if ( ! empty( $variant_conditions ) ) {
			$sql_parts[] = '(' . implode( ' OR ', $variant_conditions ) . ')';
		}
	}

	if ( empty( $sql_parts ) || empty( $original_parts ) ) {
		return $where;
	}
	
	// 安全地替换原始查询
	$original_pattern = implode( ' AND ', $original_parts );
	$replacement_sql = implode( ' AND ', $sql_parts );
	
	$where = str_replace( $original_pattern, $replacement_sql, $where );

	return $where;
}

/**
 * 载入转换表
 */
function wpcc_load_conversion_table() {
	global $wpcc_options;
	if ( ! empty( $wpcc_options['wpcc_no_conversion_ja'] ) || ! empty( $wpcc_options['wpcc_no_conversion_tag'] ) ) {
		if ( ! function_exists( 'str_get_html' ) ) {
			require_once __DIR__ . '/core/simple_html_dom.php';
		}
	}

	global $zh2Hans;
	if ( $zh2Hans == false ) {
		global $zh2Hant, $zh2TW, $zh2CN, $zh2SG, $zh2HK;
		require_once __DIR__ . '/core/ZhConversion.php';
		if ( file_exists( WP_CONTENT_DIR . '/extra_zhconversion.php' ) ) {
			require_once( WP_CONTENT_DIR . '/extra_zhconversion.php' );
		}
	}
}

/**
 * 执行转换
 */
function wpcc_do_conversion() {
	global $wpcc_direct_conversion_flag, $wpcc_options;
	wpcc_load_conversion_table();

	add_action( 'wp_head', 'wpcc_header' );

	if ( ! $wpcc_direct_conversion_flag ) {
		remove_action( 'wp_head', 'rel_canonical' );
		add_action( 'wp_head', 'wpcc_rel_canonical' );

		add_filter( 'post_link', 'wpcc_link_conversion' );
		add_filter( 'month_link', 'wpcc_link_conversion' );
		add_filter( 'day_link', 'wpcc_link_conversion' );
		add_filter( 'year_link', 'wpcc_link_conversion' );
		add_filter( 'page_link', 'wpcc_link_conversion' );
		add_filter( 'tag_link', 'wpcc_link_conversion' );
		add_filter( 'author_link', 'wpcc_link_conversion' );
		add_filter( 'category_link', 'wpcc_link_conversion' );
		add_filter( 'feed_link', 'wpcc_link_conversion' );
		add_filter( 'attachment_link', 'wpcc_link_conversion' );
		add_filter( 'search_feed_link', 'wpcc_link_conversion' );

		add_filter( 'category_feed_link', 'wpcc_fix_link_conversion' );
		add_filter( 'tag_feed_link', 'wpcc_fix_link_conversion' );
		add_filter( 'author_feed_link', 'wpcc_fix_link_conversion' );
		add_filter( 'post_comments_feed_link', 'wpcc_fix_link_conversion' );
		add_filter( 'get_comments_pagenum_link', 'wpcc_fix_link_conversion' );
		add_filter( 'get_comment_link', 'wpcc_fix_link_conversion' );

		add_filter( 'attachment_link', 'wpcc_cancel_link_conversion' );
		add_filter( 'trackback_url', 'wpcc_cancel_link_conversion' );

		add_filter( 'get_pagenum_link', 'wpcc_pagenum_link_fix' );
		add_filter( 'redirect_canonical', 'wpcc_cancel_incorrect_redirect', 10, 2 );
	}

	if ( ! empty( $wpcc_options['wpcc_no_conversion_ja'] ) || ! empty( $wpcc_options['wpcc_no_conversion_tag'] ) ) {
		add_filter( 'the_content', 'wpcc_no_conversion_filter', 15 );
		add_filter( 'the_content_rss', 'wpcc_no_conversion_filter', 15 );
	}

	if ( $wpcc_options['wpcc_use_fullpage_conversion'] == 1 ) {
		@ob_start( 'wpcc_ob_callback' );
		return;
	}

	add_filter( 'the_content', 'zhconversion2', 20 );
	add_filter( 'the_content_rss', 'zhconversion2', 20 );
	add_filter( 'the_excerpt', 'zhconversion2', 20 );
	add_filter( 'the_excerpt_rss', 'zhconversion2', 20 );

	add_filter( 'the_title', 'zhconversion' );
	add_filter( 'comment_text', 'zhconversion' );
	add_filter( 'bloginfo', 'zhconversion' );
	add_filter( 'the_tags', 'zhconversion_deep' );
	add_filter( 'term_links-post_tag', 'zhconversion_deep' );
	add_filter( 'wp_tag_cloud', 'zhconversion' );
	add_filter( 'the_category', 'zhconversion' );
	add_filter( 'list_cats', 'zhconversion' );
	add_filter( 'category_description', 'zhconversion' );
	add_filter( 'single_cat_title', 'zhconversion' );
	add_filter( 'single_post_title', 'zhconversion' );
	add_filter( 'bloginfo_rss', 'zhconversion' );
	add_filter( 'the_title_rss', 'zhconversion' );
	add_filter( 'comment_text_rss', 'zhconversion' );
}

/**
 * 输出头部信息
 */
function wpcc_header() {
	global $wpcc_target_lang, $wpcc_langs_urls, $wpcc_noconversion_url, $wpcc_direct_conversion_flag;
	
	echo "\n" . '<!-- WP Chinese Converter Plugin Version ' . esc_html( wpcc_VERSION ) . ' -->';
	
	$script_data = array(
		'wpcc_target_lang' => $wpcc_target_lang ? esc_js( $wpcc_target_lang ) : '',
		'wpcc_noconversion_url' => $wpcc_noconversion_url ? esc_url( $wpcc_noconversion_url ) : '',
		'wpcc_langs_urls' => array()
	);
	
	if ( is_array( $wpcc_langs_urls ) ) {
		foreach ( $wpcc_langs_urls as $key => $value ) {
			$safe_key = preg_match( '/^[a-z-]+$/', $key ) ? $key : '';
			if ( $safe_key && filter_var( $value, FILTER_VALIDATE_URL ) ) {
				$script_data['wpcc_langs_urls'][ $safe_key ] = esc_url( $value );
			}
		}
	}
	
	echo '<script type="text/javascript">';
	echo '/* <![CDATA[ */';
	echo 'var wpcc_target_lang="' . $script_data['wpcc_target_lang'] . '";';
	echo 'var wpcc_noconversion_url="' . $script_data['wpcc_noconversion_url'] . '";';
	echo 'var wpcc_langs_urls=' . wp_json_encode( $script_data['wpcc_langs_urls'] ) . ';';
	echo '/* ]]> */';
	echo '</script>';

	if ( ! $wpcc_direct_conversion_flag ) {
		wp_enqueue_script( 'wpcc-search-js', wpcc_DIR_URL . 'assets/js/search-variant.min.js', array(), '1.1', false );
	}

	if ( $wpcc_direct_conversion_flag ||
	     ( ( class_exists( 'All_in_One_SEO_Pack' ) || class_exists( 'Platinum_SEO_Pack' ) ) &&
	       ! is_single() && ! is_home() && ! is_page() && ! is_search() )
	) {
		return;
	}
}

/**
 * 输出回调函数
 */
function wpcc_ob_callback( $buffer ) {
	global $wpcc_target_lang, $wpcc_direct_conversion_flag;
	if ( $wpcc_target_lang && ! $wpcc_direct_conversion_flag ) {
		$wpcc_home_url = wpcc_link_conversion_auto( home_url( '/' ) );
		$buffer = preg_replace( '|(<a\s(?!class="wpcc_link")[^<>]*?href=([\'"]))' . preg_quote( esc_url( home_url( '' ) ), '|' ) . '/?(\2[^<>]*?>)|', '\\1' . esc_url( $wpcc_home_url ) . '\\3', $buffer );
	}
	return zhconversion2( $buffer ) . "\n" . '<!-- WP WP Chinese Converter Full Page Converted. Target Lang: ' . $wpcc_target_lang . ' -->';
}

/**
 * 过滤器 - 不转换指定标签内容
 */
function wpcc_no_conversion_filter( $str ) {
	global $wpcc_options;

	$html = str_get_html( $str );
	if ( $html == false ) {
		return $str;
	}
	$query = '';

	if ( ! empty( $wpcc_options['wpcc_no_conversion_ja'] ) ) {
		$query .= '*[lang="ja"]';
	}

	if ( ! empty( $wpcc_options['wpcc_no_conversion_tag'] ) ) {
		if ( $query != '' ) {
			$query .= ',';
		}
		if ( preg_match( '/^[a-z1-9|]+$/', $wpcc_options['wpcc_no_conversion_tag'] ) ) {
			$query .= str_replace( '|', ',', $wpcc_options['wpcc_no_conversion_tag'] );
		} else {
			$query .= $wpcc_options['wpcc_no_conversion_tag'];
		}
	}

	$elements = $html->find( $query );
	if ( count( $elements ) == 0 ) {
		return $str;
	}
	foreach ( $elements as $element ) {
		$id = wpcc_id();
		$element->innertext = '<!--wpcc_NC' . $id . '_START-->' . $element->innertext . '<!--wpcc_NC' . $id . '_END-->';
	}

	return (string) $html;
}

/**
 * 获取唯一ID
 */
function wpcc_id() {
	global $_wpcc_id;
	return $_wpcc_id ++;
}

/**
 * 修复链接转换
 */
function wpcc_fix_link_conversion( $link ) {
	global $wpcc_options;
	if ( $wpcc_options['wpcc_use_permalink'] == 1 ) {
		if ( $flag = strstr( $link, '#' ) ) {
			$link = substr( $link, 0, - strlen( $flag ) );
		}
		if ( preg_match( '/^(.*\/)(zh-tw|zh-cn|zh-sg|zh-hant|zh-hans|zh-my|zh-mo|zh-hk|zh|zh-reset)\/(.+)$/', $link, $tmp ) ) {
			return user_trailingslashit( $tmp[1] . trailingslashit( $tmp[3] ) . $tmp[2] ) . $flag;
		}
		return $link . $flag;
	} else if ( $wpcc_options['wpcc_use_permalink'] == 0 ) {
		if ( preg_match( '/^(.*)\?variant=([-a-zA-Z]+)\/(.*)$/', $link, $tmp ) ) {
			return add_query_arg( 'variant', $tmp[2], trailingslashit( $tmp[1] ) . $tmp[3] );
		}
		return $link;
	} else {
		return $link;
	}
}

/**
 * 取消链接转换
 */
function wpcc_cancel_link_conversion( $link ) {
	global $wpcc_options;
	if ( $wpcc_options['wpcc_use_permalink'] ) {
		if ( preg_match( '/^(.*\/)(zh-tw|zh-cn|zh-sg|zh-hant|zh-hans|zh-my|zh-mo|zh-hk|zh|zh-reset)\/(.+)$/', $link, $tmp ) ) {
			return $tmp[1] . $tmp[3];
		}
		return $link;
	} else {
		if ( preg_match( '/^(.*)\?variant=[-a-zA-Z]+\/(.*)$/', $link, $tmp ) ) {
			return trailingslashit( $tmp[1] ) . $tmp[2];
		}
		return $link;
	}
}

/**
 * 修复分页链接
 */
function wpcc_pagenum_link_fix( $link ) {
	global $wpcc_target_lang, $wpcc_options;
	global $paged;
	if ( $wpcc_options['wpcc_use_permalink'] != 1 ) {
		return $link;
	}

	if ( preg_match( '/^(.*)\/page\/\d+\/' . $wpcc_target_lang . '\/page\/(\d+)\/?$/', $link, $tmp ) ||
	     preg_match( '/^(.*)\/' . $wpcc_target_lang . '\/page\/(\d+)\/?$/', $link, $tmp ) ) {
		return user_trailingslashit( $tmp[1] . '/page/' . $tmp[2] . '/' . $wpcc_target_lang );
	} else if ( preg_match( '/^(.*)\/page\/(\d+)\/' . $wpcc_target_lang . '\/?$/', $link, $tmp ) && $tmp[2] == 2 && $paged == 2 ) {
		if ( $tmp[1] == get_option( 'home' ) ) {
			return $tmp[1] . '/' . $wpcc_target_lang . '/';
		}
		return user_trailingslashit( $tmp[1] . '/' . $wpcc_target_lang );
	}
	return $link;
}

/**
 * 取消错误重定向
 */
function wpcc_cancel_incorrect_redirect( $redirect_to, $redirect_from ) {
	global $wp_rewrite;
	if ( preg_match( '/^.*\/(zh-tw|zh-cn|zh-sg|zh-hant|zh-hans|zh-my|zh-mo|zh-hk|zh|zh-reset)\/?.+$/', $redirect_to ) ) {
		if ( ( $wp_rewrite->use_trailing_slashes && substr( $redirect_from, - 1 ) != '/' ) ||
		     ( ! $wp_rewrite->use_trailing_slashes && substr( $redirect_from, - 1 ) == '/' )
		) {
			return user_trailingslashit( $redirect_from );
		}
		return false;
	}
	return $redirect_to;
}

/**
 * 自动转换链接
 */
function wpcc_link_conversion_auto( $link, $variant = null ) {
	global $wpcc_target_lang, $wpcc_direct_conversion_flag, $wpcc_options;

	if ( $link == home_url( '' ) ) {
		$link .= '/';
	}
	if ( ! $wpcc_target_lang || $wpcc_direct_conversion_flag ) {
		return $link;
	} else {
		if ( $link == home_url( '/' ) && ! empty( $wpcc_options['wpcc_use_permalink'] ) ) {
			return trailingslashit( wpcc_link_conversion( $link ) );
		}
		return wpcc_link_conversion( $link );
	}
}

/**
 * 正确链接标签
 */
function wpcc_rel_canonical() {
	if ( ! is_singular() ) {
		return;
	}
	global $wp_the_query;
	if ( ! $id = $wp_the_query->get_queried_object_id() ) {
		return;
	}
	$link = wpcc_cancel_link_conversion( get_permalink( $id ) );
	echo "<link rel='canonical' href='$link' />\n";
}

/**
 * body class 过滤器
 */
function wpcc_body_class( $classes ) {
	global $wpcc_target_lang;
	$classes[] = $wpcc_target_lang ? $wpcc_target_lang : "zh";
	return $classes;
}

/**
 * 语言属性过滤器
 */
function wpcc_locale( $output, $doctype = 'html' ) {
	global $wpcc_target_lang, $wpcc_langs;

	wpcc_init_languages();

	$lang = get_bloginfo( 'language' );
	if ( $wpcc_target_lang && strpos( $lang, 'zh-' ) === 0 && isset( $wpcc_langs[ $wpcc_target_lang ] ) && isset( $wpcc_langs[ $wpcc_target_lang ][3] ) ) {
		$lang = $wpcc_langs[ $wpcc_target_lang ][3];
		$output = preg_replace( '/lang="[^"]+"/', "lang=\"{$lang}\"", $output );
	}
	return $output;
}

/**
 * 处理不转换内容区块
 */
function wpcc_render_no_conversion_block( $block_content, $block ) {
	if ( isset( $block['blockName'] ) && $block['blockName'] === 'wpcc/no-conversion' ) {
		$unique_id = uniqid();

		$pattern = '/<div[^>]*class="[^"]*wpcc-no-conversion-content[^"]*"[^>]*>(.*?)<\/div>/s';

		$replacement = function ( $matches ) use ( $unique_id ) {
			$content = $matches[1];
			return '<div class="wpcc-no-conversion-content"><!--wpcc_NC' . $unique_id . '_START-->' . $content . '<!--wpcc_NC' . $unique_id . '_END--></div>';
		};

		$block_content = preg_replace_callback( $pattern, $replacement, $block_content );
	}

	return $block_content;
}

/**
 * 初始化模块
 */
function wpcc_init_modules() {
	$module_manager = WPCC_Module_Manager::get_instance();

	$module_manager->register_module( 'WPCC_Cache_Addon', dirname( __FILE__ ) . '/../modules/wpcc-cache-addon.php' );
	$module_manager->register_module( 'WPCC_Network', dirname( __FILE__ ) . '/../modules/wpcc-network.php' );
	$module_manager->register_module( 'WPCC_Rest_Api', dirname( __FILE__ ) . '/../modules/wpcc-rest-api.php' );
	$module_manager->register_module( 'WPCC_Modern_Cache', dirname( __FILE__ ) . '/../modules/wpcc-modern-cache.php' );
	$module_manager->register_module( 'WPCC_SEO_Enhancement', dirname( __FILE__ ) . '/../modules/wpcc-seo-enhancement.php' );

	$module_manager->auto_discover_modules();
}

/**
 * 获取语言属性
 */
function variant_attribute( $default = "zh", $variant = false ) {
	global $wpcc_langs;

	wpcc_init_languages();

	if ( ! $variant ) {
		$variant = $GLOBALS['wpcc_target_lang'];
	}
	if ( ! $variant ) {
		return $default;
	}

	if ( !isset( $wpcc_langs[ $variant ] ) || !isset( $wpcc_langs[ $variant ][3] ) ) {
		return $default;
	}

	return $wpcc_langs[ $variant ][3];
}

/**
 * 获取当前语言代码
 */
function variant( $default = false ) {
	global $wpcc_target_lang;
	if ( ! $wpcc_target_lang ) {
		return $default;
	}
	return $wpcc_target_lang;
}

// 注册钩子
add_shortcode( 'wp-chinese-converter', 'wp_chinese_converter_shortcode' );
add_filter( "body_class", "wpcc_body_class" );
add_filter( 'language_attributes', 'wpcc_locale' );

// 初始化小部件
if ( ! empty( $wpcc_options ) && is_array( $wpcc_options ) && is_array( $wpcc_options['wpcc_used_langs'] ) ) {
	add_action( 'widgets_init', function () {
		return register_widget( 'wpcc_Widget' );
	}, 1 );
}