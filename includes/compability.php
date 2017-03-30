<?php
/**
 * Adds compability for missing methods
 *
 * @package imgix
 */

if ( ! function_exists( 'http_build_url' ) ) {
	/**
	 * This is a simplified version of http_build_url if pecl_http is missing
	 *
	 * @param array $parsed_url
	 *
	 * @return string
	 */
	function http_build_url( $parsed_url ) {
		$scheme   = isset( $parsed_url['scheme'] ) ? $parsed_url['scheme'] . '://' : '';
		$host     = isset( $parsed_url['host'] ) ? $parsed_url['host'] : '';
		$port     = isset( $parsed_url['port'] ) ? ':' . $parsed_url['port'] : '';
		$user     = isset( $parsed_url['user'] ) ? $parsed_url['user'] : '';
		$pass     = isset( $parsed_url['pass'] ) ? ':' . $parsed_url['pass'] : '';
		$pass     = ( $user || $pass ) ? "$pass@" : '';
		$path     = isset( $parsed_url['path'] ) ? $parsed_url['path'] : '';
		$query    = isset( $parsed_url['query'] ) ? '?' . $parsed_url['query'] : '';
		$fragment = isset( $parsed_url['fragment'] ) ? '#' . $parsed_url['fragment'] : '';

		return "$scheme$user$pass$host$port$path$query$fragment";
	}
}

if ( ! function_exists( 'wp_get_additional_image_sizes' ) ) {
	/**
	 * Retrieve additional image sizes.
	 *
	 * @since 4.7.0
	 *
	 * @global array $_wp_additional_image_sizes
	 *
	 * @return array Additional images size data.
	 */
	function wp_get_additional_image_sizes() {
		global $_wp_additional_image_sizes;
		if ( ! $_wp_additional_image_sizes ) {
			$_wp_additional_image_sizes = [];
		}

		return $_wp_additional_image_sizes;
	}
}
