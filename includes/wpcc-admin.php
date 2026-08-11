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
    if ( empty( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wpcc_admin_nonce' ) ) {
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
            nonce: "' . esc_js( wp_create_nonce( 'wpcc_admin_nonce' ) ) . '"
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
	$engine      = $wpcc_options['wpcc_engine'] ?? 'mediawiki';
	$is_enabled = ! empty( $wpcc_options['wpcc_enable_post_conversion'] );
	$names       = array(
		'zh-cn'   => '中国大陆',
		'zh-tw'   => '台湾正体',
		'zh-hk'   => '港澳繁体',
		'zh-hans' => '简体中文',
		'zh-hant' => '繁体中文',
		'zh-sg'   => '马新简体',
		'zh-jp'   => '日式汉字',
	);
	$engine_names = array(
		'mediawiki' => 'MediaWiki',
		'opencc'    => 'OpenCC',
	);

	if ( $is_enabled ) {
		$target_name = isset( $names[ $target_lang ] ) ? $names[ $target_lang ] . ' (' . $target_lang . ')' : $target_lang;
		echo '<p><strong>' . esc_html__( 'Automatic conversion:', 'wp-chinese-converter' ) . '</strong> ' . esc_html__( 'Enabled', 'wp-chinese-converter' ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Target:', 'wp-chinese-converter' ) . '</strong> ' . esc_html( $target_name ) . '<br>';
		echo '<strong>' . esc_html__( 'Engine:', 'wp-chinese-converter' ) . '</strong> ' . esc_html( $engine_names[ $engine ] ?? $engine ) . '</p>';
	} else {
		echo '<p><strong>' . esc_html__( 'Automatic conversion:', 'wp-chinese-converter' ) . '</strong> ' . esc_html__( 'Disabled', 'wp-chinese-converter' ) . '</p>';
	}

	$history = WPCC_Post_Conversion_Backup::get_history( $post->ID );
	if ( is_wp_error( $history ) || empty( $history ) ) {
		echo '<p id="wpcc-conversion-history-empty">' . esc_html__( 'No saved conversion history is available for this post.', 'wp-chinese-converter' ) . '</p>';
		echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=wp-chinese-converter' ) ) . '">' . esc_html__( 'Conversion settings', 'wp-chinese-converter' ) . '</a></p>';
		return;
	}

	?>
	<hr>
	<p><strong><?php esc_html_e( 'Conversion recovery history', 'wp-chinese-converter' ); ?></strong></p>
		<label class="screen-reader-text" for="wpcc-conversion-backup-id"><?php esc_html_e( 'History version', 'wp-chinese-converter' ); ?></label>
		<select id="wpcc-conversion-backup-id" name="backup_id" class="widefat" form="wpcc-conversion-restore-form">
			<?php foreach ( array_reverse( $history ) as $entry ) : ?>
				<?php
				$created = ! empty( $entry['created_at'] ) ? date_i18n( 'Y-m-d H:i:s', (int) $entry['created_at'] ) : __( 'Unknown time', 'wp-chinese-converter' );
				$label   = sprintf(
					/* translators: 1: conversion time, 2: target language, 3: conversion engine. */
					__( '%1$s — %2$s / %3$s', 'wp-chinese-converter' ),
					$created,
					(string) ( $entry['target'] ?? '' ),
					(string) ( $entry['engine'] ?? '' )
				);
				?>
				<option value="<?php echo esc_attr( (string) $entry['id'] ); ?>"><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<p class="description"><?php esc_html_e( 'Dry-run shows the title and content changes without writing. Restore is rejected if the post changed after this screen loaded.', 'wp-chinese-converter' ); ?></p>
		<p>
			<button id="wpcc-conversion-dry-run" type="submit" class="button" name="wpcc_restore_mode" value="dry-run" form="wpcc-conversion-restore-form"><?php esc_html_e( 'Preview field diff', 'wp-chinese-converter' ); ?></button>
			<button id="wpcc-conversion-restore" type="submit" class="button button-secondary" name="wpcc_restore_mode" value="restore" form="wpcc-conversion-restore-form" onclick="return confirm('<?php echo esc_js( __( 'Restore the selected title and content version?', 'wp-chinese-converter' ) ); ?>');"><?php esc_html_e( 'Restore selected version', 'wp-chinese-converter' ); ?></button>
		</p>
	<table class="widefat striped wpcc-conversion-history">
		<thead><tr><th><?php esc_html_e( 'Version', 'wp-chinese-converter' ); ?></th><th><?php esc_html_e( 'Changed fields', 'wp-chinese-converter' ); ?></th></tr></thead>
		<tbody>
		<?php foreach ( array_reverse( $history ) as $entry ) : ?>
			<?php
			$changed = array();
			foreach ( array( 'post_title', 'post_content' ) as $field ) {
				if ( (string) ( $entry['original'][ $field ] ?? '' ) !== (string) ( $entry['converted'][ $field ] ?? '' ) ) {
					$changed[] = $field;
				}
			}
			?>
			<tr data-wpcc-backup-id="<?php echo esc_attr( (string) $entry['id'] ); ?>">
				<td><code><?php echo esc_html( substr( (string) $entry['id'], 0, 12 ) ); ?></code></td>
				<td><?php echo esc_html( implode( ', ', $changed ) ); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<?php
}

/**
 * Render the recovery form outside WordPress' main post-edit form.
 */
function wpcc_render_conversion_restore_form() {
	global $post;

	if ( ! $post instanceof WP_Post || ! current_user_can( 'edit_post', $post->ID ) ) {
		return;
	}
	$history = WPCC_Post_Conversion_Backup::get_history( $post->ID );
	if ( is_wp_error( $history ) || empty( $history ) ) {
		return;
	}
	?>
	<form id="wpcc-conversion-restore-form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" style="display:none">
		<input type="hidden" name="action" value="wpcc_restore_post_conversion">
		<input type="hidden" name="post_id" value="<?php echo esc_attr( (string) $post->ID ); ?>">
		<input type="hidden" name="expected_hash" value="<?php echo esc_attr( WPCC_Post_Conversion_Backup::hash_post( $post ) ); ?>">
		<?php wp_nonce_field( 'wpcc_restore_post_conversion_' . $post->ID, 'wpcc_restore_nonce' ); ?>
	</form>
	<?php
}
add_action( 'admin_footer-post.php', 'wpcc_render_conversion_restore_form' );
add_action( 'admin_footer-post-new.php', 'wpcc_render_conversion_restore_form' );

/**
 * Process an authenticated conversion recovery request from the post editor.
 */
function wpcc_handle_post_conversion_restore() {
	$post_id = isset( $_POST['post_id'] ) && is_string( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;
	if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
		wp_die( esc_html__( 'You are not allowed to restore this post.', 'wp-chinese-converter' ), '', array( 'response' => 403 ) );
	}

	if ( ! isset( $_POST['wpcc_restore_nonce'] ) || ! is_string( $_POST['wpcc_restore_nonce'] ) ) {
		wp_die( esc_html__( 'Invalid recovery nonce.', 'wp-chinese-converter' ), '', array( 'response' => 403 ) );
	}
	check_admin_referer( 'wpcc_restore_post_conversion_' . $post_id, 'wpcc_restore_nonce' );
	$backup_id     = isset( $_POST['backup_id'] ) && is_string( $_POST['backup_id'] ) ? sanitize_key( wp_unslash( $_POST['backup_id'] ) ) : '';
	$expected_hash = isset( $_POST['expected_hash'] ) && is_string( $_POST['expected_hash'] ) ? sanitize_text_field( wp_unslash( $_POST['expected_hash'] ) ) : '';
	$mode          = isset( $_POST['wpcc_restore_mode'] ) && is_string( $_POST['wpcc_restore_mode'] ) ? sanitize_key( wp_unslash( $_POST['wpcc_restore_mode'] ) ) : '';
	$dry_run      = 'dry-run' === $mode;
	if ( ! $dry_run && 'restore' !== $mode ) {
		wp_die( esc_html__( 'Invalid recovery action.', 'wp-chinese-converter' ), '', array( 'response' => 400 ) );
	}

	$result = wpcc_restore_post_conversion( $post_id, $backup_id, $expected_hash, $dry_run );
	if ( is_wp_error( $result ) ) {
		$redirect = add_query_arg( 'wpcc_restore_status', sanitize_key( $result->get_error_code() ), get_edit_post_link( $post_id, 'url' ) );
		wp_safe_redirect( $redirect );
		exit;
	}

	if ( $dry_run ) {
		$html = '<div id="wpcc-restore-dry-run"><h1>' . esc_html__( 'Conversion recovery dry-run', 'wp-chinese-converter' ) . '</h1>';
		$html .= '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Field', 'wp-chinese-converter' ) . '</th><th>' . esc_html__( 'Current value', 'wp-chinese-converter' ) . '</th><th>' . esc_html__( 'Restored value', 'wp-chinese-converter' ) . '</th></tr></thead><tbody>';
		foreach ( $result['diff'] as $field => $change ) {
			$html .= '<tr data-wpcc-field="' . esc_attr( (string) $field ) . '"><th>' . esc_html( (string) $field ) . '</th><td><pre>' . esc_html( (string) $change['before'] ) . '</pre></td><td><pre>' . esc_html( (string) $change['after'] ) . '</pre></td></tr>';
		}
		$html .= '</tbody></table><p><a class="button" href="' . esc_url( get_edit_post_link( $post_id, 'url' ) ) . '">' . esc_html__( 'Return to editor', 'wp-chinese-converter' ) . '</a></p></div>';
		wp_die( wp_kses_post( $html ), esc_html__( 'Conversion recovery dry-run', 'wp-chinese-converter' ), array( 'response' => 200 ) );
	}

	$status   = ! empty( $result['already_restored'] ) ? 'already-restored' : 'restored';
	$redirect = add_query_arg( 'wpcc_restore_status', $status, get_edit_post_link( $post_id, 'url' ) );
	wp_safe_redirect( $redirect );
	exit;
}
add_action( 'admin_post_wpcc_restore_post_conversion', 'wpcc_handle_post_conversion_restore' );

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
		echo '默认URL: <a href="' . esc_url( $wpcc_noconversion_url ) . '">' . esc_html( $wpcc_noconversion_url ) . '</a><br />';
		echo '当前语言(空则是不转换): ' . esc_html( $wpcc_target_lang ) . "<br />";
		echo 'Query String: ' . ( isset( $_SERVER['QUERY_STRING'] ) ? esc_html( sanitize_text_field( wp_unslash( $_SERVER['QUERY_STRING'] ) ) ) : '' ) . '<br />';
		echo 'Request URI: ' . ( isset( $_SERVER['REQUEST_URI'] ) ? esc_url( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) ) : '' ) . '<br />';
		foreach ( $wpcc_langs_urls as $key => $value ) {
			echo esc_html( $key ) . ' URL: <a href="' . esc_url( $value ) . '">' . esc_html( $value ) . '</a><br />';
		}
		echo 'Category feed link: ' . esc_url( get_category_feed_link( 1 ) ) . '<br />';
		echo 'Search feed link: ' . esc_url( get_search_feed_link( 'test' ) );
		echo 'Rewrite Rules: <br />';
		echo nl2br( esc_html( (string) wp_json_encode( $wp_rewrite->rewrite_rules(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ) ) . '<br />';
		echo 'Debug Data: <br />';
		echo nl2br( esc_html( (string) wp_json_encode( $wpcc_debug_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ) );
		echo '</p>';
		echo '-->';
	}

	add_action( 'wp_footer', 'wpcc_debug' );
}
