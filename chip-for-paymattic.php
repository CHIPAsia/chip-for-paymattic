<?php

/**
 * Plugin Name: CHIP for Paymattic
 * Plugin URI: https://wordpress.org/plugins/chip-for-paymattic/
 * Description: Cash, Card and Coin Handling Integrated Platform
 * Version: 1.0.0
 * Author: Chip In Sdn Bhd
 * Author URI: https://www.chip-in.asia
 *
 * Copyright: © 2022 CHIP
 * License: GNU General Public License v3.0
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

if ( ! defined( 'ABSPATH' ) ) { die; } // Cannot access directly.

define('PYMTC_CHIP_MODULE_VERSION', 'v1.0.0');

class Chip_Paymattic {

  private static $_instance;

  public static function get_instance() {
    if ( self::$_instance == null ) {
      self::$_instance = new self();
    }

    return self::$_instance;
  }

  public function __construct() {
    $this->define();
    $this->includes();
    $this->add_filters();
  }

  public function define() {
    define( 'PYMTC_CHIP_FILE', __FILE__ );
    define( 'PYMTC_CHIP_BASENAME', plugin_basename( PYMTC_CHIP_FILE ) );
    define( 'PYMTC_CHIP_DIR_PATH', plugin_dir_path( PYMTC_CHIP_FILE ) );
    define( 'PYMTC_CHIP_URL', plugin_dir_url( PYMTC_CHIP_FILE ) );
    define( 'PYMTC_CHIP_FSLUG', 'paymattic_chip' );

    // This is CHIP API URL Endpoint as per documented in: https://developer.chip-in.asia/api
    define( 'PYMTC_CHIP_ROOT_URL', 'https://gate.chip-in.asia/' );
  }

  public function includes() {
    $includes_dir = PYMTC_CHIP_DIR_PATH . 'includes/';
    include $includes_dir . 'class-api.php';

    if ( is_admin() ){
      include $includes_dir . 'admin/global-settings.php';
      include $includes_dir . 'admin/form-settings.php';
      include $includes_dir . 'admin/backup-settings.php';
      include $includes_dir . 'admin/class-inject-chip-logo.php';
    }

    include $includes_dir . 'class-element.php';
    include $includes_dir . 'class-processor.php';
  }

  public function add_filters() {
    add_filter( 'plugin_action_links_' . PYMTC_CHIP_BASENAME, array( $this, 'setting_link' ) );
  }

  public function setting_link($links) {
    $new_links = array(
      'settings' => sprintf(
        // this has to be changed to codestar framework settings
        '<a href="%1$s">%2$s</a>', admin_url( 'admin.php?page=chip-for-paymattic' ), esc_html__( 'Settings', 'chip-for-paymattic' )
      )
    );

    return array_merge($new_links, $links);
  }
}

// it must load after paymattic plugin loaded
add_action( 'plugins_loaded', 'load_chip_for_paymattic', 11 );

function load_chip_for_paymattic() {

  if ( !class_exists( 'WPPayFormPro' ) || !class_exists( 'WPPayFormPro\GateWays\BasePaymentMethod' ) ) {
    return;
  }

  Chip_Paymattic::get_instance();
}

// csf must load by default priority
// and shall not tied to load_chip_for_paymattic
add_action( 'plugins_loaded', 'load_chip_for_paymattic_csf' );

function load_chip_for_paymattic_csf() {
  include plugin_dir_path( __FILE__ ) . 'includes/framework/classes/setup.class.php';
}