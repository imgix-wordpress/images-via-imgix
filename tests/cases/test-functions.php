<?php

class DoFunctionsTest extends WP_UnitTestCase {

	protected static $upload_url;

	/**
	 * @var Images_Via_Imgix
	 */
	protected static $plugin_instance;

	public static function setUpBeforeClass() {
		$wp_upload_dir    = wp_upload_dir( null, false );
		self::$upload_url = $wp_upload_dir['url'];

		self::$plugin_instance = Images_Via_Imgix::instance();
	}

	public function test_sanity_check() {
		$this->assertEquals( 'http://example.org/', home_url( '/' ) );
	}

	public function test_filter_wp_get_attachment_url_no_imgix_cdn() {
		$this->disable_cdn();

		$upload_file_url = $this->generate_upload_file_url( 'example.jpg' );
		$result          = self::$plugin_instance->replace_image_url( $upload_file_url );

		$this->assertEquals( $upload_file_url, $result );
	}

	public function test_filter_wp_get_attachment_url_with_imgix_cdn() {
		$this->enable_cdn();

		$upload_file_url = $this->generate_upload_file_url( 'example.jpg' );
		$expected        = $this->generate_cdn_file_url( 'example.jpg' );

		$result = self::$plugin_instance->replace_image_url( $upload_file_url );
		$this->assertEquals( $expected, $result );
	}

	public function test_filter_wp_get_attachment_url_size_arguments() {
		$this->enable_cdn();

		$upload_file_url = $this->generate_upload_file_url( 'example-400x300.png' );
		$expected        = $this->generate_cdn_file_url( 'example.png?w=400&h=300' );

		$result = self::$plugin_instance->replace_image_url( $upload_file_url );
		$this->assertEquals( $expected, $result );
	}


	public function test_filter_wp_get_attachment_url_not_image() {
		$this->enable_cdn();

		$upload_file_url = $this->generate_upload_file_url( 'example.pdf' );
		$expected        = $upload_file_url;

		$result = self::$plugin_instance->replace_image_url( $upload_file_url );
		$this->assertEquals( $expected, $result );
	}

	public function test_filter_wp_calculate_image_srcset_no_cdn() {
		$this->disable_cdn();

		$sources = [
			400 => [
				'url'        => $this->generate_upload_file_url( 'example.png' ),
				'descriptor' => 'w',
				'value'      => '400'
			],
			300 => [
				'url'        => $this->generate_upload_file_url( 'example.png?w=400&h=300' ),
				'descriptor' => 'w',
				'value'      => '300'
			]
		];

		$result = self::$plugin_instance->replace_host_in_srcset( $sources );

		$this->assertEquals( $sources, $result );
	}

	public function test_filter_wp_calculate_image_srcset_with_cdn() {
		$this->enable_cdn();

		$size_array    = [ 400, 400 ];
		$image_src     = $this->generate_upload_file_url( 'example.png' );
		$image_meta    = [];
		$attachment_id = 0;

		$sources = [
			400 => [
				'url'        => $this->generate_upload_file_url( 'example.png' ),
				'descriptor' => 'w',
				'value'      => '400'
			],
			300 => [
				'url'        => $this->generate_upload_file_url( 'example-300x300.png' ),
				'descriptor' => 'w',
				'value'      => '300'
			]
		];

		$expected = [
			400 => [
				'url'        => $this->generate_cdn_file_url( 'example.png' ),
				'descriptor' => 'w',
				'value'      => '400'
			],
			300 => [
				'url'        => $this->generate_cdn_file_url( 'example.png?w=300&h=300' ),
				'descriptor' => 'w',
				'value'      => '300'
			]
		];


		$result = apply_filters( 'wp_calculate_image_srcset', $sources, $size_array, $image_src, $image_meta, $attachment_id );

		$this->assertEquals( $expected, $result );
	}

	public function test_imgix_replace_non_wp_images_no_cdn() {
		$this->disable_cdn();

		$string = '<img src="' . $this->generate_upload_file_url( 'example.gif' ) . '" />';

		$this->assertEquals( $string, self::$plugin_instance->replace_images_in_content( $string ) );
	}

	public function test_imgix_replace_non_wp_images_no_match() {
		$this->enable_cdn();

		$string = '<html><head></head><body></body></html>';

		$this->assertEquals( $string, self::$plugin_instance->replace_images_in_content( $string ) );
	}

	public function test_imgix_replace_non_wp_images_other_src() {
		$this->enable_cdn();

		$string = '<img src="https://www.google.com/example.gif" />';

		$this->assertEquals( $string, self::$plugin_instance->replace_images_in_content( $string ) );
	}

	public function test_imgix_replace_non_wp_images_with_cdn() {
		$this->enable_cdn();

		$string   = '<img src="' . $this->generate_upload_file_url( 'example.gif' ) . '" />';
		$expected = '<img src="' . $this->generate_cdn_file_url( 'example.gif' ) . '" />';

		$this->assertEquals( $expected, self::$plugin_instance->replace_images_in_content( $string ) );
	}

	public function test_imgix_replace_non_wp_images_size_arguments() {
		$this->enable_cdn();

		$string   = '<img src="' . $this->generate_upload_file_url( 'example-400x300.gif' ) . '" />';
		$expected = '<img src="' . $this->generate_cdn_file_url( 'example.gif?w=400&#038;h=300' ) . '" />';

		$this->assertEquals( $expected, self::$plugin_instance->replace_images_in_content( $string ) );
	}


	protected function generate_upload_file_url( $filename ) {
		return trailingslashit( self::$upload_url ) . $filename;
	}

	protected function generate_cdn_file_url( $filename ) {
		$file_url = parse_url( $this->generate_upload_file_url( $filename ) );
		$cdn      = parse_url( 'https://my-source.imgix.com' );

		foreach ( [ 'scheme', 'host', 'port' ] as $url_part ) {
			if ( isset( $cdn[ $url_part ] ) ) {
				$file_url[ $url_part ] = $cdn[ $url_part ];
			} else {
				unset( $file_url[ $url_part ] );
			}
		}

		$file_url = http_build_url( $file_url );

		return $file_url;
	}

	protected function enable_cdn() {
		self::$plugin_instance->set_options( [
			'cdn_link' => 'https://my-source.imgix.com'
		] );
	}

	protected function disable_cdn() {
		self::$plugin_instance->set_options( [] );
	}
}

