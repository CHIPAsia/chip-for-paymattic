<?php
/**
 * Payment method settings for CHIP for Paymattic.
 *
 * @package CHIPForPaymattic
 */

use WPPayFormPro\GateWays\BasePaymentMethod;
use WPPayForm\Framework\Support\Arr;
use WPPayForm\App\Services\AccessControl;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * CHIP payment method settings for Paymattic.
 */
class ChipSettings extends BasePaymentMethod {


	/**
	 * Constructor. Registers the plugin's hooks.
	 *
	 * @return void
	 */
	public function __construct() {
		$logo_url = apply_filters( 'paymattic_chip_logo_url_settings', PYMTC_CHIP_URL . 'assets/chip.svg' );
		add_filter( 'wppayform_payment_method_settings', array( $this, 'get_settings' ), 10, 1 );
		/**
		 * Automatically create global payment settings page
		 *
		 * @param  String: key, title, routes_query, 'logo')
		 */
		parent::__construct(
			'chip',
			'CHIP',
			array(),
			$logo_url
		);
	}

	/**
	 * Registers the settings filters used by Paymattic's settings screen.
	 *
	 * Paymattic calls mapperSettings() before storing and validateSettings()
	 * before saving, so both are wired up here.
	 */
	public function init() {
		add_filter( 'wppayform_payment_method_settings_mapper_' . $this->key, array( $this, 'mapperSettings' ) );
		add_filter( 'wppayform_payment_method_settings_validation_' . $this->key, array( $this, 'validateSettings' ), 10, 2 );
	}

	/**
	 * Returns the global fields for this payment method.
	 *
	 * @return array The global fields.
	 */
	public function globalFields(): array {
		return array(
			'is_active'     => array(
				'value' => 'no',
				'label' => __( 'Enable/Disable', 'wp-payment-form' ),
			),
			'checkout_type' => array(
				'value'   => 'modal',
				'label'   => __( 'Checkout Logo', 'wp-payment-form' ),
				'options' => array(
					'modal'  => 'Modal checkout style',
					'hosted' => 'Hosted checkout style',
				),
			),
			// The secret key and brand id fields are intentionally absent here:
			// this plugin collects them on its own settings screen (see
			// includes/admin/global-settings.php) because they are stored per
			// form as well as globally.
			'desc'          => array(
				'value' => '<div> <p style="color: #d48916;">CHIP for Paymattic can be configured through Paymattic Pro >> <a href="' . admin_url( 'admin.php?page=chip-for-paymattic' ) . '" target="_blank" rel="noopener">CHIP Settings</a>.</p> </div>',
				'label' => __( 'Note', 'wp-payment-form' ),
				'type'  => 'html_attr',
			),
			'is_pro_item'   => array(
				'value' => 'yes',
				'label' => __( 'CHIP', 'wp-payment-form' ),
			),
		);
	}

	/**
	 * Returns the default value for each settings key.
	 *
	 * @return array The default values.
	 */
	public static function settingsKeys(): array {
		return array(
			'is_active'     => 'no',
			'checkout_type' => 'hosted',
			'secret_key'    => '',
			'brand_id'      => '',
		);
	}

	/**
	 * Returns the payment settings Paymattic renders on its settings screen.
	 *
	 * @return array The settings fields.
	 */
	public function getPaymentSettings(): array {
		$settings = $this->mapper(
			$this->globalFields(),
			static::getSettings()
		);

		return array(
			'settings'       => $settings,
			'is_key_defined' => false,
		);
	}

	/**
	 * Returns the saved settings with defaults applied.
	 *
	 * @return array
	 */
	public static function getSettings() {
		$settings = get_option( 'wppayform_payment_settings_chip', array() );
		return wp_parse_args( $settings, static::settingsKeys() );
	}

	/**
	 * Validates the settings before Paymattic stores them.
	 *
	 * Paymattic calls this from its own save handler
	 * (PaymentController::savePaymentMethodSettings) and refuses to save when a
	 * non-empty array comes back. This method did not exist at all before, so
	 * every save of the CHIP credentials died with a TypeError instead of
	 * reaching the option.
	 *
	 * @param array $errors   The errors accumulated so far.
	 * @param mixed $settings The submitted settings.
	 * @return array
	 */
	public function validateSettings( $errors, $settings ) {
		$errors = is_array( $errors ) ? $errors : array();

		$secret_key = Arr::get( $settings, 'secret_key' );
		$brand_id   = Arr::get( $settings, 'brand_id' );

		if ( empty( $secret_key ) ) {
			$errors['secret_key'] = __( 'Please provide the CHIP Secret Key.', 'chip-for-paymattic' );
		}

		if ( empty( $brand_id ) ) {
			$errors['brand_id'] = __( 'Please provide the CHIP Brand ID.', 'chip-for-paymattic' );
		}

		return $errors;
	}

	/**
	 * Maps stored settings to the shape Paymattic expects.
	 *
	 * @param mixed $settings The settings.
	 * @return array
	 */
	public function mapperSettings( $settings ) {
		return $this->mapper(
			static::settingsKeys(),
			$settings,
			false
		);
	}


	/**
	 * Returns the CHIP API routes used for a given mode.
	 *
	 * @param mixed $isLive The isLive.
	 * @param mixed $settings The settings.
	 * @return array
	 */
	public static function ApiRoutes( $isLive, $settings ) {
		return array(
			'secret_key' => Arr::get( $settings, 'secret_key' ),
			'brand_id'   => Arr::get( $settings, 'brand_id' ),
		);
	}

	/**
	 * Returns the API credentials for a form.
	 *
	 * CHIP uses a single credential pair and reports test mode per purchase
	 * (the transaction's payment_mode), so there is no live/test key split to
	 * resolve and no isLive() state to read. The previous code called a static
	 * isLive() that this class never defined, which made every call to this
	 * method die with a fatal error. ApiRoutes() does not read the flag.
	 *
	 * @param mixed $formId The formId.
	 * @return array
	 */
	public static function getApiKeys( $formId = false ) {
		return static::ApiRoutes(
			false,
			static::getSettings()
		);
	}

	/**
	 * Resolves the CHIP settings for a form, falling back to the global ones.
	 *
	 * @param mixed $methods The methods.
	 * @return array
	 */
	public function get_settings( $methods ) {
		$methods['chip'] = array(
			'title'       => __( 'CHIP', 'chip-for-paymattic' ),
			'route_name'  => 'chip',
			'svg'         => PYMTC_CHIP_URL . 'assets/chip.svg',
			'route_query' => array(),
		);
		return $methods;
	}
}
