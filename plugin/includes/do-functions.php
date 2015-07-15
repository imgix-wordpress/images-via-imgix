<?php

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
 * @return array An array of arrays that has extracted the URL's inferred w', 'h', and 'type'
 */
function imgix_extract_img_details($content) {
	preg_match_all('/-([0-9]+)x([0-9]+)\.([^"\']+)/', $content, $matches);

	$lookup = array('raw', 'w', 'h', 'type');
	$data = array();
	foreach ($matches as $k => $v) {

		foreach ($v as $ind => $val) {
			if (!array_key_exists($ind, $data)) {
					$data[$ind] = array();
			}

			$key = $lookup[$k];
			if ($key === 'type') {
				if (strpos($val, '?') !== false) {
					$parts = explode('?', $val);
					$data[$ind]['type'] = $parts[0];
					$data[$ind]['extra'] = $parts[1];
				} else {
					$data[$ind]['type'] = $val;
					$data[$ind]['extra'] = '';
				}
			} else {
				$data[$ind][$key] = $val;
			}
		}
	}

	return $data;
}

/**
 * Extract all img tags from a given $content block.
 *
 * @return array An array of matching arrays with two keys: 'url' and 'params'
 */
function imgix_extract_imgs($content) {
	preg_match_all('/src=["\']http.+\/([^\s]+?)["\']/', $content, $matches);
	$results = array();

	if (sizeof($matches) > 0) {
		foreach ($matches[1] as $k => $v) {
			if (strpos($v, '?') !== false) {
				$parts = explode('?', $v);
				array_push($results, array('url' => $parts[0], 'params' => $parts[1]));
			} else {
				array_push($results, array('url' => $v, 'params' => ''));
			}
		}
	}

	return $results;
}

/**
 * Sanitize a given URL to make sure it has a scheme, host, path, and ends
 * with a '/'.
 *
 * @return string A sanitized, full-qualified URL.
 */
function ensure_valid_url($url) {
		$parsed = parse_url($url);

		if (!array_key_exists('scheme', $parsed) || strpos($parsed['scheme'], 'http') !== 0) {
				$parsed['scheme'] = 'http';
		}

		$result = $parsed['scheme'].'://'.$parsed['host'].$parsed['path'];

		if (substr($result, -1) !== "/") {
			$result .= "/";
		}

		return $result;
}

function imgix_replace_content_cdn($content){
	global $imgix_options;
	$slink = ensure_valid_url($imgix_options['cdn_link']);

	$auto_format = $imgix_options['auto_format'];
	$auto_enhance = $imgix_options['auto_enhance'];
	if(!empty($slink)) {

		// 1) Apply imgix host
		// img src tags
		$content = str_replace('src="'.home_url('/').'wp-content/', 'src="'.$slink.'wp-content/', $content);
		$content = str_replace('src=\''.home_url('/').'wp-content/', 'src=\''.$slink.'wp-content/', $content);

		// img href tags
		$content = str_replace('href="'.home_url('/').'wp-content/', 'href="'.$slink.'wp-content/', $content);
		$content = str_replace('href=\''.home_url('/').'wp-content/', 'href=\''.$slink.'wp-content/', $content);

		$data_w_h = imgix_extract_img_details($content);

		// 2) Handle Auto options
		$autos = array();
		$auto_params = '';
		if ($auto_format === "1") {
			array_push($autos, "format");
		}

		if ($auto_enhance === "1") {
			array_push($autos, "enhance");
		}

		if (sizeof($autos) > 0) {
			$auto_params = 'auto='.implode(',', $autos);
		}

		// 3) Apply the h/w img params and any that already existed (text html edits)
		foreach ($data_w_h as $k => $v) {
			$extra = strlen($v['extra']) > 0 ? '&'.$v['extra'] : '';

			$to_replace = $v['raw'];
			$new_url = '.'.$v['type'].'?h='.$v['h'].'&w='.$v['w'].$extra;
			$content = str_replace($to_replace, $new_url, $content);
		}

		// 4) Apply the auto_params
		$imgs = imgix_extract_imgs($content);
		foreach ($imgs as $k => $v) {
			if (strlen($v['params']) > 0) {
				$new_url = $v['url'].'?'.$auto_params.'&'.$v['params'];
				$to_replace = $v['url'].'?'.$v['params'];
			} else {
				$to_replace = $v['url'];
				$new_url = $v['url'].'?'.$auto_params;
			}

			$to_replace .= '"';
			$new_url .= '"';
			if (strlen($to_replace) > 0) {
				$content = str_replace($to_replace, $new_url, $content);
			}
		}

		return $content;

	}
	return $content;
}

add_filter('the_content','imgix_replace_content_cdn');
add_filter('post_thumbnail_html', 'imgix_replace_content_cdn', 10, 2);
if(wp_get_theme('genesis')->exists()) {
	add_filter('genesis_get_image', 'imgix_replace_content_cdn', 10);
}
?>
