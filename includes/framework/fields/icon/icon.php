<?php if ( ! defined( 'ABSPATH' ) ) { die; } // Cannot access directly.
/**
 *
 * Field: icon
 *
 * @since 1.0.0
 * @version 1.0.0
 *
 */
if ( ! class_exists( 'CHIPPYMTC_Field_icon' ) ) {
  class CHIPPYMTC_Field_icon extends CHIPPYMTC_Fields {

    public function __construct( $field, $value = '', $unique = '', $where = '', $parent = '' ) {
      parent::__construct( $field, $value, $unique, $where, $parent );
    }

    public function render() {

      $args = wp_parse_args( $this->field, array(
        'button_title' => esc_html__( 'Add Icon', 'chippymtc' ),
        'remove_title' => esc_html__( 'Remove Icon', 'chippymtc' ),
      ) );

      echo $this->field_before();

      $nonce  = wp_create_nonce( 'chippymtc_icon_nonce' );
      $hidden = ( empty( $this->value ) ) ? ' hidden' : '';

      echo '<div class="chippymtc-icon-select">';
      echo '<span class="chippymtc-icon-preview'. esc_attr( $hidden ) .'"><i class="'. esc_attr( $this->value ) .'"></i></span>';
      echo '<a href="#" class="button button-primary chippymtc-icon-add" data-nonce="'. esc_attr( $nonce ) .'">'. $args['button_title'] .'</a>';
      echo '<a href="#" class="button chippymtc-warning-primary chippymtc-icon-remove'. esc_attr( $hidden ) .'">'. $args['remove_title'] .'</a>';
      echo '<input type="hidden" name="'. esc_attr( $this->field_name() ) .'" value="'. esc_attr( $this->value ) .'" class="chippymtc-icon-value"'. $this->field_attributes() .' />';
      echo '</div>';

      echo $this->field_after();

    }

    public function enqueue() {
      add_action( 'admin_footer', array( 'CHIPPYMTC_Field_icon', 'add_footer_modal_icon' ) );
      add_action( 'customize_controls_print_footer_scripts', array( 'CHIPPYMTC_Field_icon', 'add_footer_modal_icon' ) );
    }

    public static function add_footer_modal_icon() {
    ?>
      <div id="chippymtc-modal-icon" class="chippymtc-modal chippymtc-modal-icon hidden">
        <div class="chippymtc-modal-table">
          <div class="chippymtc-modal-table-cell">
            <div class="chippymtc-modal-overlay"></div>
            <div class="chippymtc-modal-inner">
              <div class="chippymtc-modal-title">
                <?php esc_html_e( 'Add Icon', 'chippymtc' ); ?>
                <div class="chippymtc-modal-close chippymtc-icon-close"></div>
              </div>
              <div class="chippymtc-modal-header">
                <input type="text" placeholder="<?php esc_html_e( 'Search...', 'chippymtc' ); ?>" class="chippymtc-icon-search" />
              </div>
              <div class="chippymtc-modal-content">
                <div class="chippymtc-modal-loading"><div class="chippymtc-loading"></div></div>
                <div class="chippymtc-modal-load"></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    <?php
    }

  }
}
