<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_Chinese_Converter {

	protected $loader;
	protected $plugin_name;
	protected $version;

	public function __construct() {
		if ( defined( 'WPCC_VERSION' ) ) {
			$this->version = WPCC_VERSION;
		} else {
			$this->version = '2.0.0';
		}
		$this->plugin_name = 'wp-chinese-converter';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();
	}

	private function load_dependencies() {
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/ZhConversion.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/simple_html_dom.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/admin/wp-chinese-converter-admin.php';
	}

	private function set_locale() {
		load_plugin_textdomain(
			'wp-chinese-converter',
			false,
			dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
		);
	}

	private function define_admin_hooks() {
		if ( is_admin() ) {
			add_action( 'admin_menu', 'wpcc_admin_init' );
			add_action( 'network_admin_menu', 'wpcc_admin_init' );
		}
	}

	private function define_public_hooks() {
		
	}

	public function run() {
		
	}

	public function get_plugin_name() {
		return $this->plugin_name;
	}

	public function get_version() {
		return $this->version;
	}
}