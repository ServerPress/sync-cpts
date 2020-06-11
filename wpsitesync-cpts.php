<?php
/*
Plugin Name: WPSiteSync for Custom Post Types
Plugin URI: http://wpsitesync.com
Description: Allow custom post types to be Synced to the Target site
Author: WPSiteSync
Author URI: http://wpsitesync.com
Version: 1.2
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
		const PLUGIN_VERSION = '1.2';
		const PLUGIN_KEY = '8ebc49045e348022083181d1460c221d';

		private function __construct()
		{
			add_action('spectrom_sync_init', array($this, 'init'));
			add_action('wp_loaded', array($this, 'wp_loaded'));
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
			add_filter('spectrom_sync_active_extensions', array($this, 'filter_active_extensions'), 10, 2);

			if (!WPSiteSyncContent::get_instance()->get_license()->check_license('sync_cpt', self::PLUGIN_KEY, self::PLUGIN_NAME)) {
SyncDebug::log(__METHOD__ . '() no license');
				return;
			}

			if (is_admin() ||
				(defined('DOING_AJAX') && DOING_AJAX)) {
				require_once (dirname(__FILE__) . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'cptsadmin.php');
				SyncCPTsAdmin::get_instance();
			}

			add_filter('spectrom_sync_allowed_post_types', array($this, 'allow_custom_post_types'));
			// use the 'spectrom_sync_api_request' filter to add any necessary taxonomy information
//			add_filter('spectrom_sync_api_request', array($this, 'add_cpt_data'), 10, 3);
			add_filter('spectrom_sync_tax_list', array($this, 'filter_taxonomies'), 10, 1);
		}

		/**
		 * Called when WP is loaded so we can check if parent plugin is active.
		 */
		public function wp_loaded()
		{
			if (is_admin() && !class_exists('WPSiteSyncContent', FALSE) && current_user_can('activate_plugins')) {
				add_action('admin_notices', array($this, 'notice_requires_wpss'));
				add_action('admin_init', array($this, 'disable_plugin'));
			}
		}

		/**
		 * Displays the warning message stating that WPSiteSync is not present.
		 */
		public function notice_requires_wpss()
		{
			$install = admin_url('plugin-install.php?tab=search&s=wpsitesync');
			$activate = admin_url('plugins.php');
			$msg = sprintf(__('The <em>WPSiteSync for Custom Post Types</em> plugin requires the main <em>WPSiteSync for Content</em> plugin to be installed and activated. Please %1$sclick here</a> to install or %2$sclick here</a> to activate.', 'wpsitesync-cpts'),
						'<a href="' . $install . '">',
						'<a href="' . $activate . '">');
			$this->_show_notice($msg, 'notice-warning');
		}

		/**
		 * Helper method to display notices
		 * @param string $msg Message to display within notice
		 * @param string $class The CSS class used on the <div> wrapping the notice
		 * @param boolean $dismissable TRUE if message is to be dismissable; otherwise FALSE.
		 */
		private function _show_notice($msg, $class = 'notice-success', $dismissable = FALSE)
		{
			echo '<div class="notice ', $class, ' ', ($dismissable ? 'is-dismissible' : ''), '">';
			echo '<p>', $msg, '</p>';
			echo '</div>';
		}

		/**
		 * Disables the plugin if WPSiteSync not installed or ACF is too old
		 */
		public function disable_plugin()
		{
			deactivate_plugins(plugin_basename(__FILE__));
		}

		/**
		 * Adds all custom post types to the list of `spectrom_sync_allowed_post_types`
		 * @param  array $post_types The post types to allow
		 * @return array
		 */
		public function allow_custom_post_types($post_types)
		{
			if (!WPSiteSyncContent::get_instance()->get_license()->check_license('sync_cpt', self::PLUGIN_KEY, self::PLUGIN_NAME))
				return $post_types;

			$cpts = get_post_types(array('_builtin' => FALSE));

			// we can't handle products and downloads since these use custom tables anyway. remove them.
			if (class_exists('WooCommerce', FALSE)) {
				unset($cpts['product']);
				unset($cpts['products']);
				unset($cpts['product_variation']);
			}
			if (class_exists('Easy_Digital_Downloads', FALSE)) {
				unset($cpts['downloads']);
				unset($cpts['edd_discount']);
				unset($cpts['edd_license']);
				unset($cpts['edd_license_log']);
				unset($cpts['edd_log']);
				unset($cpts['edd_payment']);
				unset($cpts['edd-checkout-fields']);
			}
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
//SyncDebug::log(__METHOD__.'()');
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
