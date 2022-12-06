<?php if ( ! defined( 'ABSPATH' ) ) { die; } // Cannot access directly.
/**
 *
 * Field: backup
 *
 * @since 1.0.0
 * @version 1.0.0
 *
 */
if ( ! class_exists( 'CHIPPYMTC_Field_backup' ) ) {
  class CHIPPYMTC_Field_backup extends CHIPPYMTC_Fields {

    public function __construct( $field, $value = '', $unique = '', $where = '', $parent = '' ) {
      parent::__construct( $field, $value, $unique, $where, $parent );
    }

    public function render() {

      $unique = $this->unique;
      $nonce  = wp_create_nonce( 'chippymtc_backup_nonce' );
      $export = add_query_arg( array( 'action' => 'chippymtc-export', 'unique' => $unique, 'nonce' => $nonce ), admin_url( 'admin-ajax.php' ) );

      echo $this->field_before();

      echo '<textarea name="chippymtc_import_data" class="chippymtc-import-data"></textarea>';
      echo '<button type="submit" class="button button-primary chippymtc-confirm chippymtc-import" data-unique="'. esc_attr( $unique ) .'" data-nonce="'. esc_attr( $nonce ) .'">'. esc_html__( 'Import', 'chippymtc' ) .'</button>';
      echo '<hr />';
      echo '<textarea readonly="readonly" class="chippymtc-export-data">'. esc_attr( json_encode( get_option( $unique ) ) ) .'</textarea>';
      echo '<a href="'. esc_url( $export ) .'" class="button button-primary chippymtc-export" target="_blank">'. esc_html__( 'Export & Download', 'chippymtc' ) .'</a>';
      echo '<hr />';
      echo '<button type="submit" name="chippymtc_transient[reset]" value="reset" class="button chippymtc-warning-primary chippymtc-confirm chippymtc-reset" data-unique="'. esc_attr( $unique ) .'" data-nonce="'. esc_attr( $nonce ) .'">'. esc_html__( 'Reset', 'chippymtc' ) .'</button>';

      echo $this->field_after();

    }

  }
}
