<?php
/**
 * Payment processor for CHIP for Paymattic.
 *
 * @package CHIPForPaymattic
 */

use WPPayForm\Framework\Support\Arr;
use WPPayForm\App\Models\Transaction;
use WPPayForm\App\Models\Submission;
use WPPayForm\App\Services\ConfirmationHelper;
use WPPayForm\App\Models\Form;
use WPPayForm\App\Services\PlaceholderParser;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles the CHIP payment lifecycle for a Paymattic submission.
 */
class Chip_Paymattic_Processor {

	/**
	 * Single instance of the class.
	 *
	 * @var object|null
	 */
	private static $_instance;
	/**
	 * The currencies supported by CHIP.
	 *
	 * @var array
	 */
	private $supported_currencies = array( 'MYR' );

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
		( new ChipSettings() )->init();

		$this->add_filters();
		$this->add_actions();
	}

	/**
	 * Registers the plugin's filters.
	 *
	 * @return void
	 */
	private function add_filters() {
		add_filter( 'wppayform/choose_payment_method_for_submission', array( $this, 'choose_payment_method' ), 10, 4 );
		add_filter( 'wppayform/entry_transactions_chip', array( $this, 'add_transaction_url' ), 10, 2 );
		add_filter( 'wppayform/submitted_payment_items_chip', array( $this, 'validate_subscription' ), 10, 4 );

		add_filter( 'wppayform_verify_payment_keys_chip', array( $this, 'verify_keys' ), 10, 2 );
	}

	/**
	 * Registers the plugin's actions.
	 *
	 * @return void
	 */
	private function add_actions() {
		add_action( 'wppayform/form_submission_make_payment_chip', array( $this, 'make_form_payment' ), 10, 6 );
		add_action( 'wpf_ipn_endpoint_chip', array( $this, 'callback' ) );
		add_action( 'wppayform_payment_frameless_chip', array( $this, 'redirect' ) );
	}

	/**
	 * Resolves whether CHIP is the method chosen for this submission.
	 *
	 * @param mixed $payment_method The payment method.
	 * @param mixed $elements The elements.
	 * @param mixed $form_id The form id.
	 * @param mixed $form_data The form data.
	 * @return string The chosen payment method, or the incoming value when CHIP was not chosen.
	 */
	public function choose_payment_method( $payment_method, $elements, $form_id, $form_data ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- Paymattic passes all four arguments to its payment-method filter.

		if ( $payment_method ) {
			// Somebody already selected CHIP as their payment method.
			return $payment_method;
		}

		// Analyse the submitted elements and resolve the payment method.

		foreach ( $elements as $element ) {
			if ( ( isset( $element['type'] ) && 'chip_gateway_element' === $element['type'] ) ) {
				return 'chip';
			}
		}

		return $payment_method;
	}

	/**
	 * Starts a CHIP purchase for a Paymattic submission.
	 *
	 * @param mixed $transaction_id The transaction id.
	 * @param mixed $submission_id The submission id.
	 * @param mixed $form_data The form data.
	 * @param mixed $form The form.
	 * @param mixed $has_subscriptions The has subscriptions.
	 */
	public function make_form_payment( $transaction_id, $submission_id, $form_data, $form, $has_subscriptions ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- Paymattic passes all five arguments to its payment action.

		$transaction_model = new Transaction();
		$transaction       = $transaction_model->getTransaction( $transaction_id );
		$submission        = ( new Submission() )->getSubmission( $submission_id );

		$this->is_form_currency_supported( strtoupper( $transaction->currency ) );

		$this->handle_purchase( $transaction, $submission, $form_data, $form );
	}

	/**
	 * Creates the CHIP purchase and redirects the payer to it.
	 *
	 * @param mixed $transaction The transaction.
	 * @param mixed $submission The submission.
	 * @param mixed $form_data The form data.
	 * @param mixed $form The form.
	 * @return void
	 */
	public function handle_purchase( $transaction, $submission, $form_data, $form ) {

		$submission_model = new Submission();
		$entries          = $submission_model->getParsedSubmission( $submission );

		$option = $this->get_settings( $form->ID );

		$metadata = array();
		foreach ( $entries as $label => $entry ) {
			$value = $entry['value'];
			if ( is_string( $value ) && $value ) {
				$metadata[ $entry['type'] ] = $value;
			}
		}

		$success_redirect = add_query_arg(
			array(
				'wppayform_payment' => $submission->id,
				'payment_method'    => 'chip',
				'submission_hash'   => $submission->submission_hash,
				'type'              => 'success',
			),
			site_url( 'index.php' )
		);

		$failure_redirect = add_query_arg(
			array(
				'wppayform_payment' => $submission->id,
				'payment_method'    => 'chip',
				'submission_hash'   => $submission->submission_hash,
				'type'              => 'failed',
			),
			site_url( 'index.php' )
		);

		$success_callback = add_query_arg(
			array(
				'wpf_payment_api_notify' => '1',
				'payment_method'         => 'chip',
				'submission_id'          => $submission->id,
			),
			site_url( 'index.php' )
		);

		$params = array(
			'success_callback' => $success_callback,
			'success_redirect' => $success_redirect,
			'failure_redirect' => $failure_redirect,
			'creator_agent'    => 'Paymattic: ' . PYMTC_CHIP_MODULE_VERSION,
			'reference'        => $transaction->id,
			'platform'         => 'paymattic',
			'due'              => time() + ( absint( $option['due_time'] ) * 60 ),
			'brand_id'         => $option['brand_id'],
			'client'           => array(
				'email'     => Arr::get( $metadata, 'customer_email', '' ),
				'full_name' => substr( Arr::get( $metadata, 'customer_name', '' ), 0, 30 ),
			),
			'purchase'         => array(
				'timezone'   => apply_filters( 'paymattic_chip_purchase_timezone', $this->get_timezone() ),
				'currency'   => strtoupper( $transaction->currency ),
				'due_strict' => $option['due_strict'],
				'products'   => array(
					array(
						'name'  => substr( $form->post_title, 0, 256 ),
						'price' => round( $transaction->payment_total ),
					),
				),
			),
		);

		foreach ( $params['client'] as $key => $value ) {
			if ( empty( $value ) ) {
				unset( $params['client'][ $key ] );
			}
		}

		$params = apply_filters( 'paymattic_chip_purchase_params', $params, $this );

		$chip    = Chip_Paymattic_API::get_instance( $option['secret_key'], $option['brand_id'] );
		$payment = $chip->create_payment( $params );

		if ( ! array_key_exists( 'id', $payment ) ) {

			do_action(
				'wppayform_log_data',
				array(
					'form_id'       => $form->ID,
					'submission_id' => $submission->id,
					'type'          => 'failed',
					'created_by'    => 'CHIP for Paymattic',
					'title'         => __( 'Failure to create purchase', 'chip-for-paymattic' ),
					'content'       => __( 'User is not redirected to CHIP since failure to create purchase.', 'chip-for-paymattic' ),
				)
			);

			wp_send_json_error(
				array(
					/* translators: %s: error code returned by CHIP. */
					'message' => sprintf( __( 'Failed to create purchase: %s', 'chip-for-paymattic' ), $this->error_message( $payment ) ),
				),
				422
			);
		}

		$transaction_model = new Transaction();
		$transaction_model->updateTransaction(
			$transaction->id,
			array(
				'payment_mode' => $payment['is_test'] ? 'test' : 'live',
				'charge_id'    => $payment['id'],
			)
		);

		do_action(
			'wppayform_log_data',
			array(
				'form_id'       => $form->ID,
				'submission_id' => $submission->id,
				'type'          => 'activity',
				'created_by'    => 'CHIP for Paymattic',
				'title'         => __( 'CHIP Payment Redirect', 'chip-for-paymattic' ),
				/* translators: %s: CHIP checkout URL. */
				'content'       => sprintf( __( 'User redirect to CHIP for completing the payment: %s', 'chip-for-paymattic' ), $payment['checkout_url'] ),
			)
		);

		if ( true === (bool) $payment['is_test'] ) {

			do_action(
				'wppayform_log_data',
				array(
					'form_id'       => $form->ID,
					'submission_id' => $submission->id,
					'type'          => 'info',
					'created_by'    => 'CHIP for Paymattic',
					'title'         => __( 'Test mode', 'chip-for-paymattic' ),
					'content'       => __( 'This is test environment where payment status is simulated.', 'chip-for-paymattic' ),
				)
			);
		}

		wp_send_json_success(
			array(
				'message'          => __( 'You are redirecting to CHIP to complete the purchase. Please wait while you are redirecting....', 'chip-for-paymattic' ),
				'call_next_method' => 'normalRedirect',
				'redirect_url'     => Arr::get( $payment, 'checkout_url' ),
			),
			200
		);
	}

	/**
	 * Resolves the CHIP settings for a form, falling back to the global ones.
	 *
	 * @param mixed $form_id The form id.
	 * @return array
	 */
	private function get_settings( $form_id ) {

		$options  = get_option( PYMTC_CHIP_FSLUG );
		$postfix  = '';
		$form_cid = 'form-customize-' . $form_id;

		if ( array_key_exists( $form_cid, $options ) && $options[ $form_cid ] ) {
			$postfix = "-$form_id";
		}

		return array(
			'secret_key' => $options[ 'secret-key' . $postfix ],
			'brand_id'   => $options[ 'brand-id' . $postfix ],
			'due_strict' => empty( $options[ 'due-strict' . $postfix ] ) ? false : $options[ 'due-strict' . $postfix ],
			'due_time'   => $options[ 'due-strict-timing' . $postfix ],
		);
	}

	/**
	 * Rejects a submission whose form currency CHIP does not support.
	 *
	 * @param string $currency The currency code.
	 */
	private function is_form_currency_supported( $currency ) {

		if ( ! in_array( $currency, $this->supported_currencies, true ) ) {
			/* translators: %s: the currency code configured on the form. */
			echo esc_html( sprintf( __( 'Error! Currency not supported. The only supported currency is MYR and the current currency is %s.', 'chip-for-paymattic' ), $currency ) );
			exit( 200 );
		}
	}

	/**
	 * Returns the site timezone offset in the format CHIP expects.
	 *
	 * @return string
	 */
	private function get_timezone() {
		if ( preg_match( '/^[A-z]+\/[A-z\_\/\-]+$/', wp_timezone_string() ) ) {
			return wp_timezone_string();
		}

		return 'UTC';
	}

	/**
	 * Adds the CHIP purchase link to the transaction row.
	 *
	 * @param mixed $transactions The transactions.
	 * @param mixed $submission_id The submission id.
	 * @return array The filtered transactions.
	 */
	public function add_transaction_url( $transactions, $submission_id ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- Paymattic passes both arguments to its transaction filter.

		$url = PYMTC_CHIP_ROOT_URL . 'p/';

		foreach ( $transactions as $transaction ) {
			if ( $transaction->charge_id ) {
				$transaction->transaction_url = $url . $transaction->charge_id . '/';
			}

			if ( 'paid' === $transaction->status ) {
				$transaction->transaction_url .= 'receipt/';
			} else {
				$transaction->transaction_url .= 'invoice/';
			}
		}
		return $transactions;
	}

	/**
	 * Validates the subscription items for a CHIP purchase.
	 *
	 * @param mixed $payment_items The payment items.
	 * @param mixed $formatted_elements The formatted elements.
	 * @param mixed $form_data The form data.
	 * @param mixed $subscription_items The subscription items.
	 */
	public function validate_subscription( $payment_items, $formatted_elements, $form_data, $subscription_items ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- Paymattic passes all four arguments to its subscription validation filter.
		wp_send_json_error(
			array(
				'message'       => __( 'CHIP doesn\'t support subscriptions right now', 'chip-for-paymattic' ),
				'payment_error' => true,
			),
			423
		);
	}

	/**
	 * Sends the payer to the CHIP checkout.
	 *
	 * @param mixed $data The data.
	 * @return void
	 */
	public function redirect( $data ) {

		$submission_id = absint( $data['wppayform_payment'] );

		if ( 'chip' !== $data['payment_method'] ) {
			return;
		}

		$submission  = ( new Submission() )->getSubmission( $submission_id );
		$transaction = $this->getTransaction( $submission_id );

		if ( ! $transaction || ! $submission ) {
			return;
		}

		$option  = $this->get_settings( $submission->form_id );
		$chip    = Chip_Paymattic_API::get_instance( $option['secret_key'], '' );
		$payment = $chip->get_payment( $transaction['charge_id'] );

		$GLOBALS['wpdb']->get_results(
			"SELECT GET_LOCK('pymtc_chip_payment_$submission_id', 15);"
		);

		$transaction = $this->getTransaction( $submission_id );

		if ( (int) $transaction->id !== (int) $payment['reference'] ) {
			return;
		}

		if ( 'paid' !== $transaction->status && 'paid' === $payment['status'] ) {
			$this->handlePaid( $submission, $transaction, $payment );
		}

		if ( 'failed' !== $transaction->status && 'paid' !== $payment['status'] ) {
			$this->handleFailed( $submission, $transaction, $payment );
		}

		$GLOBALS['wpdb']->get_results(
			"SELECT RELEASE_LOCK('pymtc_chip_payment_$submission_id');"
		);

		$redirect_url = $this->getSuccessURL( Form::getForm( $transaction->form_id ), $submission );

		// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- the payer is sent to CHIP's external checkout host, which wp_safe_redirect() would block.
		wp_redirect( $redirect_url );
		exit;
	}

	/**
	 * Fetches a Paymattic transaction record.
	 *
	 * @param mixed $value The value.
	 * @param mixed $key The key.
	 * @return array
	 */
	private function getTransaction( $value, $key = 'submission_id' ) {
		$transactionModel = new Transaction();

		$transaction = $transactionModel
		->where( $key, $value )
		->first();

		return $transaction;
	}

	/**
	 * Marks a submission paid from a verified CHIP response.
	 *
	 * @param mixed $submission The submission.
	 * @param mixed $transaction The transaction.
	 * @param mixed $vendorTransaction The vendorTransaction.
	 * @return void
	 */
	private function handlePaid( $submission, $transaction, $vendorTransaction ) {

		if ( ! $transaction || 'chip' !== $transaction->payment_method ) {
			return;
		}

		// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Paymattic's own hook name.
		do_action( 'wppayform/form_submission_activity_start', $transaction->form_id );

		$status = sanitize_text_field( $vendorTransaction['status'] );

		$updateData = array(
			'payment_note'  => maybe_serialize( $vendorTransaction ),
			'charge_id'     => sanitize_text_field( $vendorTransaction['id'] ),
			'payment_total' => intval( $vendorTransaction['purchase']['total'] ),
			'updated_at'    => current_time( 'Y-m-d H:i:s' ),
			'status'        => 'paid',
		);

		$transactionModel = new Transaction();
		$transactionModel->updateTransaction( $transaction->id, $updateData );

		$submissionModel = new Submission();
		$submissionData  = array(
			'payment_status' => $status,
			'updated_at'     => current_time( 'Y-m-d H:i:s' ),
		);

		$submissionModel->where( 'id', $submission->id )->update( $submissionData );

		$transaction = $transactionModel->getTransaction( $transaction->id );

		do_action(
			'wppayform_log_data',
			array(
				'form_id'       => $transaction->form_id,
				'submission_id' => $transaction->submission_id,
				'type'          => 'info',
				'created_by'    => 'CHIP for Paymattic Plugin',
				/* translators: %s: CHIP purchase id. */
				'content'       => sprintf( __( 'Transaction Marked as paid and CHIP Transaction ID: %s', 'chip-for-paymattic' ), $updateData['charge_id'] ),
			)
		);

		// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Paymattic's own hook name.
		do_action( 'wppayform/form_payment_success_chip', $submission, $transaction, $transaction->form_id, $updateData );
		// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Paymattic's own hook name.
		do_action( 'wppayform/form_payment_success', $submission, $transaction, $transaction->form_id, $updateData );
	}

	/**
	 * Marks a submission failed from a CHIP response.
	 *
	 * @param mixed $submission The submission.
	 * @param mixed $transaction The transaction.
	 * @param mixed $vendorTransaction The vendorTransaction.
	 * @return void
	 */
	private function handleFailed( $submission, $transaction, $vendorTransaction ) {

		if ( ! $transaction || 'chip' !== $transaction->payment_method ) {
			return;
		}

		$status = 'failed';

		$updateData = array(
			'payment_note' => maybe_serialize( $vendorTransaction ),
			'updated_at'   => current_time( 'Y-m-d H:i:s' ),
			'status'       => $status,
		);

		$transactionModel = new Transaction();
		$transactionModel->updateTransaction( $transaction->id, $updateData );

		$submissionModel = new Submission();
		$submissionData  = array(
			'payment_status' => $status,
			'updated_at'     => current_time( 'Y-m-d H:i:s' ),
		);

		$submissionModel->where( 'id', $submission->id )->update( $submissionData );

		do_action(
			'wppayform_log_data',
			array(
				'form_id'       => $transaction->form_id,
				'submission_id' => $transaction->submission_id,
				'type'          => 'info',
				'created_by'    => 'CHIP for Paymattic Plugin',
				/* translators: %s: CHIP purchase id. */
				'content'       => sprintf( __( 'Transaction Marked as failed and CHIP Transaction ID: %s', 'chip-for-paymattic' ), $vendorTransaction['id'] ),
			)
		);
	}

	/**
	 * Extracts a human-readable error message from a CHIP API response.
	 *
	 * The full response is deliberately not shown to the payer: it can contain
	 * request metadata. The raw response is still available in the activity log.
	 *
	 * @param mixed $response The decoded CHIP API response.
	 * @return string The message to show.
	 */
	private function error_message( $response ) {
		if ( is_array( $response ) && isset( $response['error'] ) && is_array( $response['error'] ) ) {
			$error = $response['error'];
			$parts = array();

			if ( ! empty( $error['code'] ) ) {
				$parts[] = $error['code'];
			}
			if ( ! empty( $error['message'] ) ) {
				$parts[] = $error['message'];
			}
			if ( $parts ) {
				return implode( ': ', $parts );
			}
		}

		return __( 'the payment request was rejected by CHIP.', 'chip-for-paymattic' );
	}

	/**
	 * Builds the URL the payer returns to after payment.
	 *
	 * @param mixed $form The form.
	 * @param mixed $submission The submission.
	 * @return string
	 */
	private function getSuccessURL( $form, $submission ) {
		// Check whether the form settings define a success URL.
		$confirmation = Form::getConfirmationSettings( $form->ID );
		$confirmation = ConfirmationHelper::parseConfirmation( $confirmation, $submission );
		if (
			( 'customUrl' === $confirmation['redirectTo'] && $confirmation['customUrl'] ) ||
			( 'customPage' === $confirmation['redirectTo'] && $confirmation['customPage'] )
		) {
			if ( 'customUrl' === $confirmation['redirectTo'] ) {
				$url = $confirmation['customUrl'];
			} else {
				$url = get_permalink( intval( $confirmation['customPage'] ) );
			}
			$url = add_query_arg(
				array(
					'payment_method' => 'chip',
				),
				$url
			);
			return PlaceholderParser::parse( $url, $submission );
		}
		// Fall back to the global success page.
		$globalSettings = get_option( 'wppayform_confirmation_pages' );

		if ( isset( $globalSettings['confirmation'] ) && $globalSettings['confirmation'] ) {
			return add_query_arg(
				array(
					'wpf_submission' => $submission->submission_hash,
					'payment_method' => 'chip',
				),
				get_permalink( intval( $globalSettings['confirmation'] ) )
			);
		}
		// Fall back to the default confirmation behaviour.
		return add_query_arg(
			array(
				'wpf_submission' => $submission->submission_hash,
				'payment_method' => 'chip',
			),
			home_url()
		);
	}

	/**
	 * Handles the CHIP callback that reports the final payment status.
	 *
	 * @return void
	 */
	public function callback() {

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- CHIP calls this endpoint directly, so there is no nonce to verify; the payment is confirmed against the CHIP API in success_callback().
		$payment_method = isset( $_GET['payment_method'] ) ? sanitize_key( wp_unslash( $_GET['payment_method'] ) ) : '';

		if ( 'chip' !== $payment_method ) {
			return;
		}

		if ( isset( $_GET['submission_id'] ) ) {
			$this->success_callback( absint( $_GET['submission_id'] ) );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Finalises a successful payment.
	 *
	 * @param mixed $submission_id The submission id.
	 * @return void
	 */
	private function success_callback( $submission_id ) {

		$submission  = ( new Submission() )->getSubmission( $submission_id );
		$option      = $this->get_settings( $submission->form_id );
		$transaction = $this->getTransaction( $submission_id );

		if ( ! $transaction || ! $submission ) {
			return;
		}

		$chip    = Chip_Paymattic_API::get_instance( $option['secret_key'], '' );
		$payment = $chip->get_payment( $transaction['charge_id'] );

		$GLOBALS['wpdb']->get_results(
			"SELECT GET_LOCK('pymtc_chip_payment_$submission_id', 15);"
		);

		$transaction = $this->getTransaction( $submission_id );

		if ( (int) $transaction->id !== (int) $payment['reference'] ) {
			return;
		}

		if ( 'paid' !== $transaction->status && 'paid' === $payment['status'] ) {
			$this->handlePaid( $submission, $transaction, $payment );
		}

		$GLOBALS['wpdb']->get_results(
			"SELECT RELEASE_LOCK('pymtc_chip_payment_$submission_id');"
		);
	}
}

Chip_Paymattic_Processor::get_instance();
