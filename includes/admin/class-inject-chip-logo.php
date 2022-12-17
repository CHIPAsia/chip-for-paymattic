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
  }

  public function inject_chip_logo( $data, $admin_option ) {

    $this->global_inject_chip_logo( $data, $admin_option );
  }

  private function global_inject_chip_logo( $data, $admin_option ) {

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

    if ( !file_exists( $target_icon_path ) ) {
      if ( copy( $source_icon_path, $target_icon_path ) ) {
        update_option( 'paymattic_chip_inject_logo', 'success', false );
      } else {
        update_option( 'paymattic_chip_inject_logo', 'failed', false );
      }
    }
  }
}

Chip_Paymattic_Inject_Chip_logo::get_instance();
