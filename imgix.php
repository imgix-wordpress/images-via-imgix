<?php
/*
Plugin Name: imgix
Description: A WordPress plugin to automatically use your existing (and future) WordPress images via <a href="http://www.imgix.com" target="_blank">imgix</a> for smaller, faster, and better looking images. <a href="https://github.com/wladston/imgix-wordpress" target="_blank">Learn more</a>.
Author: imgix
Author URI: http://www.imgix.com
Version: 1.1.0
*/

// Variables
$imgix_options = get_option('imgix_settings');

include('includes/do-functions.php');
include('includes/options_page.php');

// Settings
function imgix_plugin_admin_action_links($links, $file) {
	static $my_plugin;
	if (!$my_plugin) {
		$my_plugin = plugin_basename(__FILE__);
	}
	if ($file == $my_plugin) {
		$settings_link = '<a href="options-general.php?page=imgix-options">Settings</a>';
		array_unshift($links, $settings_link);
	}
	return $links;
}

add_filter('plugin_action_links', 'imgix_plugin_admin_action_links', 10, 2);
?>
