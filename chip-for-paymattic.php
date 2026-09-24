<?php
/**
 * Plugin Name: CHIP for Paymattic
 * Plugin URI: https://wordpress.org/plugins/chip-for-paymattic/
 * Description: CHIP - Digital Finance Platform
 * Version: 1.1.2
 * Author: Chip In Sdn Bhd
 * Author URI: https://www.chip-in.asia
 * Requires PHP: 7.4
 * Requires at least: 6.1
 *
 * Copyright: © 2025 CHIP
 * License: GNU General Public License v3.0
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package CHIPForPaymattic
 */

if ( ! defined( 'ABSPATH' ) ) {
	die; } // Cannot access directly.

define( 'PYMTC_CHIP_MODULE_VERSION', 'v1.1.2' );

/**
 * Main plugin class.
 */
class Chip_Paymattic {

	/**
	 * Single instance of the class.
	 *
	 * @var Chip_Paymattic|null
	 */
	private static $_instance;

	/**
	 * Gets the single instance of the class.
	 *
	 * @return Chip_Paymattic
	 */
	public static function get_instance() {
		if ( null === self::$_instance ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Constructor. Wires up the plugin.
	 */
	public function __construct() {
		$this->define();
		$this->includes();
		$this->add_filters();
	}

	/**
	 * Defines the plugin constants.
	 *
	 * @return void
	 */
	public function define() {
		define( 'PYMTC_CHIP_FILE', __FILE__ );
		define( 'PYMTC_CHIP_BASENAME', plugin_basename( PYMTC_CHIP_FILE ) );
		define( 'PYMTC_CHIP_DIR_PATH', plugin_dir_path( PYMTC_CHIP_FILE ) );
		define( 'PYMTC_CHIP_URL', plugin_dir_url( PYMTC_CHIP_FILE ) );
		define( 'PYMTC_CHIP_FSLUG', 'paymattic_chip' );

		// This is the CHIP API URL endpoint, as documented at https://docs.chip-in.asia/.
		define( 'PYMTC_CHIP_ROOT_URL', 'https://gate.chip-in.asia/' );
	}

	/**
	 * Loads the plugin files.
	 *
	 * @return void
	 */
	public function includes() {
		$includes_dir = PYMTC_CHIP_DIR_PATH . 'includes/';
		include $includes_dir . 'chip-ff-functions.php';
		include $includes_dir . 'class-api.php';
		include $includes_dir . 'class-chip-ff-settings.php';
		include $includes_dir . 'class-chip-ff-settings-page.php';

		if ( is_admin() ) {
			include $includes_dir . 'admin/global-settings.php';
			include $includes_dir . 'admin/form-settings.php';
			include $includes_dir . 'admin/backup-settings.php';
		}

		include $includes_dir . 'class-element.php';
		include $includes_dir . 'class-settings.php';
		include $includes_dir . 'class-processor.php';
	}

	/**
	 * Registers the plugin's filters.
	 *
	 * @return void
	 */
	public function add_filters() {
		add_filter( 'plugin_action_links_' . PYMTC_CHIP_BASENAME, array( $this, 'setting_link' ) );
	}

	/**
	 * Adds a Settings link to the plugin row.
	 *
	 * @param array $links The existing action links.
	 * @return array The action links with Settings prepended.
	 */
	public function setting_link( $links ) {
		$new_links = array(
			'settings' => sprintf(
				'<a href="%1$s">%2$s</a>',
				admin_url( 'admin.php?page=chip-for-paymattic' ),
				esc_html__( 'Settings', 'chip-for-paymattic' )
			),
		);

		return array_merge( $new_links, $links );
	}
}

/**
 * Builds the settings page once every settings file has registered its
 * sections.
 *
 * Building the page earlier would snapshot an empty section list and drop
 * every field.
 *
 * @return void
 */
function chip_ff_build_paymattic_settings_page() {
	if ( class_exists( 'CHIP_FF_Settings' ) ) {
		CHIP_FF_Settings::$version = PYMTC_CHIP_MODULE_VERSION;
		CHIP_FF_Settings::init_pages();
	}
}

/**
 * Boots the gateway once Paymattic Pro is available.
 *
 * @return void
 */
function load_chip_for_paymattic_csf() {

	if ( ! class_exists( 'WPPayFormPro' ) || ! class_exists( 'WPPayFormPro\GateWays\BasePaymentMethod' ) ) {
		return;
	}

	Chip_Paymattic::get_instance();
}

add_action( 'init', 'load_chip_for_paymattic_csf', 0 );
add_action( 'init', 'chip_ff_build_paymattic_settings_page', 100 );
