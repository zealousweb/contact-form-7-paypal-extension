<?php

$post_id = ( isset( $_REQUEST[ 'post' ] ) ? sanitize_text_field( $_REQUEST[ 'post' ] ) : '' );

if ( empty( $post_id ) ) {
	$wpcf7 = WPCF7_ContactForm::get_current();
	$post_id = $wpcf7->id();
}

wp_enqueue_script( 'wp-pointer' );
 wp_enqueue_style( 'wp-pointer' );

wp_enqueue_style( CF7PE_PREFIX . '_admin_css' );

$use_paypal             = get_post_meta( $post_id, CF7PE_META_PREFIX . 'use_paypal', true );
$mode_sandbox           = get_post_meta( $post_id, CF7PE_META_PREFIX . 'mode_sandbox', true );
$sandbox_client_id      = get_post_meta( $post_id, CF7PE_META_PREFIX . 'sandbox_client_id', true );
$sandbox_client_secret  = get_post_meta( $post_id, CF7PE_META_PREFIX . 'sandbox_client_secret', true );
$live_client_id         = get_post_meta( $post_id, CF7PE_META_PREFIX . 'live_client_id', true );
$live_client_secret     = get_post_meta( $post_id, CF7PE_META_PREFIX . 'live_client_secret', true );
$amount                 = get_post_meta( $post_id, CF7PE_META_PREFIX . 'amount', true );
$email                  = get_post_meta( $post_id, CF7PE_META_PREFIX . 'email', true );
$description            = get_post_meta( $post_id, CF7PE_META_PREFIX . 'description', true );
$quantity               = get_post_meta( $post_id, CF7PE_META_PREFIX . 'quantity', true );
$mailsend               = get_post_meta( $post_id, CF7PE_META_PREFIX . 'mailsend', true );
$success_returnURL      = get_post_meta( $post_id, CF7PE_META_PREFIX . 'success_returnurl', true );
$cancle_returnURL       = get_post_meta( $post_id, CF7PE_META_PREFIX . 'cancel_returnurl', true );
$message                = get_post_meta( $post_id, CF7PE_META_PREFIX . 'message', true );
$currency               = get_post_meta( $post_id, CF7PE_META_PREFIX . 'currency', true );
$enable_on_site_payment = get_post_meta( $post_id, CF7PE_META_PREFIX . 'enable_on_site_payment', true );

$currency_code = array(
	'AUD' => 'Australian Dollar',
	'BRL' => 'Brazilian Real',
	'CAD' => 'Canadian Dollar',
	'CZK' => 'Czech Koruna',
	'DKK' => 'Danish Krone',
	'EUR' => 'Euro',
	'HKD' => 'Hong Kong Dollar',
	'HUF' => 'Hungarian Forint',
	'INR' => 'Indian Rupee',
	'ILS' => 'Israeli New Shekel',
	'JPY' => 'Japanese Yen',
	'MYR' => 'Malaysian Ringgit',
	'MXN' => 'Mexican Peso',
	'TWD' => 'New Taiwan Dollar',
	'NZD' => 'New Zealand Dollar',
	'NOK' => 'Norwegian Krone',
	'PHP' => 'Philippine Peso',
	'PLN' => 'Polish Zloty',
	'GBP' => 'Pound Sterling',
	'RUB' => 'Russian Ruble',
	'SGD' => 'Singapore Dollar',
	'SEK' => 'Swedish Krona',
	'CHF' => 'Swiss Franc',
	'THB' => 'Thai Baht',
	'USD' => 'United States Dollar',
);

$mailsendoption = array(
	'successonly'=>'Success Only',
	'both'=>'Both'
);

$selected = '';

echo '<div class="cf7pe-settings">' .
	'<div class="left-box postbox">' .
		'<table class="form-table">' .
			'<tbody>' .
				'<tr class="form-field">' .
					'<th scope="row">' .
						'<label for="' . CF7PE_META_PREFIX . 'use_paypal">' .
							__( 'Use PayPal Payment Form', 'accept-paypal-payments-using-contact-form-7' ) .
						'</label>' . 
						'<span class="cf7pe-tooltip hide-if-no-js " id="cf7pe-use-paypal"></span>' .
					'</th>' .
					'<td>' .
						'<input id="' . CF7PE_META_PREFIX . 'use_paypal" name="' . CF7PE_META_PREFIX . 'use_paypal" type="checkbox" class="enable_required" value="1" ' . checked( $use_paypal, 1, false ) . '/>' .
					'</td>' .
				'</tr>' .
				'<tr class="form-field">' .
					'<th>' .
						'<label for="' . CF7PE_META_PREFIX . 'mode_sandbox">' .
							__( 'Enable Test API Mode', 'accept-paypal-payments-using-contact-form-7' ) .
						'</label>' .
						'<span class="cf7pe-tooltip hide-if-no-js " id="cf7pe-mode-sandbox"></span>' .
					'</th>' .
					'<td>' .
						'<input id="' . CF7PE_META_PREFIX . 'mode_sandbox" name="' . CF7PE_META_PREFIX . 'mode_sandbox" type="checkbox" value="1" ' . checked( $mode_sandbox, 1, false ) . ' />' .
					'</td>' .
				'</tr>' .
				'<tr class="form-field">' .
					'<th>' .
						'<label for="' . CF7PE_META_PREFIX . 'sandbox_client_id">' .
							__( 'Sandbox PayPal Client ID (required)', 'accept-paypal-payments-using-contact-form-7' ) .
						'</label>' .
						'<span class="cf7pe-tooltip hide-if-no-js " id="cf7pe-sanbox-client-id"></span>' .
					'</th>' .
					'<td>' .
						'<input id="' . CF7PE_META_PREFIX . 'sandbox_client_id" name="' . CF7PE_META_PREFIX . 'sandbox_client_id" type="text" class="large-text" value="' . esc_attr( $sandbox_client_id ) . '" />' .
					'</td>' .
				'</tr>' .
				'<tr class="form-field">' .
					'<th>' .
						'<label for="' . CF7PE_META_PREFIX . 'sandbox_client_secret">' .
							__( 'Sandbox PayPal Client Secret (required)', 'accept-paypal-payments-using-contact-form-7' ) .
						'</label>' .
						'<span class="cf7pe-tooltip hide-if-no-js " id="cf7pe-sandbox-client-secret"></span>' .
					'</th>' .
					'<td>' .
						'<input id="' . CF7PE_META_PREFIX . 'sandbox_client_secret" name="' . CF7PE_META_PREFIX . 'sandbox_client_secret" type="text" class="large-text" value="' . esc_attr( $sandbox_client_secret ) . '" />' .
					'</td>' .
				'</tr>' .
				'<tr class="form-field">' .
					'<th>' .
						'<label for="' . CF7PE_META_PREFIX . 'live_client_id">' .
							__( 'Live PayPal Client ID (required)', 'accept-paypal-payments-using-contact-form-7' ) .
						'</label>' .
						'<span class="cf7pe-tooltip hide-if-no-js" id="cf7pe-paypal-client-id"></span>' .
					'</th>' .
					'<td>' .
						'<input id="' . CF7PE_META_PREFIX . 'live_client_id" name="' . CF7PE_META_PREFIX . 'live_client_id" type="text" class="large-text" value="' . esc_attr( $live_client_id ) . '" ' . ( empty( $mode_sandbox ) && !empty( $use_paypal ) ? 'required' : '' ) . '/>' .
					'</td>' .
				'</tr>' .
				'<tr class="form-field">' .
					'<th>' .
						'<label for="' . CF7PE_META_PREFIX . 'live_client_secret">' .
							__( 'Live PayPal Client Secret (required)', 'accept-paypal-payments-using-contact-form-7' ) .
						'</label>' .
						'<span class="cf7pe-tooltip hide-if-no-js " id="cf7pe-live-client-secret"></span>' .
					'</th>' .
					'<td>' .
						'<input id="' . CF7PE_META_PREFIX . 'live_client_secret" name="' . CF7PE_META_PREFIX . 'live_client_secret" type="text" class="large-text" value="' . esc_attr( $live_client_secret ) . '" ' . ( empty( $mode_sandbox ) && !empty( $use_paypal ) ? 'required' : '' ) . '/>' .
					'</td>' .
				'</tr>' .
				'<tr class="form-field">' .
					'<th>' .
						'<label for="' . CF7PE_META_PREFIX . 'amount">' .
							__( 'Amount Field Name (required)', 'accept-paypal-payments-using-contact-form-7' ) .
						'</label>' .
						'<span class="cf7pe-tooltip hide-if-no-js" id="cf7pe-amount-field"></span>' .
					'</th>' .
					'<td>' .
						'<input id="' . CF7PE_META_PREFIX . 'amount" class="form-required-fields" name="' . CF7PE_META_PREFIX . 'amount" type="text" value="' . esc_attr( $amount ) . '" ' . ( !empty( $use_paypal ) ? 'required' : '' ) . '/>' .
					'</td>' .
				'</tr>' .
				'<tr class="form-field">' .
					'<th>' .
						'<label for="' . CF7PE_META_PREFIX . 'quantity">' .
							__( 'Quantity Field Name (Optional)', 'accept-paypal-payments-using-contact-form-7' ) .
						'</label>' .
						'<span class="cf7pe-tooltip hide-if-no-js " id="cf7pe-quantity"></span>' .
					'</th>' .
					'<td>' .
						'<input id="' . CF7PE_META_PREFIX . 'quantity" name="' . CF7PE_META_PREFIX . 'quantity" type="text" value="' . esc_attr( $quantity ) . '" />' .
					'</td>' .
				'</tr>' .
				'<tr class="form-field">' .
						'<th>' .
							'<label for="' . CF7PE_META_PREFIX . 'email">' .
								__( 'Customer Email Field Name (Optional)', 'accept-paypal-payments-using-contact-form-7' ) .
							'</label>' .
							'<span class="cf7pe-tooltip hide-if-no-js " id="cf7pe-email"></span>' .
						'</th>' .
						'<td>' .
							'<input class="cf7sa_cus_css" id="' . CF7PE_META_PREFIX . 'email" name="' . CF7PE_META_PREFIX . 'email" type="text" value="' . esc_attr( $email ) . '" ' . ( !empty( $email ) ? 'required' : '' ) . ' />' .
						'</td>' .
					'</tr>' .
				'<tr class="form-field">' .
					'<th>' .
						'<label for="' . CF7PE_META_PREFIX . 'description">' .
							__( 'Description Field Name (Optional)', 'accept-paypal-payments-using-contact-form-7' ) .
						'</label>' .
						'<span class="cf7pe-tooltip hide-if-no-js " id="cf7pe-description"></span>' .
					'</th>' .
					'<td>' .
						'<input id="' . CF7PE_META_PREFIX . 'description" name="' . CF7PE_META_PREFIX . 'description" type="text" value="' . esc_attr( $description ) . '" />' .
					'</td>' .
				'</tr>' .
	 			'<tr class="form-field">' .
					'<th>' .
						'<label for="' . CF7PE_META_PREFIX . 'currency">' .
							__( 'Select Currency', 'accept-paypal-payments-using-contact-form-7' ) .
						'</label>' .
						'<span class="cf7pe-tooltip hide-if-no-js" id="cf7pe-currency-select"></span>' .
					'</th>' .
					'<td>' .
						'<select id="' . CF7PE_META_PREFIX . 'currency" name="' . CF7PE_META_PREFIX . 'currency">';

							if ( !empty( $currency_code ) ) {
								foreach ( $currency_code as $key => $value ) {
									echo '<option value="' . esc_attr( $key ) . '" ' . selected( $currency, $key, false ) . '>' . esc_attr( $value ) . '</option>';
								}
							}

						echo '</select>' .
					'</td>' .
				'</tr/>' .
				'<tr class="form-field">' .
					'<th>' .
						'<label for="' . CF7PE_META_PREFIX . 'success_returnurl">' .
							__( 'Success Return URL (Optional)', 'accept-paypal-payments-using-contact-form-7' ) .
						'</label>' .
                        '<span class="cf7pe-tooltip hide-if-no-js" id="cf7pe-success-url"></span>'.
					'</th>' .
					'<td>' .
						'<input id="' . CF7PE_META_PREFIX . 'success_returnurl" name="' . CF7PE_META_PREFIX . 'success_returnurl"  type="text" class="regular-text" value="' . esc_attr( $success_returnURL ) . '" />' .
					'</td>' .
				'</tr>' .
				'<tr class="form-field">' .
					'<th>' .
						'<label for="' . CF7PE_META_PREFIX . 'cancel_returnurl">' .
							__( 'Cancel Return URL (Optional)', 'accept-paypal-payments-using-contact-form-7' ) .
						'</label>' .
						'<span class="cf7pe-tooltip hide-if-no-js " id="cf7pe-cancel-returnurl"></span>' .
					'</th>' .
					'<td>' .
						'<input id="' . CF7PE_META_PREFIX . 'cancel_returnurl" name="' . CF7PE_META_PREFIX . 'cancel_returnurl" type="text" class="regular-text" value="' . esc_attr( $cancle_returnURL ) . '" />' .
					'</td>' .
				'</tr>';
				/**
				 * - On-site Payment Methods
				 *
				 * @var int $post_id
				 */
				echo '<tr class="form-field">' .
					 '<th colspan="2">' .
						 '<label for="' . CF7PE_META_PREFIX . 'on-site-payment">' .
							 '<h3 style="margin: 0;">' .
								 __( 'On Site Payment', 'accept-paypal-payments-using-contact-form-7' ) .
								 '<span class="arrow-switch"></span>' .
							 '</h3>' .
						 '</label>' .
					 '</th>' .
				'</tr>'.
				'<tr class="form-field">' .
					'<th scope="row">' .
						'<label for="' . CF7PE_META_PREFIX . 'enable_on_site_payment">' .
							__( 'Enable On Site Payment', 'accept-paypal-payments-using-contact-form-7' ) .
						'</label>' .
						'<span class="cf7pe-tooltip hide-if-no-js" id="cf7pe-on-site-payment"></span>' .
						__( '</br>Requires "On Site Payment" tag in CF7', 'accept-paypal-payments-using-contact-form-7' ) .
					'</th>' .
					'<td>' .
						'<input id="' . CF7PE_META_PREFIX . 'enable_on_site_payment" name="' . CF7PE_META_PREFIX . 'enable_on_site_payment" type="checkbox" class="enable_required" value="1" ' . checked( $enable_on_site_payment, 1, false ) . '/>' .
					'</td>' .
				'</tr>';
				echo '<input type="hidden" name="post" value="' . esc_attr( $post_id ) . '">' .
			'</tbody>' .
		'</table>' .
	'</div>' .
	'<div class="right-box">' .
		'<div id="configuration-help" class="postbox">' .
			apply_filters(
				CF7PE_PREFIX . '/postbox',
				'<h3>' . __( 'Do you need help for configuration?', CF7PE_PREFIX ) . '</h3>' .
				'<p></p>' .
				'<ol>' .
					'<li><a href="https://store.zealousweb.com/accept-paypal-payments-using-contact-form-7" target="_blank">Refer the document.</a></li>' .
					'<li><a href="https://www.zealousweb.com/contact/" target="_blank">Contact Us</a></li>' .
					'<li><a href="mailto:support@zealousweb.com">Email us</a></li>' .
				'</ol>'
			) .
		'</div>' .
	'</div>' .
'</div>';


add_action('admin_print_footer_scripts', function() {
	ob_start();
	?>
	<script type="text/javascript">
		//<![CDATA[
		jQuery(document).ready( function($) {

			//jQuery selector to point to
			jQuery( '#cf7pe-use-paypal' ).on( 'mouseenter click', function() {
				jQuery( 'body .wp-pointer-buttons .close' ).trigger( 'click' );
				jQuery( '#cf7pe-use-paypal' ).pointer({
					pointerClass: 'wp-pointer cf7adn-pointer',
					content: '<?php
					echo '<h3>'. esc_html__('Enable PayPal Payment','accept-paypal-payments-using-contact-form-7') .'</h3>'.
					'<p>'.esc_html__('To make enable PayPal Payment with this Form.','accept-paypal-payments-using-contact-form-7') .'</p>';?>',
					position: 'left center',
				} ).pointer('open');
			} );
			jQuery( '#cf7pe-mode-sandbox' ).on( 'mouseenter click', function() {
				jQuery( 'body .wp-pointer-buttons .close' ).trigger( 'click' );
				jQuery( '#cf7pe-mode-sandbox' ).pointer({
					pointerClass: 'wp-pointer cf7adn-pointer',
					content: '<?php
						echo '<h3>'. esc_html__('Sandbox mode','accept-paypal-payments-using-contact-form-7') .'</h3>'.
						'<p>'.esc_html__('Check the PayPal testing guide','accept-paypal-payments-using-contact-form-7').' <a href="https://developer.paypal.com/tools/sandbox/" target="_blank">' .esc_html__('here','accept-authorize-net-payments-using-contact-form-7'). '</a> '. esc_html__('This will display "sandbox mode" warning on checkout.','accept-paypal-payments-using-contact-form-7').'</p>'; ?>',
					position: 'left center',
				} ).pointer('open');
			} );
			jQuery( '#cf7pe-sanbox-client-id' ).on( 'mouseenter click', function() {
			    jQuery( 'body .wp-pointer-buttons .close' ).trigger( 'click' );
			    jQuery( '#cf7pe-sanbox-client-id' ).pointer({
			        pointerClass: 'wp-pointer cf7pe-pointer',
			        content: '<?php
			            echo '<h3>' . esc_html__( 'Get your API test credentials', 'accept-paypal-payments-using-contact-form-7' ) . '</h3>' .
			                 '<p>' . esc_html__( 'The PayPal Developer site also assigns each sandbox Business account a set of test API credentials. Log in to the PayPal Developer site and navigate to the ', 'accept-paypal-payments-using-contact-form-7' ) . 
			                 '<a href="https://developer.paypal.com/developer/accounts/" target="_blank">' . esc_html__( 'Sandbox Accounts', 'accept-paypal-payments-using-contact-form-7' ) . '</a>' . 
			                 esc_html__( ' page or ', 'accept-paypal-payments-using-contact-form-7' ) . '<strong>' . esc_html__( 'Dashboard > Sandbox > Accounts', 'accept-paypal-payments-using-contact-form-7' ) . '</strong>. ' . 
			                 esc_html__( 'View your test API credentials by clicking the expand icon next to the Business account that you want to use in your request. Then, navigate to the ', 'accept-paypal-payments-using-contact-form-7' ) . 
			                 '<strong>' . esc_html__( 'Profile > API credentials', 'accept-paypal-payments-using-contact-form-7' ) . '</strong> ' . 
			                 esc_html__( 'tab of the sandbox account.', 'accept-paypal-payments-using-contact-form-7' ) . '</p>' . 
			                 '<p><a href="https://developer.paypal.com/docs/api/sandbox/sb-credentials/" target="_blank">' . esc_html__( 'More Info', 'accept-paypal-payments-using-contact-form-7' ) . '</a></p>';
			        ?>',
			        position: 'left center',
			    }).pointer('open');
			});
			jQuery('#cf7pe-sandbox-client-secret').on('mouseenter click', function() {
			    jQuery('body .wp-pointer-buttons .close').trigger('click');
			    jQuery('#cf7pe-sandbox-client-secret').pointer({
			        pointerClass: 'wp-pointer cf7adn-pointer',
			        content: '<?php echo '<h3>' . esc_html__('Get Your Sandbox Client Secret', 'accept-paypal-payments-using-contact-form-7') . '</h3>' .
			            '<p>' . esc_html__('Get it from', 'accept-paypal-payments-using-contact-form-7') . ' <a href="https://developer.paypal.com/api/rest/" target="_blank">' . esc_html__('Sandbox Paypal', 'accept-paypal-payments-using-contact-form-7') . '</a> ' . esc_html__('then ', 'accept-paypal-payments-using-contact-form-7') . esc_html__(' page in your PayPal account. you cannot view your Client Secret','accept-paypal-payments-using-contact-form-7') . '</p>'; ?>',
			        position: 'left center',
			    }).pointer('open');
			});
			jQuery( '#cf7pe-paypal-client-id' ).on( 'mouseenter click', function() {
			    jQuery( 'body .wp-pointer-buttons .close' ).trigger( 'click' );
			    jQuery( '#cf7pe-paypal-client-id' ).pointer({
			        pointerClass: 'wp-pointer cf7pe-pointer',
			        content: '<?php
			            echo '<h3>' . esc_html__('Get your REST API credentials', 'accept-paypal-payments-using-contact-form-7') . '</h3>' .
			                 '<p>' . esc_html__('You can view and manage the REST API sandbox and live credentials on the PayPal Developer site', 'accept-paypal-payments-using-contact-form-7') . 
			                 ' <a href="https://developer.paypal.com/developer/applications/" target="_blank"><strong>' . esc_html__('My Apps & Credentials', 'accept-paypal-payments-using-contact-form-7') . '</strong></a> ' .
			                 esc_html__('page. Within the setting for each of your apps, use', 'accept-paypal-payments-using-contact-form-7') . 
			                 ' <strong>' . esc_html__('Live', 'accept-paypal-payments-using-contact-form-7') . '</strong> ' . 
			                 esc_html__('toggle in the top right corner of the app settings page to view the API credentials and default PayPal account for each of these environments. If you have not created an app, navigate to the', 'accept-paypal-payments-using-contact-form-7') . 
			                 ' <a href="https://developer.paypal.com/developer/applications/" target="_blank"><strong>' . esc_html__('My Apps & Credentials', 'accept-paypal-payments-using-contact-form-7') . '</strong></a> ' . 
			                 esc_html__('page.', 'accept-paypal-payments-using-contact-form-7') . '</p>' . 
			                 '<p><a href="https://developer.paypal.com/docs/api/overview/#get-credentials" target="_blank">' . esc_html__('More Info', 'accept-paypal-payments-using-contact-form-7') . '</a></p>';
			        ?>',
			        position: 'left center',
			    }).pointer('open');
			});
			jQuery('#cf7pe-live-client-secret').on('mouseenter click', function() {
			    jQuery('body .wp-pointer-buttons .close').trigger('click');
			    jQuery('#cf7pe-live-client-secret').pointer({
			        pointerClass: 'wp-pointer cf7adn-pointer',
			        content: '<?php echo '<h3>' . esc_html__('Get Your Live Client Secret', 'accept-paypal-payments-using-contact-form-7') . '</h3>' .
			            '<p>' . esc_html__('Get it from', 'accept-paypal-payments-using-contact-form-7') . ' <a href="https://developer.paypal.com/api/rest/" target="_blank">' . esc_html__('PayPal', 'accept-paypal-payments-using-contact-form-7') . '</a> ' . esc_html__(' then', 'accept-paypal-payments-using-contact-form-7') .esc_html__(' page in your PayPal account.', 'accept-authorize-net-payments-using-contact-form-7') . '</p>'; ?>',
			        position: 'left center',
			    }).pointer('open');
			});
			jQuery( '#cf7pe-amount-field' ).on( 'mouseenter click', function() {
			    jQuery( 'body .wp-pointer-buttons .close' ).trigger( 'click' );
			    jQuery( '#cf7pe-amount-field' ).pointer({
			        pointerClass: 'wp-pointer cf7pe-pointer',
			        content: '<?php
			            echo '<h3>' . esc_html__('Amount Field name', 'accept-paypal-payments-using-contact-form-7') . '</h3>' .
			                 '<p>' . esc_html__('Enter the name of the field from where amount value needs to be retrieved.', 'accept-paypal-payments-using-contact-form-7') . 
			                 '</p>';
			        ?>',
			        position: 'left center',
			    }).pointer('open');
			});
			jQuery('#cf7pe-quantity').on('mouseenter click', function() {
			    jQuery('body .wp-pointer-buttons .close').trigger('click');
			    jQuery('#cf7pe-quantity').pointer({
			        pointerClass: 'wp-pointer cf7adn-pointer',
			        content: '<?php echo '<h3>' . esc_html__('Add Quantity Field Name', 'accept-paypal-payments-using-contact-form-7') . '</h3>' .
			            '<p>' . esc_html__('Add here the Name of quantity field created in Form.', 'accept-paypal-payments-using-contact-form-7') . '</p>' .
			            '<p><strong><span style="color:red">' . esc_html__('Note:', 'accept-paypal-payments-using-contact-form-7') . '</span> ' . esc_html__('Save the FORM details to view the list of fields.', 'accept-paypal-payments-using-contact-form-7') . '</strong></p>'; ?>',
			        position: 'left center',
			    }).pointer('open');
			});
			jQuery('#cf7pe-email').on('mouseenter click', function() {
			    jQuery('body .wp-pointer-buttons .close').trigger('click');
			    jQuery('#cf7pe-email').pointer({
			        pointerClass: 'wp-pointer cf7adn-pointer',
			        content: '<?php echo '<h3>' . esc_html__('Add Customer Email Field Name', 'accept-paypal-payments-using-contact-form-7') . '</h3>' .
			            '<p>' . esc_html__('Add here the Name of customer email field created in Form.', 'accept-paypal-payments-using-contact-form-7') . '</p>' .
			            '<p><strong><span style="color:red">' . esc_html__('Note:', 'accept-paypal-payments-using-contact-form-7') . '</span> ' . esc_html__('Save the FORM details to view the list of fields.', 'accept-paypal-payments-using-contact-form-7') . '</strong></p>'; ?>',
			        position: 'left center',
			    }).pointer('open');
			});
			jQuery('#cf7pe-description').on('mouseenter click', function() {
			    jQuery('body .wp-pointer-buttons .close').trigger('click');
			    jQuery('#cf7pe-description').pointer({
			        pointerClass: 'wp-pointer cf7adn-pointer',
			        content: '<?php echo '<h3>' . esc_html__('Add Description Field Name', 'accept-paypal-payments-using-contact-form-7') . '</h3>' .
			            '<p>' . esc_html__('Add here the Name of description field created in Form.', 'accept-paypal-payments-using-contact-form-7') . '</p>'; ?>',
			        position: 'left center',
			    }).pointer('open');
			});
			jQuery( '#cf7pe-currency-select' ).on( 'mouseenter click', function() {
			    jQuery( 'body .wp-pointer-buttons .close' ).trigger( 'click' );
			    jQuery( '#cf7pe-currency-select' ).pointer({
			        pointerClass: 'wp-pointer cf7pe-pointer',
			        content: '<?php
			            echo '<h3>' . esc_html__('Payouts Country and Currency Codes', 'accept-paypal-payments-using-contact-form-7') . '</h3>' .
			                 '<p>' . esc_html__('This currency is supported as a payment currency and a currency balance for in-country PayPal accounts only.', 'accept-paypal-payments-using-contact-form-7') . '</p>' . 
			                 '<p><a href="https://developer.paypal.com/docs/api/reference/currency-codes/" target="_blank">' . esc_html__('More Info', 'accept-paypal-payments-using-contact-form-7') . '</a></p>';
			        ?>',
			        position: 'left center',
			    }).pointer('open');
			});

			jQuery( '#cf7pe-success-url' ).on( 'mouseenter click', function() {
			    jQuery( 'body .wp-pointer-buttons .close' ).trigger( 'click' );
			    jQuery( '#cf7pe-success-url' ).pointer({
			        pointerClass: 'wp-pointer cf7pe-pointer',
			        content: '<?php
			            echo '<h3>' . esc_html__('Auto redirect on success payment', 'accept-paypal-payments-using-contact-form-7') . '</h3>' .
			                 '<p>1) ' . esc_html__('Go to the PayPal website and log in to your account.', 'accept-paypal-payments-using-contact-form-7') . '</p>' .
			                 '<p>2) ' . esc_html__('Click "Profile" at the top of the page.', 'accept-paypal-payments-using-contact-form-7') . '</p>' .
			                 '<p>3) ' . esc_html__('Click "Website Payments" at the sidebar of the page.', 'accept-paypal-payments-using-contact-form-7') . '</p>' .
			                 '<p>4) ' . esc_html__('Click "Website Preferences".', 'accept-paypal-payments-using-contact-form-7') . '</p>' .
			                 '<p>5) ' . esc_html__('Click the Auto Return "On" button.', 'accept-paypal-payments-using-contact-form-7') . '</p>' .
			                 '<p>6) ' . esc_html__('Review the Return URL Requirements.', 'accept-paypal-payments-using-contact-form-7') . '</p>' .
			                 '<p>7) ' . esc_html__('Enter the Return URL.', 'accept-paypal-payments-using-contact-form-7') . '</p>' .
			                 '<p>8) ' . esc_html__('Click the Payment data transfer "On".', 'accept-paypal-payments-using-contact-form-7') . '</p>' .
			                 '<p>9) ' . esc_html__('Click "Save".', 'accept-paypal-payments-using-contact-form-7') . '</p>';
			        ?>',
			        position: 'left center',
			    }).pointer('open');
			});
			jQuery('#cf7pe-cancel-returnurl').on('mouseenter click', function() {
			    jQuery('body .wp-pointer-buttons .close').trigger('click');
			    jQuery('#cf7pe-cancel-returnurl').pointer({
			        pointerClass: 'wp-pointer cf7adn-pointer',
			        content: '<?php echo '<h3>' . esc_html__('Cancel Page URL', 'accept-paypal-payments-using-contact-form-7') . '</h3>' .
			            '<p>' .	 esc_html__('Here is the list of all pages. You need to create your Cancel Page and Add that page from this link, when any payment is canceled, it will redirect to this Cancel Page.', 'accept-paypal-payments-using-contact-form-7') . '</p>'; ?>',
			        position: 'left center',
			    }).pointer('open');
			});
			jQuery('#cf7pe-on-site-payment').on('mouseenter click', function() {
			    jQuery('body .wp-pointer-buttons .close').trigger('click');
			    jQuery('#cf7pe-on-site-payment').pointer({
			        pointerClass: 'wp-pointer cf7adn-pointer',
			         content: '<?php
		                echo '<h3>' . esc_html__('On Site Payment', 'accept-paypal-payments-using-contact-form-7') . '</h3>' .
		                     '<p><strong>' . esc_html__('Make the "On Site Payment" Tag Mandatory in Contact Form 7', 'accept-paypal-payments-using-contact-form-7') .'</strong>'.
		                     ' ' . esc_html__('Accept PayPal payments directly on your website without redirecting customers.', 'accept-paypal-payments-using-contact-form-7') . '</p>';
		            ?>',
			        position: 'left center',
			    }).pointer('open');
			});

		} );
		//]]>
	</script>
	<?php
	echo ob_get_clean();
} );
