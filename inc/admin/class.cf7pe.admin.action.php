<?php
/**
 * CF7PE_Admin_Action Class
 *
 * Handles the admin functionality.
 *
 * @package WordPress
 * @subpackage Accept PayPal Payments using Contact Form 7
 * @since 3.5
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

if ( !class_exists( 'CF7PE_Admin_Action' ) ){

	/**
	 *  The CF7PE_Admin_Action Class
	 */
	class CF7PE_Admin_Action {

		function __construct()  {

			add_action( 'init',  array( $this, 'action__init' ) );
			add_action( 'init',  array( $this, 'action__init_99' ), 99 );
			add_action( 'add_meta_boxes', array( $this, 'action__add_meta_boxes' ) );
			
			// Save settings of contact form 7 admin
			add_action( 'wpcf7_save_contact_form', array( $this, 'action__wpcf7_save_contact_form' ), 20, 2 );
			add_action( 'manage_cf7pe_data_posts_custom_column', array( $this, 'action__manage_cf7pe_data_posts_custom_column' ), 10, 2 );
			add_action( 'restrict_manage_posts', array( $this, 'action__restrict_manage_posts' ) );
			add_action( 'parse_query', array( $this, 'action__parse_query_cf7pe' ) );
			add_action('upgrader_process_complete',array( $this, 'option_tbl_upgrade_action', 10, 2) );
		}
		/**
		 * Action: option_tbl
		 *
		 * - Filter data by form id.
		 *
		 * @method option_tbl_upgrade_action
		 *
		 * @param  object $query WP_Query
		 */
		function option_tbl_upgrade_action($upgrader_object, $options) {
			if(!get_option('_exceed_cfpezw_l')){
				add_option('_exceed_cfpezw_l', 'cfpezw10');
			}
			
		}
		/**
		 * Action: parse_query
		 *
		 * - Filter data by form id.
		 *
		 * @method action__parse_query_cf7pe
		 *
		 * @param  object $query WP_Query
		 */
		function action__parse_query_cf7pe( $query ) {
			if (
				! is_admin()
				|| !in_array ( $query->get( 'post_type' ), array( 'cf7pe_data' ) )
			)
				return;

			if (
				is_admin()
				&& isset( $_GET['form-id'] )
				&& 'all' != $_GET['form-id']
			) {
				$query->query_vars['meta_key']     = '_form_id';
				$query->query_vars['meta_value']   = $_GET['form-id'];
				$query->query_vars['meta_compare'] = '=';
			} elseif ( isset( $_GET['form-id'] ) && 'all' == $_GET['form-id'] && !isset( $_REQUEST['cf7pe_export_csv'] )) {
				add_action( 'admin_notices', array( $this, 'action__admin_notices_export_not_found' ) );
				return;
			}

		}
		/**
		 * Action: init 99
		 *
		 * - Used to perform the CSV export functionality.
		 *
		 */
		function action__init_99() {
		
			if (
				   isset( $_REQUEST['cf7pe_export_csv'] )
				&& isset( $_REQUEST['form-id'] )
				&& !empty( $_REQUEST['form-id'] )
			) {
				
				$form_id = sanitize_text_field($_REQUEST['form-id']);
				$exceed_ct = sanitize_text_field( substr( get_option( '_exceed_cfpezw_l' ), 6 ) );
				
				if ( 'all' == $form_id ) {
					add_action( 'admin_notices', array( $this, 'action__admin_notices_export' ) );
					return;
				}

				$args = array(
					'post_type' => 'cf7pe_data',
					'posts_per_page' => 10,
					'post_status' => 'publish',
					'order'          => 'ASC',  // DESC for descending order (latest first)
				);

				$exported_data = get_posts( $args );

				if ( empty( $exported_data ) )
					return;

				/** CSV Export **/
				$filename = 'cf7pe-' . $form_id . '-' . time() . '.csv';

				$header_row = array(
					'_form_id'            => 'Form ID/Name',
					'_transaction_id'     => 'Transaction ID',
					'_invoice_no'         => 'Invoice ID',
					'_amount'             => 'Amount',
					'_quantity'           => 'Quantity',
					'_total'              => 'Total',
					'_currency'           => 'Currency code',
					'_submit_time'        => 'Submit Time',
					'_request_Ip'         => 'Request IP',
					'_transaction_status' => 'Transaction status'
				);

				$data_rows = array();
				$special_row_added = false;
				if ( !empty( $exported_data ) ) {
					foreach ( $exported_data as $entry ) {

						$row = array();

						if ( !empty( $header_row ) ) {
							foreach ( $header_row as $key => $value ) {

								if (
									   $key != '_transaction_status'
									&& $key != '_submit_time'
								) {

									$row[$key] = __(
										(
											(
												'_form_id' == $key
												&& !empty( get_the_title( get_post_meta( $entry->ID, $key, true ) ) )
											)
											? get_the_title( get_post_meta( $entry->ID, $key, true ) )
											: get_post_meta( $entry->ID, $key, true )
										)
									);

								} else if ( $key == '_transaction_status' ) {

									$transaction_status = get_post_meta($entry->ID, $key, true);
									if (!empty($transaction_status) && $transaction_status !== 'approved') {
										$row[$key] = ucfirst($transaction_status); // Capitalize first letter of status
									} elseif ($transaction_status === 'approved') {
										$row[$key] = esc_html__('Succeeded'); // Display 'Succeeded' for approved transactions
									} else {
										$row[$key] = ''; // Empty string if no status
									}

								} else if ( '_submit_time' == $key ) {
									$row[$key] = __( get_the_date( 'd, M Y H:i:s', $entry->ID ) );
								}
							}
						}

						/* form_data */
						$data = unserialize(get_post_meta( $entry->ID, '_form_data', true )) ;

						$hide_data = apply_filters( CF7PE_PREFIX . '/hide-display', array( '_wpcf7', '_wpcf7_version', '_wpcf7_locale', '_wpcf7_unit_tag', '_wpcf7_container_post' ) );
						foreach ( $hide_data as $key => $value ) {
							if ( array_key_exists( $value, $data ) ) {
								unset( $data[$value] );
							}
						}

						// Check for _exceed_num_cfpezw and handle it
						if (array_key_exists('_exceed_num_cfpezw', $data) && !$special_row_added) {
							$special_row = array_fill_keys(array_keys($header_row), '');
							$special_row['_transaction_id'] = "To unlock more export data, consider upgrading to PRO. Visit: " . esc_url(CF7PE_PRODUCT);
							$data_rows[] = $special_row;
							
							// Set the flag to true to prevent adding the special row again
							$special_row_added = true;
							// Skip adding other data for this entry
							continue;
						}
						
						if ( !empty( $data ) ) {
							foreach ( $data as $key => $value ) {
								if ( strpos( $key, 'paypal-' ) === false ) {

									if ( !in_array( $key, $header_row ) ) {
										$header_row[$key] = $key;
									}

									$row[$key] = __( is_array( $value ) ? implode( ', ', $value ) : $value );

								}
							}
						}

						$data_rows[] = $row;

					}
				}

				ob_start();

				$fh = @fopen( 'php://output', 'w' );
				fprintf( $fh, chr(0xEF) . chr(0xBB) . chr(0xBF) );
				header( 'Cache-Control: must-revalidate, post-check=0, pre-check=0' );
				header( 'Content-Description: File Transfer' );
				header( 'Content-type: text/csv' );
				header( "Content-Disposition: attachment; filename={$filename}" );
				header( 'Expires: 0' );
				header( 'Pragma: public' );
				fputcsv( $fh, $header_row );
				foreach ( $data_rows as $data_row ) {
					fputcsv( $fh, $data_row );
				}
				fclose( $fh );

				ob_end_flush();
				die();

			}
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
		function action__init() {
			//wp_register_script( CF7PE_PREFIX . '_admin_js', CF7PE_URL . 'assets/js/admin.min.js', array( 'jquery-core' ), CF7PE_VERSION );
			wp_enqueue_script( CF7PE_PREFIX . '_admin_js',CF7PE_URL . 'assets/js/admin.js?t='.time(), array( 'jquery' ), true );
			//wp_register_style( CF7PE_PREFIX . '_admin_css', CF7PE_URL . 'assets/css/admin.min.css', array(), CF7PE_VERSION );
			wp_enqueue_style( CF7PE_PREFIX . '_admin_css', CF7PE_URL . 'assets/css/admin.css', array(), CF7PE_VERSION );
			wp_localize_script( CF7PE_PREFIX . '_admin_js', 'admin_ajax_url', cf7pap_ajax_admin_URL());
		}

		/**
		 * Action: add_meta_boxes
		 *
		 * - Add mes boxes for the CPT "cf7sa_data"
		 */
		function action__add_meta_boxes() {
			add_meta_box( 'cf7pe_data', __( 'From Data', 'accept-paypal-payments-using-contact-form-7' ), array( $this, 'cfpe_show_from_data' ), 'cf7pe_data', 'normal', 'high' );
			add_meta_box( 'cfpe-help', __( 'Do you need help for configuration?', 'accept-paypal-payments-using-contact-form-7' ), array( $this, 'cfpe_show_help_data' ), 'cf7pe_data', 'side', 'high' );
		}

		/**
		 * Save PayPal field settings
		 */
		public function action__wpcf7_save_contact_form( $WPCF7_form ) {

			$wpcf7 = WPCF7_ContactForm::get_current();

			if ( !empty( $wpcf7 ) ) {
				$post_id = $wpcf7->id;
			}

			$form_fields = array(
				CF7PE_META_PREFIX . 'use_paypal',
				CF7PE_META_PREFIX . 'mode_sandbox',
				CF7PE_META_PREFIX . 'sandbox_client_id',
				CF7PE_META_PREFIX . 'sandbox_client_secret',
				CF7PE_META_PREFIX . 'live_client_id',
				CF7PE_META_PREFIX . 'live_client_secret',
				CF7PE_META_PREFIX . 'amount',
				CF7PE_META_PREFIX . 'quantity',
				CF7PE_META_PREFIX . 'email',
				CF7PE_META_PREFIX . 'description',
				CF7PE_META_PREFIX . 'currency',
				CF7PE_META_PREFIX . 'success_returnurl',
				CF7PE_META_PREFIX . 'cancel_returnurl',
			);

			if(!get_option('_exceed_cfpezw_l')){
				add_option('_exceed_cfpezw_l', 'cfpezw10');
			}

			if ( !empty( $form_fields ) ) {
				foreach ( $form_fields as $key ) {
					$keyval = sanitize_text_field( $_REQUEST[ $key ] ); //phpcs:ignore
					update_post_meta( $post_id, $key, $keyval );
				}
			}

		}

         /**
		 * Action: manage_cf7pe_data_posts_custom_column
		 *
		 * @method action__manage_cf7pe_data_posts_custom_column
		 *
		 * @param  string  $column
		 * @param  int     $post_id
		 *
		 * @return string
		 */
		function action__manage_cf7pe_data_posts_custom_column( $column, $post_id ) {
			$data_ct = $this->cfsazw_check_data_ct( sanitize_text_field( $post_id ) );
			//print_r($data_ct);die();
			switch ( $column ) {

				case 'form_id' :
					if( $data_ct ){
							echo "<a href='".esc_url(CF7PE_PRODUCT)."' target='_blank'>To unlock more features consider upgrading to PRO.</a>";
					}else{
						echo (
							!empty( get_post_meta( $post_id , '_form_id', true ) )
							? (
								!empty( get_the_title( get_post_meta( $post_id , '_form_id', true ) ) )
								? get_the_title( get_post_meta( $post_id , '_form_id', true ) )
								: get_post_meta( $post_id , '_form_id', true )
							)
							: ''
						);
					}					
				break;

				case 'transaction_status' :
					if( $data_ct ){
							echo "<a href='".esc_url(CF7PE_PRODUCT)."' target='_blank'>To unlock more features consider upgrading to PRO.</a>";
					}else{
						$transaction_status = get_post_meta( $post_id , '_transaction_status', true );
						if(!empty($transaction_status) && $transaction_status !== 'approved') {
							echo ucfirst($transaction_status);
						}elseif($transaction_status === 'approved'){
							echo esc_html__('Succeeded');
						}else{
							echo '';
						}
					}
				break;

				case 'total' :
					if( $data_ct ){
							echo "<a href='".esc_url(CF7PE_PRODUCT)."' target='_blank'>To unlock more features consider upgrading to PRO.</a>";
					}else{

						echo ( !empty( get_post_meta( $post_id , '_total', true ) ) ? get_post_meta( $post_id , '_total', true ) : '' ) .' ' .
							( !empty( get_post_meta( $post_id , '_currency', true ) ) ? strtoupper( get_post_meta( $post_id , '_currency', true ) ) : '' );
					}
				break;

			}
		}

		
		/**
		* check data ct
		*/
		function cfsazw_check_data_ct( $post_id ){
			$data = unserialize(get_post_meta( $post_id, '_form_data', true ));
			if( !empty( get_post_meta( $post_id, '_form_data', true ) ) && isset( $data['_exceed_num_cfpezw'] ) && !empty( $data['_exceed_num_cfpezw'] ) ){
				return $data['_exceed_num_cfpezw'];
			}else{
				return '';
			}

		}

		/**
		 * Action: restrict_manage_posts
		 *
		 * - Used to creat filter by form and export functionality.
		 *
		 * @method action__restrict_manage_posts
		 *
		 * @param  string $post_type
		 */
		function action__restrict_manage_posts( $post_type ) {

			if ( 'cf7pe_data' != $post_type ) {
				return;
			}

			$posts = get_posts(
				array(
					'post_type'        => 'wpcf7_contact_form',
					'post_status'      => 'publish',
					'suppress_filters' => false,
					'posts_per_page'   => -1
				)
			);

			if ( empty( $posts ) ) {
				return;
			}

			$selected = ( isset( $_GET['form-id'] ) ? sanitize_text_field($_GET['form-id']) : '' );

			echo '<select name="form-id" id="form-id">';
			echo '<option value="all">' . esc_html__( 'Select Form', 'accept-paypal-payments-using-contact-form-7' ) . '</option>';
			foreach ( $posts as $post ) {
			    echo '<option value="' . esc_attr( $post->ID ) . '" ' . selected( $selected, $post->ID, false ) . '>' . esc_html( $post->post_title ) . '</option>';
			}
			echo '</select>';

			echo '<input type="submit" id="cf7pe_export_csv" name="cf7pe_export_csv" class="button action" value="' . esc_attr__( 'Export CSV', 'accept-paypal-payments-using-contact-form-7' ) . '"> ';

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
		 * - Used to display the form data in CPT detail page.
		 *
		 * @method cfpe_show_from_data
		 *
		 * @param  object $post WP_Post
		 */
		function cfpe_show_from_data( $post ) {
			$fields = CF7PE()->lib->data_fields;
			
			$form_id = get_post_meta( $post->ID, '_form_id', true );
			$data_ct = $this->cfsazw_check_data_ct( sanitize_text_field( $post->ID ) ); 
			$_paymen_type = get_post_meta($post->ID, '_paymen_type', true );
			$subscription_canceled = get_post_meta($post->ID , 'subscription_canceled', true );
			//$_agreement_Id = get_post_meta($post->ID, '_agreement_Id', true );
			echo '<table class="cf7sa-box-data form-table">' .
				'<style>.inside-field td, .inside-field th{ padding-top: 5px; padding-bottom: 5px;} .postbox table.form-table{ word-break: break-all; }</style>';

				if ( !empty( $fields ) ) {

					if( $data_ct ){

						echo'<tr class="inside-field"><th scope="row">You are using Accept PayPal Payments using Contact Form 7 - no license needed. Enjoy! 🙂</th></tr>';
							echo'<tr class="inside-field"><th scope="row"><a href="https://store.zealousweb.com/accept-paypal-payments-using-contact-form-7-pro" target="_blank">To unlock more features consider upgrading to PRO.</a></th></tr>';

					}else{
						$attachment = ( !empty( get_post_meta( $post->ID, '_attachment', true ) ) ? '' : '' );
						$root_path = get_home_path();

						foreach ( $fields as $key => $value ) {

							if (
								!empty( get_post_meta( $post->ID, $key, true ) )
								&& $key != '_form_data'
								&& $key != '_transaction_response'
								&& $key != '_transaction_status'
							) {

								$val = get_post_meta( $post->ID, $key, true );

								echo '<tr class="form-field">' .
									'<th scope="row">' .
										'<label for="hcf_author">' . __( sprintf( '%s', $value ), 'accept-paypal-payments-using-contact-form-7' ) . '</label>' .
									'</th>' .
									'<td>' .
										(
											(
												'_form_id' == $key
												&& !empty( get_the_title( get_post_meta( $post->ID, $key, true ) ) )
											)
											? get_the_title( get_post_meta( $post->ID, $key, true ) )
											: get_post_meta( $post->ID, $key, true )
										) .
									'</td>' .
								'</tr>';

							} else if (
								!empty( get_post_meta( $post->ID, $key, true ) )
								&& $key == '_transaction_status'
							) {

								$transaction_status = get_post_meta($post->ID, '_transaction_status', true);
								echo '<tr class="form-field">' .
									     '<th scope="row">' .
									            '<label for="hcf_author">' . __( sprintf('%s', $value), 'accept-paypal-payments-using-contact-form-7' ) . '</label>' .
									       '</th>' .
									  '<td>';

								if (!empty($transaction_status)) {
								    if ($transaction_status === 'approved') {
								        echo esc_html__('Succeeded');
								    } else {
								        echo ucfirst($transaction_status);
								    }
								} else {
								    echo '';
								}

								echo '</td>' .
								    '</tr>';

							} else if (
								!empty( get_post_meta( $post->ID, $key, true ) )
								&& $key == '_form_data'
							) {

								echo '<tr class="form-field">' .
									'<th scope="row">' .
										'<label for="hcf_author">' . __( sprintf( '%s', $value ), 'accept-paypal-payments-using-contact-form-7' ) . '</label>' .
									'</th>' .
									'<td>' .
										'<table>';

											$data = unserialize( get_post_meta( $post->ID, $key, true ) );
											$hide_data = apply_filters( CF7PE_PREFIX . '/hide-display', array( '_wpcf7', '_wpcf7_version', '_wpcf7_locale', '_wpcf7_unit_tag', '_wpcf7_container_post' ) );
											foreach ( $hide_data as $key => $value ) {
												if ( array_key_exists( $value, $data ) ) {
													unset( $data[$value] );
												}
											}

											if ( !empty( $data ) ) {
												foreach ( $data as $key => $value ) {
													if ( strpos( $key, 'stripe-' ) === false ) {
														echo '<tr class="inside-field">' .
															'<th scope="row">' .
																__( sprintf( '%s', $key ), 'accept-paypal-payments-using-contact-form-7' ) .
															'</th>' .
															'<td>' .
																(
																	(
																		!empty( $attachment )
																		&& array_key_exists( $key, $attachment )
																	)
																	? '<a href="' . esc_url( home_url( str_replace( $root_path, '/', $attachment[$key] ) ) ) . '" target="_blank" download>' . __( sprintf( '%s', $value ), 'accept-paypal-payments-using-contact-form-7' ) . '</a>'
																	: __( sprintf( '%s', ( is_array( $value ) ? implode( ', ', $value ) : $value ) ), 'accept-paypal-payments-using-contact-form-7' )
																) .
															'</td>' .
														'</tr>';
													}
												}
											}

										echo '</table>' .
									'</td>
								</tr>';

							} else if (
								!empty( get_post_meta( $post->ID, $key, true ) )
								&& $key == '_transaction_response'
							) {

							echo '<tr class="form-field">' .
							'<th scope="row">' .
								'<label for="hcf_author">' . esc_html__( sprintf( '%s', $value ), 'contact-form-7-paypal-addon-pro' ) . '</label>' .
							'</th>' .
							'<td>' .
								'<code style="word-break: break-all;">' .
									(
										(
											!empty(  get_post_meta( $post->ID , $key, true ) )
											&& (
												is_array( get_post_meta( $post->ID , $key, true ) )
												|| is_object( get_post_meta( $post->ID , $key, true ) )
											)
										)
										? json_encode(  get_post_meta( $post->ID , $key, true ) )
										: esc_html(get_post_meta( $post->ID , $key, true ) )
									) .
								'</code>' .
							'</td>' .
						'</tr>';

							}
							else if( $key == '_refund_payment') {
								if(empty($_paymen_type)) {
									$transaction_status_get = '';
									$transaction_status_get = get_post_meta($post->ID, '_transaction_status', true );
										if($transaction_status_get != 'cancel') {
											$transaction_id = get_post_meta($post->ID, '_transaction_id', true );
											echo '<tr class="form-field">' .
												'<th scope="row">' .
													'<label for="hcf_author">' . esc_html__( sprintf( '%s', $value ), 'contact-form-7-paypal-addon-pro' ) . '</label>' .
												'</th>';
												if($transaction_status_get === 'refunded') {
													echo '<td>Already Refunded</td>';
												}else{
													echo '<td>'.
															'<button type="button" class="pap-refund-payment" id="pap-refund-payment">Refund Payment</button>'.
															'<input type="hidden" id="entry_id" name="entry_id" value="'.esc_attr($post->ID).'">'.
															'<input type="hidden" id="contact_form_id" name="contact_form_id" value="'.esc_attr($form_id).'">'.
															'<input type="hidden" id="transaction_id" name="transaction_id" value="'.esc_attr($transaction_id).'">'.
															'<div id="pap-refund-payment-loader" class=""></div>'.
													'</td>';
												}
											'<tr>';	
										}
								}						
							}
							
							

						}

					}

					
				}

			echo '</table>';
		}

		/**
		 * - Used to add meta box in CPT detail page.
		 */
		function cfpe_show_help_data() {
			echo '<div id="cf7sa-data-help">' .
				apply_filters(
					CF7PE_PREFIX . '/help/cf7pe_data/postbox',
					'<ol>' .
						'<li><a href="https://store.zealousweb.com/accept-paypal-payments-using-contact-form-7" target="_blank">Refer the document.</a></li>' .
						'<li><a href="https://www.zealousweb.com/contact/" target="_blank">Contact Us</a></li>' .
						'<li><a href="mailto:support@zealousweb.com">Email us</a></li>' .
					'</ol>'
				) .
			'</div>';
		}

		/**
		 * Action: admin_notices
		 *
		 * - Added use notice when trying to export without selecting the form.
		 *
		 * @method action__admin_notices_export
		 */
		function action__admin_notices_export() {
			echo '<div class="error">' .
				'<p>' .
				esc_html__( 'Please Select Form to Export.', 'accept-paypal-payments-using-contact-form-7' ) .
				'</p>' .
			'</div>';
		}

		/**
		 * Action: admin_notices
		 *
		 * - Added use notice when trying to export without selecting the form.
		 *
		 * @method action__admin_notices_export_not_found
		 */
		function action__admin_notices_export_not_found() {
			echo '<div class="error">' .
				'<p>' .
				esc_html__( 'Please Select to Form.', 'accept-paypal-payments-using-contact-form-7' ) .
				'</p>' .
			'</div>';
		}

	}

	add_action( 'plugins_loaded' , function() {
		CF7PE()->admin->action = new CF7PE_Admin_Action;
	} );
}



//Admin ajax call
function cf7pap_ajax_admin_URL() {
	$MyTemplatepath = get_stylesheet_directory_uri();
	$MyHomepath = esc_url( home_url( '/' ) );
	$admin_URL = admin_url( 'admin-ajax.php' ); // Your File Path
	return array(
	'admin_URL' => $admin_URL,
	'MyTemplatepath' => $MyTemplatepath,
	'MyHomepath' => $MyHomepath,
	'post_id' => get_the_ID()
	);
}
