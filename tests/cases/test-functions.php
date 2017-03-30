<?php

class DoFunctionsTest extends WP_UnitTestCase {

	protected static $upload_url;

	/**
	 * @var Images_Via_Imgix
	 */
	protected static $plugin_instance;

	protected $attachment_id;
	protected $attachment_url;
	protected $attachment_filename;

	public static function setUpBeforeClass() {
		$wp_upload_dir    = wp_upload_dir( null, false );
		self::$upload_url = $wp_upload_dir['url'];

		self::$plugin_instance = Images_Via_Imgix::instance();
	}

	public function tearDown() {
		// Remove all uploads.
		$this->remove_added_uploads();
		parent::tearDown();
	}

	public function setUp() {
		parent::setUp();

		$filename = ( DIR_TESTDATA . '/images/test-image-large.png' );
		$contents = file_get_contents( $filename );

		$upload = wp_upload_bits( basename( $filename ), null, $contents );
		$this->assertTrue( empty( $upload['error'] ) );

		$this->attachment_id = $this->_make_attachment( $upload );

		$this->attachment_url      = $upload['url'];
		$this->attachment_filename = basename( $upload['url'] );
	}

	public function test_sanity_check() {
		$this->assertEquals( 'http://example.org/', home_url( '/' ) );
	}

	public function test_filter_wp_get_attachment_url_no_imgix_cdn() {
		$this->disable_cdn();

		$result = wp_get_attachment_image_src( $this->attachment_id, 'full' );

		$this->assertEquals( $this->attachment_url, $result[0] );
	}

	public function test_filter_wp_get_attachment_url_with_imgix_cdn() {
		$this->enable_cdn();

		$expected = $this->generate_cdn_file_url( $this->attachment_filename );

		$result = wp_get_attachment_image_src( $this->attachment_id, 'full' );
		$this->assertEquals( $expected, $result[0] );
	}

	public function test_filter_wp_get_attachment_url_with_size() {
		$this->enable_cdn();

		$expected = $this->generate_cdn_file_url( $this->attachment_filename . '?w=300&h=300' );

		$result = wp_get_attachment_image_src( $this->attachment_id, 'medium' );
		$this->assertEquals( $expected, $result[0] );
	}

	public function test_imgix_replace_non_wp_images_no_cdn() {
		$this->disable_cdn();

		$string = '<img src="' . $this->generate_upload_file_url( 'example.gif' ) . '">';

		$this->assertEquals( $string, self::$plugin_instance->replace_images_in_content( $string ) );
	}

	public function test_imgix_replace_non_wp_images_no_match() {
		$this->enable_cdn();

		$string = '<html><head></head><body></body></html>';

		$this->assertEquals( $string, self::$plugin_instance->replace_images_in_content( $string ) );
	}

	public function test_imgix_replace_non_wp_images_other_src() {
		$this->enable_cdn();

		$string = '<img src="https://www.google.com/example.gif">';

		$this->assertEquals( $string, self::$plugin_instance->replace_images_in_content( $string ) );
	}

	public function test_imgix_replace_non_wp_images_with_cdn() {
		$this->enable_cdn();

		$string   = '<img src="' . $this->generate_upload_file_url( 'example.gif' ) . '">';
		$expected = '<img src="' . $this->generate_cdn_file_url( 'example.gif' ) . '">';

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

