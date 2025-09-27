<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface WPCC_Converter_Interface {
	
	public function convert( $text, $target_variant );
	
	public function get_supported_variants();
	
	public function get_engine_name();
	
	public function get_engine_info();
	
	public function is_available();
}