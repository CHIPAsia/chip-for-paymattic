<?php
/**
 * CHIP logo injection for CHIP for Paymattic.
 *
 * @package CHIPForPaymattic
 */

/**
 * Copies the CHIP logo into Paymattic's payment logo directory.
 *
 * Note: this file is not loaded by the bootstrap any more (see
 * chip-for-paymattic.php). It is kept for reference only.
 */
class Chip_Paymattic_Inject_Chip_logo {

	/**
	 * Single instance of the class.
	 *
	 * @var object|null
	 */
	private static $_instance;

	/**
	 * Gets the single instance of the class.
	 *
	 * @return object
	 */
	public static function get_instance() {
		if ( null === self::$_instance ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Constructor. Registers the plugin's hooks.
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'chip_ff_paymattic_chip_save_before', array( $this, 'inject_chip_logo' ), 10, 2 );
		add_action( 'upgrader_process_complete', array( $this, 'reinject_chip_logo' ), 10, 2 );
	}

	/**
	 * Copies the CHIP logo into Paymattic's payment logo directory.
	 *
	 * @param mixed $data The data.
	 * @param mixed $admin_option The admin option.
	 * @return void
	 */
	public function inject_chip_logo( $data, $admin_option ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- signature fixed by add_action().

		if ( empty( $data['inject-chip-logo'] ) ) {
			return;
		}

		if ( false === (bool) $data['inject-chip-logo'] ) {
			return;
		}

		if ( ! defined( 'WPPAYFORM_DIR' ) ) {
			return;
		}

		$source_icon_path = PYMTC_CHIP_DIR_PATH . 'assets/chip.svg';
		$target_icon_path = WPPAYFORM_DIR . 'assets/images/payment-logo/chip.svg';

		if ( ! wp_is_writable( dirname( $target_icon_path ) ) || ! wp_is_file_mod_allowed( 'paymattic_chip_inject_logo' ) ) {
			update_option( 'paymattic_chip_inject_logo', 'failed', false );
			return;
		}

		if ( ! file_exists( $target_icon_path ) ) {
			if ( copy( $source_icon_path, $target_icon_path ) ) {
				update_option( 'paymattic_chip_inject_logo', 'success', false );
			} else {
				update_option( 'paymattic_chip_inject_logo', 'failed', false );
			}
		}
	}

	/**
	 * Re-copies the CHIP logo after Paymattic is updated.
	 *
	 * @param mixed $upgrader_object The upgrader object.
	 * @param mixed $options The options.
	 * @return void
	 */
	public function reinject_chip_logo( $upgrader_object, $options ) {

		if ( ! defined( 'WPPAYFORM_MAIN_FILE' ) ) {
			return;
		}

		$chip_options = get_option( PYMTC_CHIP_FSLUG );

		if ( ! isset( $chip_options['inject-chip-logo'] ) || false === (bool) $chip_options['inject-chip-logo'] ) {
			return;
		}

		$plugin_path_name = plugin_basename( WPPAYFORM_MAIN_FILE );

		if ( 'update' === $options['action'] && 'plugin' === $options['type'] ) {
			foreach ( $options['plugins'] as $each_plugin ) {
				if ( $plugin_path_name === $each_plugin ) {
					$this->inject_chip_logo( $chip_options, null );
				}
			}
		}
	}
}

Chip_Paymattic_Inject_Chip_logo::get_instance();
