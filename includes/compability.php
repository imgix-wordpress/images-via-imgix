<?php
/**
 * Adds compability for missing methods
 *
 * @package imgix
 */

if ( ! function_exists( 'wp_parse_url' ) ) {
	/**
	 * A wrapper for PHP's parse_url() function that handles edgecases in < PHP 5.4.7
	 *
	 * PHP 5.4.7 expanded parse_url()'s ability to handle non-absolute url's, including
	 * schemeless and relative url's with :// in the path, this works around those
	 * limitations providing a standard output on PHP 5.2~5.4+.
	 *
	 * Error suppression is used as prior to PHP 5.3.3, an E_WARNING would be generated
	 * when URL parsing failed.
	 *
	 * @since 4.4.0
	 *
	 * @param string $url The URL to parse.
	 *
	 * @return bool|array False on failure; Array of URL components on success;
	 *                    See parse_url()'s return values.
	 */
	function wp_parse_url( $url ) {
		$parts = @parse_url( $url );
		if ( ! $parts ) {
			// < PHP 5.4.7 compat, trouble with relative paths including a scheme break in the path
			if ( '/' == $url[0] && false !== strpos( $url, '://' ) ) {
				// Since we know it's a relative path, prefix with a scheme/host placeholder and try again
				if ( ! $parts = @parse_url( 'placeholder://placeholder' . $url ) ) {
					return $parts;
				}
				// Remove the placeholder values
				unset( $parts['scheme'], $parts['host'] );
			} else {
				return $parts;
			}
		}

		// < PHP 5.4.7 compat, doesn't detect schemeless URL's host field
		if ( '//' == substr( $url, 0, 2 ) && ! isset( $parts['host'] ) ) {
			$path_parts    = explode( '/', substr( $parts['path'], 2 ), 2 );
			$parts['host'] = $path_parts[0];
			if ( isset( $path_parts[1] ) ) {
				$parts['path'] = '/' . $path_parts[1];
			} else {
				unset( $parts['path'] );
			}
		}

		return $parts;
	}
}

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