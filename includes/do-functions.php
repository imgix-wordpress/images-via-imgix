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
 * @param $content
 *
 * @return string Content with retina-enriched image tags.
 */
function add_retina( $content ) {
	$pattern = '/<img((?![^>]+srcset )([^>]*)';
	$pattern .= 'src=[\'"]([^\'"]*imgix.net[^\'"]*\?[^\'"]*w=[^\'"]*)[\'"]([^>]*)*?)>/i';
	$repl    = '<img$2src="$3" srcset="${3}, ${3}&amp;dpr=2 2x, ${3}&amp;dpr=3 3x,"$4>';
	$content = preg_replace( $pattern, $repl, $content );

	return preg_replace( $pattern, $repl, $content );
}

/**
 * Returns a array of global parameters to be applied in all images,
 * according to plugin's settings.
 *
 * @return array Global parameters to be appened at the end of each img URL.
 */
function get_global_params() {
	global $imgix_options;

	$params = [];
	// For now, only "auto" is supported.

	$auto = [];
	if ( ! empty( $imgix_options['auto_format'] ) ) {
		array_push( $auto, 'format' );
	}

	if ( ! empty( $imgix_options['auto_enhance'] ) ) {
		array_push( $auto, 'enhance' );
	}

	if ( ! empty( $auto ) ) {
		$params['auto'] = implode( ',', $auto );
	}

	return $params;
}

/**
 * Convert sizes in filename to parameters and returns origina filename without sizes.
 * If no size is found the original filename is returned.
 *
 * @param string $filename
 *
 * @return array with filename and size arguments.
 */
function convert_filename_to_size_args( $filename ) {
	$arguments = [];

	$filename = preg_replace_callback( '/-(?<width>\d+)x(?<height>\d+)(?<extension>\.\w{3,4}$)/', function ( $match ) use ( &$arguments ) {
		$arguments = [
			'w' => $match['width'],
			'h' => $match['height']
		];

		return $match['extension'];
	}, $filename );

	return [ $filename, $arguments ];
}

/**
 * Modify image urls for attachments to use imgix host.
 *
 * @param string $url
 *
 * @return string
 */
function imgix_file_url( $url ) {

	global $imgix_options;

	if ( empty ( $imgix_options['cdn_link'] ) ) {
		return $url;
	}

	$file = pathinfo( $url );

	if ( in_array( $file['extension'], [ 'jpg', 'gif', 'png', 'jpeg' ] ) ) {
		return str_replace( trailingslashit( home_url( '/' ) ), trailingslashit( $imgix_options['cdn_link'] ), $url );
	}

	return $url;
}

add_filter( 'wp_get_attachment_url', 'imgix_file_url' );
add_filter( 'imgix/add-image-url', 'imgix_file_url' );

/**
 * Modify image urls in srcset to use imgix host.
 *
 * @param array $sources
 *
 * @return array $sources
 */
function imgix_cdn_srcset( $sources ) {
	foreach ( $sources as $source ) {
		$sources[ $source['value'] ]['url'] = apply_filters( 'imgix/add-image-url', $sources[ $source['value'] ]['url'] );
	}

	return $sources;
}

add_filter( 'wp_calculate_image_srcset', 'imgix_cdn_srcset', 10 );

/**
 * Modify image urls in content to use imgix host.
 *
 * @param $content
 *
 * @return string
 */
function imgix_replace_non_wp_images( $content ) {
	global $imgix_options;

	if ( ! empty( $imgix_options['cdn_link'] ) ) {

		$content = preg_replace_callback( '/(?<=\shref="|\ssrc="|\shref=\'|\ssrc=\').*(?=\'|")/', function ( $match ) use ( $imgix_options ) {
			$url = $match[0];

			$pathinfo = pathinfo( $url );

			if ( in_array( $pathinfo['extension'], [ 'jpg', 'gif', 'png', 'jpeg' ], true ) ) {
				$file_url = parse_url( $url );
				if ( $file_url['host'] === parse_url( home_url( '/' ), PHP_URL_HOST ) ) {
					$cdn = parse_url( $imgix_options['cdn_link'] );
					foreach ( [ 'scheme', 'host', 'port' ] as $url_part ) {
						if ( isset( $cdn[ $url_part ] ) ) {
							$file_url[ $url_part ] = $cdn[ $url_part ];
						} else {
							unset( $file_url[ $url_part ] );
						}
					}

					list( $filename, $arguments ) = convert_filename_to_size_args( $pathinfo['basename'] );

					$arguments = array_merge( $arguments, get_global_params() );

					$file_url['path'] = trailingslashit( dirname( $file_url['path'] ) ) . $filename;

					if ( ! empty( $arguments ) ) {
						$file_url['query'] = empty( $file_url['query'] ) ? build_query( $arguments ) : $file_url['query'] . '&' . build_query( $arguments );
					}

					$url = http_build_url( $file_url );
				}
			}


			return esc_url( $url );
		}, $content );

	}

	return $content;
}

add_filter( 'the_content', 'imgix_replace_non_wp_images' );

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
		ob_start( 'add_retina' );
	}

	function buffer_end() {
		ob_end_flush();
	}

	add_action( 'after_setup_theme', 'buffer_start' );
	add_action( 'shutdown', 'buffer_end' );
	add_filter( 'the_content', 'add_retina' );
}
