=== Images via imgix ===

Contributors: thisislawatts, wladston
Tags: imgix
Requires at least: 3.3
Tested up to: Latest
Stable tag: trunk
License: BSD-2
License URI: http://opensource.org/licenses/BSD-2-Clause

A community powered WordPress plugin to automatically load all your WordPress images via the imgix service for smaller, faster, better looking images.

== Description ==

* Your images behind a CDN.
* Automatically smaller and faster images with the [Auto Format](http://blog.imgix.com/post/90838796454/webp-jpeg-xr-progressive-jpg-support-w-auto) option.
* Automatically better looking images with the [Auto Enhance](http://blog.imgix.com/post/85095931364/autoenhance) option.
* Use arbitrary [imgix API params](http://www.imgix.com/docs/reference) when editing `<img>` tags in "Text mode" and they will pass through.
* No lock in! Disable the plugin and your images will be served as they were before installation.

<a name="getting-started"></a>
Getting Started
---------------

If you don't already have an imgix account then sign up at [imgix.com](http://www.imgix.com).

1. Create a `Web Folder` imgix source with the `Base URL` set to your WordPress root URL (__without__ the `wp-content` part). For example, if your WordPress instance is at [http://example.com](http://example.com) and an example image is `http://example.com/wp-content/uploads/2017/01/image.jpg` then your source's `Base URL` would be just `http://example.com/`.

2. [Download](https://github.com/imgix-wordpress/images-via-imgix/releases) the plugin `images-via-imgix.zip` and install on your WordPress instance. In the WordPress admin, click "Plugins" on the right and then "Add New". This will take you to a page to upload the `images-via-imgix.zip` file. Alternatively, you can extract the contents of `images-via-imgix.zip` into the `wp-content/plugins` directory of your WordPress instance.

3. Return to the "Plugins" page and ensure the "imgix plugin" is activated. Once activated, click the "settings" link and populate the "imgix Host" field (e.g., `http://yourcompany.imgix.net`). This is the full host of the imgix source you created in step #1. Optionally, you can also turn on [Auto Format](http://blog.imgix.com/post/90838796454/webp-jpeg-xr-progressive-jpg-support-w-auto) or [Auto Enhance](http://blog.imgix.com/post/85095931364/autoenhance). Finally, click "Save Options" when you're done.

4. Go to a post on your WordPress blog and ensure your images are now serving through imgix.

<a name="history"></a>
History
--------

Originally this plugin was hosted as part of the official [imgix](https://github.com/imgix) organisation. It was depreciated as they focused their efforts on the [PHP library](https://github.com/imgix/imgix-php).

This plugin is maintained and managed by the community. Imgix have kindly permitted the use of their name here but it should be noted that there is no official support available for this plugin. If you've got any problems [submit an issue on this repo](https://github.com/imgix-wordpress/images-via-imgix/issues/new).

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/images-via-imgix` directory, or install the plugin through the WordPress plugins screen directly.
1. Activate the plugin through the 'Plugins' screen in WordPress
1. Use the Settings->imgix screen to configure the plugin

__Note__ An imgix account is required for this plugin to work.

== Frequently Asked Questions ==

Qustions? Email support@imgix.com

== Screenshots ==

No Screenshots are available.
