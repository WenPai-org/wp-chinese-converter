<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once dirname( __FILE__ ) . '/abstract-module.php';

/**
 * WPCC 扩展基础类
 * 
 * 为第三方扩展提供标准化的基础架构
 */
abstract class WPCC_Extension extends WPCC_Abstract_Module {
	
	protected $license_key;
	protected $license_status = 'inactive';
	protected $update_server_url;
	protected $extension_slug;
	protected $is_premium = true;
	protected $required_core_version = '1.3.0';
	protected $pricing_tier = 'pro'; // free, pro, enterprise, ultimate
	
	public function __construct() {
		$this->extension_slug = $this->get_extension_slug();
		$this->check_core_compatibility();
		$this->init_license_system();
		
		parent::__construct();
	}
	
	/**
	 * 获取扩展唯一标识符
	 */
	abstract protected function get_extension_slug(): string;
	
	/**
	 * 检查核心插件兼容性
	 */
	private function check_core_compatibility(): bool {
		if ( ! defined( 'wpcc_VERSION' ) ) {
			add_action( 'admin_notices', [ $this, 'core_plugin_missing_notice' ] );
			return false;
		}
		
		if ( version_compare( wpcc_VERSION, $this->required_core_version, '<' ) ) {
			add_action( 'admin_notices', [ $this, 'core_plugin_outdated_notice' ] );
			return false;
		}
		
		return true;
	}
	
	/**
	 * 初始化许可证系统
	 */
	private function init_license_system(): void {
		if ( ! $this->is_premium ) {
			return;
		}
		
		$this->license_key = get_option( $this->extension_slug . '_license_key', '' );
		$this->license_status = get_option( $this->extension_slug . '_license_status', 'inactive' );
		
		add_action( 'admin_init', [ $this, 'check_license_status' ] );
		add_action( 'wp_ajax_' . $this->extension_slug . '_activate_license', [ $this, 'activate_license' ] );
		add_action( 'wp_ajax_' . $this->extension_slug . '_deactivate_license', [ $this, 'deactivate_license' ] );
	}
	
	/**
	 * 检查许可证状态
	 */
	public function check_license_status(): void {
		if ( empty( $this->license_key ) ) {
			return;
		}
		
		// 每天检查一次许可证状态
		$last_check = get_option( $this->extension_slug . '_license_last_check', 0 );
		if ( time() - $last_check < DAY_IN_SECONDS ) {
			return;
		}
		
		$response = wp_remote_post( $this->update_server_url . '/api/license/check', [
			'body' => [
				'license_key' => $this->license_key,
				'product_slug' => $this->extension_slug,
				'site_url' => home_url()
			],
			'timeout' => 15
		] );
		
		if ( is_wp_error( $response ) ) {
			return;
		}
		
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( isset( $body['status'] ) ) {
			$this->license_status = $body['status'];
			update_option( $this->extension_slug . '_license_status', $this->license_status );
		}
		
		update_option( $this->extension_slug . '_license_last_check', time() );
	}
	
	/**
	 * 激活许可证
	 */
	public function activate_license(): void {
		if ( ! check_ajax_referer( $this->extension_slug . '_license_nonce', 'nonce' ) ) {
			wp_die( 'Security check failed' );
		}
		
		$license_key = sanitize_text_field( $_POST['license_key'] ?? '' );
		if ( empty( $license_key ) ) {
			wp_send_json_error( 'License key is required' );
		}
		
		$response = wp_remote_post( $this->update_server_url . '/api/license/activate', [
			'body' => [
				'license_key' => $license_key,
				'product_slug' => $this->extension_slug,
				'site_url' => home_url()
			]
		] );
		
		if ( is_wp_error( $response ) ) {
			wp_send_json_error( $response->get_error_message() );
		}
		
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( isset( $body['success'] ) && $body['success'] ) {
			update_option( $this->extension_slug . '_license_key', $license_key );
			update_option( $this->extension_slug . '_license_status', 'active' );
			$this->license_key = $license_key;
			$this->license_status = 'active';
			wp_send_json_success( 'License activated successfully' );
		} else {
			wp_send_json_error( $body['message'] ?? 'License activation failed' );
		}
	}
	
	/**
	 * 停用许可证
	 */
	public function deactivate_license(): void {
		if ( ! check_ajax_referer( $this->extension_slug . '_license_nonce', 'nonce' ) ) {
			wp_die( 'Security check failed' );
		}
		
		wp_remote_post( $this->update_server_url . '/api/license/deactivate', [
			'body' => [
				'license_key' => $this->license_key,
				'product_slug' => $this->extension_slug,
				'site_url' => home_url()
			]
		] );
		
		delete_option( $this->extension_slug . '_license_key' );
		delete_option( $this->extension_slug . '_license_status' );
		$this->license_key = '';
		$this->license_status = 'inactive';
		
		wp_send_json_success( 'License deactivated successfully' );
	}
	
	/**
	 * 是否已授权
	 */
	public function is_licensed(): bool {
		return $this->license_status === 'active' || ! $this->is_premium;
	}
	
	/**
	 * 获取许可证信息
	 */
	public function get_license_info(): array {
		return [
			'key' => $this->license_key,
			'status' => $this->license_status,
			'is_premium' => $this->is_premium,
			'pricing_tier' => $this->pricing_tier
		];
	}
	
	/**
	 * 渲染许可证设置界面
	 */
	public function render_license_settings(): string {
		if ( ! $this->is_premium ) {
			return '';
		}
		
		ob_start();
		?>
		<div class="wpcc-license-settings">
			<h3><?php echo esc_html( $this->get_name() ); ?> License</h3>
			
			<?php if ( $this->license_status === 'active' ): ?>
				<div class="notice notice-success inline">
					<p>✅ License is active</p>
				</div>
				<button type="button" class="button wpcc-deactivate-license" 
				        data-extension="<?php echo esc_attr( $this->extension_slug ); ?>">
					Deactivate License
				</button>
			<?php else: ?>
				<div class="notice notice-warning inline">
					<p>⚠️ Please enter your license key to activate this extension</p>
				</div>
				<input type="text" class="regular-text wpcc-license-key" 
				       placeholder="Enter your license key" 
				       value="<?php echo esc_attr( $this->license_key ); ?>">
				<button type="button" class="button button-primary wpcc-activate-license"
				        data-extension="<?php echo esc_attr( $this->extension_slug ); ?>">
					Activate License
				</button>
			<?php endif; ?>
		</div>
		
		<script>
		jQuery(document).ready(function($) {
			$('.wpcc-activate-license').click(function() {
				var button = $(this);
				var extension = button.data('extension');
				var licenseKey = button.siblings('.wpcc-license-key').val();
				
				button.prop('disabled', true).text('Activating...');
				
				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: extension + '_activate_license',
						license_key: licenseKey,
						nonce: '<?php echo wp_create_nonce( $this->extension_slug . '_license_nonce' ); ?>'
					},
					success: function(response) {
						if (response.success) {
							location.reload();
						} else {
							alert('Error: ' + response.data);
							button.prop('disabled', false).text('Activate License');
						}
					}
				});
			});
			
			$('.wpcc-deactivate-license').click(function() {
				var button = $(this);
				var extension = button.data('extension');
				
				if (!confirm('Are you sure you want to deactivate this license?')) {
					return;
				}
				
				button.prop('disabled', true).text('Deactivating...');
				
				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: extension + '_deactivate_license',
						nonce: '<?php echo wp_create_nonce( $this->extension_slug . '_license_nonce' ); ?>'
					},
					success: function(response) {
						location.reload();
					}
				});
			});
		});
		</script>
		<?php
		return ob_get_clean();
	}
	
	/**
	 * 核心插件缺失提醒
	 */
	public function core_plugin_missing_notice(): void {
		?>
		<div class="notice notice-error">
			<p>
				<strong><?php echo esc_html( $this->get_name() ); ?></strong> requires 
				<strong>WP Chinese Converter</strong> to be installed and activated.
			</p>
		</div>
		<?php
	}
	
	/**
	 * 核心插件版本过低提醒
	 */
	public function core_plugin_outdated_notice(): void {
		?>
		<div class="notice notice-error">
			<p>
				<strong><?php echo esc_html( $this->get_name() ); ?></strong> requires 
				<strong>WP Chinese Converter</strong> version <?php echo esc_html( $this->required_core_version ); ?> or higher. 
				Current version: <?php echo esc_html( wpcc_VERSION ); ?>
			</p>
		</div>
		<?php
	}
	
	/**
	 * 重写is_enabled方法，加入许可证检查
	 */
	public function is_enabled(): bool {
		return parent::is_enabled() && $this->is_licensed();
	}
	
	/**
	 * 获取扩展状态（包含许可证信息）
	 */
	public function get_status(): array {
		$status = parent::get_status();
		$status['license'] = $this->get_license_info();
		return $status;
	}
}