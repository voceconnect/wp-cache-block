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

1. Upload `wp-cache-block` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Add cache block areas to the slower parts of your theme.  See below.

Adding a cache block only requires wrapping the section of content you want to cache in a couple of lines of code.  The key is make sure that the cache block is uniquely idtentified based on the content it will render.

For simple content that is the same on every page:

`<?php if(wp_cacheblock_start('my-content')) : ?>
//the content you want to cache
<?php endif; wp_cacheblock_end(); ?>`

Content that varies based on page, user, etc, will need to use key builders.  Key builders are simple classes that help make a unique key for the block of content based on different parameters.

For caching the_loop in index.php you'll need to add a Query_Unique_Key_Builder which creates a key based on passed in query_var keys:

`<?php $key_builders = array(new Query_Unique_Key_Builder( array ('paged') ) );
<?php if(wp_cacheblock_start( 'index-loop', array('key_builders'=> $key_builders) ) ) : ?>
  //the post loop
<?php endif; wp_cacheblock_end(); ?>`

To cache the comment output, you can use the Pending_Comment_Unique_Key_Builder so users with pending comments will get their own cached page.

`<?php $key_builders = array( new Pending_Comment_Unique_Key_Builder() );
<?php if(wp_cacheblock_start('index-loop', array('key_builders'=> $key_builders) ) ) : ?>
  //the comment loop
<?php endif; wp_cacheblock_end(); ?>`

If you're having to render complex data per user, the Per_User_Unique_Key_Builder will help create a unique key per user for the content:

`<?php $key_builders = array( new Per_User_Unique_Key_Builder() );
<?php if(wp_cacheblock_start('index-loop', array('key_builders'=> $key_builders) ) ) : ?>
  //the user specific content
<?php endif; wp_cacheblock_end(); ?>`

== Frequently Asked Questions ==

= Do I need this plugin? =

Probably not.  Full page rendering is the best solution for most sites.  This plugin is really only useful for sites that have a great deal of user specific content areas that causes full page caching to no longer be an option.

== Changelog ==

= 0.1.0 =
* Initial release.