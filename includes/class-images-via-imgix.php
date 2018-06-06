<?php

class Images_Via_Imgix {

	/**
	 * The instance of the class.
	 *
	 * @var Images_Via_Imgix
	 */
	protected static $instance;

	/**
	 * Plugin options
	 *
	 * @var array
	 */
	protected $options = [];

	/**
	 * Buffer is started by plugin and should be ended on shutdown.
	 *
	 * @var bool
	 */
	protected $buffer_started = false;

	/**
	 * ImagesViaImgix constructor.
	 */
	public function __construct() {
		$this->options = get_option( 'imgix_settings', [] );

		// Change filter load order to ensure it loads after other CDN url transformations i.e. Amazon S3 which loads at position 99.
		add_filter( 'wp_get_attachment_url', [ $this, 'replace_image_url' ], 100 );
		add_filter( 'imgix/add-image-url', [ $this, 'replace_image_url' ] );

		add_filter( 'image_downsize', [ $this, 'image_downsize' ], 10, 3 );

		add_filter( 'wp_calculate_image_srcset', [ $this, 'calculate_image_srcset' ], 10, 5 );

		add_filter( 'the_content', [ $this, 'replace_images_in_content' ] );
		add_action( 'wp_head', [ $this, 'prefetch_cdn' ], 1 );

		add_action( 'after_setup_theme', [ $this, 'buffer_start_for_retina' ] );
		add_action( 'shutdown', [ $this, 'buffer_end_for_retina' ], 0 );
	}

	/**
	 * Plugin loader instance.
	 *
	 * @return Images_Via_Imgix
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Set a single option.
	 *
	 * @param string $key
	 * @param mixed $value
	 */
	public function set_option( $key, $value ) {
		$this->options[ $key ] = $value;
	}

	/**
	 * Get a single option.
	 *
	 * @param  string $key
	 * @param  mixed $default
	 * @return mixed
	 */
	public function get_option( $key, $default = '' ) {
		return array_key_exists( $key, $this->options ) ? $this->options[ $key ] : $default;
	}

	/**
	 * Override options from settings.
	 * Used in unit tests.
	 *
	 * @param array $options
	 */
	public function set_options( $options ) {
		$this->options = $options;
	}

	/**
	 * Find all img tags with sources matching "imgix.net" without the parameter
	 * "srcset" and add the "srcset" parameter to all those images, appending a new
	 * source using the "dpr=2" modifier.
	 *
	 * @param $content
	 *
	 * @return string Content with retina-enriched image tags.
	 */
	public function add_retina( $content ) {
		$pattern = '/<img((?![^>]+srcset )([^>]*)';
		$pattern .= 'src=[\'"]([^\'"]*imgix.net[^\'"]*\?[^\'"]*w=[^\'"]*)[\'"]([^>]*)*?)>/i';
		$repl    = '<img$2src="$3" srcset="${3}, ${3}&amp;dpr=2 2x, ${3}&amp;dpr=3 3x,"$4>';
		$content = preg_replace( $pattern, $repl, $content );

		return preg_replace( $pattern, $repl, $content );
	}

	/**
	 * Modify image urls for attachments to use imgix host.
	 *
	 * @param string $url
	 *
	 * @return string
	 */
	public function replace_image_url( $url ) {
		if ( ! empty ( $this->options['cdn_link'] ) ) {
			$parsed_url = parse_url( $url );

			//Check if image is hosted on current site url -OR- the CDN url specified. Using strpos because we're comparing the host to a full CDN url.
			if (
				isset( $parsed_url['host'], $parsed_url['path'] )
				&& ($parsed_url['host'] === parse_url( home_url( '/' ), PHP_URL_HOST ) || ( isset($this->options['external_cdn_link']) && ! empty($this->options['external_cdn_link']) && strpos( $this->options['external_cdn_link'], $parsed_url['host']) !== false ) )
				&& preg_match( '/\.(jpg|jpeg|gif|png)$/i', $parsed_url['path'] )
			) {
				$cdn = parse_url( $this->options['cdn_link'] );

				foreach ( [ 'scheme', 'host', 'port' ] as $url_part ) {
					if ( isset( $cdn[ $url_part ] ) ) {
						$parsed_url[ $url_part ] = $cdn[ $url_part ];
					} else {
						unset( $parsed_url[ $url_part ] );
					}
				}

				if ( ! empty( $this->options['external_cdn_link'] ) ) {
					$cdn_path = parse_url( $this->options['external_cdn_link'],  PHP_URL_PATH );

					if ( isset( $cdn_path, $parsed_url['path'] ) && $cdn_path !== '/' && ! empty( $parsed_url['path'] ) ) {
						$parsed_url['path'] = str_replace( $cdn_path, '', $parsed_url['path'] );
					}
				}

				$url = http_build_url( $parsed_url );

				$url = add_query_arg( $this->get_global_params(), $url );
			}
		}

		return $url;
	}

	/**
	 * Set params when running image_downsize
	 *
	 * @param false|array  $return
	 * @param int          $attachment_id
	 * @param string|array $size
	 *
	 * @return false|array
	 */
	public function image_downsize( $return, $attachment_id, $size ) {
		if ( ! empty ( $this->options['cdn_link'] ) ) {
			$img_url = wp_get_attachment_url( $attachment_id );

			$params = [];
			if ( is_array( $size ) ) {
				$params['w'] = $width = isset( $size[0] ) ? $size[0] : 0;
				$params['h'] = $height = isset( $size[1] ) ? $size[1] : 0;
			} else {
				$available_sizes = $this->get_all_defined_sizes();
				if ( isset( $available_sizes[ $size ] ) ) {
					$size        = $available_sizes[ $size ];
					$params['w'] = $width = $size['width'];
					$params['h'] = $height = $size['height'];
				}
			}

			$params = array_filter( $params );

			$img_url = add_query_arg( $params, $img_url );

			if ( ! isset( $width ) || ! isset( $height ) ) {
				// any other type: use the real image
				$meta   = wp_get_attachment_metadata( $attachment_id );

				// Image sizes is missing for pdf thumbnails
				$meta['width']  = isset( $meta['width'] ) ? $meta['width'] : 0;
				$meta['height'] = isset( $meta['height'] ) ? $meta['height'] : 0;

				$width  = isset( $width ) ? $width : $meta['width'];
				$height = isset( $height ) ? $height : $meta['height'];
			}

			$return = [ $img_url, $width, $height, true ];
		}

		return $return;
	}

	/**
	 * Change url for images in srcset
	 *
	 * @param array  $sources
	 * @param array  $size_array
	 * @param string $image_src
	 * @param array  $image_meta
	 * @param int    $attachment_id
	 *
	 * @return array
	 */
	public function calculate_image_srcset( $sources, $size_array, $image_src, $image_meta, $attachment_id ) {
		if ( ! empty ( $this->options['cdn_link'] ) ) {
			foreach ( $sources as $i => $image_size ) {
				if ( $image_size['descriptor'] === 'w' ) {
					if ( $attachment_id ) {
						$image_src = wp_get_attachment_url( $attachment_id );
					}

					$image_src            = remove_query_arg( 'h', $image_src );
					$sources[ $i ]['url'] = add_query_arg( 'w', $image_size['value'], $image_src );
				}
			}
		}

		return $sources;
	}

	/**
	 * Modify image urls in content to use imgix host.
	 *
	 * @param $content
	 *
	 * @return string
	 */
	public function replace_images_in_content( $content ) {
		// Added null to apply filters wp_get_attachment_url to improve compatibility with https://en-gb.wordpress.org/plugins/amazon-s3-and-cloudfront/ - does not break wordpress if the plugin isn't present.
		if ( ! empty ( $this->options['cdn_link'] ) ) {
			if ( preg_match_all( '/<img\s[^>]*src=([\"\']??)([^\" >]*?)\1[^>]*>/iU', $content, $matches ) ) {
				foreach ( $matches[2] as $image_src ) {
					$content = str_replace( $image_src, apply_filters( 'wp_get_attachment_url', $image_src, null ), $content );
				}
			}

			if ( preg_match_all( '/<img\s[^>]*srcset=([\"\']??)([^\">]*?)\1[^>]*\/?>/iU', $content, $matches ) ) {

				foreach ( $matches[2] as $image_srcset ) {
					$new_image_srcset = preg_replace_callback( '/(\S+)(\s\d+\w)/', function ( $srcset_matches ) {
						return apply_filters( 'wp_get_attachment_url', $srcset_matches[1], null ) . $srcset_matches[2];
					}, $image_srcset );

					$content = str_replace( $image_srcset, $new_image_srcset, $content );
				}
			}

			if ( preg_match_all( '/<a\s[^>]*href=([\"\']??)([^\" >]*?)\1[^>]*>(.*)<\/a>/iU', $content, $matches ) ) {
				foreach ( $matches[0] as $link ) {
					$content = str_replace( $link[2], apply_filters( 'wp_get_attachment_url', $link[2], null ), $content );
				}
			}

      if ( preg_match_all('/url\(([\s])?([\"|\'])?(.*?)([\"|\'])?([\s])?\)/i', $content, $matches ) ) {
        foreach ( $matches[3] as $image_src ) {
          $content = str_replace( $image_src, apply_filters( 'wp_get_attachment_url', $image_src, null ), $content );
        }
      }
		}
		return $content;
	}

	/**
	 * Add tag to dns prefetch cdn host
	 */
	public function prefetch_cdn() {
		if ( ! empty ( $this->options['cdn_link'] ) ) {
			$host = parse_url( $this->options['cdn_link'], PHP_URL_HOST );

			printf(
				'<link rel="dns-prefetch" href="%s"/>',
				esc_attr( '//' . $host )
			);
		}
	}

	/**
	 * Start output buffer if auto retina is enabled
	 */
	public function buffer_start_for_retina() {
		if ( ! empty ( $this->options['add_dpi2_srcset'] ) ) {
			$this->buffer_started = ob_start( [ $this, 'add_retina' ] );
		}
	}

	/**
	 * Stop output buffer if it was enabled by the plugin
	 */
	public function buffer_end_for_retina() {
		if ( $this->buffer_started && ob_get_level() ) {
			ob_end_flush();
		}
	}

	/**
	 * Returns a array of global parameters to be applied in all images,
	 * according to plugin's settings.
	 *
	 * @return array Global parameters to be appened at the end of each img URL.
	 */
	protected function get_global_params() {
		$params = [];

		// For now, only "auto" is supported.
		$auto = [];
		if ( ! empty ( $this->options['auto_format'] ) ) {
			array_push( $auto, 'format' );
		}

		if ( ! empty ( $this->options['auto_enhance'] ) ) {
			array_push( $auto, 'enhance' );
		}

		if ( ! empty ( $this->options['auto_compress'] ) ) {
			array_push( $auto, 'compress' );
		}

		if ( ! empty( $auto ) ) {
			$params['auto'] = implode( '%2C', $auto );
		}

		return $params;
	}

	/**
	 * Get all defined image sizes
	 *
	 * @return array
	 */
	protected function get_all_defined_sizes() {
		// Make thumbnails and other intermediate sizes.
		$theme_image_sizes = wp_get_additional_image_sizes();

		$sizes = [];
		foreach ( get_intermediate_image_sizes() as $s ) {
			$sizes[ $s ] = [ 'width' => '', 'height' => '', 'crop' => false ];
			if ( isset( $theme_image_sizes[ $s ] ) ) {
				// For theme-added sizes
				$sizes[ $s ]['width']  = intval( $theme_image_sizes[ $s ]['width'] );
				$sizes[ $s ]['height'] = intval( $theme_image_sizes[ $s ]['height'] );
				$sizes[ $s ]['crop']   = $theme_image_sizes[ $s ]['crop'];
			} else {
				// For default sizes set in options
				$sizes[ $s ]['width']  = get_option( "{$s}_size_w" );
				$sizes[ $s ]['height'] = get_option( "{$s}_size_h" );
				$sizes[ $s ]['crop']   = get_option( "{$s}_crop" );
			}
		}

		return $sizes;
	}
}

Images_Via_Imgix::instance();
