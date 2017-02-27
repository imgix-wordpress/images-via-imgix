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

		add_filter( 'wp_get_attachment_url', [ $this, 'replace_image_url' ] );
		add_filter( 'imgix/add-image-url', [ $this, 'replace_image_url' ] );

		add_filter( 'wp_calculate_image_srcset', [ $this, 'replace_host_in_srcset' ], 10 );
		add_filter( 'the_content', [ $this, 'replace_images_in_content' ] );
		add_action( 'wp_head', [ $this, 'prefetch_cdn' ], 1 );

		add_action( 'after_setup_theme', [ $this, 'buffer_start_for_retina' ] );
		add_action( 'shutdown', [ $this, 'buffer_end_for_retina' ] );
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
			$pathinfo = pathinfo( $url );

			if ( isset( $pathinfo['extension'] ) && in_array( $pathinfo['extension'], [
					'jpg',
					'gif',
					'png',
					'jpeg'
				] )
			) {
				$parsed_url = parse_url( $url );
				if ( isset( $parsed_url['host'] ) && $parsed_url['host'] === parse_url( home_url( '/' ), PHP_URL_HOST ) ) {
					$cdn = parse_url( $this->options['cdn_link'] );
					foreach ( [ 'scheme', 'host', 'port' ] as $url_part ) {
						if ( isset( $cdn[ $url_part ] ) ) {
							$parsed_url[ $url_part ] = $cdn[ $url_part ];
						} else {
							unset( $parsed_url[ $url_part ] );
						}
					}

					list( $filename, $arguments ) = $this->convert_filename_to_size_args( $pathinfo['basename'] );

					$arguments = array_merge( $arguments, $this->get_global_params() );

					$parsed_url['path'] = trailingslashit( dirname( $parsed_url['path'] ) ) . $filename;

					if ( ! empty( $arguments ) ) {
						$parsed_url['query'] = empty( $parsed_url['query'] ) ? build_query( $arguments ) : $parsed_url['query'] . '&' . build_query( $arguments );
					}

					$url = http_build_url( $parsed_url );
				}
			}
		}

		return $url;
	}

	/**
	 * Modify image urls in srcset to use imgix host.
	 *
	 * @param array $sources
	 *
	 * @return array $sources
	 */
	public function replace_host_in_srcset( $sources ) {
		foreach ( $sources as $source ) {
			$sources[ $source['value'] ]['url'] = apply_filters( 'imgix/add-image-url', $sources[ $source['value'] ]['url'] );
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
		if ( ! empty ( $this->options['cdn_link'] ) ) {
			$content = preg_replace_callback( '/(?<=\shref="|\ssrc="|\shref=\'|\ssrc=\').*(?=\'|")/', function ( $match ) {
				return esc_url( apply_filters( 'imgix/add-image-url', $match[0] ) );
			}, $content );

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
			$this->buffer_started = true;
			ob_start( [ $this, 'add_retina' ] );
		}
	}

	/**
	 * Stop output buffer if it was enabled by the plugin
	 */
	public function buffer_end_for_retina() {
		if ( $this->buffer_started === true ) {
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
	protected function convert_filename_to_size_args( $filename ) {
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
}

Images_Via_Imgix::instance();
