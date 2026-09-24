<?php
/**
 * CHIP API client for CHIP for Paymattic.
 *
 * @package CHIPForPaymattic
 */

/*
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

/**
 * CHIP API client.
 *
 * One instance is cached per credential pair.
 */
class Chip_Paymattic_API {

	/**
	 * Single instance of the class.
	 *
	 * @var object|null
	 */
	private static $_instance;
	/**
	 * The account secret key.
	 *
	 * @var string
	 */
	private $secret_key;

	/**
	 * The account brand id.
	 *
	 * @var string
	 */
	private $brand_id;

	/**
	 * Gets the single instance of the class.
	 *
	 * @param object $secret_key The secret key.
	 * @param object $brand_id The brand id.
	 * @return object
	 */
	public static function get_instance( $secret_key, $brand_id ) {
		if ( null === self::$_instance ) {
			self::$_instance = new self( $secret_key, $brand_id );
		}

		return self::$_instance;
	}

	/**
	 * Constructor. Registers the plugin's hooks.
	 *
	 * @param mixed $secret_key The secret key.
	 * @param mixed $brand_id The brand id.
	 * @return void
	 */
	public function __construct( $secret_key, $brand_id ) {
		$this->secret_key = $secret_key;
		$this->brand_id   = $brand_id;
	}

	/**
	 * Creates a purchase via the CHIP API.
	 *
	 * @param array $params The params.
	 * @return array
	 */
	public function create_payment( $params ) {
		// time() is used to force a fresh response instead of a cached one.
		return $this->call( 'POST', '/purchases/?time=' . time(), $params );
	}

	/**
	 * Registers a webhook with CHIP.
	 *
	 * @param mixed $params The params.
	 * @return array
	 */
	public function create_webhook( $params ) {
		// time() is used to force a fresh response instead of a cached one.
		return $this->call( 'POST', '/webhooks/?time=' . time(), $params );
	}

	/**
	 * Lists the payment methods available for a currency.
	 *
	 * @param array $currency The currency.
	 * @param array $language The language.
	 * @return array
	 */
	public function payment_methods( $currency, $language ) {
		return $this->call(
			'GET',
			"/payment_methods/?brand_id={$this->brand_id}&currency={$currency}&language={$language}"
		);
	}

	/**
	 * Fetches a purchase from the CHIP API.
	 *
	 * @param string $payment_id The payment id.
	 * @return array
	 */
	public function get_payment( $payment_id ) {
		// time() is used to force a fresh response instead of a cached one.
		$result = $this->call( 'GET', "/purchases/{$payment_id}/?time=" . time() );
		return $result;
	}

	/**
	 * Checks whether a CHIP response reports a successful payment.
	 *
	 * @param mixed $payment_id The payment id.
	 * @return bool
	 */
	public function was_payment_successful( $payment_id ) {
		$result = $this->get_payment( $payment_id );
		return $result && isset( $result['status'] ) && 'paid' === $result['status'];
	}

	/**
	 * Fetches the account public key used to verify webhooks.
	 *
	 * @return array
	 */
	public function get_public_key() {
		return $this->call( 'GET', '/public_key/' );
	}

	/**
	 * Lists the webhooks registered on the account.
	 *
	 * @return array
	 */
	public function get_webhooks() {
		return $this->call( 'GET', '/webhooks/' );
	}

	/**
	 * Refunds a purchase through the CHIP API.
	 *
	 * @param array $payment_id The payment id.
	 * @param array $params The params.
	 * @return array
	 */
	public function refund_payment( $payment_id, $params = array() ) {
		return $this->call( 'POST', "/purchases/{$payment_id}/refund/", $params );
	}

	/**
	 * Performs a CHIP API call and decodes the response.
	 *
	 * @param array $method The method.
	 * @param array $route The route.
	 * @param array $params The params.
	 * @return array
	 */
	private function call( $method, $route, $params = array() ) {
		$secret_key = $this->secret_key;
		if ( ! empty( $params ) ) {
			$params = wp_json_encode( $params );
		}

		$response = $this->request(
			$method,
			sprintf( '%sapi/v1%s', PYMTC_CHIP_ROOT_URL, $route ),
			$params,
			array(
				'Content-type'  => 'application/json',
				'Authorization' => 'Bearer ' . $secret_key,
			)
		);

		$result = json_decode( $response, true );
		if ( ! $result ) {
			return null;
		}

		if ( ! empty( $result['errors'] ) ) {
			return null;
		}

		return $result;
	}

	/**
	 * Performs the HTTP request against the CHIP API.
	 *
	 * @param string $method The HTTP method.
	 * @param string $url The request URL.
	 * @param array  $params The request body.
	 * @param array  $headers Additional request headers.
	 * @return array
	 */
	private function request( $method, $url, $params = array(), $headers = array() ) {
		$wp_request = wp_remote_request(
			$url,
			array(
				'method'    => $method,
				'sslverify' => apply_filters( 'paymattic_chip_sslverify', true ),
				'headers'   => $headers,
				'body'      => $params,
			)
		);

		// A transport failure (DNS, TLS, timeout) returns WP_Error. Without this
		// guard the body is read as an empty string and every caller sees an
		// unrecognisable "invalid response" instead of a failed request.
		if ( is_wp_error( $wp_request ) ) {
			return null;
		}

		$response = wp_remote_retrieve_body( $wp_request );

		$code = wp_remote_retrieve_response_code( $wp_request );

		switch ( $code ) {
			case 200:
			case 201:
				break;
			default:
		}

		return $response;
	}
}
