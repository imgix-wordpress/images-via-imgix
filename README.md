![imgix logo](https://assets.imgix.net/imgix-logo-web-2014.pdf?page=2&fm=png&w=200&h=200)
![Build Status](https://travis-ci.org/imgix/imgix-wordpress.svg)

imgix-wordpress
===============

A WordPress plugin to automatically use your existing (and future) WordPress images via imgix for smaller, faster, and better looking images.

* [Features](#features)
* [Getting Started](#getting-started)
* [Updates](#updates)

<a name="features"></a>
Features
--------

* Your images behind a CDN.
* Automatically smaller and faster images with the [Auto Format](http://blog.imgix.com/post/90838796454/webp-jpeg-xr-progressive-jpg-support-w-auto) option.
* Automatically better looking images with the [Auto Enhance](http://blog.imgix.com/post/85095931364/autoenhance) option.
* Use arbitrary [imgix API params](http://www.imgix.com/docs/reference) when editing `<img>` tags in "Text mode" and they will pass through.
* No lock in! Disable the plugin and your images will serve as they did before installation.

<a name="getting-started"></a>
Getting Started
---------------
[![Screencast](http://assets.imgix.net/videos/wordpressplugin.png?w=700)](http://vimeo.com/111582654)

If you don't already have an imgix account then sign up at [imgix.com](http://www.imgix.com).

1. Create a `Web Folder` imgix source with the `Base URL` set to your WordPress root URL (__without__ the `wp-content` part). For example, if your WordPress instance is at [http://students.miguelcardona.com](http://students.miguelcardona.com) and an example image is `http://students.miguelcardona.com/wp-content/uploads/2013/01/beat.jpg` then your source's `Base URL` would be just `http://students.miguelcardona.com/`.

2. [Download](https://github.com/imgix/imgix-wordpress/releases) the imgix WordPress plugin `imgix_plugin.zip` and install on your WordPress instance. In the WordPress admin, click "Plugins" on the right and then "Add New". This will take you to a page to upload the `imgix_plugin.zip` file. Alternatively, you can extract the contents of `imgix_plugin.zip` into the `wp-content/plugins` directory of your WordPress instance.

3. Return to the "Plugins" page and ensure the "imgix plugin" is activated. Once activated, click the "settings" link and populate the "imgix Host" field (e.g., `http://yourcompany.imgix.net`). This is the full host of the imgix source you created in step #1. Optionally, you can also turn on [Auto Format](http://blog.imgix.com/post/90838796454/webp-jpeg-xr-progressive-jpg-support-w-auto) or [Auto Enhance](http://blog.imgix.com/post/85095931364/autoenhance). Finally, click "Save Options" when you're done.

4. Go to a post on your WordPress blog and ensure your images are now serving through imgix.

<a name="Updates"></a>
Updates
-------

If you find bugs or have a feature request please open a [github issue](https://github.com/imgix/imgix-wordpress/issues). Thanks!
