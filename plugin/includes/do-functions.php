<?php

/**
 * Find all img tags with sources matching "imgix.net" without the parameter 
 * "srcset" and add the "srcset" parameter to all those images, appending a new 
 * source using the "dpr=2" modifier.
 *
 * @return string Content with retina-enriched image tags.
 */

function add_retina($content) {
	$pattern = '/<img((?![^>]+srcset)([^>]*)';
	$pattern .= 'src=[\'"]([^\'"]*imgix.net[^\'"]*\?[^\'"]*)[\'"]([^>]*)*?)>/i';
	$repl = '<img$2src="$3" srcset="${3}, ${3}&amp;dpr=2 2x, ${3}&amp;dpr=3 3x,"$4>';
	$content = preg_replace($pattern, $repl, $content);

	$pattern = '/<img((?![^>]+srcset)([^>]*)';
	$pattern .= 'src=[\'"]([^\'"]*imgix.net[^\'"]*)[\'"]([^>]*)*?)>/i';
	$repl = '<img$2src="$3" srcset="${3}, ${3}?dpr=2 2x, ${3}?dpr=3 3x,"$4>';
	return preg_replace($pattern, $repl, $content);
}

/**
 * Extract all img tags from a given $content block into an array.
 *
 * @return array An array of matching arrays with two keys: 'url' and 'params'
 */
function imgix_extract_imgs($content) {
	preg_match_all('/src=["\']http.+\/([^\s]+?)["\']/', $content, $matches);
	$results = array();
	if ($matches)
		foreach ($matches[1] as $url)
			array_push($results, $url);
	return $results;
}

/**
 * Searches "$content" for all occurences of "$url" and add the given
 * querystring parameters to the URL, preserving existing querystring
 * parameters.
 *
 * @return string Content with matching URLs having the new querystrings.
 */
function apply_parameters_to_url($url, $params, $content) {
	$parts = explode('?', $url.'?');
	list($base_url, $base_params) = array($parts[0], $parts[1]);
	$new_url = $old_url = $base_url;
	$new_url .= '?' . $params;
	$new_url .= $base_params ? '&amp;' . $base_params : '';
	$old_url .= $base_params ? '?'. $base_params : '';
	return str_replace($old_url, $new_url, $content);
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
	if ($imgix_options['auto_format'])
		array_push($auto, "format");
	if ($imgix_options['auto_enhance'])
		array_push($auto, "enhance");
	if (!empty($auto))
		array_push($params, 'auto='.implode('%2C', $auto));
	return implode('&amp;', $params);
}

/**
 * Sanitize a given URL to make sure it has a scheme (or alternatively, '//'),
 * host, path, and ends with a '/'.
 *
 * @return string A sanitized URL.
 */
function ensure_valid_url($url) {
	$slash = strpos($url, '//') == 0 ? '//' : '';
	if($slash)
		$url = substr($url, 2);
	$urlp = parse_url($url);
	$pref = array_key_exists('scheme', $urlp) ? $urlp['scheme'].'://' : $slash;
	if(!$slash && strpos($pref, 'http') !== 0)
		$pref = 'http://';
	$result = $urlp['host'] ? $pref . $urlp['host'] . $urlp['path'] : '';
	if($result)
		return substr($result, -1) == "/" ? $result: $result.'/';
	return NULL;
}

/**
 * Given a wordpress registered size keyword, return its properties.
 *
 * @return array Size's width, height and crop values.
 */
function get_size_info($size){
	global $_wp_additional_image_sizes;
	if($size == 'original')
		return array('width' => '', 'height' => '', 'crop' => false);
	elseif(is_array($size))
		return array('width' => $size[1],
		             'height' => $size[0],
		             'crop' => false);
	elseif (in_array($size, array('thumbnail', 'medium', 'large')))
		return array('width' => get_option($size . '_size_w'),
					 'height' => get_option($size . '_size_h'),
					 'crop' => (bool) get_option( $size . '_crop'));
	elseif(isset($_wp_additional_image_sizes[$size]))
		return array('width' => $_wp_additional_image_sizes[$size]['width'],
					 'height' => $_wp_additional_image_sizes[$size]['height'],
					 'crop' => $_wp_additional_image_sizes[$size]['crop']);
	else
		return NULL;
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
 * @return array An array of arrays that has extracted the URL's inferred w',
 * 'h', and 'type'
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
 * Finds references to the wordpress site URL in the given string,
 * (optionally prefixed by "src"), and changes them to the imgix URL.
 *
 * @return array An array countaining the final string, and a boolean value
 * indicating if it's different from the given input string.
 */
function replace_host($str, $require_prefix = false) {
	global $imgix_options;
	$new_host = ensure_valid_url($imgix_options['cdn_link']);
	if(!$new_host)
		return array($str, false);
	// As soon as srcset is supported…
	//$prefix = $require_prefix? 'srcs?e?t?=[\'"]|,[\S+\n\r\s]*': '';
	$prefix = $require_prefix? 'src=[\'"]': '';
	$src = '('.preg_quote(home_url('/'), '/').'|\/\/)';
	$patt = '/('.$prefix.')'.$src.'/i';
	$str = preg_replace($patt, '$1'.$new_host, $str, -1, $count);
	return array($str, (bool) $count);
}

/**
 * Given an inmage URL and the target wordpress size to display the image, 
 * return the appropriate transformed image source.
 *
 * @return string equivalent imgix source with correct parameters.
 */
function replace_src($src, $size) {
	$size_info = get_size_info($size);
	if($size_info){
		list($src, $match_src) = replace_host($src, false);
		if ($match_src) {
			$g_params = get_global_params_string();
			$params = array();
			if (isset($size_info['crop']) && $size_info['crop'])
				array_push($params, 'fit=crop');
			if (isset($size_info['width']) && $size_info['width'])
				array_push($params, 'w='.$size_info['width']);
			if (isset($size_info['height']) && $size_info['height'])
				array_push($params, 'h='.$size_info['height']);
			$p = implode('&amp;', $params);
			$p = ($p && $g_params) ? $p .'&amp;'. $g_params : $p . $g_params;
			$src = apply_parameters_to_url($src, $p, $src);
		}
	}
	return $src;
}

add_filter('image_downsize', 'no_image_downsize', 10, 3);
function no_image_downsize($return, $id, $size) {
	$url = wp_get_attachment_url($id);
	$new_url = replace_src($url, $size);
	$size_info = get_size_info($size);
	return array($new_url, $size_info['width'], $size_info['height'], true);
}

add_filter('the_content', 'imgix_replace_non_wp_images');
function imgix_replace_non_wp_images($content){
	list($content, $match) = replace_host($content, true);
	if($match) {
		//Apply image-tag-encoded params for every image in $content.
		foreach (imgix_extract_img_details($content) as $img) {
			$to_replace = $img['raw'];
			$extra_params = $img['extra'] ? '&amp;'.$img['extra'] : '';
			$new_url = '.'.$img['type'].'?h='.$img['h'].'&amp;w='.$img['w'].$extra_params;
			$content = str_replace($to_replace, $new_url, $content);
		}

		// Apply global parameters.
		$g_params = get_global_params_string();
		foreach (imgix_extract_imgs($content) as $img_url)
			$content = apply_parameters_to_url($img_url, $g_params, $content);
	}
	return $content;
}

if($imgix_options['add_dpi2_srcset']) {
	function buffer_start() { ob_start("add_retina"); }
	function buffer_end() { ob_end_flush(); }
	add_action('after_setup_theme', 'buffer_start');
	add_action('shutdown', 'buffer_end');
	add_filter('the_content', 'add_retina');
}
?>
