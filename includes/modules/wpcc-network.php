<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once dirname( dirname( __FILE__ ) ) . '/core/abstract-module.php';

class WPCC_Network extends WPCC_Abstract_Module {
	
	public function init() {
		$this->name = 'Network Multisite';
		$this->version = '1.4';
		$this->description = '多站点网络支持模块';
		$this->dependencies = array(
			array( 'type' => 'function', 'name' => 'is_multisite' )
		);
		
		if ( $this->is_enabled() && is_multisite() ) {
			// 移除重复的网络管理菜单注册，使用主要的wp-chinese-converter菜单
			// add_action( 'network_admin_menu', array( $this, 'add_network_admin_menu' ) );
			add_action( 'wpmu_new_blog', array( $this, 'setup_new_site' ), 10, 6 );
			add_action( 'wp_initialize_site', array( $this, 'initialize_site_settings' ) );
		}
	}
	
	public function is_compatible() {
		return parent::is_compatible() && is_multisite();
	}
	
	public function add_network_admin_menu() {
		add_submenu_page(
			'settings.php',
			'WPCC 网络设置',
			'WPCC 网络设置',
			'manage_network_options',
			'wpcc-network-settings',
			array( $this, 'network_settings_page' )
		);
	}
	
	public function network_settings_page() {
		if ( isset( $_POST['submit'] ) && wp_verify_nonce( $_POST['wpcc_network_nonce'], 'wpcc_network_settings' ) ) {
			$this->save_network_settings();
		}
		
		$network_settings = $this->get_network_settings();
		?>
		<div class="wrap">
			<h1>WPCC 网络设置</h1>
			<form method="post" action="">
				<?php wp_nonce_field( 'wpcc_network_settings', 'wpcc_network_nonce' ); ?>
				
				<table class="form-table">
					<tr>
						<th scope="row">默认转换引擎</th>
						<td>
							<select name="default_engine">
								<option value="mediawiki" <?php selected( $network_settings['default_engine'], 'mediawiki' ); ?>>MediaWiki</option>
								<option value="opencc" <?php selected( $network_settings['default_engine'], 'opencc' ); ?>>OpenCC</option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row">允许子站点覆盖设置</th>
						<td>
							<input type="checkbox" name="allow_site_override" value="1" <?php checked( $network_settings['allow_site_override'], 1 ); ?> />
							<label>允许子站点管理员修改转换设置</label>
						</td>
					</tr>
					<tr>
						<th scope="row">默认启用的语言</th>
						<td>
							<?php
							$default_langs = $network_settings['default_languages'] ?? array( 'zh-cn', 'zh-tw' );
							$available_langs = array(
								'zh-cn' => '简体中文',
								'zh-tw' => '台湾正体',
								'zh-hk' => '香港繁体',
								'zh-sg' => '新加坡简体'
							);
							
							foreach ( $available_langs as $code => $name ) {
								echo '<label><input type="checkbox" name="default_languages[]" value="' . esc_attr( $code ) . '"' . 
								     ( in_array( $code, $default_langs ) ? ' checked' : '' ) . '> ' . esc_html( $name ) . '</label><br>';
							}
							?>
						</td>
					</tr>
				</table>
				
				<?php submit_button( '保存网络设置' ); ?>
			</form>
			
			<h2>站点状态</h2>
			<?php $this->display_sites_status(); ?>
		</div>
		<?php
	}
	
	private function save_network_settings() {
		$settings = array(
			'default_engine' => sanitize_text_field( $_POST['default_engine'] ?? 'mediawiki' ),
			'allow_site_override' => isset( $_POST['allow_site_override'] ) ? 1 : 0,
			'default_languages' => array_map( 'sanitize_text_field', $_POST['default_languages'] ?? array() )
		);
		
		update_site_option( 'wpcc_network_settings', $settings );
		
		echo '<div class="notice notice-success"><p>网络设置已保存</p></div>';
	}
	
	public function get_network_settings() {
		return get_site_option( 'wpcc_network_settings', array(
			'default_engine' => 'mediawiki',
			'allow_site_override' => 1,
			'default_languages' => array( 'zh-cn', 'zh-tw' )
		) );
	}
	
	public function setup_new_site( $blog_id, $user_id, $domain, $path, $site_id, $meta ) {
		switch_to_blog( $blog_id );
		
		$network_settings = $this->get_network_settings();
		
		$site_options = array(
			'wpcc_engine' => $network_settings['default_engine'],
			'wpcc_used_langs' => $network_settings['default_languages'],
			'wpcc_translate_type' => 0,
			'wpcc_use_fullpage_conversion' => 1
		);
		
		update_option( 'wpcc_options', $site_options );
		
		restore_current_blog();
	}
	
	public function initialize_site_settings( $new_site ) {
		$this->setup_new_site( $new_site->blog_id, 0, '', '', 0, array() );
	}
	
	public function display_sites_status() {
		$sites = get_sites( array( 'number' => 50 ) );
		
		echo '<table class="wp-list-table widefat fixed striped">';
		echo '<thead><tr><th>站点</th><th>转换引擎</th><th>启用语言</th><th>状态</th></tr></thead>';
		echo '<tbody>';
		
		foreach ( $sites as $site ) {
			switch_to_blog( $site->blog_id );
			
			$site_options = get_option( 'wpcc_options', array() );
			$engine = $site_options['wpcc_engine'] ?? 'mediawiki';
			$languages = $site_options['wpcc_used_langs'] ?? array();
			$active = is_plugin_active( 'wp-chinese-converter/wp-chinese-converter.php' );
			
			echo '<tr>';
			echo '<td><a href="' . esc_url( get_home_url() ) . '">' . esc_html( get_bloginfo( 'name' ) ) . '</a></td>';
			echo '<td>' . esc_html( $engine ) . '</td>';
			echo '<td>' . esc_html( implode( ', ', $languages ) ) . '</td>';
			echo '<td>' . ( $active ? '✅ 激活' : '❌ 未激活' ) . '</td>';
			echo '</tr>';
			
			restore_current_blog();
		}
		
		echo '</tbody></table>';
	}
	
	// 同步功能已移至网络设置统一管理
	// public function sync_settings_to_all_sites( $settings ) {
	//     // 此功能已由网络管理模块统一处理
	// }
	
	public function get_network_statistics() {
		$sites = get_sites();
		$stats = array(
			'total_sites' => count( $sites ),
			'active_sites' => 0,
			'engines' => array( 'mediawiki' => 0, 'opencc' => 0 ),
			'languages' => array()
		);
		
		foreach ( $sites as $site ) {
			switch_to_blog( $site->blog_id );
			
			if ( is_plugin_active( 'wp-chinese-converter/wp-chinese-converter.php' ) ) {
				$stats['active_sites']++;
				
				$site_options = get_option( 'wpcc_options', array() );
				$engine = $site_options['wpcc_engine'] ?? 'mediawiki';
				$languages = $site_options['wpcc_used_langs'] ?? array();
				
				$stats['engines'][ $engine ]++;
				
				foreach ( $languages as $lang ) {
					$stats['languages'][ $lang ] = ( $stats['languages'][ $lang ] ?? 0 ) + 1;
				}
			}
			
			restore_current_blog();
		}
		
		return $stats;
	}
}
