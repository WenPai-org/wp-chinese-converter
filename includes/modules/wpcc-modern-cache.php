<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once dirname( dirname( __FILE__ ) ) . '/core/abstract-module.php';

class WPCC_Modern_Cache extends WPCC_Abstract_Module {
	
	private $cache_group = 'wpcc_conversions';
	private $cache_expiry = 3600;
	
	public function init() {
		$this->name = 'Modern Cache';
		$this->version = '1.4';
		$this->description = '现代缓存模块，支持对象缓存、Redis、Memcached';
		$this->dependencies = array();
		
		$this->settings = array(
			'enable_object_cache' => true,
			'enable_conversion_cache' => true,
			'cache_expiry' => 3600,
			'max_cache_size' => 1000
		);
		
		if ( $this->is_enabled() ) {
			add_action( 'init', array( $this, 'setup_cache_hooks' ) );
			add_filter( 'wpcc_before_conversion', array( $this, 'get_cached_conversion' ), 10, 3 );
			add_action( 'wpcc_after_conversion', array( $this, 'cache_conversion' ), 10, 4 );
		}
	}
	
	public function setup_cache_hooks() {
		if ( $this->settings['enable_object_cache'] && wp_using_ext_object_cache() ) {
			add_action( 'wpcc_language_switched', array( $this, 'clear_object_cache' ) );
		}
		
		add_action( 'wpcc_settings_updated', array( $this, 'clear_all_cache' ) );
		add_action( 'wpcc_engine_switched', array( $this, 'clear_conversion_cache' ) );
	}
	
	public function get_cached_conversion( $text, $target, $engine ) {
		if ( ! $this->settings['enable_conversion_cache'] ) {
			return null;
		}
		
		$cache_key = $this->generate_cache_key( $text, $target, $engine );
		
		if ( wp_using_ext_object_cache() ) {
			return wp_cache_get( $cache_key, $this->cache_group );
		}
		
		return get_transient( $cache_key );
	}
	
	public function cache_conversion( $original_text, $converted_text, $target, $engine ) {
		if ( ! $this->settings['enable_conversion_cache'] ) {
			return;
		}
		
		$cache_key = $this->generate_cache_key( $original_text, $target, $engine );
		$cache_data = array(
			'original' => $original_text,
			'converted' => $converted_text,
			'target' => $target,
			'engine' => $engine,
			'timestamp' => time()
		);
		
		if ( wp_using_ext_object_cache() ) {
			wp_cache_set( $cache_key, $cache_data, $this->cache_group, $this->cache_expiry );
		} else {
			set_transient( $cache_key, $cache_data, $this->cache_expiry );
		}
		
		$this->manage_cache_size();
	}
	
	private function generate_cache_key( $text, $target, $engine ) {
		$key_data = array(
			'text' => $text,
			'target' => $target,
			'engine' => $engine,
			'version' => $this->version
		);
		
		return 'wpcc_conv_' . md5( serialize( $key_data ) );
	}
	
	private function manage_cache_size() {
		$cache_keys = $this->get_cache_keys();
		
		if ( count( $cache_keys ) > $this->settings['max_cache_size'] ) {
			$keys_to_delete = array_slice( $cache_keys, 0, count( $cache_keys ) - $this->settings['max_cache_size'] );
			
			foreach ( $keys_to_delete as $key ) {
				if ( wp_using_ext_object_cache() ) {
					wp_cache_delete( $key, $this->cache_group );
				} else {
					delete_transient( $key );
				}
			}
		}
	}
	
	private function get_cache_keys() {
		$keys_option = 'wpcc_cache_keys';
		return get_option( $keys_option, array() );
	}
	
	private function add_cache_key( $key ) {
		$keys_option = 'wpcc_cache_keys';
		$keys = get_option( $keys_option, array() );
		
		if ( ! in_array( $key, $keys ) ) {
			$keys[] = $key;
			update_option( $keys_option, $keys );
		}
	}
	
	public function clear_object_cache() {
		if ( wp_using_ext_object_cache() ) {
			wp_cache_flush_group( $this->cache_group );
		}
	}
	
	public function clear_conversion_cache() {
		$cache_keys = $this->get_cache_keys();
		
		foreach ( $cache_keys as $key ) {
			if ( wp_using_ext_object_cache() ) {
				wp_cache_delete( $key, $this->cache_group );
			} else {
				delete_transient( $key );
			}
		}
		
		delete_option( 'wpcc_cache_keys' );
	}
	
	public function clear_all_cache() {
		$this->clear_conversion_cache();
		$this->clear_object_cache();
		
		do_action( 'wpcc_cache_cleared' );
	}
	
	public function get_cache_statistics() {
		$stats = array(
			'object_cache_enabled' => wp_using_ext_object_cache(),
			'conversion_cache_enabled' => $this->settings['enable_conversion_cache'],
			'cache_expiry' => $this->cache_expiry,
			'max_cache_size' => $this->settings['max_cache_size'],
			'current_cache_count' => count( $this->get_cache_keys() )
		);
		
		if ( wp_using_ext_object_cache() ) {
			$stats['cache_type'] = $this->detect_cache_type();
		} else {
			$stats['cache_type'] = 'database_transients';
		}
		
		return $stats;
	}
	
	private function detect_cache_type() {
		global $wp_object_cache;
		
		if ( class_exists( 'Redis' ) && method_exists( $wp_object_cache, 'redis_instance' ) ) {
			return 'redis';
		}
		
		if ( class_exists( 'Memcached' ) && method_exists( $wp_object_cache, 'get_mc' ) ) {
			return 'memcached';
		}
		
		if ( class_exists( 'Memcache' ) ) {
			return 'memcache';
		}
		
		return 'unknown_object_cache';
	}
	
	public function preload_common_conversions() {
		$common_texts = array(
			'你好',
			'谢谢',
			'欢迎',
			'首页',
			'关于我们',
			'联系我们',
			'产品',
			'服务',
			'新闻',
			'博客'
		);
		
		$targets = array( 'zh-tw', 'zh-hk', 'zh-cn' );
		$engines = array( 'mediawiki', 'opencc' );
		
		foreach ( $common_texts as $text ) {
			foreach ( $targets as $target ) {
				foreach ( $engines as $engine ) {
					try {
						$converter = WPCC_Converter_Factory::get_converter( $engine );
						$converted = $converter->convert( $text, $target );
						$this->cache_conversion( $text, $converted, $target, $engine );
					} catch ( Exception $e ) {
						continue;
					}
				}
			}
		}
	}
	
	public function warm_cache_from_content() {
		$posts = get_posts( array(
			'numberposts' => 10,
			'post_type' => array( 'post', 'page' ),
			'post_status' => 'publish'
		) );
		
		foreach ( $posts as $post ) {
			$content = wp_strip_all_tags( $post->post_content );
			$sentences = preg_split( '/[。！？]/', $content, -1, PREG_SPLIT_NO_EMPTY );
			
			foreach ( array_slice( $sentences, 0, 5 ) as $sentence ) {
				$sentence = trim( $sentence );
				if ( mb_strlen( $sentence ) > 5 && mb_strlen( $sentence ) < 100 ) {
					try {
						$converter = WPCC_Converter_Factory::get_converter();
						$converted = $converter->convert( $sentence, 'zh-tw' );
						$this->cache_conversion( $sentence, $converted, 'zh-tw', $converter->get_engine_name() );
					} catch ( Exception $e ) {
						continue;
					}
				}
			}
		}
	}
}