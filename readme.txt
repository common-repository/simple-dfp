=== Simple GAM ===
Plugin Name: Simple GAM
Plugin URI: https://wordpress.org/plugins/simple-dfp/
Contributors: munger41
Tags: gam, google, ad, manager, block, shortcode, template, function
Requires at least: 4.0
Author: Termel
Author URI: https://www.termel.fr/
Tested up to: 5.2
Version: 1.1
Stable tag: 1.1

Finally an easy plugin to add Google Ad Manager (GAM) blocks into WP - shortcode and template function. 

== Description ==

Finally an easy plugin to add Google Ad Manager (GAM) blocks into WP - shortcode and template function. It was designed to work with a multisite install, please tell me if any problem on a single site install. Thanks!

### Shortcode ###

Simply use `[simpledfp_block ad_id=xxxx]` where `xxxx` is the id of your DFP ad post type, created when you installed the plugin.

### Template function ###

Please use the following code:

`if (class_exists('simplegam_main')) {
	// checks the plugin is installed
	simpledfpBlock(array('ad_id' => xxxx);
}`

With, also `xxxx` being your Simple GAM Ad id, previously created in wordpress admin panel. You can [retrive this ID like this.](https://premium.wpmudev.org/blog/display-wordpress-post-page-ids/ "Retreive ID")
I  recommend the [Reveal IDs plugin](https://wordpress.org/plugins/reveal-ids-for-wp-admin-25/).

### Steps to use correctly ###

1. Install the plugin
2. Fill the DFP network identifier in the settings of the plugin
3. A new menu DFP Ads appears in the sidebar of admin panel
4. Use it to Add a new ad, fill mandatory fields :
  * DFP Bloc code, as it appears in DFP backoffice
  * Size of bloc to display on you site, in the format `WWWxHHH` (where WWW is the width of the block, and HHH the height of the block)
5. Refresh you frontend, and you should see block creations (as they are defined in DFP backoffice) appear on your site. 

== Installation ==

### Easy ###
1. Search via plugins > add new.
2. Find the plugin listed and click activate.
3. Use the shortcode or template function

== Screenshots ==

1. Network settings

== Changelog ==

* 1.3.3 - dashicon changed

* 1.3.2 - collapse emptu divs

* 1.3.1 - tested with WP 5.2

* 1.3.0 - key value pairs ok on multisite

* 1.2.1 - key value pairs ok on single site

* 1.2 - collapse empty divs now

* 1.1 - fix : checks before display prevent display...

* 1.0 - fix on TypeError: googletag.pubads is not a function

* 0.5 - online fixes

* 0.4 - ready for single site

* 0.3 - no settings menu on single site...

* 0.2 - no settings menu on single site...

* 0.1 - initial commit

== Upgrade Notice ==

Simple use WP backoffice upgrader