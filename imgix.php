<?php
/**
 * WordPress plugin for imgix service.
 *
 * @package imgix
 * @author wladston
 * @license BSD-2
 *
 * @wordpress-plugin
 *
 * Plugin Name: Images via imgix
 * Plugin URI:  https://github.com/imgix-wordpress/images-via-imgix
 * Description: A WordPress plugin to automatically use your existing (and future) WordPress images from <a href="http://www.imgix.com" target="_blank">imgix</a> for smaller, faster, and better looking images. <a href="https://github.com/imgix-wordpress/images-via-imgix" target="_blank">Learn more</a>.
 * Version:     1.3.2
 * Author:      wladston
 * Author URI:  http://github.com/imgix-wordpress
 */

include( 'includes/compability.php' );
include( 'includes/class-images-via-imgix.php' );
include( 'includes/options-page.php' );

function imgix_plugin_admin_action_links( $links, $file ) {
	if ( $file === plugin_basename( __FILE__ ) ) {
		$settings_link = '<a href="options-general.php?page=imgix-options">Settings</a>';
		array_unshift( $links, $settings_link );
	}

	return $links;
}

add_filter( 'plugin_action_links', 'imgix_plugin_admin_action_links', 10, 2 );
