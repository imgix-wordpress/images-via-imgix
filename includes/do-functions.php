<?php
/**
 *
 * @package imgix
 */

/**
 * Find all img tags with sources matching "imgix.net" without the parameter
 * "srcset" and add the "srcset" parameter to all those images, appending a new
 * source using the "dpr=2" modifier.
 *
 * @param string $content The content to search for images in.
 *
 * @return string Content with retina-enriched image tags.
 */
function add_retina( $content ) {
	$pattern = '/<img((?![^>]+srcset )([^>]*)';
	$pattern .= 'src=[\'"]([^\'"]*imgix.net[^\'"]*\?[^\'"]*w=[^\'"]*)[\'"]([^>]*)*?)>/i';
	$repl = '<img$2src="$3" srcset="${3}, ${3}&amp;dpr=2 2x, ${3}&amp;dpr=3 3x,"$4>';
	$content = preg_replace( $pattern, $repl, $content );

	return preg_replace( $pattern, $repl, $content );
}

/**
 * Extract all img tags from a given $content block into an array.
 *
 * @param string $content The content to search for images in.
 *
 * @return array An array of matching arrays with two keys: 'url' and 'params'.
 */
function imgix_extract_imgs( $content ) {
	preg_match_all( '/src=["\']http.+\/([^\s]+?)["\']/', $content, $matches );
	$results = array();

	if ( $matches ) {
		foreach ( $matches[1] as $url ) {
			array_push( $results, $url );
		}
	}

	return $results;
}

/**
 * Searches "$content" for all occurences of "$url" and add the given
 * querystring parameters to the URL, preserving existing querystring
 * parameters.
 *
 * @param string $url Current url.
 * @param string $params Parameter-string to add to the url.
 * @param string $content Content to replace URL in.
 *
 * @return string Content with matching URLs having the new querystrings.
 */
function apply_parameters_to_url( $url, $params, $content ) {
	$parts = explode( '?', $url . '?' );

	list( $base_url, $base_params ) = array( $parts[0], $parts[1] );

	$new_url = $old_url = $base_url;
	$new_url .= '?' . $params;
	$new_url .= $base_params ? '&amp;' . $base_params : '';
	$old_url .= $base_params ? '?' . $base_params : '';

	return str_replace( $old_url, $new_url, $content );
}

/**
 * Returns a string of global parameters to be applied in all images,
 * acording to plugin's settings.
 *
 * @return string Global parameters to be appened at the end of each img URL.
 */
function get_global_params_string() {
	global $imgix_options;
	$params = array();
	// For now, only "auto" is supported.
	$auto = array();

	if ( isset( $imgix_options['auto_format'] ) && $imgix_options['auto_format'] ) {
		array_push( $auto, 'format' );
	}

	if ( isset( $imgix_options['auto_enhance'] ) && $imgix_options['auto_enhance'] ) {
		array_push( $auto, 'enhance' );
	}

	if ( ! empty( $auto ) ) {
		array_push( $params, 'auto=' . implode( '%2C', $auto ) );
	}

	return implode( '&amp;', $params );
}

/**
 * Sanitize a given URL to make sure it has a scheme (or alternatively, '//' ),
 * host, path, and ends with a '/'.
 *
 * @param string $url The given URL.
 *
 * @return string A sanitized URL.
 */
function ensure_valid_url( $url ) {
	$slash = strpos( $url, '//' ) == 0 ? '//' : '';

	if ( $slash ) {
		$url = substr( $url, 2 );
	}

	$urlp = wp_parse_url( $url );
	$pref = array_key_exists( 'scheme', $urlp ) ? $urlp['scheme'] . '://' : $slash;

	if ( ! $slash && strpos( $pref, 'http' ) !== 0 ) {
		$pref = 'http://';
	}

	$result = $urlp['host'] ? $pref . $urlp['host'] : false;

	if ( $result ) {
		return trailingslashit( $result );
	}

	return null;
}

/**
 * Given a wordpress registered size keyword, return its properties.
 *
 * @param string $size The requested size keyword.
 *
 * @return array Size's width, height and crop values.
 */
function get_size_info( $size ) {
	global $_wp_additional_image_sizes;

	if ( $size === 'original' ) {
		return array(
			'width' => '',
			'height' => '',
			'crop' => false,
		);
	} elseif ( is_array( $size ) ) {
		return array(
			'width' => $size[1],
			'height' => $size[0],
			'crop' => false,
		);
	} elseif ( in_array( $size, array( 'thumbnail', 'medium', 'large' ), true ) ) {
		return array(
			'width' => get_option( $size . '_size_w' ),
			'height' => get_option( $size . '_size_h' ),
			'crop' => (boolean) get_option( $size . '_crop' ),
		);
	} elseif ( isset( $_wp_additional_image_sizes[ $size ] ) ) {
		return array(
			'width' => $_wp_additional_image_sizes[ $size ]['width'],
			'height' => $_wp_additional_image_sizes[ $size ]['height'],
			'crop' => $_wp_additional_image_sizes[ $size ]['crop'],
		);
	}

	return null;
}

/**
 * Parse through img element src attributes to extract height and width parameters
 * based on the structure of the src URL's path.
 *
 * For example, if we are giving an img src ending in "-400x300.png",
 * we return an array structured like so:
 *
 *    array(array(
 *      'raw' => '-400x300.png',
 *      'w' => '400',
 *      'h' => '300',
 *      'type' => 'png',
 *      'extra' => ''
 *    ))
 *
 * @param string $content The filename to search for sizes in.
 *
 * @return array An array of arrays that has extracted the URL's inferred w',
 * 'h', and 'type'
 */
function imgix_extract_img_details( $content ) {
	preg_match_all( '/-([0-9]+)x([0-9]+)\.([^"\']+)/', $content, $matches );

	$lookup = array( 'raw', 'w', 'h', 'type' );
	$data = array();

	foreach ( $matches as $k => $v ) {

		foreach ( $v as $index => $value ) {

			if ( ! array_key_exists( $index, $data ) ) {
				$data[ $index ] = array();
			}

			$key = $lookup[ $k ];

			if ( $key === 'type' ) {
				if ( strpos( $value, '?' ) !== false ) {
					$parts = explode( '?', $value );
					$data[ $index ]['type'] = $parts[0];
					$data[ $index ]['extra'] = $parts[1];
				} else {
					$data[ $index ]['type'] = $value;
					$data[ $index ]['extra'] = '';
				}
			} else {
				$data[ $index ][ $key ] = $value;
			}
		}
	}

	return $data;
}

/**
 * Finds references to the wordpress site URL in the given string,
 * (optionally prefixed by "src"), and changes them to the imgix URL.
 *
 * @param string $str Hostname to searh for.
 * @param bool $require_prefix Require prefix.
 *
 * @return array An array countaining the final string, and a boolean value
 * indicating if it's different from the given input string.
 */
function replace_host( $str, $require_prefix = false ) {
	global $imgix_options;

	if ( ! isset( $imgix_options['cdn_link'] ) || ! $imgix_options['cdn_link'] ) {
		return array( $str, false );
	}

	$new_host = ensure_valid_url( $imgix_options['cdn_link'] );
	if ( ! $new_host ) {
		return array( $str, false );
	}

	// As soon as srcset is supported…
	// $prefix = $require_prefix? 'srcs?e?t?=[\'"]|,[\S+\n\r\s]*': '';
	$prefix = $require_prefix? 'src=[\'"]': '';
	$src = '(' . preg_quote( home_url( '/' ), '/' ) . '|\/\/)';
	$patt = '/(' . $prefix . ' )' . $src . '/i';
	$str = preg_replace( $patt, '$1' . $new_host, $str, -1, $count );

	return array( $str, (boolean) $count );
}

/**
 * Given an image URL and the target wordpress size to display the image,
 * return the appropriate transformed image source.
 *
 * @param string $src  The image source.
 * @param string $size Requested image size.
 *
 * @return string equivalent imgix source with correct parameters.
 */
function replace_src( $src, $size ) {
	$size_info = get_size_info( $size );

	if ( $size_info ) {

		list( $src, $match_src ) = replace_host( $src, false );

		if ( $match_src ) {
			$g_params = get_global_params_string();
			$params = array();

			if ( isset( $size_info['crop'] ) && $size_info['crop'] ) {
				array_push( $params, 'fit=crop' );
			}

			if ( isset( $size_info['width'] ) && $size_info['width'] ) {
				array_push( $params, 'w=' . $size_info['width'] );
			}

			if ( isset( $size_info['height'] ) && $size_info['height'] ) {
				array_push( $params, 'h=' . $size_info['height'] );
			}

			$p = implode( '&amp;', $params );
			$p = ( $p && $g_params ) ? $p . '&amp;' . $g_params : $p . $g_params;
			$src = apply_parameters_to_url( $src, $p, $src );
		}
	}

	return $src;
}

/**
 * Replace image url with imgix url for attachments
 *
 * @param string $url The original url
 *
 * @return string
 */
function imgix_file_url( $url ) {
	global $imgix_options;

	$imgix_url = $imgix_options['cdn_link'];
	$file = pathinfo( $url );

	if ( ! $imgix_url ) {
		return $url;
	}

	if ( in_array( $file['extension'], array( 'jpg', 'gif', 'png', 'jpeg' ) ) ) {
		return str_replace( get_bloginfo( 'wpurl' ), $imgix_url, $url );
	}

	return $url;
}
add_filter( 'wp_get_attachment_url', 'imgix_file_url' );
add_filter( 'imgix/add-image-url', 'imgix_file_url' );

/**
 * Replace srcset urls with imgix urls.
 *
 * @param array $sources
 *
 * @return array $sources
 *
 */
function imgix_cdn_srcset( $sources ) {
	foreach ( $sources as $source ) {
		$sources[ $source['value'] ]['url'] = apply_filters( 'imgix/add-image-url', $sources[ $source['value'] ]['url'] );
	}

	return $sources;
}
add_filter( 'wp_calculate_image_srcset', 'imgix_cdn_srcset', 10 );

/**
 * Replace image urls in content with imgix urls.
 *
 * @param string $content The content to search for images in.
 *
 * @return string
 */
function imgix_replace_non_wp_images( $content ) {
	list( $content, $match ) = replace_host( $content, true );

	if ( $match ) {
		// Apply image-tag-encoded params for every image in $content.
		foreach ( imgix_extract_img_details( $content ) as $img ) {
			$to_replace = $img['raw'];
			$extra_params = $img['extra'] ? '&amp;' . $img['extra'] : '';
			$new_url = '.' . $img['type'] . '?h=' . $img['h'] . '&amp;w=' . $img['w'] . $extra_params;
			$content = str_replace( $to_replace, $new_url, $content );
		}

		// Apply global parameters.
		$g_params = get_global_params_string();
		foreach ( imgix_extract_imgs( $content ) as $img_url ) {
			$content = apply_parameters_to_url( $img_url, $g_params, $content );
		}
	}
	return $content;
}
add_filter( 'the_content', 'imgix_replace_non_wp_images' );

/**
 * Add tag to prefetch imgix url to head.
 */
function imgix_wp_head() {
	global $imgix_options;

	if ( isset( $imgix_options['cdn_link'] ) && $imgix_options['cdn_link'] ) {
		printf( "<link rel='dns-prefetch' href='%s'/>",
			esc_url( preg_replace( '/^https?:/', '', untrailingslashit( $imgix_options['cdn_link'] ) ) )
		);
	}
}
add_action( 'wp_head', 'imgix_wp_head', 1 );

if ( isset( $imgix_options['add_dpi2_srcset'] ) && $imgix_options['add_dpi2_srcset'] ) {
	function buffer_start() {
		ob_start( 'add_retina' ); }
	function buffer_end() {
		ob_end_flush(); }
	add_action( 'after_setup_theme', 'buffer_start' );
	add_action( 'shutdown', 'buffer_end' );
	add_filter( 'the_content', 'add_retina' );
}
