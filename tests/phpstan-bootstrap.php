<?php

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}
require_once dirname( __DIR__ ) . '/includes/core/interface-module.php';
require_once dirname( __DIR__ ) . '/includes/core/abstract-module.php';
require_once dirname( __DIR__ ) . '/includes/core/interface-converter.php';

if ( ! function_exists( 'wpcc_mobile_exist' ) ) {
	function wpcc_mobile_exist( $name ) {
		return is_string( $name );
	}
}
