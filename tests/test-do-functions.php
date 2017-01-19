<?php

class DoFunctionsTest extends WP_UnitTestCase {

	function tearDown() {
		global $imgix_options;
		$imgix_options = array();
	}

	function test_sanity_check() {
		$this->assertEquals( 'http://example.org/', home_url( '/' ) );
	}

	// #ensure_valid_url
	function test_ensure_valid_url_adds_trailing_slash() {
		$this->assertEquals( 'http://example.com/cats.gif/', ensure_valid_url( 'http://example.com/cats.gif' ) );
	}

	function test_ensure_valid_url_adds_http_scheme() {
		$this->assertEquals( 'http://example.com/cats.gif/', ensure_valid_url( '//example.com/cats.gif' ) );
	}

	function test_ensure_valid_url_maintains_https() {
		$this->assertEquals( 'https://example.com/cats.gif/', ensure_valid_url( 'https://example.com/cats.gif' ) );
	}

	function test_ensure_valid_url_adds_http() {
		$this->assertEquals( 'http://NOTAVALIDURL/', ensure_valid_url( 'NOTAVALIDURL' ) ); // WTF
	}

	function test_ensure_valid_url_with_null() {
		$this->assertEquals( 'http://', ensure_valid_url( null ) ); // WTF
	}

	// #imgix_extract_imgs
	function test_imgix_extract_imgs_extracts_nothing() {
		$result = imgix_extract_imgs( '<html><head></head><body></body></html>' );
		$expected = array();

		$this->assertEquals( count( $expected ), count( $result ) );
	}

	function test_imgix_extract_imgs_extracts_single_image() {
		$result = imgix_extract_imgs( '<img src="https://google.com/cats.gif" />' );
		$expected = array(
			array(
				'url' => 'cats.gif',
				'params' => '',
			),
		);

		$this->assertEquals( count( $expected ), count( $result ) );
		$this->assertEquals( $expected[0], $result[0] );
	}

	function test_imgix_extract_imgs_extracts_single_image_with_params() {
		$result = imgix_extract_imgs( '<img src="https://google.com/cats.gif?party=1&bad-vibes=0" />' );
		$expected = array(
			array(
				'url' => 'cats.gif',
				'params' => 'party=1&bad-vibes=0',
			),
		);

		$this->assertEquals( count( $expected ), count( $result ) );
		$this->assertEquals( $expected[0], $result[0] );
	}

	function test_imgix_extract_imgs_extracts_single_image_with_params_and_single_quotes() {
		$result = imgix_extract_imgs( "<img src='https://google.com/cats.gif?party=1&bad-vibes=0' />" );
		$expected = array(
			array(
				'url' => 'cats.gif',
				'params' => 'party=1&bad-vibes=0',
			),
		);

		$this->assertEquals( count( $expected ), count( $result ) );
		$this->assertEquals( $expected[0], $result[0] );
	}

	// #imgix_extract_img_details
	function test_imgix_extract_img_details_noop() {
		$content = 'youshallnotmatch.png';
		$expected = array();
		$result = imgix_extract_img_details( $content );

		$this->assertEquals( $expected, $result );
	}

	function test_imgix_extract_img_details_sanity() {
		$content = '-400x300.png';
		$expected = array(array(
			'raw' => '-400x300.png',
			'w' => '400',
			'h' => '300',
			'type' => 'png',
			'extra' => '',
		),
		);
		$result = imgix_extract_img_details( $content );

		$this->assertEquals( $expected, $result );
	}

	function test_imgix_extract_img_details_img_tag_single_quote() {
		$content = "<img src='https://google.com/cats-400x300.gif' />";
		$expected = array(array(
			'raw' => '-400x300.gif',
			'w' => '400',
			'h' => '300',
			'type' => 'gif',
			'extra' => '',
		),
		);
		$result = imgix_extract_img_details( $content );

		$this->assertEquals( $expected, $result );
	}

	function test_imgix_extract_img_details_img_tag_double_quote() {
		$content = '<img src="https://google.com/cats-400x300.gif" />';
		$expected = array(array(
			'raw' => '-400x300.gif',
			'w' => '400',
			'h' => '300',
			'type' => 'gif',
			'extra' => '',
		),
		);
		$result = imgix_extract_img_details( $content );

		$this->assertEquals( $expected, $result );
	}

	// #imgix_replace_content_cdn
	function test_imgix_replace_content_cdn_on_img_tag_src_attribute() {
		global $imgix_options;
		$imgix_options['cdn_link'] = 'https://my-source.imgix.com';

		$string = '<img src="http://example.org/wp-content/cats.gif" />';
		$this->assertEquals( '<img src="https://my-source.imgix.com/wp-content/cats.gif?" />', imgix_replace_content_cdn( $string ) );
	}

	function test_imgix_replace_content_cdn_on_img_tag_href_attribute() {
		global $imgix_options;
		$imgix_options['cdn_link'] = 'https://my-source.imgix.com';

		$string = '<img href="http://example.org/wp-content/cats.gif" />';
		$this->assertEquals( '<img href="https://my-source.imgix.com/wp-content/cats.gif" />', imgix_replace_content_cdn( $string ) );
	}

	function test_imgix_replace_content_cdn_on_img_tag_src_attribute_with_single_quotes() {
		global $imgix_options;
		$imgix_options['cdn_link'] = 'https://my-source.imgix.com';

		$string = "<img src='http://example.org/wp-content/cats.gif' />";
		$this->assertEquals( "<img src='https://my-source.imgix.com/wp-content/cats.gif' />", imgix_replace_content_cdn( $string ) );
	}

	function test_imgix_replace_content_cdn_on_img_tag_href_attribute_with_single_quotes() {
		global $imgix_options;
		$imgix_options['cdn_link'] = 'https://my-source.imgix.com';

		$string = "<img href='http://example.org/wp-content/cats.gif' />";
		$this->assertEquals( "<img href='https://my-source.imgix.com/wp-content/cats.gif' />", imgix_replace_content_cdn( $string ) );
	}

	function test_imgix_replace_content_cdn_adds_auto_format_parameter() {
		global $imgix_options;
		$imgix_options['cdn_link'] = 'https://my-source.imgix.com';
		$imgix_options['auto_format'] = '1';

		$string = '<img src="http://example.org/wp-content/cats.gif" />';
		$this->assertEquals( '<img src="https://my-source.imgix.com/wp-content/cats.gif?auto=format" />', imgix_replace_content_cdn( $string ) );
	}

	function test_imgix_replace_content_cdn_adds_auto_enhance_parameter() {
		global $imgix_options;
		$imgix_options['cdn_link'] = 'https://my-source.imgix.com';
		$imgix_options['auto_enhance'] = '1';

		$string = '<img src="http://example.org/wp-content/cats.gif" />';
		$this->assertEquals( '<img src="https://my-source.imgix.com/wp-content/cats.gif?auto=enhance" />', imgix_replace_content_cdn( $string ) );
	}

	function test_imgix_replace_content_cdn_adds_auto_enhance_and_format_parameter() {
		global $imgix_options;
		$imgix_options['cdn_link'] = 'https://my-source.imgix.com';
		$imgix_options['auto_enhance'] = '1';
		$imgix_options['auto_format'] = '1';

		$string = '<img src="http://example.org/wp-content/cats.gif" />';
		$this->assertEquals( '<img src="https://my-source.imgix.com/wp-content/cats.gif?auto=format,enhance" />', imgix_replace_content_cdn( $string ) );
	}

	function test_imgix_replace_content_cdn_translates_dimensions_into_parameters() {
		global $imgix_options;
		$imgix_options['cdn_link'] = 'https://my-source.imgix.com';

		$string = '<img src="http://example.org/wp-content/cats-300x200.gif" />';
		$this->assertEquals( '<img src="https://my-source.imgix.com/wp-content/cats.gif?&h=200&w=300" />', imgix_replace_content_cdn( $string ) );
	}

	function test_imgix_replace_content_cdn_maintains_existing_parameters() {
		global $imgix_options;
		$imgix_options['cdn_link'] = 'https://my-source.imgix.com';

		$string = '<img src="http://example.org/wp-content/cats-300x200.gif?party=1&bad_vibes=0" />';
		$this->assertEquals( '<img src="https://my-source.imgix.com/wp-content/cats.gif?&h=200&w=300&party=1&bad_vibes=0" />', imgix_replace_content_cdn( $string ) );
	}
}

