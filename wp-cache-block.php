<?php
/*
Plugin Name: WP Cache Block
Description: Adds ability to globally cache certain segments of code.
Version: 0.1.1
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
	const CACHE_FLAG_PREFIX = 'CBF';

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
	 * Constructor method.  Kept private to maintain singleton instance.
	 *
	 */
	private function __construct()
	{
		$this->output_stack = array();
		$this->set_cache_flag();
	}

	public function init() {
		if(is_admin()) {
			add_action('admin_menu', array($this, 'admin_menu'));
		}
	}

	public function admin_menu() {
		$hook = add_options_page('WP Block Cache', 'WP Block Cache', 'manage_options', 'wp-cache-block', array($this, 'options_page'));
		add_action('load-'.$hook, array($this, 'on_load_options_page'));
	}

	public function on_load_options_page() {
		if(isset($_POST['cache_block_nonce']) && wp_verify_nonce($_POST['cache_block_nonce'], 'flush_cache')) {
			$this->clear_cache();
			wp_redirect(admin_url('options-general.php?page=wp-cache-block&cleared=1'));
			die();
		}
	}

	public function options_page() {
		if(isset($_GET['cleared'])) : ?>
			<div id="message" class="updated">Cache has been cleared.</div>
		<?php endif; ?>
		<div class="wrap">
			<h2>WP Cache Block</h2>
			<form action="<?php echo admin_url('options-general.php?page=wp-cache-block');?>" method="POST">
				<?php wp_nonce_field('flush_cache', 'cache_block_nonce') ?>
				<input type="submit" name="submit" value="Flush Cache" />
			</form>
		</div>
		<?php
	}


	/**
	 * Method to start a cache block segment.  The static method should be used in most situations.
	 *
	 * Sample usage:
	 * if(wp_cacheblock_start('block_name') :
	 *   #code to cache here
	 * endif; wp_cacheblock_end();
	 *
	 * @param string $key If no key is provided, one will be created from the a hash of the_wp_query vars and the current stack trace
	 * @param bool set to true if content is page specific.  Will add unique hash to cache key.
	 * @param int seconds to cache the content
	 * @return bool true if the code is not cached and should be executed, false if the cache block already exists
	 */
	public function start_block($blockname, $args = array())
	{
		$defaults = array(
			'unique_key' => '',
			'key_builders' => array(),
			'expires' => 300
		);

		$args = wp_parse_args($args, $defaults);

		if(!empty($args['unique_key'])) {
			$unique_key = $blockname.'_'.$args['unique_key'];
		} elseif(is_array($args['key_builders']) && count($args['key_builders']) > 0) {
			$keys = array();
			foreach ($args['key_builders'] as $key_builder){
				$keys[] = $key_builder->get_key_string();
			}
			$unique_key = join('',$keys);

		} else {
			$unique_key = $blockname;
		}
		$key = $this->cache_flag. substr(md5($unique_key), 0, 30);
		$block_data = array('key'=>$key, 'output' => false);

		$block_data['output'] = get_transient($key);
		if($block_data['output'] === null)
		{
			$block_data['output'] = false;
		}
		$block_data['expires'] = $args['expires'];

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
			ob_end_flush(); //go ahead and output the content
			set_transient($key, $output, $expires);
		}
		else
		{
			if (self::CACHE_BLOCK_DEBUG)	echo "<!-- WP_Cache_Block from Key {$key} --> \n";
			echo $output;
			if (self::CACHE_BLOCK_DEBUG) echo "<!-- End WP_Cache_Block from Key {$key} --> \n";
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
			delete_option(self::CACHE_FLAG_PREFIX);
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
			$this->cache_flag = self::CACHE_FLAG_PREFIX . '_' . time() . '_';
			update_option(self::CACHE_FLAG_PREFIX, $this->cache_flag);
		}
	}
}
add_action('init', array(WP_Cache_Block::GetInstance(), 'init'));

interface iCache_Block_Unique_Key_Builder {

	/**
	 * Returns an MD5 hash representation of the Unique_Key
	 *
	 */
	public function get_key_strjng();
}

class Query_Unique_Key_Builder {
	protected $key_string;

	public function __construct($query_vars = array()) {
		$data = array();
		foreach($query_vars as $query_var) {
			if($value = get_query_var($query_var)) {
				$data[$query_var] = $value;
			}
		}
		if(count($data)) {
			$this->key_string = md5(serialize($data));
		}
	}

	public function get_key_string() {
		return $this->key_string;
	}
}

class Pending_Comment_Unique_Key_Builder {
	protected $key_string;

	public function __construct() {
		global $post_id;

		$this->key_string = '';
		if(is_single()) {
			$num_pending = 0;
			if(is_user_logged_in())
			{
				$cache_key = 'pending_comments_'.get_current_user_id().'_'.$post_id;
				$sql = $wpdb->prepare("SELECT COUNT(comment_ID) FROM {$wpdb->comments} WHERE comment_post_ID = %d AND comment_approved = 0 AND user_id = %d",$post_id, wp_get_current_user()->ID);
			}
			else
			{
				$commenter = wp_get_current_commenter();
				extract($commenter, EXTR_SKIP);
				if(!empty($comment_author))
				{
					$cache_key = 'pending_comments_'.$comment_author.'_'.$comment_author_email.'_'.$post_id;
					$sql = $wpdb->prepare("SELECT COUNT(comment_ID) FROM {$wpdb->comments} WHERE comment_post_ID = %d AND comment_approved = 0 AND comment_author = %s AND comment_author_email = %s", $post_id, wp_specialchars_decode($comment_author,ENT_QUOTES), $comment_author_email);
				} else {
					return;
				}
			}

			if(isset($cache_key)) {
				$num_pending = get_transient($cache_key);
				if($num_pending === false && $sql !== '') {
					$num_pending = $wpdb->get_var($sql);
					set_transient($cache_key, $num_pending);
				}
				if($num_pending > 0) { //they have a pending comment for this post
					$this->key_string = md5(serialize(array('$cache_key'=> $num_pending)));
				}
			}
		}
	}

	public function get_key_string() {
		return $this->key_string;
	}
}
function clear_pending_comment_count_cache($comment_id) {
	$comment = get_comment($comment_id);
	if(empty($comment->user_id)) {
		$cache_key = 'pending_comments_'.$comment->comment_author.'_'.$comment->comment_author_email.'_'.$comment->comment_post_ID;
	} else {
		$cache_key = 'pending_comments_'.$comment->user_id.'_'.$comment->comment_post_ID;
	}
	delete_transient($cache_key);
}
add_action('wp_set_comment_status', 'clear_pending_comment_count_cache', 10, 1);
add_action('wp_insert_comment', 'clear_pending_comment_count_cache', 10, 1);

class Per_User_Unique_Key_Builder {
	protected $key_string;
	public function __construct() {
		if(is_user_logged_in()) {
			$this->key_string = 'user_id_'.get_current_user_id();
		}	else {
			$this->key_string = '';
		}
	}
	public function get_key_string() {
		return $this->key_string;
	}
}

function wp_cacheblock_start($blockname, $args = array()) {
	return WP_Cache_Block::GetInstance()->start_block($blockname, $args);
}

function wp_cacheblock_end() {
	return WP_Cache_Block::GetInstance()->end_block();
}