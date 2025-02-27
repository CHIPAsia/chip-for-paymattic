<?php

use WPPayFormPro\GateWays\BasePaymentMethod;
use WPPayForm\Framework\Support\Arr;
use WPPayForm\App\Services\AccessControl;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class ChipSettings extends BasePaymentMethod
{

    public function __construct()
    {
      $logo_url = apply_filters( 'paymattic_chip_logo_url_settings', PYMTC_CHIP_URL . 'assets/chip.svg' );
      add_filter( 'wppayform_payment_method_settings', array( $this, 'get_settings' ), 10, 1 );
    /**
     * Automatically create global payment settings page
     * @param  String: key, title, routes_query, 'logo')
     */
        parent::__construct(
            'chip',
            'CHIP',
            [],
            $logo_url
        );

    }

    /**
     * @function mapperSettings, To map key => value before store
     * @function validateSettings, To validate before save settings
     */

    public function init()
    {
        add_filter('wppayform_payment_method_settings_mapper_'.$this->key, array($this, 'mapperSettings'));
        add_filter('wppayform_payment_method_settings_validation_' . $this->key, array($this, 'validateSettings'), 10, 2);
    }

    /**
     * @return Array of global fields
     */
    public function globalFields(): array {
        return array(
            'is_active' => array(
                'value' => 'no',
                'label' => __('Enable/Disable', 'wp-payment-form'),
            ),
            'checkout_type' => array(
                'value' => 'modal',
                'label' => __('Checkout Logo', 'wp-payment-form'),
                'options' => ['modal' => 'Modal checkout style', 'hosted' => 'Hosted checkout style'],
            ),
            // 'secret_key' => array(
            //     'value' => '',
            //     'label' => __('Secret Key', 'wp-payment-form'),
            //     'type' => 'text',
            //     'placeholder' => __('CHIP API Secret', 'wp-payment-form')
            // ),
            // 'brand_id' => array(
            //     'value' => '',
            //     'label' => __('Brand ID', 'wp-payment-form'),
            //     'type' => 'text',
            //     'placeholder' => __('CHIP Brand ID', 'wp-payment-form')
            // ),
            'desc' => array(
                'value' => '<div> <p style="color: #d48916;">CHIP for Paymattic can be configured through Paymattic Pro >> <a href="'.admin_url('admin.php?page=chip-for-paymattic').'" target="_blank" rel="noopener">CHIP Settings</a>.</p> </div>',
                'label' => __('Note', 'wp-payment-form'),
                'type' => 'html_attr'
            ),
            'is_pro_item' => array(
                'value' => 'yes',
                'label' => __('CHIP', 'wp-payment-form'),
            ),
        );
    }

    /**
     * @return Array of default fields
     */
    public static function settingsKeys(): array
    {
        return array(
            'is_active' => 'no',
            'checkout_type' => 'hosted',
            'secret_key' => '',
            'brand_id' => ''
        );
    }
    
    /**
     * @return Array of global_payments settings fields
     */
    public function getPaymentSettings(): array
    {
        $settings = $this->mapper(
            $this->globalFields(), 
            static::getSettings()
        );

        return array(
            'settings' => $settings,
            'is_key_defined' => false
        );
    }

    public static function getSettings()
    {
        $settings = get_option('wppayform_payment_settings_chip', array());
        return wp_parse_args($settings, static::settingsKeys());
    }

    public function mapperSettings ($settings)
    {
        return $this->mapper(
            static::settingsKeys(), 
            $settings, 
            false
        );
    }


    public static function ApiRoutes($isLive, $settings)
    {
        return array(
            'secret_key' => Arr::get($settings, 'secret_key'),
            'brand_id' => Arr::get($settings, 'brand_id')
        );
    }

    public static function getApiKeys($formId = false)
    {
        return static::ApiRoutes(
            static::isLive($formId),
            static::getSettings()
        );
    }

    public function get_settings( $methods ) {
      $methods['chip'] = array(
        'title'       => __( 'CHIP', 'chip-for-paymattic' ),
        'route_name'  => 'chip',
        'svg'         => PYMTC_CHIP_URL . 'assets/chip.svg',
        'route_query' => [],
      );
      return $methods;
    }
}
