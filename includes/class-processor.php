<?php

use WPPayForm\Framework\Support\Arr;
use WPPayForm\App\Models\Transaction;
use WPPayForm\App\Models\Submission;
use WPPayForm\App\Services\ConfirmationHelper;
use WPPayForm\App\Models\Form;
use WPPayForm\App\Services\PlaceholderParser;

if (!defined('ABSPATH')) {
  exit;
}

class Chip_Paymattic_Processor {

  private static $_instance;

  public static function get_instance() {
    if ( self::$_instance == null ) {
      self::$_instance = new self();
    }

    return self::$_instance;
  }

  public function __construct() {
    $this->add_filters();
    $this->add_actions();
  }

  private function add_filters() {
    add_filter( 'wppayform/choose_payment_method_for_submission', array( $this, 'choose_payment_method' ), 10, 4 );
    add_filter( 'wppayform/entry_transactions_chip', array( $this, 'add_transaction_url' ), 10, 2 );
    add_filter( 'wppayform/submitted_payment_items_chip', array( $this, 'validate_subscription' ), 10, 4 );

    add_filter( 'wppayform_verify_payment_keys_chip', array( $this, 'verify_keys' ), 10, 2 );
  }

  private function add_actions() {
    add_action( 'wppayform/form_submission_make_payment_chip', array( $this, 'make_form_payment' ), 10, 6 );
    add_action( 'wpf_ipn_endpoint_chip', array( $this, 'callback' ) );
    add_action( 'wppayform_payment_frameless_chip', array( $this, 'redirect' ) );
  }

  public function choose_payment_method( $payment_method, $elements, $form_id, $form_data ) {

    if ( $payment_method ) {
      // Already someone choose that it's their payment method
      return $payment_method;
    }
  
    // Now We have to analyze the elements and return our payment method
  
    foreach ( $elements as $element ) {
      if ( ( isset($element['type']) && $element['type'] == 'chip_gateway_element' ) ) {
        return 'chip';
      }
    }

    return $payment_method;
  }

  public function make_form_payment( $transaction_id, $submission_id, $form_data, $form, $has_subscriptions) {

    $transaction_model = new Transaction();
    $transaction       = $transaction_model->getTransaction( $transaction_id );
    $submission        = (new Submission())->getSubmission( $submission_id );

    $this->handle_purchase( $transaction, $submission, $form_data, $form );
  }

  public function handle_purchase( $transaction, $submission, $form_data, $form ) {

    $submission_model = new Submission();
    $entries         = $submission_model->getParsedSubmission( $submission );

    $metadata = [];
    foreach ( $entries as $label => $entry ) {
      $value = $entry['value'];
      if ( is_string( $value ) && $value ) {
        $metadata[$entry['type']] = $value;
      }
    }

    $success_redirect = add_query_arg(array(
      'wppayform_payment' => $submission->id,
      'payment_method'    => 'chip',
      'submission_hash'   => $submission->submission_hash,
      'type'              => 'success'
    ), site_url('index.php'));

    $failure_redirect = add_query_arg(array(
      'wppayform_payment' => $submission->id,
      'payment_method'    => 'chip',
      'submission_hash'   => $submission->submission_hash,
      'type'              => 'failed'
    ), site_url('index.php'));

    $success_callback =  add_query_arg([
      'wpf_payment_api_notify' => '1',
      'payment_method'         => 'chip',
      'submission_id'          => $submission->id
    ], site_url('index.php'));

    $params = array(
      'success_callback' => $success_callback,
      'success_redirect' => $success_redirect,
      'failure_redirect' => $failure_redirect,
      'creator_agent'    => 'ChipPaymattic: ' . PYMTC_CHIP_MODULE_VERSION,
      'reference'        => $transaction->id,
      'platform'         => 'paymattic',
      'send_receipt'     => true,
      'due'              => time() + ( absint( 60 )  * 60 ),
      'brand_id'         => 'brand-id',
      'client'           => [
        'email'     => Arr::get( $metadata, 'customer_email', '' ),
        'full_name' => substr( Arr::get( $metadata, 'customer_name', '' ), 0, 30),
      ],
      'purchase'         => array(
        'timezone'   => apply_filters( 'paymattic_chip_purchase_timezone', $this->get_timezone() ),
        'currency'   => strtoupper($transaction->currency),
        'due_strict' => true,
        'products'   => array([
          'name'     => substr( $form->post_title, 0, 256 ),
          'price'    => round( $transaction->payment_total ),
        ]),
      ),
    );

    $chip    = Chip_Paymattic_API::get_instance('secret-key', 'brand-id');
    $payment = $chip->create_payment( $params );

    $transaction_model = new Transaction();
    $transaction_model->updateTransaction( $transaction->id, array(
      'payment_mode' => $payment['is_test'] ? 'test' : 'live',
      'charge_id'    => $payment['id'],
    ));

    if ( !array_key_exists( 'id', $payment ) ) {
      wp_send_json_error( array(
        'message' => sprintf( __( 'Failed to create purchase: %s', 'chip-for-paymattic' ), print_r($payment, true) ),
      ), 422);
    }

    do_action('wppayform_log_data', [
      'form_id'       => $form->ID,
      'submission_id' => $submission->id,
      'type'          => 'activity',
      'created_by'    => 'CHIP for Paymattic',
      'title'         => __( 'CHIP Payment Redirect', 'chip-for-paymattic' ),
      'content'       => sprintf( __( 'User redirect to CHIP for completing the payment: %s', 'chip-for-paymattic' ), $payment['checkout_url'] ),
    ]);

    wp_send_json_success([
      'message'          => __( 'You are redirecting to CHIP to complete the purchase. Please wait while you are redirecting....', 'chip-for-paymattic' ),
      'call_next_method' => 'normalRedirect',
      'redirect_url'     => Arr::get( $payment, 'checkout_url' )
    ], 200);
  }

  private function get_timezone() {
    if ( preg_match( '/^[A-z]+\/[A-z\_\/\-]+$/', wp_timezone_string() ) ) {
      return wp_timezone_string();
    }

    return 'UTC';
  }

  public function add_transaction_url( $transactions, $submission_id ) {

    $url = 'https://gate.chip-in.asia/p/';

    foreach ( $transactions as $transaction ) {
      if ( $transaction->charge_id ) {
        $transaction->transaction_url =  $url . $transaction->charge_id . '/';
      }
    }
    return $transactions;
  }

  public function validate_subscription( $payment_items, $formatted_elements, $form_data, $subscription_items ) {
    wp_send_json_error( array(
      'message'       => __( 'CHIP doesn\'t support subscriptions right now', 'chip-for-paymattic' ),
      'payment_error' => true
    ), 423 );
  }

  public function redirect( $data ) {

    $submission_id = absint( $data['wppayform_payment'] );

    if ( $data['payment_method'] != 'chip' ) {
      return;
    }

    $submission = (new Submission())->getSubmission( $submission_id );
    $transaction = $this->getTransaction( $submission_id );

    if (!$transaction || !$submission) {
      return;
    }

    $chip    = Chip_Paymattic_API::get_instance( 'secret-key', '' );
    $payment = $chip->get_payment( $transaction['charge_id'] );

    $GLOBALS['wpdb']->get_results(
      "SELECT GET_LOCK('pymtc_chip_payment_$submission_id', 15);"
    );

    $transaction = $this->getTransaction( $submission_id );

    if ( $transaction->id != $payment['reference'] ) {
      return;
    }

    if ( $transaction->status != 'paid' && $payment['status'] == 'paid') {
      $this->handlePaid( $submission, $transaction, $payment );
    }

    if ( $transaction->status != 'failed' && $payment['status'] != 'paid') {
      $this->handleFailed( $submission, $transaction, $payment );
    }

    $GLOBALS['wpdb']->get_results(
      "SELECT RELEASE_LOCK('pymtc_chip_payment_$submission_id');"
    );

    $redirect_url = $this->getSuccessURL( Form::getForm( $transaction->form_id ), $submission );

    wp_redirect( $redirect_url );
    exit;
  }

  private function getTransaction( $value, $key = 'submission_id' ) {
    $transactionModel = new Transaction();

    $transaction = $transactionModel
      ->where( $key, $value )
      ->first();

    return $transaction;
  }

  private function handlePaid( $submission, $transaction, $vendorTransaction ) {
    
    if ( !$transaction || $transaction->payment_method != 'chip' ) {
      return;
    }

    do_action('wppayform/form_submission_activity_start', $transaction->form_id);

    $status = sanitize_text_field( $vendorTransaction['status'] );

    $updateData = [
      'payment_note'  => maybe_serialize($vendorTransaction),
      'charge_id'     => sanitize_text_field($vendorTransaction['id']),
      'payment_total' => intval($vendorTransaction['purchase']['total']),
      'updated_at'    => current_time('Y-m-d H:i:s'),
      'status'        => 'paid',
    ];

    $transactionModel = new Transaction();
    $transactionModel->updateTransaction( $transaction->id, $updateData );

    $submissionModel = new Submission();
    $submissionData  = array(
      'payment_status' => $status,
      'updated_at'     => current_time('Y-m-d H:i:s')
    );

    $submissionModel->where( 'id', $submission->id )->update( $submissionData );

    $transaction = $transactionModel->getTransaction( $transaction->id );

    do_action('wppayform_log_data', [
      'form_id' => $transaction->form_id,
      'submission_id' => $transaction->submission_id,
      'type' => 'info',
      'created_by' => 'CHIP for Paymattic Plugin',
      'content' => sprintf( __( 'Transaction Marked as paid and CHIP Transaction ID: %s', 'chip-for-paymattic' ), $updateData['charge_id'] ),
    ]);

    do_action('wppayform/form_payment_success_chip', $submission, $transaction, $transaction->form_id, $updateData);
    do_action('wppayform/form_payment_success', $submission, $transaction, $transaction->form_id, $updateData);
  }

  private function handleFailed( $submission, $transaction, $vendorTransaction ) {
    
    if ( !$transaction || $transaction->payment_method != 'chip' ) {
      return;
    }

    $status = 'failed';

    $updateData = [
      'payment_note'  => maybe_serialize($vendorTransaction),
      'updated_at'    => current_time('Y-m-d H:i:s'),
      'status'        => $status,
    ];

    $transactionModel = new Transaction();
    $transactionModel->updateTransaction( $transaction->id, $updateData );

    $submissionModel = new Submission();
    $submissionData  = array(
      'payment_status' => $status,
      'updated_at'     => current_time('Y-m-d H:i:s')
    );

    $submissionModel->where( 'id', $submission->id )->update( $submissionData );
  }

  private function getSuccessURL($form, $submission)
  {
      // Check If the form settings have success URL
      $confirmation = Form::getConfirmationSettings($form->ID);
      $confirmation = ConfirmationHelper::parseConfirmation($confirmation, $submission);
      if (
          ($confirmation['redirectTo'] == 'customUrl' && $confirmation['customUrl']) ||
          ($confirmation['redirectTo'] == 'customPage' && $confirmation['customPage'])
      ) {
          if ($confirmation['redirectTo'] == 'customUrl') {
              $url = $confirmation['customUrl'];
          } else {
              $url = get_permalink(intval($confirmation['customPage']));
          }
          $url = add_query_arg(array(
              'payment_method' => 'chip'
          ), $url);
          return PlaceholderParser::parse($url, $submission);
      }
      // now we have to check for global Success Page
      $globalSettings = get_option('wppayform_confirmation_pages');
      if (isset($globalSettings['confirmation']) && $globalSettings['confirmation']) {
          return add_query_arg(array(
              'wpf_submission' => $submission->submission_hash,
              'payment_method' => 'chip'
          ), get_permalink(intval($globalSettings['confirmation'])));
      }
      // In case we don't have global settings
      return add_query_arg(array(
          'wpf_submission' => $submission->submission_hash,
          'payment_method' => 'chip'
      ), home_url());
  }

  public function callback() {

    if ( !isset($_GET['payment_method']) OR $_GET['payment_method'] != 'chip' ) {
      return;
    }

    if ( isset( $_GET['submission_id'] ) ){

      $this->success_callback( absint( $_GET['submission_id'] ) );
    } else {
      // TODO: Add refund callback
    }

  }

  private function success_callback( $submission_id ) {

    $submission = (new Submission())->getSubmission( $submission_id );
    $transaction = $this->getTransaction( $submission_id );

    if (!$transaction || !$submission) {
      return;
    }

    $chip    = Chip_Paymattic_API::get_instance( 'secret-key', '' );
    $payment = $chip->get_payment( $transaction['charge_id'] );

    $GLOBALS['wpdb']->get_results(
      "SELECT GET_LOCK('pymtc_chip_payment_$submission_id', 15);"
    );

    $transaction = $this->getTransaction( $submission_id );

    if ( $transaction->id != $payment['reference'] ) {
      return;
    }

    if ( $transaction->status != 'paid' && $payment['status'] == 'paid') {
      $this->handlePaid( $submission, $transaction, $payment );
    }

    $GLOBALS['wpdb']->get_results(
      "SELECT RELEASE_LOCK('pymtc_chip_payment_$submission_id');"
    );
  }
}

Chip_Paymattic_Processor::get_instance();