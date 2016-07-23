<?php
/*
 * Adds the Sync metabox to custom post types
 * @package Sync
 * @author Dave Jesch
 */

class SyncCPTsAdmin
{
	private static $_instance = NULL;

	private function __construct()
	{
		add_action('spectrom_sync_register_settings', array(&$this, 'settings_api_init'));
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
	 * Callback for the settings API to indicate CPTs is installed
	 */
	public function settings_api_init()
	{
		$section_id = 'sync_section';

		add_settings_section(
			'synccpt-install-notification',								// id
			__('WPSiteSync for Custom Post Types add-on is installed.', 'wpsitesync-cpts'),		// title
			array(&$this, 'render_target_section'),		// callback
			SyncSettings::SETTINGS_PAGE					// option page
		);
	}

	/**
	 * Renders the CPY section on the settings page.
	 */
	public function render_target_section()
	{
		// noop
	}
}

// EOF
