<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once dirname( __FILE__ ) . '/interface-module.php';

class WPCC_Module_Manager {
	
	private static $instance = null;
	private $modules = array();
	private $loaded_modules = array();
	
	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
	
	private function __construct() {
		add_action( 'init', array( $this, 'load_modules' ), 5 );
	}
	
	public function register_module( $module_class, $module_file = '' ) {
		if ( ! class_exists( $module_class ) ) {
			if ( ! empty( $module_file ) && file_exists( $module_file ) ) {
				require_once $module_file;
			}
		}
		
		if ( class_exists( $module_class ) ) {
			$this->modules[ $module_class ] = $module_file;
			return true;
		}
		
		return false;
	}
	
	public function load_modules() {
		foreach ( $this->modules as $module_class => $module_file ) {
			try {
				$module = new $module_class();
				
				if ( $module instanceof WPCC_Module_Interface ) {
					if ( $module->is_enabled() ) {
						$this->loaded_modules[ $module_class ] = $module;
						do_action( 'wpcc_module_loaded', $module );
					}
				}
			} catch ( Exception $e ) {
				error_log( 'WPCC Module Load Error: ' . $e->getMessage() );
			}
		}
		
		do_action( 'wpcc_modules_loaded', $this->loaded_modules );
	}
	
	public function get_module( $module_class ) {
		return $this->loaded_modules[ $module_class ] ?? null;
	}
	
	public function get_all_modules() {
		return $this->loaded_modules;
	}
	
	public function get_module_status( $module_class = null ) {
		if ( $module_class ) {
			$module = $this->get_module( $module_class );
			return $module ? $module->get_status() : null;
		}
		
		$status = array();
		foreach ( $this->loaded_modules as $class => $module ) {
			$status[ $class ] = $module->get_status();
		}
		
		return $status;
	}
	
	public function activate_module( $module_class ) {
		$module = $this->get_module( $module_class );
		if ( $module ) {
			return $module->activate();
		}
		return false;
	}
	
	public function deactivate_module( $module_class ) {
		$module = $this->get_module( $module_class );
		if ( $module ) {
			return $module->deactivate();
		}
		return false;
	}
	
	public function check_dependencies() {
		$issues = array();
		
		foreach ( $this->loaded_modules as $class => $module ) {
			if ( ! $module->is_compatible() ) {
				$issues[ $class ] = array(
					'name' => $module->get_name(),
					'dependencies' => $module->get_dependencies(),
					'compatible' => false
				);
			}
		}
		
		return $issues;
	}
	
	public function auto_discover_modules() {
		$modules_dir = dirname( dirname( __FILE__ ) ) . '/modules/';
		
		if ( ! is_dir( $modules_dir ) ) {
			return;
		}
		
		$files = glob( $modules_dir . 'wpcc-*.php' );
		
		foreach ( $files as $file ) {
			$basename = basename( $file, '.php' );
			$class_name = 'WPCC_' . str_replace( 'wpcc-', '', $basename );
			$class_name = str_replace( '-', '_', $class_name );
			$class_name = ucwords( $class_name, '_' );
			
			$this->register_module( $class_name, $file );
		}
	}
}