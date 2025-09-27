<?php

class wpcc_Admin {
	var $base = '';
	var $is_submitted = false;
	var $is_success = false;
	var $is_error = false;
	var $message = '';
	var $options = false;
	var $langs = false;
	var $url = '';
	var $admin_lang = false;

	function wpcc_Admin() {
		return $this->__construct();
	}

	function clean_invalid_langs() {
		global $wpcc_langs;
		if ( isset( $this->options['wpcc_used_langs'] ) && is_array( $this->options['wpcc_used_langs'] ) ) {
			$valid_langs = array();
			foreach ( $this->options['wpcc_used_langs'] as $lang ) {
				if ( isset( $wpcc_langs[ $lang ] ) ) {
					$valid_langs[] = $lang;
				}
			}
			$this->options['wpcc_used_langs'] = $valid_langs;
		}
	}

	function __construct() {
		global $wpcc_options, $wpcc_langs, $wpcc_modules;
		if ( function_exists( 'wpcc_init_languages' ) ) {
			wpcc_init_languages();
		}
		$locale = str_replace( '_', '-', strtolower( get_locale() ) );
		if ( $wpcc_options === false ) {
			$wpcc_options = get_wpcc_option( 'wpcc_options' );
		}
		$this->langs   = &$wpcc_langs;
		$this->options = $wpcc_options;
		if ( empty( $this->options ) ) {
			$this->options = array(
				'wpcc_search_conversion'       => 1,
				'wpcc_used_langs'              => array( 'zh-cn', 'zh-tw', 'zh-hk' ),
				'wpcc_browser_redirect'        => 0,
				'wpcc_auto_language_recong'    => 0,
				'wpcc_flag_option'             => 1,
				'wpcc_use_cookie_variant'      => 0,
				'wpcc_use_fullpage_conversion' => 1,
				'wpcso_use_sitemap'            => 1,
				'wpcc_trackback_plugin_author' => 0,
				'wpcc_add_author_link'         => 0,
				'wpcc_use_permalink'           => 0,
				'wpcc_no_conversion_tag'       => '',
				'wpcc_no_conversion_ja'        => 0,
				'wpcc_no_conversion_qtag'      => 0,
				'wpcc_engine'                  => 'mediawiki',
			);
		}

		$this->clean_invalid_langs();
		update_wpcc_option('wpcc_options', $this->options);

		$this->base = plugin_basename( dirname( __FILE__ ) ) . '/';
		$page_slug = 'wp-chinese-converter';

		if ( is_network_admin() && wpcc_mobile_exist( 'network' ) ) {
			$this->url = network_admin_url( 'settings.php?page=' . $page_slug );
		} else {
			$this->url = admin_url( 'options-general.php?page=' . $page_slug );
		}

		if ( is_multisite() && wpcc_mobile_exist( 'network' ) ) {
			add_submenu_page( 'settings.php', 'WP Chinese Converter', 'WP Chinese Converter', 'manage_network_options', $page_slug, array(
				&$this,
				'display_options'
			) );
		} else {
			add_options_page( 'WP Chinese Converter', 'WP Chinese Converter', 'manage_options', $page_slug, array(
				&$this,
				'display_options'
			) );
		}

		wp_enqueue_script( 'jquery' );
	}

	function action_links( $links, $file ) {
		if ( $file == $this->base . 'wp-chinese-converter.php' ) {
			$links[] = '<a href="options-general.php?page=wp-chinese-converter" title="Change Settings">Settings</a>';
		}

		return $links;
	}

	function install_cache_module() {
		global $wpcc_options;

		$ret = true;

		$file = file_get_contents( dirname( __FILE__ ) . '/../modules/wpcc-cache-addon.php' );

		$used_langs = 'Array()';
		if ( count( $wpcc_options['wpcc_used_langs'] ) > 0 ) {
			$used_langs = "Array('" . implode( "', '", $wpcc_options['wpcc_used_langs'] ) . "')";
		}
		$file = str_replace( '##wpcc_auto_language_recong##',
			$wpcc_options['wpcc_auto_language_recong'], $file );
		$file = str_replace( '##wpcc_used_langs##',
			$used_langs, $file );

		$fp = @fopen( WP_PLUGIN_DIR . '/wp-super-cache/plugins/wpcc-wp-super-cache-plugin.php', 'w' );
		if ( $fp ) {
			@fwrite( $fp, $file );
			@fclose( $fp );
		} else {
			$ret = false;
		}

		return $ret;
	}

	function uninstall_cache_module() {
		return unlink( WP_PLUGIN_DIR . '/wp-super-cache/plugins/wpcc-wp-super-cache-plugin.php' );
	}

	function get_cache_status() {
		if ( ! function_exists( 'wp_cache_is_enabled' ) ) {
			return 0;
		}
		if ( ! file_exists( WP_PLUGIN_DIR . '/wp-super-cache/plugins/wpcc-wp-super-cache-plugin.php' ) ) {
			return 1;
		}

		return 2;
	}

	function display_options() {
		global $wp_rewrite;
		
		wp_enqueue_style(
			'wpcc-admin',
			plugin_dir_url( dirname( dirname( __DIR__ ) ) . '/wp-chinese-converter.php' ) . 'assets/admin/admin.css',
			[],
			'2.0.0'
		);
		
		wp_enqueue_script(
			'wpcc-admin',
			plugin_dir_url( dirname( dirname( __DIR__ ) ) . '/wp-chinese-converter.php' ) . 'assets/admin/admin.js',
			['jquery'],
			'2.0.0',
			true
		);

		if ( ! empty( $_POST['wpcco_uninstall_nonce'] ) ) {
			delete_option( 'wpcc_options' );
			update_option( 'rewrite_rules', '' );
			echo '<div class="wrap"><h2>WP Chinese Converter Setting</h2><div class="updated">Uninstall Successfully. 卸载成功, 现在您可以到<a href="plugins.php">插件菜单</a>里禁用本插件.</div></div>';
			return;
		} else if ( $this->options === false ) {
			echo '<div class="wrap"><h2>WP Chinese Converter Setting</h2><div class="error">错误: 没有找到配置信息, 可能由于WordPress系统错误或者您已经卸载了本插件. 您可以<a href="plugins.php">尝试</a>禁用本插件后再重新激活.</div></div>';
			return;
		}

		if ( ! empty( $_POST['toggle_cache'] ) ) {
			if ( $this->get_cache_status() == 1 ) {
				$result = $this->install_cache_module();
				if ( $result ) {
					echo '<div class="updated fade" style=""><p>' . _e( '安装WP Super Cache 兼容成功.', 'wp-chinese-converter' ) . '</p></div>';
				} else {
					echo '<div class="error" style=""><p>' . _e( '错误: 安装WP Super Cache 兼容失败', 'wp-chinese-converter' ) . '.</p></div>';
				}
			} else if ( $this->get_cache_status() == 2 ) {
				$result = $this->uninstall_cache_module();
				if ( $result ) {
					echo '<div class="updated fade" style=""><p>' . _e( '卸载WP Super Cache 兼容成功', 'wp-chinese-converter' ) . '</p></div>';
				} else {
					echo '<div class="error" style=""><p>' . _e( '错误: 卸载WP Super Cache 兼容失败', 'wp-chinese-converter' ) . '.</p></div>';
				}
			}
		}

		if ( ! empty( $_POST['wpcco_submitted'] ) ) {
			$this->is_submitted = true;
			$this->process();

			if ( $this->get_cache_status() == 2 ) {
				$this->install_cache_module();
			}
		}
		
		ob_start();
		include dirname(__FILE__) . '/../templates/modern-settings-page.php';
		$o = ob_get_clean();
		
		if ($this->admin_lang) {
			wpcc_load_conversion_table();
			$o = limit_zhconversion($o, $this->langs[$this->admin_lang][0]);
		}
		
		echo $o;
	}

	function navi() {
		$variant = ( isset( $_GET['variant'] ) && ! empty( $_GET['variant'] ) ) ? $_GET['variant'] : '';
		$str     = '<span><a title="' . __( '默认', 'wp-chinese-converter' ) . '" href="' . $this->url . '" ' . ( ! $variant ? 'style="color: #464646; text-decoration: none !important;"' : '' ) . ' >' . __( '默认', 'wp-chinese-converter' ) . '</a></span>&nbsp;';
		if ( ! $this->options['wpcc_used_langs'] ) {
			return $str;
		}
		foreach ( $this->langs as $key => $value ) {
			$str .= '<span><a href="' . $this->url . '&variant=' . $key . '" title="' . $value[2] . '" ' . ( $variant == $key ? 'style="color: #464646; text-decoration: none !important;"' : '' ) . '>' . $value[2] . '</a>&nbsp;</span>';
		}

		return $str;
	}

	function process() {
		global $wp_rewrite, $wpcc_options;
		$langs = array();
		if ( is_array( $this->langs ) ) {
			foreach ( $this->langs as $key => $value ) {
				if ( isset( $_POST[ 'wpcco_variant_' . $key ] ) ) {
					$langs[] = $key;
				}
			}
		}
		$options = array(
			'wpcc_used_langs'              => $langs,
			'wpcc_search_conversion'       => ( isset( $_POST['wpcco_search_conversion'] ) ? intval( $_POST['wpcco_search_conversion'] ) : 0 ),
			'wpcc_browser_redirect'        => ( isset( $_POST['wpcco_browser_redirect'] ) ? intval( $_POST['wpcco_browser_redirect'] ) : 0 ),
			'wpcc_translate_type'          => ( isset( $_POST['wpcc_translate_type'] ) ? intval( $_POST['wpcc_translate_type'] ) : 0 ),
			'wpcc_use_cookie_variant'      => ( isset( $_POST['wpcco_use_cookie_variant'] ) ? intval( $_POST['wpcco_use_cookie_variant'] ) : 0 ),
			'wpcc_use_fullpage_conversion' => ( isset( $_POST['wpcco_use_fullpage_conversion'] ) ? 1 : 0 ),
			'wpcso_use_sitemap'            => ( isset( $_POST['wpcco_use_sitemap'] ) ? 1 : 0 ),
			'wpcso_sitemap_post_type'      => ( isset( $_POST['wpcco_sitemap_post_type'] ) ? trim( $_POST['wpcco_sitemap_post_type'] ) : 'post,page' ),
			'wpcc_trackback_plugin_author' => ( isset( $_POST['wpcco_trackback_plugin_author'] ) ? intval( $_POST['wpcco_trackback_plugin_author'] ) : 0 ),
			'wpcc_add_author_link'         => ( isset( $_POST['wpcco_add_author_link'] ) ? 1 : 0 ),
			'wpcc_use_permalink'           => ( isset( $_POST['wpcco_use_permalink'] ) ? intval( $_POST['wpcco_use_permalink'] ) : 0 ),
			'wpcc_auto_language_recong'    => ( isset( $_POST['wpcco_auto_language_recong'] ) ? 1 : 0 ),
			'wpcc_no_conversion_tag'       => ( isset( $_POST['wpcco_no_conversion_tag'] ) ? trim( $_POST['wpcco_no_conversion_tag'], " \t\n\r\0\x0B,|" ) : '' ),
			'wpcc_no_conversion_ja'        => ( isset( $_POST['wpcco_no_conversion_ja'] ) ? 1 : 0 ),
			'wpcc_no_conversion_qtag'      => ( isset( $_POST['wpcco_no_conversion_qtag'] ) ? 1 : 0 ),
			'nctip'                        => ( isset( $_POST['wpcco_no_conversion_tip'] ) ? trim( $_POST['wpcco_no_conversion_tip'] ) : '不转换' ),
		);

		if ( is_array( $this->langs ) ) {
			foreach ( $this->langs as $lang => $value ) {
				if ( is_array( $value ) && isset( $value[1] ) && isset( $_POST[ $value[1] ] ) && ! empty( $_POST[ $value[1] ] ) ) {
					$options[ $value[1] ] = trim( $_POST[ $value[1] ] );
				}
			}
		}

		if ( $this->get_cache_status() == 2 && empty( $options['wpcc_browser_redirect'] ) && empty( $options['wpcc_use_cookie_variant'] ) ) {
			$this->uninstall_cache_module();
		}

		$wpcc_options = $options;
		if ( $this->options['wpcc_use_permalink'] != $options['wpcc_use_permalink'] ||
		     ( $this->options['wpcc_use_permalink'] != 0 && $this->options['wpcc_used_langs'] != $options['wpcc_used_langs'] )
		) {
			if ( ! has_filter( 'rewrite_rules_array', 'wpcc_rewrite_rules' ) ) {
				add_filter( 'rewrite_rules_array', 'wpcc_rewrite_rules' );
			}
			$wp_rewrite->flush_rules();
		}

		update_wpcc_option( 'wpcc_options', $options );

		$this->options    = $options;
		$this->is_success = true;
		$this->message    .= '设置已更新。';
	}
}
