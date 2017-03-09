<?php

class PHPCS_Diff_Cache {

	public function __construct() {

	}

	public function get( $cache_key, $cache_group ) {
		wp_cache_get( $cache_key, $cache_group );
	}

}