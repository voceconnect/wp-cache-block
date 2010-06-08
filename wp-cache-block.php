<?php
/*
Plugin Name: WP Cache Block
Description: Adds ability to globally cache certain segments of code.
Version: 1.0
Author: Michael Pretty (voce connect)

*******************************************************************
Copyright 2009-2009 Michael Pretty  (email : mpretty@voceconnect.com)

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*******************************************************************
*/

class WP_Cache_Block
{
	const CACHE_BLOCK_DEBUG = false;
	const CACHE_FLAG_PREFIX = 'CACHE_BLOCK_FLAG';

	static $instance = null;
	private $output_stack;
	private $cache_flag;

	/**
	 * Provider method for Cache Block instance
	 *
	 * @return WP_Cache_Block
	 */
	public static function GetInstance()
	{
		if(self::$instance == null)
		{
			self::$instance = new WP_Cache_Block();
		}
		return self::$instance;
	}

	/**
	 * Static method to start a cache block segment.  Sample usage:
	 * if(WP_Cache_Block::StartBlock()) :
	 *   #code to cache here
	 * endif; WP_Cache_Block:EndBlock();
	 *
	 *
	 * @param string $key If no key is provided, one will be created from the a hash of the_wp_query vars and the current stack trace
	 * @param bool set to true if content is page specific
	 * @param int seconds to cache the content
	 * @return bool true if the code is not cached and should be executed, false if the cache block already exists
	 */
	public static function StartBlock($key = '', $unique = true, $expires = 300)
	{
		return self::GetInstance()->start_block($key, $unique, $expires);
	}

	/**
	 * Static method to close a cache block.  Every call to StartBlock should be closed.
	 *
	 */
	public static function EndBlock()
	{
		self::GetInstance()->end_block();
	}

	/**
	 * Constructor method.  Kept private to maintain singleton instance.
	 *
	 */
	private function __construct()
	{
		$this->output_stack = array();
		$this->set_cache_flag();
	}

	/**
	 * Method to start a cache block segment.  The static method should be used in most situations.
	 *
	 * Sample usage:
	 * if(WP_Cache_Block::StartBlock()) :
	 *   #code to cache here
	 * endif; WP_Cache_Block:EndBlock();
	 *
	 * @param string $key If no key is provided, one will be created from the a hash of the_wp_query vars and the current stack trace
	 * @param bool set to true if content is page specific.  Will add unique hash to cache key.
	 * @param int seconds to cache the content
	 * @return bool true if the code is not cached and should be executed, false if the cache block already exists
	 */
	public function start_block($key, $unique, $expires)
	{
		$filter_key = $key;
		if(empty($key) || $unique)
		{
			$wp_query = $GLOBALS['wp_the_query'];
			if(!empty($key)) $key.= ':';
			$key .= wp_hash(serialize(debug_backtrace()) . serialize($wp_query->query_vars));
		}

		$block_data = array('key'=>$key, 'use_cache' => true, 'output' => false);
		if(!empty($filter_key))
		{
			$block_data['use_cache'] = apply_filters('cache_block_start_'.$filter_key, $block_data['use_cache']);
		}
		if($block_data['use_cache'])
		{
			$block_data['output'] = wp_cache_get($key, $this->cache_flag);
			if($block_data['output'] === null)
			{
				$block_data['output'] = false;
			}
		}
		$block_data['expires'] = $expires;

		array_push($this->output_stack, $block_data);

		if($block_data['output'] === false)
		{
			ob_start();
			return true;
		}
		return false;
	}

	/**
	 * Method to close a cache block.  Will print the previously cached content or if it does not exist, the
	 * content on the top buffer stack.
	 *
	 * @return bool
	 */
	public function end_block()
	{
		$block_data = array_pop($this->output_stack);
		if(!is_array($block_data))
		{
			//oops!  end_block was called too many times.
			return false;
		}
		$key = $block_data['key'];
		$output = $block_data['output'];
		$expires = $block_data['expires'];
		if($output === false)
		{
			$output = ob_get_contents();
			ob_end_flush();
			if($block_data['use_cache'])
			{
				wp_cache_set($key, $output, $this->cache_flag, $expires);
			}
		}
		else
		{
			if (self::CACHE_BLOCK_DEBUG)	echo "<!-- WP_Cache_Block from Key {$key} -->\n";
			echo $output;
			if (self::CACHE_BLOCK_DEBUG) echo "<!-- End WP_Cache_Block from Key {$key} -->\n";
		}
		return true;
	}

	/**
	 * Clears the current cache blocks.
	 *
	 * Since there is no easy way to delete cache for a given flag.  A new flag is
	 * created to use instead.  Previously cached items will eventually go into garbage
	 * collection.
	 *
	 */
	public function clear_cache()
	{
		if($this->cache_flag !== false)
		{
			delete_option($this->cache_flag);
		}
		$this->set_cache_flag();
	}

	/**
	 * Sets the cache flag for this instance.  Will pull from options if exists, else
	 * it will create a new flag based on the current timestamp.
	 *
	 */
	private function set_cache_flag()
	{
		$this->cache_flag = get_option(self::CACHE_FLAG_PREFIX);
		if(!$this->cache_flag)
		{
			$this->cache_flag = self::CACHE_FLAG_PREFIX . '_'. time();
			update_option(self::CACHE_FLAG_PREFIX, $this->cache_flag);
		}
	}
}

class WP_Cache_Block_Filters
{
	/**
	 * Filter checks if cache should be used for comments based on whether user has pending comment for post
	 *
	 * @param bool $use_cache
	 * @return bool
	 */
	public static function has_pending_comment($use_cache)
	{
		if(!$use_cache)
		{
			return $use_cache;
		}

		global $wpdb;
		$wp_query = $_GLOBALS['wp_the_query'];
		$post_id = $wp_query->posts[0]->ID;

		$sql = false;
		$num_pending = 0;
		if(is_user_logged_in())
		{
			$sql = $wpdb->prepare("SELECT COUNT(comment_ID) FROM {$wpdb->comments} WHERE comment_post_ID = %d AND comment_approved = 0 AND user_id = %d",$post_id, wp_get_current_user()->ID);
		}
		else
		{
			$commenter = wp_get_current_commenter();
			extract($commenter, EXTR_SKIP);
			if(!empty($comment_author))
			{
				$sql = $wpdb->prepare("SELECT COUNT(comment_ID) FROM {$wpdb->comments} WHERE comment_post_ID = %d AND comment_approved = 0 AND comment_author = %s AND comment_author_email = %s", $post_id, wp_specialchars_decode($comment_author,ENT_QUOTES), $comment_author_email);
			}
		}
		if($sql)
		{
			$num_pending = $wpdb->get_var($sql);
		}
		if($num_pending > 0)
		{
			$use_cache = false;
		}
		/*
		Other options to potentially look into when deciding to cache comment list output:

		option 1: dont use cache if user has any pending comments for this post on this page
		upside: will cache all comment pages except for ones with pending comment
		downside: could require a query for every pending comment a user has to find out if one exists on the current page.

		option 2: dont cache if user has pending comment on this post, but use the cache, if exists, as long as its not the last page of comments
		upside: will be able to use cache for most comments when not the last page.
		downside: users may be confused if they sometimes see their pending comments on other pages and don't other times.
		*/
		return $use_cache;
	}

	/**
	 * Filter to use if block should only be cached if user is not logged in.
	 *
	 * @param bool $use_cache
	 * @return bool
	 */
	public static function user_logged_in($use_cache)
	{
		return $use_cache && !is_user_logged_in();
	}

}

add_filter('cache_block_start_comment_list', 'WP_Cache_Block_Filters::has_pending_comment');