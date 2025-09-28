<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once dirname( dirname( __FILE__ ) ) . '/core/abstract-module.php';

class WPCC_Cache_Addon extends WPCC_Abstract_Module {
	
	public function init() {
		$this->name = 'Cache Addon';
		$this->version = '2.0.0';
		$this->description = '缓存插件兼容性模块，支持多种缓存插件';
		$this->dependencies = array();
		
		global $wpcc_options;
		$this->enabled = isset( $wpcc_options['wpcc_enable_cache_addon'] ) ? $wpcc_options['wpcc_enable_cache_addon'] : true;
		
		if ( $this->is_enabled() ) {
			add_action( 'init', array( $this, 'setup_cache_hooks' ) );
		}
	}
	
	public function setup_cache_hooks() {
		if ( $this->is_wp_super_cache_active() ) {
			$this->setup_wp_super_cache();
		}
		
		if ( $this->is_wp_rocket_active() ) {
			$this->setup_wp_rocket();
		}
		
		if ( $this->is_litespeed_cache_active() ) {
			$this->setup_litespeed_cache();
		}
		
		if ( $this->is_w3_total_cache_active() ) {
			$this->setup_w3_total_cache();
		}
		
		if ( $this->is_wp_fastest_cache_active() ) {
			$this->setup_wp_fastest_cache();
		}
		
		if ( $this->is_autoptimize_active() ) {
			$this->setup_autoptimize();
		}
		
		if ( $this->is_jetpack_boost_active() ) {
			$this->setup_jetpack_boost();
		}
	}
	
	private function is_wp_super_cache_active() {
		return function_exists( 'wp_cache_is_enabled' ) || function_exists( 'wp_super_cache_init' );
	}
	
	private function is_wp_rocket_active() {
		return function_exists( 'rocket_clean_domain' );
	}
	
	private function is_litespeed_cache_active() {
		return class_exists( 'LiteSpeed\Core' );
	}
	
	private function is_w3_total_cache_active() {
		return function_exists( 'w3tc_flush_all' );
	}
	
	private function is_wp_fastest_cache_active() {
		return class_exists( 'WpFastestCache' );
	}
	
	private function is_autoptimize_active() {
		return class_exists( 'autoptimizeMain' ) || function_exists( 'autoptimize_autoload' );
	}
	
	private function is_jetpack_boost_active() {
		return defined( 'JETPACK_BOOST_VERSION' ) || class_exists( 'Automattic\Jetpack_Boost\Jetpack_Boost' );
	}
	
	private function setup_wp_super_cache() {
		add_action( 'wpcc_language_switched', array( $this, 'clear_wp_super_cache' ) );
	}
	
	private function setup_wp_rocket() {
		add_action( 'wpcc_language_switched', array( $this, 'clear_wp_rocket_cache' ) );
	}
	
	private function setup_litespeed_cache() {
		add_action( 'wpcc_language_switched', array( $this, 'clear_litespeed_cache' ) );
	}
	
	private function setup_w3_total_cache() {
		add_action( 'wpcc_language_switched', array( $this, 'clear_w3_total_cache' ) );
	}
	
	private function setup_wp_fastest_cache() {
		add_action( 'wpcc_language_switched', array( $this, 'clear_wp_fastest_cache' ) );
	}
	
	private function setup_autoptimize() {
		add_action( 'wpcc_language_switched', array( $this, 'clear_autoptimize_cache' ) );
	}
	
	private function setup_jetpack_boost() {
		add_action( 'wpcc_language_switched', array( $this, 'clear_jetpack_boost_cache' ) );
	}
	
	public function clear_wp_super_cache() {
		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			wp_cache_clear_cache();
		}
	}
	
	public function clear_wp_rocket_cache() {
		do_action( 'rocket_purge_cache' );
		
		if ( function_exists( 'rocket_clean_domain' ) ) {
			call_user_func( 'rocket_clean_domain' );
		}
		
		if ( function_exists( 'rocket_clean_files' ) ) {
			call_user_func( 'rocket_clean_files', array( get_home_url() ) );
		}
	}
	
	public function clear_litespeed_cache() {
		do_action( 'litespeed_purge_all' );
		do_action( 'litespeed_cache_purge_all' );
		
		if ( class_exists( 'LiteSpeed\Purge' ) && method_exists( 'LiteSpeed\Purge', 'purge_all' ) ) {
			call_user_func( array( 'LiteSpeed\Purge', 'purge_all' ) );
		}
	}
	
	public function clear_w3_total_cache() {
		do_action( 'w3tc_flush_cache' );
		
		if ( function_exists( 'w3tc_flush_all' ) ) {
			call_user_func( 'w3tc_flush_all' );
		}
		
		if ( function_exists( 'w3tc_pgcache_flush' ) ) {
			call_user_func( 'w3tc_pgcache_flush' );
		}
	}
	
	public function clear_wp_fastest_cache() {
		if ( class_exists( 'WpFastestCache' ) ) {
			$wpfc = new WpFastestCache();
			if ( method_exists( $wpfc, 'deleteCache' ) ) {
				$wpfc->deleteCache();
			}
		}
		
		do_action( 'wpfc_clear_all_cache' );
	}
	
	public function clear_autoptimize_cache() {
		if ( class_exists( 'autoptimizeCache' ) ) {
			autoptimizeCache::clearall();
		}
		
		do_action( 'autoptimize_action_cachepurged' );
	}
	
	public function clear_jetpack_boost_cache() {
		do_action( 'jetpack_boost_clear_cache' );
	}
	
	public function get_cache_status() {
		$status = array();
		
		if ( $this->is_wp_super_cache_active() ) {
			$status['wp_super_cache'] = 'active';
		}
		
		if ( $this->is_wp_rocket_active() ) {
			$status['wp_rocket'] = 'active';
		}
		
		if ( $this->is_litespeed_cache_active() ) {
			$status['litespeed_cache'] = 'active';
		}
		
		if ( $this->is_w3_total_cache_active() ) {
			$status['w3_total_cache'] = 'active';
		}
		
		if ( $this->is_wp_fastest_cache_active() ) {
			$status['wp_fastest_cache'] = 'active';
		}
		
		if ( $this->is_autoptimize_active() ) {
			$status['autoptimize'] = 'active';
		}
		
		if ( $this->is_jetpack_boost_active() ) {
			$status['jetpack_boost'] = 'active';
		}
		
		return $status;
	}
}
