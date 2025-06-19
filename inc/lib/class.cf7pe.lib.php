<?php
/**
 * CF7PE_Lib Class
 *
 * Handles the Library functionality.
 *
 * @package WordPress
 * @subpackage Accept PayPal Payments using Contact Form 7
 * @since 3.5
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

use PayPal\Rest\ApiContext;
use PayPal\Auth\OAuthTokenCredential;
use PayPal\Api\Amount;
use PayPal\Api\Details;
use PayPal\Api\Payer;
use PayPal\Api\Item;
use PayPal\Api\ItemList;
use PayPal\Api\Payment;
use PayPal\Api\PaymentExecution;
use PayPal\Api\RedirectUrls;
use PayPal\Api\Transaction;
use PayPal\Api\ExecutePayment;
use PayPal\Exception\PayPalConnectionException;
use PayPal\Api\Capture;
use PayPal\Api\Refund;
use PayPal\Api\RefundRequest;
use PayPal\Api\Sale;
use PayPal\Api\PaymentDefinition;
use PayPal\Api\Currency;
use PayPal\Api\Plan;
use PayPal\Api\Agreement;
use PayPal\Api\AgreementStateDescriptor;
use PayPal\Api\ShippingAddress;
use PayPal\Api\PayerInfo;
use PayPal\Api\MerchantPreferences;
use PayPal\Api\Patch;
use PayPal\Api\PatchRequest;
use PayPal\Common\PayPalModel;

if ( !class_exists( 'CF7PE_Lib' ) ) {

	class CF7PE_Lib {

		var $data_fields = array(
			'_form_id'              => 'Form ID/Name',
			'_email'                => 'Email Address',
			'_transaction_id'       => 'Transaction ID',
			'_invoice_no'           => 'Invoice ID',
			'_amount'               => 'Amount',
			'_quantity'             => 'Quantity',
			'_total'                => 'Total',
			'_submit_time'          => 'Submit Time',
			'_request_Ip'           => 'Request IP',
			'_currency'             => 'Currency code',
			'_form_data'            => 'Form data',
			'_transaction_response' => 'Transaction response',
			'_transaction_status'   => 'Transaction status',
			'_paymen_type'   => 'Payment type',
			'_refund_payment'   => 'Refund Payment',
		);
		
		var $context = '';

		function __construct() {

			add_action( 'init', array( $this, 'action__init' ) );

			add_action( 'wpcf7_before_send_mail', array( $this, 'action__wpcf7_before_send_mail' ), 20, 3 );

			add_action( CF7PE_PREFIX . '/paypal/save/data', array( $this, 'action__cf7pe_paypal_save_data' ), 10, 4 );

			add_filter( 'wpcf7_ajax_json_echo',   array( $this, 'filter__wpcf7_ajax_json_echo'   ), 20, 2 );
			add_action( 'wpcf7_init', array( $this, 'action__wpcf7_verify_version' ), 10, 0 );
			
			// Refund payment functionality
			add_action('wp_ajax_action__refund_payment_free' ,array( $this, 'action__refund_payment_free'));
			add_action('wp_ajax_nopriv_action__refund_payment_free', array( $this,'action__refund_payment_free')) ;
		}

		/*
		   ###     ######  ######## ####  #######  ##    ##  ######
		  ## ##   ##    ##    ##     ##  ##     ## ###   ## ##    ##
		 ##   ##  ##          ##     ##  ##     ## ####  ## ##
		##     ## ##          ##     ##  ##     ## ## ## ##  ######
		######### ##          ##     ##  ##     ## ##  ####       ##
		##     ## ##    ##    ##     ##  ##     ## ##   ### ##    ##
		##     ##  ######     ##    ####  #######  ##    ##  ######
		*/
		/**
		 * Action: init
		 *
		 * - Fire the email when return back from the paypal.
		 *
		 * @method action__cf7pe_paypal_save_data
		 *
		 */
		function action__cf7pe_paypal_save_data( $from_data, $token, $payment, $invoiceNumber ) {
			$cf7pap_post_id = '';
			
			if ( empty( $from_data ) )
				return;

			add_filter( CF7PE_PREFIX . '/paypal/form/data', function( $frm_data, $mail ) use ( $from_data, $token, $payment ) {

				if ( empty( $payment ) ) {
					return $frm_data;
				}

				if ( strpos( $mail['body'], '[paypal-pro' ) === false ) {
					return $frm_data;
				}

				$data = array();
				$data[] = 'Transaction ID: ' . ( !empty( $payment ) ? $payment->getId() : '' );
				$data[] = 'Transaction Status: ' . ( !empty( $payment ) ?  $payment->getState() : 'cancel' );

				if ( !empty( $mail['use_html'] ) ) {
					$frm_data['paypal-pro'] = implode( '<br/>', $data );
				} else {
					$frm_data['paypal-pro'] = implode( "\n", $data );
				}
				

				return $frm_data;
			}, 10, 5 );
			
			// if (isset($from_data->contact_form) && $from_data->contact_form instanceof WPCF7_ContactForm) {
			// 	$form_ID = $from_data->contact_form->id();
			// }
			// //print_r($from_data);die();
			// $email  = get_post_meta( $form_ID, CF7PE_META_PREFIX . 'email', true );
			$email = !empty($email) && array_key_exists($email, $from_data) ? $from_data[$email] : '';
			global $wpdb;

			if ( $token ) {

				$cf7pap_query = $wpdb->prepare(
					'SELECT ID FROM ' . $wpdb->posts . '
					WHERE post_title = %s
					AND post_type = \'cf7pe_data\'',
					( !empty( $token ) ? $token : '' )
				);
				
				$wpdb->query( $cf7pap_query );

				if ( !$wpdb->num_rows ) {

					$cf7pap_post_id = wp_insert_post( array (
						'post_type' => 'cf7pe_data',
						'post_title' => ( !empty( $email ) ? $email : $token), // email/invoice_no
						'post_status' => 'publish',
						'comment_status' => 'closed',
						'ping_status' => 'closed',
					) );
				}

			}
			$exceed_ct	= sanitize_text_field( substr( get_option( '_exceed_cfpezw_l' ), 6 ) );
			if ( !empty( $cf7pap_post_id ) ) {

				$stored_data = ( !empty( $from_data ) ? $from_data->get_posted_data() : array() );
				$form_ID       = ( !empty( $from_data ) ? $from_data->get_contact_form()->id() : '' );

				$attachent = '';

				if ( !empty( $from_data->uploaded_files() ) && !empty( $invoiceNumber ) ) {
					$attachent = get_transient( CF7PE_META_PREFIX . 'form_attachment_' . $invoiceNumber );
				}

				$currency    = get_post_meta( $form_ID, CF7PE_META_PREFIX . 'currency', true );
				$amount      = get_post_meta( $form_ID, CF7PE_META_PREFIX . 'amount', true );
				$quantity    = get_post_meta( $form_ID, CF7PE_META_PREFIX . 'quantity', true );
				$amount_val  = ( ( !empty( $amount ) && array_key_exists( $amount, $stored_data ) ) ? floatval( $stored_data[$amount] ) : '0' );
				$quanity_val = ( ( !empty( $quantity ) && array_key_exists( $quantity, $stored_data ) ) ? floatval( $stored_data[$quantity] ) : '' );
				
				// recurring data get
				$amountPayable = (float) ( empty( $quanity_val ) ? $amount_val : ( $quanity_val * $amount_val ) );

				if (
					!empty( $amount )
					&& array_key_exists( $amount, $stored_data )
					&& is_array( $stored_data[$amount] )
					&& !empty( $stored_data[$amount] )
				) {
					$val = 0;
					foreach ( $stored_data[$amount] as $k => $value ) {
						$val = $val + floatval($value);
					}
					$amount_val = $val;
				}

				if (
					!empty( $quantity )
					&& array_key_exists( $quantity, $stored_data )
					&& is_array( $stored_data[$quantity] )
					&& !empty( $stored_data[$quantity] )
				) {
					$qty_val = 0;
					foreach ( $stored_data[$quantity] as $k => $qty ) {
						$qty_val = $qty_val + floatval($qty);
					}
					$quanity_val = $qty_val;
				}


				if(!get_option('_exceed_cfpezw')){
					sanitize_text_field( add_option('_exceed_cfpezw', '1') );
				}else{
					$exceed_val = sanitize_text_field( get_option( '_exceed_cfpezw' ) ) + 1;
					update_option( '_exceed_cfpezw', $exceed_val );
				}

				if ( !empty( sanitize_text_field( get_option( '_exceed_cfpezw' ) ) ) && sanitize_text_field( get_option( '_exceed_cfpezw' ) ) > $exceed_ct ) {
					$stored_data['_exceed_num_cfpezw'] = '1';
				}

				if(!get_option('_exceed_cfpezw_l')){
					add_option('_exceed_cfpezw_l', 'cfpezw10');
				}
				add_post_meta( $cf7pap_post_id, '_email', $email );
				add_post_meta( $cf7pap_post_id, '_form_id', $form_ID );
				add_post_meta( $cf7pap_post_id, '_payer_email', ( !empty( $payment ) ? $payment->getPayer()->getPayerInfo()->getEmail() : '' ) );
				add_post_meta( $cf7pap_post_id, '_transaction_id', ( !empty( $payment ) ? $payment->getId() : '' ));
				add_post_meta( $cf7pap_post_id, '_amount', $amount_val );
				add_post_meta( $cf7pap_post_id, '_quantity', $quanity_val );
				add_post_meta( $cf7pap_post_id, '_request_Ip', $this->getUserIpAddr() );
				add_post_meta( $cf7pap_post_id, '_currency', $currency );
				add_post_meta( $cf7pap_post_id, '_form_data', serialize($stored_data) );
				add_post_meta( $cf7pap_post_id, '_attachment', $attachent );
				add_post_meta( $cf7pap_post_id, '_transaction_response', ( !empty( $payment ) ? json_decode($payment) : '' ) );
				add_post_meta( $cf7pap_post_id, '_invoice_no', ( !empty( $payment ) ? $payment->transactions[0]->invoice_number : '' ) );
				add_post_meta( $cf7pap_post_id, '_total', ( !empty( $payment ) ? $payment->transactions[0]->amount->total : $amountPayable ) );
				add_post_meta( $cf7pap_post_id, '_transaction_status', ( !empty( $payment ) ?  $payment->getState() : 'cancel' ) );
				
				
			}

			set_transient( CF7PE_META_PREFIX . 'post_entry_id' . $invoiceNumber, $cf7pap_post_id, (60 * 60 * 24) );

		}

		/**
		 * - Refund payment
		 */
		function action__refund_payment_free() {

			$enable_log = trim( get_option( '' . CF7PE_META_PREFIX . 'enable_log' ) );
			$contact_form_id = '';
			$entry_id = '';
			$transaction_id = '';
			if( isset($_POST['contact_form_id']) && !empty($_POST['contact_form_id']) ) { //phpcs:ignore
				$contact_form_id = $_POST['contact_form_id']; //phpcs:ignore
			}
			if( isset( $_POST['entry_id']) && !empty( $_POST['entry_id']) ) { //phpcs:ignore
				$entry_id = $_POST['entry_id']; //phpcs:ignore
			}
			if( isset($_POST['transaction_id']) && !empty($_POST['transaction_id']) ) { //phpcs:ignore
				$transaction_id = $_POST['transaction_id']; //phpcs:ignore
			}
			$mode_sandbox = trim( get_post_meta( $contact_form_id, CF7PE_META_PREFIX . 'mode_sandbox', true ) );
			$sandbox_client_id = get_post_meta( $contact_form_id, CF7PE_META_PREFIX . 'sandbox_client_id', true );
			$sandbox_client_secret = get_post_meta( $contact_form_id, CF7PE_META_PREFIX . 'sandbox_client_secret', true );
			$live_client_id  = get_post_meta( $contact_form_id, CF7PE_META_PREFIX . 'live_client_id', true );
			$live_client_secret = get_post_meta( $contact_form_id, CF7PE_META_PREFIX . 'live_client_secret', true );
			
			if( $mode_sandbox ){
				$client_id = $sandbox_client_id;
				$client_secret = $sandbox_client_secret;
				$curl_token_url = CF7PE_SANDBOX_TKL;
				$curl_sale_url = CF7PE_SANDBOX_PMT;
				$curl_refund_url = CF7PE_SANDBOX_SALE;
			}else{
				$client_id = $live_client_id;
				$client_secret = $live_client_secret;
				$curl_token_url = CF7PE_LIVE_TKL;
				$curl_sale_url = CF7PE_LIVE_PMT;
				$curl_refund_url = CF7PE_LIVE_SALE;
			}
		
			/* Get PayPal access token via cURL */
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
			curl_setopt($ch, CURLOPT_URL, $curl_token_url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=client_credentials");
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_USERPWD, $client_id . ":" . $client_secret);
			$headers = array();
			$headers[] = "Accept: application/json";
			$headers[] = "Accept-Language: en_US";
			$headers[] = "Content-Type: application/x-www-form-urlencoded";
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
			$result = curl_exec($ch);
			$access_token_id = json_decode($result)->access_token;
			if (curl_errno($ch)) {
				echo 'Error:' . curl_error($ch); //phpcs:ignore
			}

			/* Get sale id via cURL */
			$curl = curl_init($curl_sale_url.$transaction_id);
			curl_setopt($curl, CURLOPT_POST, false);
			curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($curl, CURLOPT_HEADER, false);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($curl, CURLOPT_HTTPHEADER, array(
				'Authorization: Bearer ' . $access_token_id,
				'Accept: application/json',
				'Content-Type: application/json'
			));
			$response = curl_exec($curl);
			$response_data=json_decode($response,true);
			
			/* Payment refund via cURL */
			$paypal_sale_id = $response_data['transactions'][0]['related_resources'][0]['sale']['id'];
			$header = Array(
				"Content-Type: application/json",
				"Authorization: Bearer $access_token_id",
			);
			$ch = curl_init($curl_refund_url.$paypal_sale_id.'/refund');
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, '{}');
			curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
			$response = json_decode(curl_exec($ch));
			$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			curl_close($ch);
			if( $response->name === 'TRANSACTION_REFUSED'){
				echo 'already payment refund';
				if($enable_log === '1'){
					$message="already payment refund.";
					wpcf7pap_error_log_generate($message);
				}

			}else{
				echo 'Payment refund successful';
				update_post_meta($entry_id, '_transaction_status', 'refunded');

				if($enable_log === '1'){
					$message="Payment refund successful.";
					wpcf7pap_error_log_generate($message);
				}
			}
			exit();
		}
		
		function action__init() {
			
			if ( !isset( $_SESSION ) || session_status() == PHP_SESSION_NONE ) {
				session_start();
			}

			/**
			 * Fire email after failed/canle payment from paypal
			 */
			if (
				    isset( $_GET['token' ] )
				&& !isset($_GET['paymentId'])
				&& !isset($_GET['PayerID'])
				&& isset( $_SESSION[ CF7PE_META_PREFIX . 'form_instance' ] )
				&& !empty( $_SESSION[ CF7PE_META_PREFIX . 'form_instance' ] )
			) {
				
				$invoiceNumber = ( isset( $_REQUEST['inv'] ) ? $_REQUEST['inv'] : '' );
				$from_data = unserialize( get_transient( CF7PE_META_PREFIX . 'form_instance' . $invoiceNumber ) );
				$from_data = unserialize( $_SESSION[ CF7PE_META_PREFIX . 'form_instance' ] );
				$form_ID = $from_data->get_contact_form()->id();
				
				do_action( CF7PE_PREFIX . '/paypal/save/data', $from_data, $token = $_GET['token' ], $payment = '', $invoiceNumber,$form_ID );

				add_filter( 'wpcf7_mail_components', array( $this, 'filter__wpcf7_mail_components' ), 888, 3 );
				remove_filter( 'wpcf7_mail_components', array( $this, 'filter__wpcf7_mail_components' ), 888, 3 );
				

				if ( isset( $_SESSION[ CF7PE_META_PREFIX . 'context_' . $form_ID ] ) ) {
					unset( $_SESSION[ CF7PE_META_PREFIX . 'context_' . $form_ID ] );
				}

				if ( !empty( $this->get_form_attachments( $form_ID ) ) ) {
					$this->zw_remove_uploaded_files( $this->get_form_attachments( $form_ID ) );
				}
			}

			/**
			 * Fire email after success payment from paypal
			 */
			if (
				!empty($_GET['paymentId'])
				&& !empty($_GET['PayerID'])
				&& isset( $_SESSION[ CF7PE_META_PREFIX . 'form_instance' ] )
				&& !empty( $_SESSION[ CF7PE_META_PREFIX . 'form_instance' ] )
			) {

				$invoiceNumber = ( isset( $_REQUEST['inv'] ) ? $_REQUEST['inv'] : '' );
				
				$from_data = unserialize( get_transient( CF7PE_META_PREFIX . 'form_instance' . $invoiceNumber ) );
				
				$from_data = unserialize( $_SESSION[ CF7PE_META_PREFIX . 'form_instance' ] );
				$form_ID = $from_data->get_contact_form()->id();

				if ( !empty( $form_ID ) ) {

					/*$apiContext = $_SESSION[ CF7PE_META_PREFIX . 'context_' . $form_ID ];*/

					$paymentId = $_GET['paymentId'];

					$mode_sandbox           = get_post_meta( $form_ID, CF7PE_META_PREFIX . 'mode_sandbox', true );
					$sandbox_client_id      = get_post_meta( $form_ID, CF7PE_META_PREFIX . 'sandbox_client_id', true );
					$sandbox_client_secret  = get_post_meta( $form_ID, CF7PE_META_PREFIX . 'sandbox_client_secret', true );
					$live_client_id         = get_post_meta( $form_ID, CF7PE_META_PREFIX . 'live_client_id', true );
					$live_client_secret     = get_post_meta( $form_ID, CF7PE_META_PREFIX . 'live_client_secret', true );
					$currency               = get_post_meta( $form_ID, CF7PE_META_PREFIX . 'currency', true );
					$email                  = get_post_meta( $form_ID, CF7PE_META_PREFIX . 'email', true );
					$quantity            	= get_post_meta( $form_ID, CF7PE_META_PREFIX . 'quantity', true );
					$amount_form            = get_post_meta( $form_ID, CF7PE_META_PREFIX . 'amount', true );
									
				}

				$paypalConfig = [
					'client_id'     => ( !empty( $mode_sandbox ) ? $sandbox_client_id : $live_client_id ),
					'client_secret' => ( !empty( $mode_sandbox ) ? $sandbox_client_secret : $live_client_secret ),
					'mode' => ( !empty( $mode_sandbox ) ? 'sandbox' : 'live' )
				];

				$apiContext = $this->getApiContext( $paypalConfig['client_id'], $paypalConfig['client_secret'], $mode_sandbox );
				
				$apimode = ( $mode_sandbox ) ? 'sandbox' : 'live';
				
				$apiContext->setConfig(
					array(
						'mode'			 => $apimode,
						'http.CURLOPT_SSL_VERIFYPEER' => false,
					)
				);

				$payment = Payment::get($paymentId, $apiContext);
				/**
				 * Add transctions to Paypal Account
				 */
				$amountPayable = $payment->transactions[0]->amount->total;
				$execution = new PaymentExecution();
				$execution->setPayerId($_GET['PayerID']);
				//call all the require API class
				$transaction = new Transaction();
				$amount = new Amount();
				$details = new Details();

				//Details data
				$details->setSubtotal( $amountPayable );
				//Amount data
				$amount->setCurrency( $currency )
					->setTotal( $amountPayable )
					->setDetails($details);
				//Transaction Data
				$transaction->setAmount( $amount );
				// Add the above transaction object inside our Execution object.
				$execution->addTransaction($transaction);
				
				try {
					// Execute the payment
					// (See bootstrap.php for more on `ApiContext`)
					$result = $payment->execute($execution, $apiContext);

					try {
						//$payment = Payment::get($paymentId, $apiContext);
					} catch (Exception $ex) {
						echo $ex->getCode(); // Prints the Error Code
    					echo $ex->getData(); // Prints the detailed error message
						return;
					}
				} catch (Exception $ex) {
					//echo "<pre>"; print_r($ex);
					echo $ex->getCode(); // Prints the Error Code
    				echo $ex->getData(); // Prints the detailed error message
					return;
				}

				$data = [
					'transaction_id' => $payment->getId(),
					'payment_amount' => $payment->transactions[0]->amount->total,
					'payment_status' => $payment->getState(),
					'invoice_id' => $payment->transactions[0]->invoice_number
					
				];
				$payment_json = json_encode($result->toArray(), JSON_PRETTY_PRINT);
				add_filter( 'wpcf7_mail_components', array( $this, 'filter__wpcf7_mail_components' ), 888, 3 );
				$this->mail( $from_data, $from_data->get_posted_data() );
				$form_data_pe = serialize($from_data->get_posted_data());
				$posted_data = unserialize($form_data_pe);

				$email = !empty($email) && array_key_exists($email, $posted_data) ? $posted_data[$email] : '';
				$quanity_val = ( ( !empty( $quantity ) && array_key_exists( $quantity, $posted_data ) ) ? floatval( $posted_data[$quantity] ) : '' );
				$amount_val  = ( ( !empty( $amount_form ) && array_key_exists( $amount_form, $posted_data ) ) ? floatval( $posted_data[$amount_form] ) : '0' );
				remove_filter( 'wpcf7_mail_components', array( $this, 'filter__wpcf7_mail_components' ), 888, 3 );

				if ( isset( $_SESSION[ CF7PE_META_PREFIX . 'form_instance' ] ) ) {
					unset( $_SESSION[ CF7PE_META_PREFIX . 'form_instance' ] );
				}

				if ( isset( $_SESSION[ CF7PE_META_PREFIX . 'context_' . $form_ID ] ) ) {
					unset( $_SESSION[ CF7PE_META_PREFIX . 'context_' . $form_ID ] );
				}

				if ( !empty( $this->get_form_attachments( $form_ID ) ) ) {
					$this->zw_remove_uploaded_files( $this->get_form_attachments( $form_ID ) );
				}

				
				do_action( CF7PE_PREFIX . '/paypal/save/data', $from_data, $token = $_GET['token' ], $payment, $invoiceNumber );

			}
		}

		/**
		 * PayPal Verify CF7 dependencies.
		 *
		 * @method action__wpcf7_verify_version
		 *
		 */
		function action__wpcf7_verify_version(){

			$cf7_verify = $this->wpcf7_version();
			if ( version_compare($cf7_verify, '5.2') >= 0 ) {
				add_filter( 'wpcf7_feedback_response',   array( $this, 'filter__wpcf7_ajax_json_echo'   ), 20, 2 );
			} else{
				add_filter( 'wpcf7_ajax_json_echo',   array( $this, 'filter__wpcf7_ajax_json_echo'   ), 20, 2 );
			}

		}
		/**
		 * Get the attachment upload directory from plugin.
		 *
		 * @method zw_wpcf7_upload_tmp_dir
		 *
		 * @return string
		 */
		function zw_wpcf7_upload_tmp_dir() {

			$upload = wp_upload_dir();
			$upload_dir = $upload['basedir'];
			$cf7sa_upload_dir = $upload_dir . '/cf7sa-uploaded-files';

			if ( !is_dir( $cf7sa_upload_dir ) ) {
				mkdir( $cf7sa_upload_dir, 0400 );
			}

			return $cf7sa_upload_dir;
		}

		/**
		 * Copy the attachment into the plugin folder.
		 *
		 * @method zw_cf7_upload_files
		 *
		 * @param  array $attachment
		 *
		 * @uses $this->zw_wpcf7_upload_tmp_dir(), WPCF7::wpcf7_maybe_add_random_dir()
		 *
		 * @return array
		 */
		function zw_cf7_upload_files( $attachment ) {
			if ( empty( $attachment ) || $attachment === "" ) {
				return;
			}
			$new_attachment = $attachment;
			foreach ( $attachment as $key => $value ) {
				// Check if $value is a non-empty string before proceeding
				if ( is_string( $value ) && $value !== "" ) {
					$tmp_name = $value;
					$uploads_dir = wpcf7_maybe_add_random_dir( $this->zw_wpcf7_upload_tmp_dir() );
					$new_file = path_join( $uploads_dir, end( explode( '/', $value ) ) );
		
					if ( copy( $value, $new_file ) ) {
						chmod( $new_file, 0400 );
						$new_attachment[$key] = $new_file;
					}
				}
			}
		
			return $new_attachment;
		}


		/**
		 * Action: CF7 before send email
		 *
		 * @method action__wpcf7_before_send_mail
		 *
		 * @param  object $contact_form WPCF7_ContactForm::get_instance()
		 *
		 */
		function action__wpcf7_before_send_mail( $contact_form ) {

			$submission    = WPCF7_Submission::get_instance(); // CF7 Submission Instance
			$form_ID       = $contact_form->id();
		
			$form_instance = WPCF7_ContactForm::get_instance($form_ID); // CF7 From Instance

			if ( $submission ) {
				// CF7 posted data
				$posted_data = $submission->get_posted_data();
			}

			if ( !empty( $form_ID ) ) {

				$use_paypal = get_post_meta( $form_ID, CF7PE_META_PREFIX . 'use_paypal', true );

				if ( empty( $use_paypal ) )
					return;

				$mode_sandbox           = get_post_meta( $form_ID, CF7PE_META_PREFIX . 'mode_sandbox', true );
				$sandbox_client_id      = get_post_meta( $form_ID, CF7PE_META_PREFIX . 'sandbox_client_id', true );
				$sandbox_client_secret  = get_post_meta( $form_ID, CF7PE_META_PREFIX . 'sandbox_client_secret', true );
				$live_client_id         = get_post_meta( $form_ID, CF7PE_META_PREFIX . 'live_client_id', true );
				$live_client_secret     = get_post_meta( $form_ID, CF7PE_META_PREFIX . 'live_client_secret', true );
				$amount                 = get_post_meta( $form_ID, CF7PE_META_PREFIX . 'amount', true );
				$quantity               = get_post_meta( $form_ID, CF7PE_META_PREFIX . 'quantity', true );
				$description            = get_post_meta( $form_ID, CF7PE_META_PREFIX . 'description', true );
				$success_returnURL      = get_post_meta( $form_ID, CF7PE_META_PREFIX . 'success_returnurl', true );
				$cancle_returnURL       = get_post_meta( $form_ID, CF7PE_META_PREFIX . 'cancel_returnurl', true );
				$mail                   = get_post_meta( $form_ID, CF7PE_META_PREFIX . 'email', true );
				// Set some example data for the payment.
				$currency               = get_post_meta( $form_ID, CF7PE_META_PREFIX . 'currency', true );
				

				$mail       = ( ( !empty( $mail ) && array_key_exists( $mail, $posted_data ) ) ? $posted_data[$mail] : '' );
				$description = ( ( !empty( $description ) && array_key_exists( $description, $posted_data ) ) ? $posted_data[$description] : get_bloginfo( 'name' ) );
				add_filter( 'wpcf7_skip_mail', array( $this, 'filter__wpcf7_skip_mail' ), 20 );

				$amount_val  = ( ( !empty( $amount ) && array_key_exists( $amount, $posted_data ) ) ? floatval( $posted_data[$amount] ) : '0' );
				$quanity_val = ( ( !empty( $quantity ) && array_key_exists( $quantity, $posted_data ) ) ? floatval( $posted_data[$quantity] ) : '' );

				$description_val = ( ( !empty( $description ) && array_key_exists( $description, $posted_data ) ) ? $posted_data[$description] : get_bloginfo( 'name' ) );

                if (
					!empty( $amount )
					&& array_key_exists( $amount, $posted_data )
					&& is_array( $posted_data[$amount] )
					&& !empty( $posted_data[$amount] )
				) {
					$val = 0;
					foreach ( $posted_data[$amount] as $k => $value ) {
						$val = $val + floatval($value);
					}
					$amount_val = $val;
				}

				if (
					!empty( $quantity )
					&& array_key_exists( $quantity, $posted_data )
					&& is_array( $posted_data[$quantity] )
					&& !empty( $posted_data[$quantity] )
				) {
					$qty_val = 0;
					foreach ( $posted_data[$quantity] as $k => $qty ) {
						$qty_val = $qty_val + floatval($qty);
					}
					$quanity_val = $qty_val;
				}

				if ( empty( $amount_val ) ) {
					$_SESSION[ CF7PE_META_PREFIX . 'amount_error' . $form_ID ] = __( 'Empty Amount field or Invalid configuration.', CF7PE_PREFIX );
					return;
				}

				// PayPal settings. Change these to your account details and the relevant URLs
				// for your site.
				$paypalConfig = [
					'client_id'     => ( !empty( $mode_sandbox ) ? $sandbox_client_id : $live_client_id ),
					'client_secret' => ( !empty( $mode_sandbox ) ? $sandbox_client_secret : $live_client_secret ),
					'return_url'    => ( !empty( $success_returnURL ) ? esc_url( $success_returnURL ) : site_url() ),
					'cancel_url'    => ( !empty( $cancle_returnURL ) ? esc_url( $cancle_returnURL ) : site_url() ),
				];

				$apimode = ( $mode_sandbox ) ? 'sandbox' : 'live';
				$apiContext = $this->getApiContext( $paypalConfig['client_id'], $paypalConfig['client_secret'], $apimode );

				$apiContext->setConfig(
					array(
						'log.LogEnabled' => true,
						'log.FileName'   => CF7PE_DIR . '/inc/lib/log/paypal.log',
						'log.LogLevel'   => 'DEBUG',
						'mode'			 => $apimode,
						'http.CURLOPT_SSL_VERIFYPEER' => false,
					)
				);

				$_SESSION[ CF7PE_META_PREFIX . 'context_' . $form_ID ] = $apiContext;

				$payer = new Payer();
				$payer->setPaymentMethod( 'paypal' );

				// Set some example data for the payment.
				$amountPayable = (float) ( empty( $quanity_val ) ? $amount_val : ( $quanity_val * $amount_val ) );
				$invoiceNumber = uniqid();

				$item = new Item();
				$item->setName( $description_val )
					->setCurrency( $currency )
					->setQuantity( ( empty( $quanity_val ) ? 1 : $quanity_val ) )
					->setPrice( $amount_val );

				$itemList = new ItemList();
				$itemList->setItems( array( $item ) );

				$details = new Details();
				$details->setSubtotal( $amountPayable );

				$amount = new Amount();
				$amount->setCurrency( $currency )
					->setTotal( $amountPayable )
					->setDetails($details);

				$transaction = new Transaction();
				$transaction->setAmount( $amount )
					->setItemList( $itemList )
					->setDescription( $description_val )
					->setInvoiceNumber( $invoiceNumber );

				$redirectUrls = new RedirectUrls();
				$redirectUrls->setReturnUrl( $paypalConfig[ 'return_url' ] )
					->setCancelUrl( $paypalConfig[ 'cancel_url' ] );

				$payment = new Payment();
				$payment->setIntent( 'sale' )
					->setPayer( $payer )
					->setId( $invoiceNumber )
					->setTransactions( array( $transaction ) )
					->setRedirectUrls( $redirectUrls );

				$request = clone $payment;

				try {
					$payment->create( $apiContext );
				} catch ( Exception $e ) {
					$_SESSION[ CF7PE_META_PREFIX . 'exception_' . $form_ID ] = $e->getData();
					remove_filter( 'wpcf7_skip_mail', array( $this, 'filter__wpcf7_skip_mail' ), 20 );
					return;
				}

				if( !empty( $submission->uploaded_files() ) ) {

					$cf7_verify = $this->wpcf7_version();

					if ( version_compare( $cf7_verify, '5.4' ) >= 0 ) {
						$uploaded_files = $this->zw_cf7_upload_files( $submission->uploaded_files(), 'new' );
					}else{
						$uploaded_files = $this->zw_cf7_upload_files( array( $submission->uploaded_files() ), 'old' );
					}

					if ( !empty( $uploaded_files ) ) {
						$_SESSION[ CF7PE_META_PREFIX . 'form_attachment_' . $form_ID ] = serialize( $uploaded_files );
					}
				}

				if ( $payment->getApprovalLink() ) {
					$_SESSION[ CF7PE_META_PREFIX . 'paypal_url' . $form_ID ] = $payment->getApprovalLink();
				}

				$_SESSION[ CF7PE_META_PREFIX . 'form_instance' ] = serialize( $submission );

				if ( !$submission->is_restful() ) {
					wp_redirect( $payment->getApprovalLink() );
					exit;
				}

			}

		}


		/*
		######## #### ##       ######## ######## ########   ######
		##        ##  ##          ##    ##       ##     ## ##    ##
		##        ##  ##          ##    ##       ##     ## ##
		######    ##  ##          ##    ######   ########   ######
		##        ##  ##          ##    ##       ##   ##         ##
		##        ##  ##          ##    ##       ##    ##  ##    ##
		##       #### ########    ##    ######## ##     ##  ######
		*/

		/**
		 * Filter: Skip email when paypal enable.
		 *
		 * @method filter__wpcf7_skip_mail
		 *
		 * @param  bool $bool
		 *
		 * @return bool
		 */
		function filter__wpcf7_skip_mail( $bool ) {
			return true;
		}

		/**
		 * Filter: Modify the contact form 7 response.
		 *
		 * @method filter__wpcf7_ajax_json_echo
		 *
		 * @param  array $response
		 * @param  array $result
		 *
		 * @return array
		 */
		function filter__wpcf7_ajax_json_echo( $response, $result ) {

			if (
				   array_key_exists( 'contact_form_id' , $result )
				&& array_key_exists( 'status' , $result )
				&& !empty( $result[ 'contact_form_id' ] )
				&& !empty( $_SESSION[ CF7PE_META_PREFIX . 'paypal_url' . $result[ 'contact_form_id' ] ] )
				&& $result[ 'status' ] == 'mail_sent'
			) {
				$response[ 'redirection_url' ] = $_SESSION[ CF7PE_META_PREFIX . 'paypal_url' . $result[ 'contact_form_id' ] ];
				$response[ 'message' ] = __( 'You are redirecting to PayPal.', CF7PE_PREFIX );
				unset( $_SESSION[ CF7PE_META_PREFIX . 'paypal_url' . $result[ 'contact_form_id' ] ] );
			}

			if (
				   array_key_exists( 'contact_form_id' , $result )
				&& array_key_exists( 'status' , $result )
				&& !empty( $result[ 'contact_form_id' ] )
				&& !empty( $_SESSION[ CF7PE_META_PREFIX . 'exception_' . $result[ 'contact_form_id' ] ] )
				&& $result[ 'status' ] == 'mail_sent'
			) {
				$exception = (array)json_decode( $_SESSION[ CF7PE_META_PREFIX . 'exception_' . $result[ 'contact_form_id' ] ] );
				$response[ 'message' ] = ( !empty( $exception ) && array_key_exists( 'error_description', $exception ) ? '<strong style="color: #ff0000; ">' . $exception['error_description']. '</strong>' : '' ) . '<br/>' . $response[ 'message' ];
				unset( $_SESSION[ CF7PE_META_PREFIX . 'exception_' . $result[ 'contact_form_id' ] ] );
			}

			if (
				   array_key_exists( 'contact_form_id' , $result )
				&& array_key_exists( 'status' , $result )
				&& !empty( $result[ 'contact_form_id' ] )
				&& !empty( $_SESSION[ CF7PE_META_PREFIX . 'amount_error' . $result[ 'contact_form_id' ] ] )
				&& $result[ 'status' ] == 'mail_sent'
			) {

				$response[ 'message' ] = $_SESSION[ CF7PE_META_PREFIX . 'amount_error' . $result[ 'contact_form_id' ] ];
				$response[ 'status' ] = 'mail_failed';
				unset( $_SESSION[ CF7PE_META_PREFIX . 'amount_error' . $result[ 'contact_form_id' ] ] );
			}

			return $response;
		}

		/**
		 * Filter: Modify the email components.
		 *
		 * @method filter__wpcf7_mail_components
		 *
		 * @param  array $components
		 * @param  object $current_form WPCF7_ContactForm::get_current()
		 * @param  object $mail WPCF7_Mail::get_current()
		 *
		 * @return array
		 */
		function filter__wpcf7_mail_components( $components, $current_form, $mail ) {

			$from_data = unserialize( $_SESSION[ CF7PE_META_PREFIX . 'form_instance' ] );
			$form_ID = $from_data->get_contact_form()->id();

			if (
				   !empty( $mail->get( 'attachments', true ) )
				&& !empty( $this->get_form_attachments( $form_ID ) )
			) {
				$components['attachments'] = $this->get_form_attachments( $form_ID );
			}

			return $components;
		}

		/*
		######## ##     ## ##    ##  ######  ######## ####  #######  ##    ##  ######
		##       ##     ## ###   ## ##    ##    ##     ##  ##     ## ###   ## ##    ##
		##       ##     ## ####  ## ##          ##     ##  ##     ## ####  ## ##
		######   ##     ## ## ## ## ##          ##     ##  ##     ## ## ## ##  ######
		##       ##     ## ##  #### ##          ##     ##  ##     ## ##  ####       ##
		##       ##     ## ##   ### ##    ##    ##     ##  ##     ## ##   ### ##    ##
		##        #######  ##    ##  ######     ##    ####  #######  ##    ##  ######
		*/
		/**
		 * Set up a connection to the API
		 *
		 * @param string $clientId
		 *
		 * @param string $clientSecret
		 *
		 * @param bool   $enableSandbox Sandbox mode toggle, true for test payments
		 *
		 * @return \PayPal\Rest\ApiContext
		 */
		function getApiContext( $clientId, $clientSecret, $enableSandbox = false ) {
			$apiContext = new ApiContext( new OAuthTokenCredential( $clientId, $clientSecret ) );

			$apiContext->setConfig([ 'mode' => $enableSandbox ? 'sandbox' : 'live' ]);

			return $apiContext;
		}

	
		/**
		 * Email send
		 *
		 * @method mail
		 *
		 * @param  object $contact_form WPCF7_ContactForm::get_instance()
		 * @param  [type] $posted_data  WPCF7_Submission::get_posted_data()
		 *
		 * @uses $this->prop(), $this->mail_replace_tags(), $this->get_form_attachments(),
		 *
		 * @return bool
		 */
		function mail( $contact_form, $posted_data ) {

			if( empty( $contact_form ) ) {
				return false;
			}

			$contact_form_data = $contact_form->get_contact_form();
			$mail = $this->prop( 'mail', $contact_form_data );
			$mail = $this->mail_replace_tags( $mail, $posted_data );

			$result = WPCF7_Mail::send( $mail, 'mail' );

			if ( $result ) {
				$additional_mail = array();

				if (
					$mail_2 = $this->prop( 'mail_2', $contact_form_data )
					and $mail_2['active']
				) {

					$mail_2 = $this->mail_replace_tags( $mail_2, $posted_data );
					$additional_mail['mail_2'] = $mail_2;
				}

				$additional_mail = apply_filters( 'wpcf7_additional_mail',
					$additional_mail, $contact_form_data );

				foreach ( $additional_mail as $name => $template ) {
					WPCF7_Mail::send( $template, $name );
				}

				return true;
			}

			return false;
		}

		/**
		 * get the property from the
		 *
		 * @method prop    used from WPCF7_ContactForm:prop()
		 *
		 * @param  string $name
		 * @param  object $class_object WPCF7_ContactForm:get_current()
		 *
		 * @return mixed
		 */
		public function prop( $name, $class_object ) {
			$props = $class_object->get_properties();
			return isset( $props[$name] ) ? $props[$name] : null;
		}

		/**
		 * Mail tag replace
		 *
		 * @method mail_replace_tags
		 *
		 * @param  array $mail
		 * @param  array $data
		 *
		 * @return array
		 */
		function mail_replace_tags( $mail, $data ) {
			$mail = ( array ) $mail;
			$data = ( array ) $data;

			$new_mail = array();
			if ( !empty( $mail ) && !empty( $data ) ) {
				foreach ( $mail as $key => $value ) {
					if( $key != 'attachments' ) {
						foreach ( $data as $k => $v ) {
							if ( isset( $v ) && is_array( $v ) ) {
								$array_string = implode(", ",$v);
								$value = str_replace( '[' . $k . ']' , $array_string, $value );
							} else {
								$value = str_replace( '[' . $k . ']' , $v, $value );
							}
						}
					}
					$new_mail[$key] = $value;
				}
			}

			return $new_mail;
		}

		/**
		 * Get attachment for the from
		 *
		 * @method get_form_attachments
		 *
		 * @param  int $form_ID form_id
		 *
		 * @return array
		 */
		function get_form_attachments( $form_ID ) {
			if(
				!empty( $form_ID )
				&& isset( $_SESSION[ CF7PE_META_PREFIX . 'form_attachment_' . $form_ID ] )
				&& !empty( $_SESSION[ CF7PE_META_PREFIX . 'form_attachment_' . $form_ID ] )
			) {
				return unserialize( $_SESSION[ CF7PE_META_PREFIX . 'form_attachment_' . $form_ID ] );
			}
		}

		function zw_remove_uploaded_files( $files ) {

			if (
				   !is_array( $files )
				&& empty( $files )
			)
				return;

			foreach ( (array) $files as $name => $path ) {
				wpcf7_rmdir_p( $path );

				if ( $dir = dirname( $path )
				and false !== ( $files = scandir( $dir ) )
				and ! array_diff( $files, array( '.', '..' ) ) ) {
					// remove parent dir if it's empty.
					rmdir( $dir );
				}
			}
		}

		/**
		 * Get current conatct from 7 version.
		 *
		 * @method wpcf7_version
		 *
		 * @return string
		 */
		function wpcf7_version() {

			$wpcf7_path = plugin_dir_path( CF7PE_DIR ) . 'contact-form-7/wp-contact-form-7.php';

			if( ! function_exists('get_plugin_data') ){
				require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
			}
			$plugin_data = get_plugin_data( $wpcf7_path );

			return $plugin_data['Version'];
		}

		/**
		 * Function: getUserIpAddr
		 *
		 * @method getUserIpAddr
		 *
		 * @return string
		 */
		function getUserIpAddr() {
			$ip = '';
			if ( !empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
				//ip from share internet
				$ip = $_SERVER['HTTP_CLIENT_IP'];
			} else if ( !empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
				//ip pass from proxy
				$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
			} else {
				$ip = $_SERVER['REMOTE_ADDR'];
			}
			return $ip;
		}

	}

	add_action( 'plugins_loaded', function() {
		CF7PE()->lib = new CF7PE_Lib;
	} );
}
