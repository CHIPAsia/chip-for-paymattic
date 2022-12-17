<?php

use WPPayForm\App\Services\GeneralSettings;

$slug = PYMTC_CHIP_FSLUG;

$pymtc_global_currency = GeneralSettings::getGlobalCurrencySettings();

CSF_Setup::createOptions( $slug, array(
  'framework_title' => sprintf( __( 'CHIP for Paymattic %1$s%3$s%2$s', 'chip-for-paymattic' ), '<small>', '</small>', PYMTC_CHIP_MODULE_VERSION ),

  'menu_title'  => __( 'CHIP Settings', 'chip-for-paymattic' ),
  'menu_slug'   => 'chip-for-paymattic',
  'menu_type'   => 'submenu',
  'menu_parent' => 'wppayform.php',
  'footer_text' => sprintf( __( 'CHIP for Paymattic %s', 'chip-for-paymattic' ) , PYMTC_CHIP_MODULE_VERSION ),
  'theme'       => 'light',
) );

$credentials_global_fields = array(
  array(
    'type'    => 'notice',
    'style'   => 'danger',
    'content' => sprintf( __( 'The default currency is set to non compatible currencies! %sClick here%s to update currency configuration.', 'chip-for-paymattic' ), '<a target=_blank href=' . admin_url('admin.php?page=wppayform_settings') . ' >', '</a>' ),
    'class'   => $pymtc_global_currency['currency'] == 'MYR' ? 'hidden' : '',
  ),
  array(
    'type'    => 'notice',
    'style'   => 'normal',
    'content' => __( 'Note: Please add Email and Name field on your form to get payment data correctly.', 'chip-for-paymattic' ),
  ),
  array(
    'type'    => 'subheading',
    'content' => 'Credentials',
  ),
  array(
    'id'    => 'secret-key',
    'type'  => 'text',
    'title' => __( 'Secret Key', 'chip-for-paymattic' ),
    'desc'  => __( 'Enter your Secret Key.', 'chip-for-paymattic' ),
    'help'  => __( 'Secret key is used to identify your account with CHIP. You are recommended to create dedicated secret key for each website.', 'chip-for-paymattic' ),
  ),
  array(
    'id'    => 'brand-id',
    'type'  => 'text',
    'title' => __( 'Brand ID', 'chip-for-paymattic' ),
    'desc'  => __( 'Enter your Brand ID.', 'chip-for-paymattic' ),
    'help'  => __( 'Brand ID enables you to represent your Brand suitable for the system using the same CHIP account.', 'chip-for-paymattic' ),
  ) );

$miscellaneous_global_fields = array(
  array(
    'type'    => 'subheading',
    'content' => 'Miscellaneous',
  ),
  array(
    'id'          => 'payment-title',
    'type'        => 'text',
    'title'       => __( 'Payment Title', 'chip-for-paymattic' ),
    'desc'        => __( 'Enter your Payment Title. Default is <strong>CHIP</strong>', 'chip-for-paymattic' ),
    'help'        => __( 'This allows you to customize the payment title.', 'chip-for-paymattic' ),
    'placeholder' => 'CHIP',
    'default'     => 'CHIP',
  ),
  array(
    'id'    => 'send-receipt',
    'type'  => 'switcher',
    'title' => __( 'Purchase Send Receipt', 'chip-for-paymattic' ),
    'desc'  => __( 'Send receipt upon payment completion.', 'chip-for-paymattic' ),
    'help'  => __( 'Whether to send receipt email when it\'s paid. If configured, the receipt email will be send by CHIP. Default is off.', 'chip-for-paymattic' ),
  ),
  array(
    'id'      => 'due-strict',
    'type'    => 'switcher',
    'title'   => __( 'Due Strict', 'chip-for-paymattic' ),
    'desc'    => __( 'Turn this on to prevent payment after specific time.', 'chip-for-paymattic' ),
    'help'    => __( 'Whether to permit payments when Purchase\'s due has passed. By default those are permitted (and status will be set to overdue once due moment is passed). If this is set to true, it won\'t be possible to pay for an overdue invoice, and when due is passed the Purchase\'s status will be set to expired.', 'chip-for-paymattic' ),
    'default' => true,
  ),
  array(
    'id'          => 'due-strict-timing',
    'type'        => 'number',
    'after'       => 'minutes',
    'title'       => __( 'Due Strict Timing', 'chip-for-paymattic' ),
    'help'        => __( 'Set due time to enforce due timing for purchases. 60 for 60 minutes. If due_strict is set while due strict timing unset, it will default to 1 hour.', 'chip-for-paymattic' ),
    'desc'        => __( 'Default 60 for 1 hour.', 'chip-for-paymattic' ),
    'default'     => '60',
    'placeholder' => '60',
    'dependency'  => array( ['due-strict', '==', 'true'] ),
    'validate'    => 'csf_validate_numeric',
  ),
  array(
    'id'    => 'inject-chip-logo',
    'type'  => 'checkbox',
    'title' => __( 'Set CHIP Logo', 'chip-for-paymattic' ),
    'desc'  => sprintf( __( 'By activating this option, you agree to allow the plugin to add CHIP Icon to Paymattic. This will not work for WordPress installed on ephemeral file system. Source icon: %s. Target location: %s', 'chip-for-paymattic' ), PYMTC_CHIP_URL . 'assets/logo.svg', WPPAYFORM_DIR . 'assets/images/payment-logo/chip.svg' ),
    'help'  => __( 'By defaullt Paymattic didn\'t bundle together with CHIP logo. Tick this option to inject logo to Paymattic to fix broken icon.', 'chip-for-paymattic' ),
  ),
  array(
    'type'    => 'notice',
    'style'   => 'danger',
    'content' => __( 'Set CHIP logo failed!.', 'chip-for-paymattic' ),
    'class'   => get_option( 'paymattic_chip_inject_logo', 'success' ) == 'success' ? 'hidden' : '',

    'dependency'  => array( ['inject-chip-logo', '==', 'true'] ),
  ),
);

CSF_Setup::createSection( $slug, array(
  'id'    => 'global-configuration',
  'title' => __( 'Global Configuration', 'chip-for-paymattic' ),
  'icon'  => 'fa fa-home',
) );

CSF_Setup::createSection( $slug, array(
  'parent'      => 'global-configuration',
  'id'          => 'credentials',
  'title'       => __( 'Credentials', 'chip-for-paymattic' ),
  'description' => __( 'Configure your Secret Key and Brand ID.', 'chip-for-paymattic' ),
  'fields'      => $credentials_global_fields,
) );

CSF_Setup::createSection( $slug, array(
  'parent'      => 'global-configuration',
  'id'          => 'miscellaneous',
  'title'       => __( 'Miscellaneous', 'chip-for-paymattic' ),
  'description' => __( 'Miscellaneous settings.', 'chip-for-paymattic' ),
  'fields'      => $miscellaneous_global_fields,
) );
