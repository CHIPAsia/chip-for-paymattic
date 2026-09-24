<?php
/**
 * Uninstall routine for CHIP for Paymattic.
 *
 * @package CHIPForPaymattic
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	die;
}

delete_option( 'paymattic_chip' );
delete_option( 'paymattic_chip_inject_logo' );
