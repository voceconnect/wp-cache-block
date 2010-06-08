=== Subscribers Only ===
Contributors: Michael Pretty
Donate link: http://voceconnect.com/
Tags: caching, block level, performance
Requires at least: 2.5
Tested up to: 2.8
Stable tag: trunk

Adds ability to globally cache certain segments of code.

== Description ==

Adds ability to globally cache certain segments of code.

WP Cache and some other caching plugins work amazingly well, however, their benefits are limited in sites with a large percentage of logged in users.  While working on these projects, we, Voce Connect, found the need for a better caching solution.  We needed to easily cache rendered portions of the theme that were the same for each user without caching other portions that may have user specific content.  The solution was to come up with a plugin that could be easily integrated into a theme to help cache these blocks of content.

Requirements:

*   "php5" - I'm a big proponent of dropping the php4 compatibility of WordPress due to the improved OO support.  Because of this, I prefer to write my plugins in php5 form in hopes to help push the community along.

== Installation ==

1. Upload `wp-cache-block.php` to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3....
