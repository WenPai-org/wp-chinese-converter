<?php

/**
 * WP Chinese Converter Admin Panel Functions
 *
 * 管理后台相关功能
 *
 * @package WPChineseConverter
 * @version 1.4
 */

// 添加管理员功能：手动刷新重写规则
add_action( 'wp_ajax_wpcc_flush_rewrite_rules', 'wpcc_ajax_flush_rewrite_rules' );
function wpcc_ajax_flush_rewrite_rules() {
    // 权限检查
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( '无权限操作' );
    }
    
    // 验证nonce
    if ( ! wp_verify_nonce( $_POST['nonce'], 'wpcc_admin_nonce' ) ) {
        wp_die( '安全验证失败' );
    }
    
    // 刷新重写规则
    flush_rewrite_rules( false );
    update_option( 'wpcc_rewrite_autoflush_ts', time() );
    
    wp_send_json_success( '重写规则已刷新' );
}

// 添加管理面板提示
add_action( 'admin_notices', 'wpcc_rewrite_rules_notice' );
function wpcc_rewrite_rules_notice() {
    // 仅在WPCC设置页面显示
    $screen = get_current_screen();
    if ( ! $screen || strpos( $screen->id, 'wpcc' ) === false ) {
        return;
    }
    
    global $wpcc_options;
    if ( empty( $wpcc_options['wpcc_use_permalink'] ) ) {
        return;
    }
    
    // 检查是否存在语言规则
    $rules = get_option( 'rewrite_rules', [] );
    $enabled_langs = $wpcc_options['wpcc_used_langs'] ?? [];
    if ( empty( $enabled_langs ) ) {
        return;
    }
    
    $reg = implode( '|', $enabled_langs );
    $expected = '^(' . $reg . '|zh|zh-reset)/?$';
    $has_rule = false;
    
    foreach ( $rules as $regex => $query ) {
        if ( $regex === $expected && strpos( $query, 'variant=' ) !== false ) {
            $has_rule = true;
            break;
        }
    }
    
    if ( ! $has_rule ) {
        echo '<div class="notice notice-warning is-dismissible">';
        echo '<p><strong>WP Chinese Converter:</strong> 检测到语言重写规则可能未正确配置，这可能导致语言主页404错误。 ';
        echo '<a href="#" id="wpcc-flush-rules" class="button button-secondary">刷新重写规则</a></p>';
        echo '</div>';
        
        // 添加JavaScript
        echo '<script type="text/javascript">
jQuery(document).ready(function($) {
    $("#wpcc-flush-rules").on("click", function(e) {
        e.preventDefault();
        var button = $(this);
        button.prop("disabled", true).text("刷新中...");
        
        $.post(ajaxurl, {
            action: "wpcc_flush_rewrite_rules",
            nonce: "' . wp_create_nonce( 'wpcc_admin_nonce' ) . '"
        }, function(response) {
            if (response.success) {
                button.closest(".notice").fadeOut();
                $(".wrap h1").after("<div class=\"notice notice-success is-dismissible\"><p>重写规则已成功刷新！请测试语言主页是否能正常访问。</p></div>");
            } else {
                alert("刷新失败：" + response.data);
                button.prop("disabled", false).text("刷新重写规则");
            }
        }).fail(function() {
            alert("请求失败，请稍后再试");
            button.prop("disabled", false).text("刷新重写规则");
        });
    });
});
</script>';
    }
}

// 防止直接访问
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 初始化管理员界面
 */
function wpcc_admin_init() {
	global $wpcc_admin;
	require_once __DIR__ . '/admin/class-wpcc-admin.php';
	$wpcc_admin = new wpcc_Admin();
	add_filter( 'plugin_action_links', array( $wpcc_admin, 'action_links' ), 10, 2 );
}

/**
 * 插件激活时执行
 */
function wpcc_activate() {
	$current_options = (array) get_wpcc_option( 'wpcc_options' );
	$wpcc_options = array(
		'wpcc_search_conversion'       => 1,
		'wpcc_used_langs'              => array( 'zh-hans', 'zh-hant', 'zh-cn', 'zh-hk', 'zh-sg', 'zh-tw' ),
		'wpcc_browser_redirect'        => 0,
		'wpcc_auto_language_recong'    => 0,
		'wpcc_flag_option'             => 1,
		'wpcc_use_cookie_variant'      => 0,
		'wpcc_use_fullpage_conversion' => 1,
		'wpcco_use_sitemap'            => 1,
		'wpcc_trackback_plugin_author' => 0,
		'wpcc_add_author_link'         => 0,
		'wpcc_use_permalink'           => 0,
		'wpcc_no_conversion_tag'       => '',
		'wpcc_no_conversion_ja'        => 0,
		'wpcc_no_conversion_qtag'      => 0,
		'wpcc_engine'                  => 'mediawiki',
		'nctip'                        => '',
	);

	foreach ( $current_options as $key => $value ) {
		if ( isset( $wpcc_options[ $key ] ) ) {
			$wpcc_options[ $key ] = $value;
		}
	}

	foreach (
		array(
			'zh-hans' => "hanstip",
			'zh-hant' => "hanttip",
			'zh-cn'   => "cntip",
			'zh-hk'   => "hktip",
			'zh-sg'   => "sgtip",
			'zh-tw'   => "twtip",
			'zh-my'   => "mytip",
			'zh-mo'   => "motip",
			'zh-jp'   => "jptip"
		) as $lang => $tip
	) {
		if ( ! empty( $current_options[ $tip ] ) ) {
			$wpcc_options[ $tip ] = $current_options[ $tip ];
		}
	}

	update_wpcc_option( 'wpcc_options', $wpcc_options );
}

/**
 * 添加编辑器快速标签
 */
function wpcc_appthemes_add_quicktags() {
	global $wpcc_options;
	if ( ! empty( $wpcc_options ) && ! empty( $wpcc_options['wpcc_no_conversion_qtag'] ) ) {
		?>
        <script type="text/javascript">
            //<![CDATA[
            jQuery(document).ready(function($) {
                if (typeof QTags !== 'undefined' && QTags.addButton) {
                    QTags.addButton('eg_wpcc_nc', 'wpcc_NC', '[wpcc_nc]', '[/wpcc_nc]', null, 'WP Chinese Converter: Insert no-convert markers', 120);
                } else {
                    setTimeout(function() {
                        if (typeof QTags !== 'undefined' && QTags.addButton) {
                            QTags.addButton('eg_wpcc_nc', 'wpcc_NC', '[wpcc_nc]', '[/wpcc_nc]', null, 'WP Chinese Converter: Insert no-convert markers', 120);
                        }
                    }, 100);
                }
            });
            //]]>
        </script>
		<?php
	}
}

/**
 * 初始化文章转换功能
 */
function wpcc_init_post_conversion() {
	global $wpcc_options;

	if ( empty( $wpcc_options ) ) {
		$wpcc_options = get_wpcc_option( 'wpcc_options' );
	}

	if ( ! empty( $wpcc_options['wpcc_enable_post_conversion'] ) ) {
		$target_lang = $wpcc_options['wpcc_post_conversion_target'] ?? 'zh-cn';

		/**
		 * 在保存时安全地转换块内容：
		 * - 跳过 WPCC 自有区块（wpcc/*），避免破坏区块占位和结构
		 * - 仅转换非 WPCC 区块的纯文本片段（innerContent 字符串等）
		 */
		add_filter( 'content_save_pre', function ( $content ) use ( $target_lang ) {
			if ( empty( $content ) ) {
				return $content;
			}
			try {
				return wpcc_convert_post_content_safely( $content, $target_lang );
			} catch ( Exception $e ) {
				error_log( 'WPCC Content Conversion Error: ' . $e->getMessage() );
				return $content;
			}
		} );

		add_filter( 'title_save_pre', function ( $title ) use ( $target_lang ) {
			if ( empty( $title ) ) {
				return $title;
			}
			try {
				return zhconversion( $title, $target_lang );
			} catch ( Exception $e ) {
				error_log( 'WPCC Title Conversion Error: ' . $e->getMessage() );
				return $title;
			}
		} );

		add_action( 'add_meta_boxes', 'wpcc_add_conversion_meta_box' );
	}
}

/**
 * 使用区块解析安全转换文章内容，仅转换非 WPCC 区块的纯文本
 */
function wpcc_convert_post_content_safely( $content, $target_lang ) {
	if ( ! function_exists( 'parse_blocks' ) || ! function_exists( 'serialize_blocks' ) ) {
		// 回退：无法解析区块时，使用整体转换（可能导致占位被转换）
		return zhconversion( $content, $target_lang );
	}

	$blocks = parse_blocks( $content );
	$converted = wpcc_convert_blocks_array_safely( $blocks, $target_lang );
	return serialize_blocks( $converted );
}

function wpcc_convert_blocks_array_safely( $blocks, $target_lang ) {
	foreach ( $blocks as &$block ) {
		$name = isset( $block['blockName'] ) ? (string) $block['blockName'] : '';

		// 跳过 WPCC 自有区块，避免转换其内部占位与结构
		if ( substr( $name, 0, 5 ) === 'wpcc/' ) {
			// 递归处理其子块（如不希望转换子块，也可以直接 continue）
			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				$block['innerBlocks'] = wpcc_convert_blocks_array_safely( $block['innerBlocks'], $target_lang );
			}
			continue;
		}

		// 转换非 WPCC 区块的纯文本内容
		if ( isset( $block['innerContent'] ) && is_array( $block['innerContent'] ) ) {
			foreach ( $block['innerContent'] as $i => $piece ) {
				if ( is_string( $piece ) && $piece !== '' ) {
					$block['innerContent'][ $i ] = zhconversion( $piece, $target_lang );
				}
			}
		}

		if ( isset( $block['innerHTML'] ) && is_string( $block['innerHTML'] ) && $block['innerHTML'] !== '' ) {
			$block['innerHTML'] = zhconversion( $block['innerHTML'], $target_lang );
		}

		// 递归处理子块
		if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
			$block['innerBlocks'] = wpcc_convert_blocks_array_safely( $block['innerBlocks'], $target_lang );
		}
	}
	return $blocks;
}

/**
 * 添加转换设置元框
 */
function wpcc_add_conversion_meta_box() {
	add_meta_box(
		'wpcc-conversion-meta-box',
		'文派译词',
		'wpcc_conversion_meta_box_callback',
		array( 'post', 'page' ),
		'side',
		'default'
	);
}

/**
 * 获取语言模块配置
 * 使用中心化的语言配置管理
 */
function wpcc_get_language_config() {
	global $wpcc_options;
	
	// 使用中心化的语言配置
	return WPCC_Language_Config::get_custom_names( $wpcc_options );
}

/**
 * 元框回调函数
 */
function wpcc_conversion_meta_box_callback( $post ) {
	global $wpcc_options;
	$target_lang = $wpcc_options['wpcc_post_conversion_target'] ?? 'zh-cn';
	$enabled_langs = $wpcc_options['wpcc_used_langs'] ?? array();

	$default_names = array(
		'zh-cn' => '中国大陆',
		'zh-tw' => '台湾正体',
		'zh-hk' => '港澳繁体',
		'zh-hans' => '简体中文',
		'zh-hant' => '繁体中文',
		'zh-sg' => '马新简体',
		'zh-jp' => '日式汉字'
	);

	$is_enabled = !empty($wpcc_options['wpcc_enable_post_conversion']);
	
	if (!$is_enabled) {
		echo '<p>发表时自动转换功能已禁用。</p>';
		echo '<p><a href="' . admin_url('admin.php?page=wp-chinese-converter') . '">前往设置启用</a></p>';
		return;
	}

	if (empty($enabled_langs)) {
		echo '<p>未启用任何语言模块。</p>';
		echo '<p><a href="' . admin_url('admin.php?page=wp-chinese-converter') . '">前往设置启用语言模块</a></p>';
		return;
	}

	if (!in_array($target_lang, $enabled_langs)) {
		echo '<p style="color: #d63638;">当前转换目标语言未启用。</p>';
		echo '<p><a href="' . admin_url('admin.php?page=wp-chinese-converter') . '">前往设置修改</a></p>';
		return;
	}

	$target_display_name = isset($default_names[$target_lang]) ? $default_names[$target_lang] . ' (' . $target_lang . ')' : $target_lang;
	echo '<p><strong>目标：</strong>' . $target_display_name . '</p>';
	
	$engine = $wpcc_options['wpcc_engine'] ?? 'mediawiki';
	$engine_names = array(
		'mediawiki' => 'MediaWiki',
		'opencc' => 'OpenCC'
	);
	echo '<p><strong>引擎：</strong>' . ($engine_names[$engine] ?? $engine) . '</p>';
	
	echo '<p><small>发表时将自动转换标题和内容。<a target="_blank" href="' . admin_url('admin.php?page=wp-chinese-converter') . '">修改设置 ↗</a></small></p>';
}

/**
 * 获取首页slug
 */
function get_home_page_slug() {
	$frontpage_id = get_option( 'page_on_front' );

	if ( $frontpage_id ) {
		$frontpage = get_post( $frontpage_id );
		return $frontpage->post_name;
	}

	return null;
}

/**
 * 防止首页重定向
 */
function prevent_home_redirect() {
	if ( is_page( get_home_page_slug() ) ) {
		remove_action( 'template_redirect', 'redirect_canonical' );
	}
}

/**
 * 为导航区块添加语言后缀
 */
function add_suffix_to_links( $html ) {
	$pattern = '/(<a\s+[^>]*href=["\'])([^"\']*)(["\'][^>]*>)/i';

	$result = preg_replace_callback( $pattern, function ( $matches ) {
		$href = $matches[2];
		$new_href = wpcc_link_conversion( $href );
		return $matches[1] . $new_href . $matches[3];
	}, $html );

	return $result;
}

/**
 * 自定义区块渲染
 */
function custom_render_block( $block_content, $block ) {
	if ( $block['blockName'] === 'core/navigation' ) {
		return add_suffix_to_links( $block_content );
	}
	return $block_content;
}

/**
 * AJAX处理函数 - 清除缓存
 */
function my_ajax_clear_cache_handler() {
	// 验证nonce
	if ( ! check_ajax_referer( 'wpcc_clear_cache_nonce', 'nonce', false ) ) {
		wp_die( '安全验证失败' );
	}

	// 验证权限
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( '权限不足' );
	}

	// 清除缓存逻辑
	if ( function_exists( 'wp_cache_clean_cache' ) ) {
		wp_cache_clean_cache( '' );
	}

	wp_die( '缓存已清除' );
}


// 注册管理钩子
// 注意：网络管理菜单现在由 wpcc-network-settings.php 处理
if ( is_multisite() && wpcc_mobile_exist( 'network' ) ) {
	// add_action( 'network_admin_menu', 'wpcc_admin_init' ); // 已被新的网络设置模块替代
	add_action( 'admin_menu', 'wpcc_admin_init' );
} else {
	add_action( 'admin_menu', 'wpcc_admin_init' );
}

// 注册激活钩子
register_activation_hook( dirname( __DIR__ ) . '/wp-chinese-converter.php', 'wpcc_activate' );

// 注册编辑器增强钩子
add_action( 'admin_print_footer_scripts', 'wpcc_appthemes_add_quicktags' );

// TinyMCE 可视化编辑器按钮（仅在开启“快速标签”选项时添加按钮；短代码始终可用）
function wpcc_register_tinymce_plugin( $plugins ) {
    global $wpcc_options;
    if ( empty( $wpcc_options ) ) {
        $wpcc_options = get_wpcc_option( 'wpcc_options' );
    }
    // 仅当设置开启时添加按钮脚本
    if ( ! empty( $wpcc_options['wpcc_no_conversion_qtag'] ) ) {
        $plugins['wpcc_nc'] = wpcc_DIR_URL . 'assets/js/tinymce-wpcc-nc.js';
    }
    return $plugins;
}
function wpcc_add_tinymce_button( $buttons ) {
    global $wpcc_options;
    if ( empty( $wpcc_options ) ) {
        $wpcc_options = get_wpcc_option( 'wpcc_options' );
    }
    if ( ! empty( $wpcc_options['wpcc_no_conversion_qtag'] ) ) {
        $buttons[] = 'wpcc_nc';
    }
    return $buttons;
}
add_filter( 'mce_external_plugins', 'wpcc_register_tinymce_plugin' );
add_filter( 'mce_buttons', 'wpcc_add_tinymce_button' );

// 注册文章转换钩子
add_action( 'init', 'wpcc_init_post_conversion' );

// 注册防重定向钩子
add_action( 'template_redirect', 'prevent_home_redirect', 0 );

// 注册区块渲染钩子
add_filter( 'render_block', 'custom_render_block', 10, 2 );

// 注册AJAX钩子
add_action( 'wp_ajax_my_action', 'my_ajax_clear_cache_handler' );

// 调试函数
if ( defined( 'WP_DEBUG' ) && WP_DEBUG || defined( 'wpcc_DEBUG' ) && wpcc_DEBUG ) {
	function wpcc_debug() {
		global $wpcc_noconversion_url, $wpcc_target_lang, $wpcc_langs_urls, $wpcc_debug_data, $wpcc_langs, $wpcc_options, $wp_rewrite;
		echo '<!--';
		echo '<p style="font-size:20px;color:red;">';
		echo 'WP WP Chinese Converter Plugin Debug Output:<br />';
		echo '默认URL: <a href="' . $wpcc_noconversion_url . '">' . $wpcc_noconversion_url . '</a><br />';
		echo '当前语言(空则是不转换): ' . $wpcc_target_lang . "<br />";
		echo 'Query String: ' . ( isset( $_SERVER['QUERY_STRING'] ) ? $_SERVER['QUERY_STRING'] : '' ) . '<br />';
		echo 'Request URI: ' . ( isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '' ) . '<br />';
		foreach ( $wpcc_langs_urls as $key => $value ) {
			echo $key . ' URL: <a href="' . $value . '">' . $value . '</a><br />';
		}
		echo 'Category feed link: ' . get_category_feed_link( 1 ) . '<br />';
		echo 'Search feed link: ' . get_search_feed_link( 'test' );
		echo 'Rewrite Rules: <br />';
		echo nl2br( htmlspecialchars( var_export( $wp_rewrite->rewrite_rules(), true ) ) ) . '<br />';
		echo 'Debug Data: <br />';
		echo nl2br( htmlspecialchars( var_export( $wpcc_debug_data, true ) ) );
		echo '</p>';
		echo '-->';
	}

	add_action( 'wp_footer', 'wpcc_debug' );
}