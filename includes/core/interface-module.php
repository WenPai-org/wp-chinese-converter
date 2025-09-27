<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface WPCC_Module_Interface {
	
	public function init();
	
	public function get_name();
	
	public function get_version();
	
	public function get_description();
	
	public function get_dependencies();
	
	public function is_compatible();
	
	public function is_enabled();
	
	public function activate();
	
	public function deactivate();
	
	public function get_settings();
	
	public function update_settings( $settings );
	
	public function get_status();
}