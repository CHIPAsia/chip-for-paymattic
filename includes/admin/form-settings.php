<?php

use WPPayForm\App\Models\Form;

$slug = PYMTC_CHIP_FSLUG;
function pymtc_chip_form_fields( $form ){

  $pymtc_form_currency = Form::getCurrencySettings( $form->ID );

  $form_fields = array(
    array(
      'id'    => 'form-customize-' . $form->ID,
      'type'  => 'switcher',
      'title' => sprintf( __( 'Customization', 'chip-for-paymattic' ) ),
      'desc'  => sprintf( __( 'Form ID: <strong>#%s</strong>. Form Title: <strong>%s</strong>', 'chip-for-paymattic' ), $form->ID, $form->post_title),
      'help'  => sprintf( __( 'This to enable customization per form-basis for form: #%s', 'chip-for-paymattic' ), $form->ID ),
    ),
    array(
      'type' => 'notice',
      'style' => 'danger',
      'content' => sprintf( __( 'The default currency is set to non compatible currencies! %sClick here%s to update currency configuration.', 'chip-for-paymattic' ), '<a target=_blank href=' . admin_url('admin.php?page=wppayform_settings') . ' >', '</a>' ),
      'class' => $pymtc_form_currency['currency'] == 'MYR' ? 'hidden' : '',
    ),
    array(
      'type'    => 'subheading',
      'content' => 'Credentials',
      'dependency'  => array( ['form-customize-' . $form->ID, '==', 'true'] ),
    ),
    array(
      'id'    => 'secret-key-' . $form->ID,
      'type'  => 'text',
      'title' => __( 'Secret Key', 'chip-for-paymattic' ),
      'desc'  => __( 'Enter your Secret Key.', 'chip-for-paymattic' ),
      'help'  => __( 'Secret key is used to identify your account with CHIP. You are recommended to create dedicated secret key for each website.', 'chip-for-paymattic' ),
      
      'dependency'  => array( ['form-customize-' . $form->ID, '==', 'true'] ),
    ),
    array(
      'id'    => 'brand-id-' . $form->ID,
      'type'  => 'text',
      'title' => __( 'Brand ID', 'chip-for-paymattic' ),
      'desc'  => __( 'Enter your Brand ID.', 'chip-for-paymattic' ),
      'help'  => __( 'Brand ID enables you to represent your Brand suitable for the system using the same CHIP account.', 'chip-for-paymattic' ),

      'dependency'  => array( ['form-customize-' . $form->ID, '==', 'true'] ),
    ),
    array(
      'type'    => 'subheading',
      'content' => 'Miscellaneous',
      'dependency'  => array( ['form-customize-' . $form->ID, '==', 'true'] ),
    ),
    array(
      'id'    => 'send-receipt-' . $form->ID,
      'type'  => 'switcher',
      'title' => __( 'Purchase Send Receipt', 'chip-for-paymattic' ),
      'desc'  => __( 'Send receipt upon payment completion.', 'chip-for-paymattic' ),
      'help'  => __( 'Whether to send receipt email when it\'s paid. If configured, the receipt email will be send by CHIP. Default is off.', 'chip-for-paymattic' ),

      'dependency'  => array( ['form-customize-' . $form->ID, '==', 'true'] ),
    ),
    array(
      'id'      => 'due-strict-' . $form->ID,
      'type'    => 'switcher',
      'title'   => __( 'Due Strict', 'chip-for-paymattic' ),
      'desc'    => __( 'Turn this on to prevent payment after specific time.', 'chip-for-paymattic' ),
      'help'    => __( 'Whether to permit payments when Purchase\'s due has passed. By default those are permitted (and status will be set to overdue once due moment is passed). If this is set to true, it won\'t be possible to pay for an overdue invoice, and when due is passed the Purchase\'s status will be set to expired.', 'chip-for-paymattic' ),
      'default' => true,
      
      'dependency'  => array( ['form-customize-' . $form->ID, '==', 'true'] ),
    ),
    array(
      'id'          => 'due-strict-timing-' . $form->ID,
      'type'        => 'number',
      'after'       => 'minutes',
      'title'       => __( 'Due Strict Timing', 'chip-for-paymattic' ),
      'help'        => __( 'Set due time to enforce due timing for purchases. 60 for 60 minutes. If due_strict is set while due strict timing unset, it will default to 1 hour.', 'chip-for-paymattic' ),
      'desc'        => __( 'Default 60 for 1 hour.', 'chip-for-paymattic' ),
      'default'     => '60',
      'placeholder' => '60',
      'dependency'  => array( ['due-strict-' . $form->ID, '==', 'true'], ['form-customize-' . $form->ID, '==', 'true'] ),
      'validate'    => 'chippymtc_validate_numeric',
    ),
  );

  return $form_fields;
}

CHIPPYMTC_Setup::createSection( $slug, array(
  'id'    => 'form-configuration',
  'title' => __( 'Form Configuration', 'chip-for-paymattic' ),
  'icon'  => 'fa fa-gear'
));

$all_forms_query = Form::getAllForms();

foreach( $all_forms_query as $form ) {

  CHIPPYMTC_Setup::createSection( $slug, array(
    'parent'      => 'form-configuration',
    'id'          => 'form-id-' . $form->ID,
    'title'       => sprintf( __( 'Form #%s - %s', 'chip-for-paymattic' ), $form->ID, substr( $form->post_title, 0, 15 ) ),
    'description' => sprintf( __( 'Configuration for Form #%s - %s', 'chip-for-paymattic' ), $form->ID, $form->post_title ),
    'fields'      => pymtc_chip_form_fields( $form ),
  ));
}