<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once dirname( __FILE__ ) . '/interface-module.php';

abstract class WPCC_Abstract_Module implements WPCC_Module_Interface {
	
	protected $name;
	protected $version;
	protected $description;
	protected $dependencies = array();
	protected $settings = array();
	protected $enabled = true;
	
	public function __construct() {
		$this->init();
	}
	
	public function get_name() {
		return $this->name;
	}
	
	public function get_version() {
		return $this->version;
	}
	
	public function get_description() {
		return $this->description;
	}
	
	public function get_dependencies() {
		return $this->dependencies;
	}
	
	public function is_compatible() {
		global $wp_version;
		
		if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
			return false;
		}
		
		if ( version_compare( $wp_version, '5.0', '<' ) ) {
			return false;
		}
		
		foreach ( $this->dependencies as $dependency ) {
			if ( ! $this->check_dependency( $dependency ) ) {
				return false;
			}
		}
		
		return true;
	}
	
	public function is_enabled() {
		return $this->enabled && $this->is_compatible();
	}
	
	public function activate() {
		if ( ! $this->is_compatible() ) {
			return false;
		}
		
		$this->enabled = true;
		$this->on_activate();
		return true;
	}
	
	public function deactivate() {
		$this->enabled = false;
		$this->on_deactivate();
		return true;
	}
	
	public function get_settings() {
		return $this->settings;
	}
	
	public function update_settings( $settings ) {
		$this->settings = array_merge( $this->settings, $settings );
		$this->on_settings_update( $settings );
	}
	
	public function get_status() {
		return array(
			'name' => $this->get_name(),
			'version' => $this->get_version(),
			'enabled' => $this->is_enabled(),
			'compatible' => $this->is_compatible(),
			'dependencies' => $this->get_dependencies(),
			'settings' => $this->get_settings()
		);
	}
	
	protected function check_dependency( $dependency ) {
		if ( is_string( $dependency ) ) {
			return function_exists( $dependency ) || class_exists( $dependency );
		}
		
		if ( is_array( $dependency ) ) {
			$type = $dependency['type'] ?? 'function';
			$name = $dependency['name'] ?? '';
			
			switch ( $type ) {
				case 'plugin':
					return is_plugin_active( $name );
				case 'class':
					return class_exists( $name );
				case 'function':
					return function_exists( $name );
				default:
					return false;
			}
		}
		
		return false;
	}
	
	protected function on_activate() {
		
	}
	
	protected function on_deactivate() {
		
	}
	
	protected function on_settings_update( $settings ) {
		
	}
	
	abstract public function init();
}