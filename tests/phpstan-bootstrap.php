<?php

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}
defined( 'wpcc_VERSION' ) || define( 'wpcc_VERSION', 'test' );
defined( 'wpcc_DIR_URL' ) || define( 'wpcc_DIR_URL', 'https://example.test/wp-content/plugins/wpcc/' );
defined( 'COOKIEHASH' ) || define( 'COOKIEHASH', 'test' );
defined( 'COOKIEPATH' ) || define( 'COOKIEPATH', '/' );
defined( 'COOKIE_DOMAIN' ) || define( 'COOKIE_DOMAIN', '' );
require_once dirname( __DIR__ ) . '/includes/core/interface-module.php';
require_once dirname( __DIR__ ) . '/includes/core/abstract-module.php';
require_once dirname( __DIR__ ) . '/includes/core/interface-converter.php';

if ( ! function_exists( 'wpcc_mobile_exist' ) ) {
	function wpcc_mobile_exist( $name ) {
		return is_string( $name );
	}
}

// The bundled simple_html_dom implementation is excluded from analysis because
// it is third-party legacy code; declare the only first-party entry point used.
if ( ! function_exists( 'str_get_html' ) ) {
	function str_get_html( $str ) {
		return false;
	}
}
