<?php
/*
Plugin Name: WPSiteSync for Custom Post Types
Plugin URI: http://wpsitesync.com
Description: Allow custom post types to be Synced to the Ttarget site
Author: WPSiteSync
Author URI: http://wpsitesync.com
Version: 1.0
Text Domain: wpsitesync-cpts

 * The PHP code portions are distributed under the GPL license. If not otherwise stated, all
images, manuals, cascading stylesheets and included JavaScript are NOT GPL.
*/

if (!class_exists('WPSiteSyncCPT')) {

	/*
	 * @package WPSiteSyncCPT
	 * @author Dave Jesch
	 */
	class WPSiteSyncCPT
	{
		private static $_instance = NULL;

		const PLUGIN_NAME = 'WPSiteSync for Custom Post Types';
		const PLUGIN_VERSION = '1.0';
		const PLUGIN_KEY = '8ebc49045e348022083181d1460c221d';

		private $_license = NULL;

		private function __construct()
		{
			add_action('spectrom_sync_init', array(&$this, 'init'));
		}

		/*
		 * retrieve singleton class instance
		 * @return instance reference to plugin
		 */
		public static function get_instance()
		{
			if (NULL === self::$_instance)
				self::$_instance = new self();
			return self::$_instance;
		}

		/**
		 * Callback for the 'spectrom_sync_init' action. Used to initialize this plugin knowing the WPSiteSync exists
		 */
		public function init()
		{
			add_filter('spectrom_sync_active_extensions', array(&$this, 'filter_active_extensions'), 10, 2);

			$this->_license = new SyncLicensing();
			if (!$this->_license->check_license('sync_cpt', self::PLUGIN_KEY, self::PLUGIN_NAME))
				return;

			if (is_admin() ||
				(defined('DOING_AJAX') && DOING_AJAX)) {
				require_once (dirname(__FILE__) . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'cptsadmin.php');
				SyncCPTsAdmin::get_instance();
			}

			add_filter('spectrom_sync_allowed_post_types', array(&$this, 'allow_custom_post_types'));
			// use the 'spectrom_sync_api_request' filter to add any necessary taxonomy information
//			add_filter('spectrom_sync_api_request', array(&$this, 'add_cpt_data'), 10, 3);
			add_filter('spectrom_sync_tax_list', array(&$this, 'filter_taxonomies'), 10, 1);
		}

		/**
		 * Adds all custom post types to the list of `spectrom_sync_allowed_post_types`
		 * @param  array $post_types The post types to allow
		 * @return array
		 */
		public function allow_custom_post_types($post_types)
		{
			if (!$this->_license->check_license('sync_cpt', self::PLUGIN_KEY, self::PLUGIN_NAME))
				return $post_types;

			$cpts = get_post_types(array('_builtin' => FALSE));

			// we can't handle products and downloads since these use custom tables anyway. remove them.
			unset($cpts['products']);
			unset($cpts['downloads']);
			return array_merge($post_types, $cpts);
		}

		/**
		 * Adds all known taxonomies to the list of available taxonomies for Syncing
		 * @param array $tax Array of taxonomy information to filter
		 * @return array The taxonomy list, with all taxonomies added to it
		 */
		public function filter_taxonomies($tax)
		{
			$all_tax = get_taxonomies(array(), 'objects');
			$tax = array_merge($tax, $all_tax);
			return $tax;
		}

		/**
		 * Adds custom taxonomy information to the data array collected for the current post
		 * @param array $data The array of data that will be sent to the Target
		 * @param string $action The API action, i.e. 'auth', 'post', etc.
		 * @param string $request_args The arguments being sent to wp_remote_post()
		 * @return array The modified data with CPT specific information added
		 */
		public function add_cpt_data($data, $action, $request_args)
		{
SyncDebug::log(__METHOD__.'() action=' . $action);
			if ('push' !== $action && 'pull' !== $action)
				return $data;
if (!isset($data['post_data']))
	SyncDebug::log(__METHOD__.'() no post_data element found in ' . var_export($data, TRUE));
else if (!isset($data['post_data']['post_type']))
	SyncDebug::log(__METHOD__.'() no post_type element found in ' . var_export($data['post_data'], TRUE));

			if (!in_array($data['post_data']['post_type'], array('post', 'page'))) {
				// TODO: collect CPT taxonomy data and add to array
			}
			// TODO: add custom taxonomy information
			return $data;
		}

		/**
		 * Add the CPT add-on to the list of known WPSiteSync extensions
		 * @param array $extensions The list to add to
		 * @param boolean $set
		 * @return array The list of extensions, with the WPSiteSync CPT add-on included
		 */
		public function filter_active_extensions($extensions, $set = FALSE)
		{
SyncDebug::log(__METHOD__.'()');
			if ($set || $this->_license->check_license('sync_cpt', self::PLUGIN_KEY, self::PLUGIN_NAME))
				$extensions['sync_cpt'] = array(
					'name' => self::PLUGIN_NAME,
					'version' => self::PLUGIN_VERSION,
					'file' => __FILE__,
				);
			return $extensions;
		}
	}

	// Initialize the extension
	WPSiteSyncCPT::get_instance();
}

// EOF
