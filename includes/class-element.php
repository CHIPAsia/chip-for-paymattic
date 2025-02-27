<?php

use WPPayForm\App\Modules\FormComponents\BaseComponent;
use WPPayForm\Framework\Support\Arr;
use WPPayForm\App\Models\Form;

if (!defined('ABSPATH')) {
    exit;
}

class Chip_Paymattic_Element extends BaseComponent
{
  private static $_instance;

  public static function get_instance() {
    if ( self::$_instance == null ) {
      self::$_instance = new self();
    }

    return self::$_instance;
  }

  public function __construct() {

    parent::__construct( 'chip_gateway_element', 27 );

    add_filter( 'wppayform/validate_gateway_api_chip', function ( $data, $form ) {
      return $this->validate_api( $form->ID );
    }, 2, 10 );

    add_action( 'wppayform/payment_method_choose_element_render_chip', array( $this, 'renderForMultiple' ), 10, 3 );
    add_filter( 'wppayform/available_payment_methods', array( $this, 'push_payment_method' ), 2, 1 );
  }

  public function component() {
    return array(
      'type' => 'chip_gateway_element',
      'editor_title' => __( 'CHIP Payment', 'chip-for-paymattic' ),
      'conditional_hide' => true,
      'editor_icon' => '',
      'group' => 'payment_method_element',
      'method_handler' => 'chip',
      'postion_group' => 'payment_method',
      'single_only' => true,
      'editor_elements' => array(
        'label' => array(
          'label' => 'Field Label',
          'type' => 'text'
        )
      ),
      'field_options' => array(
        'label' => __('CHIP Payment Gateway', 'chip-for-paymattic')
      )
    );
  }

  public function render( $element, $form, $elements ) {
    if ( !$this->validate_api( $form->ID ) ) { ?>
      <p style="color: red">You did not configure CHIP payment gateway. Please configure CHIP payment
        gateway by setting Brand ID and Secret Key from <b>Paymattic Pro->CHIP Settings</b> to start accepting payments</p>
      <?php return;
    }
    if ( !$this->is_supported_currency( $form->ID ) ) { ?>
      <p style="color: red">CHIP doesn't support currency except MYR (Malaysian Ringgit)!<br/>
        Set currency MYR from <b>Paymattic->Settings->Currency</b> to start accepting payments !</p>
      <?php return;
    }
    echo '<input data-wpf_payment_method="chip" type="hidden" name="__chip_payment_gateway" value="chip" />';
  }

  private function validate_api( $form_id ) {

    if ( !( $options = get_option( PYMTC_CHIP_FSLUG ) ) ) {
      return false;
    }

    $postfix = '';

    if ( $options['form-customize-' . $form_id] ) {
      $postfix = "-$form_id";
    }

    if ( strlen( $options['secret-key' . $postfix] ) < 1 || strlen( $options['brand-id' . $postfix] ) < 1 ) {
      return false;
    }

    return true;
  }

  private function is_supported_currency( $form_id ) {
    $currency_setting = Form::getCurrencySettings( $form_id );

    return Arr::get( $currency_setting, 'currency' ) == 'MYR';
  }

  public function renderForMultiple( $paymentSettings, $form, $elements ) {
    $component                  = $this->component();
    $component['id']            = 'chip_gateway_element';
    $component['field_options'] = $paymentSettings;

    $this->render( $component, $form, $elements );
  }

  public function push_payment_method( $methods ) {

    $options       = get_option( PYMTC_CHIP_FSLUG );
    $payment_title = Arr::get($options, 'payment-title', 'CHIP');
    
    $methods['chip'] = array(
      'label'    => $payment_title,
      'isActive' => true,
      'logo' => PYMTC_CHIP_URL . '/assets/chip.svg',
      'editor_elements' => array(
        'label' => array(
          'label'   => __( 'Payment Option Label', 'chip-for-paymattic' ),
          'type'    => 'text',
          'default' => 'Pay with CHIP'
        )
      )
    );
    
    return apply_filters( 'chip_paymattic_push_payment_method_return', $methods, $this );
  }
}

Chip_Paymattic_Element::get_instance();