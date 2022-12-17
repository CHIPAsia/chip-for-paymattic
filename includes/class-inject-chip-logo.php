<?php

class Chip_Paymattic_Inject_Chip_logo {

  private static $_instance;

  public static function get_instance() {
    if ( self::$_instance == null ) {
      self::$_instance = new self();
    }

    return self::$_instance;
  }

  public function __construct() {
    add_action( 'csf_paymattic_chip_save_before', array( $this, 'inject_chip_logo' ), 10, 2 );
    add_action( 'upgrader_process_complete', array( $this, 'reinject_chip_logo' ), 10, 2 );
  }

  public function inject_chip_logo( $data, $admin_option ) {

    if ( empty( $data['inject-chip-logo'] ) ) {
      return;
    }

    if ( $data['inject-chip-logo'] == false ) {
      return;
    }

    if ( !defined( 'WPPAYFORM_DIR' ) ) {
      return;
    }

    $source_icon_path = PYMTC_CHIP_DIR_PATH . 'assets/logo.svg';
    $target_icon_path = WPPAYFORM_DIR . 'assets/images/payment-logo/chip.svg';

    if ( !wp_is_writable( dirname( $target_icon_path ) ) OR !wp_is_file_mod_allowed( 'paymattic_chip_inject_logo' ) ) {
      update_option( 'paymattic_chip_inject_logo', 'failed', false );
      return;
    }

    if ( !file_exists( $target_icon_path ) ) {
      if ( copy( $source_icon_path, $target_icon_path ) ) {
        update_option( 'paymattic_chip_inject_logo', 'success', false );
      } else {
        update_option( 'paymattic_chip_inject_logo', 'failed', false );
      }
    }
  }

  public function reinject_chip_logo( $upgrader_object, $options ) {

    if ( !defined( 'WPPAYFORM_MAIN_FILE' ) ) {
      return;
    }

    $chip_options = get_option( PYMTC_CHIP_FSLUG );

    if ( !isset( $chip_options['inject-chip-logo'] ) OR $chip_options['inject-chip-logo'] == false ) {
      return;
    }

    // $plugin_path_name = 'wp-payment-form/wp-payment-form.php';
    $plugin_path_name = plugin_basename( WPPAYFORM_MAIN_FILE );

    if ( $options['action'] == 'update' AND $options['type'] == 'plugin' ) {
      foreach( $options['plugins'] as $each_plugin ) {
        if ( $each_plugin == $plugin_path_name ) {
          $this->inject_chip_logo( $chip_options, null );
        }
      }
   }
  }
}

Chip_Paymattic_Inject_Chip_logo::get_instance();
