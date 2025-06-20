<?php
/**
 * CF7PE Class
 *
 * Handles the plugin functionality.
 *
 * @package WordPress
 * @subpackage Accept PayPal Payments using Contact Form 7
 * @since 3.5
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;


if ( !class_exists( 'CF7PE' ) ) {

	/**
	 * The main CF7PE class
	 */
	class CF7PE {

		private static $_instance = null;

		var $admin = null,
		    $front = null,
		    $lib   = null;

		public static function instance() {

			if ( is_null( self::$_instance ) )
				self::$_instance = new self();

			return self::$_instance;
		}

		function __construct() {

			add_action( 'init', array( $this, 'action__init' ) );

			// Action to load plugin text domain
			add_action( 'plugins_loaded', array( $this, 'action__plugins_loaded' ) );

			// Register plugin activation hook
			register_activation_hook( CF7PE_FILE, array( $this, 'action__plugin_activation' ) );

			// Action to display notice
			add_action( 'admin_notices', array( $this, 'action__admin_notices' ) );

			add_action( 'wpcf7_admin_init', array( $this, 'action__wpcf7_admin_init_paypal_tags' ), 15, 0 );

			add_action( 'wpcf7_init', array( $this, 'action__wpcf7_fronted_tag_generate' ), 10, 0 );

			// AJAX handler for creating orders
			add_action( 'wp_ajax_cf7pap_create_order', array( $this, 'cf7pap_create_order' ));
			add_action( 'wp_ajax_nopriv_cf7pap_create_order', array( $this, 'cf7pap_create_order' ));

			// Add nonce verification for security
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_payment_scripts' ) );
		}

		/**
		 * Enqueue payment scripts and localize data
		 */
		function enqueue_payment_scripts() {
			if (!is_admin()) {
				wp_enqueue_script('cf7pap-front', CF7PE_URL . 'assets/js/front.js', array('jquery'), CF7PE_VERSION, true);
				
				wp_localize_script('cf7pap-front', 'CF7PAP_ajax_object', array(
					'ajax_url' => admin_url('admin-ajax.php'),
					'nonce' => wp_create_nonce('cf7pap_ajax_nonce')
				));
			}
		}

		/**
		 * action__wpcf7_admin_init_paypal_tags	 
		*/

		function action__wpcf7_admin_init_paypal_tags() {

			$tag_generator = WPCF7_TagGenerator::get_instance();
			$tag_generator->add(
				'onsitepayment',
				__( 'On Site Payment', 'accept-paypal-payments-using-contact-form-7' ),
				array( $this, 'wpcf7_tag_generator_paypal_onsitepayment' ));
		}

		/**
		 * wpcf7_tag_generator_stripe_net_paypal_onsitepayment 
		 * Paypal Method Popup tag
		 */
		function wpcf7_tag_generator_paypal_onsitepayment( $contact_form, $args = '',$tag='') {
		
		$args = wp_parse_args( $args, array() );
		$type = $args['id'];
		$description = __( "Generate a form-tag for to display On-Site payment", 'accept-paypal-payments-using-contact-form-7' );
		?>
			<div class="control-box">
				<fieldset>
					<legend><?php echo esc_html( $description ); ?></legend>

					<table class="form-table">
						<tbody>
							<tr>
							<th scope="row"><label for="<?php echo esc_attr( $args['content'] . '-name' ); ?>"><?php echo esc_html( __( 'Name', 'contact-form-7' ) ); ?></label></th>
							<td>
								<legend class="screen-reader-text"><input type="checkbox" name="required" value="on" checked="checked" /></legend>
								<input type="text" name="name" class="tg-name oneline" id="<?php echo esc_attr( $args['content'] . '-name' ); ?>" /></td>
							</tr>
							
							<tr>
								<th scope="row"><label for="<?php echo esc_attr($args['content'] . '-class'); ?>"><?php echo esc_html(__('Class attribute', 'contact-form-7')); ?></label></th>
								<td><input type="text" name="class" class="classvalue oneline option" id="<?php echo esc_attr($args['content'] . '-class'); ?>" /></td>
							</tr>

						</tbody>
					</table>
				</fieldset>
			</div>

			<div class="insert-box">
				<input type="text" name="<?php echo esc_attr($type); ?>" class="tag code" readonly="readonly" onfocus="this.select()"/>

				<div class="submitbox">
					<input type="button" class="button button-primary insert-tag" value="<?php echo esc_attr( __( 'Insert Tag', 'contact-form-7' ) ); ?>" />
				</div>

				<br class="clear" />

				<p class="description mail-tag">
					<label for="<?php echo esc_attr( $args['content'] . '-mailtag' ); ?>">
						<?php echo sprintf( esc_html( __( "To use the value input through this field in a mail field, you need to insert the corresponding mail-tag (%s) into the field on the Mail tab.", 'contact-form-7' ) ), '<strong><span class="mail-tag"></span></strong>' ); ?>
						<input type="text" class="mail-tag code hidden" readonly="readonly" id="<?php echo esc_attr( $args['content'] . '-mailtag' ); ?>" />
					</label>
				</p>
			</div>
		<?php
		}

		/**
		 * action__wpcf7_fronted_tag_generate 
		*/
		function action__wpcf7_fronted_tag_generate(){
			/* On sitepayment Mehtod Frontend tags */
            wpcf7_add_form_tag( array( 'onsitepayment', 'onsitepayment*' ), array( $this, 'wpcf7_add_form_tag_onsitepayment_method' ), array( 'name-attr' => true ) );

		}

		function wpcf7_add_form_tag_onsitepayment_method( $tag ) {
			
			if ( empty( $tag->name ) ) {
				return '';
			}

			$validation_error = wpcf7_get_validation_error( $tag->name );
			$class = wpcf7_form_controls_class( $tag->type, 'wpcf7-text' );
			
			if (
				in_array(
					$tag->basetype,
					array(
						'email',
						'url',
						'tel'
					)
				)
			) {
				$class .= ' wpcf7-validates-as-' . $tag->basetype;
			}

			if ( $validation_error ) {
				$class .= ' wpcf7-not-valid';
			}

			$atts = array();

			if ( $tag->is_required() ) {
				$atts['aria-required'] = 'true';
			}

			$atts['aria-invalid'] = $validation_error ? 'true' : 'false';

			$atts['value'] = 1;

			$atts['type'] = 'hidden';
			$atts['name'] = $tag->name;
			$atts         = wpcf7_format_atts($atts);

			$form_instance = WPCF7_ContactForm::get_current();
			$form_id       = $form_instance->id();
			$use_paypal           = trim(get_post_meta( $form_id, CF7PE_META_PREFIX . 'use_paypal', true ));
            $mode_sandbox           = trim(get_post_meta( $form_id, CF7PE_META_PREFIX . 'mode_sandbox', true ));
            $sandbox_client_id      = get_post_meta( $form_id, CF7PE_META_PREFIX . 'sandbox_client_id', true );
            $live_client_id         = get_post_meta( $form_id, CF7PE_META_PREFIX . 'live_client_id', true );
            $currency               = get_post_meta( $form_id, CF7PE_META_PREFIX . 'currency', true );
			$enable_on_site_payment = get_post_meta( $form_id, CF7PE_META_PREFIX . 'enable_on_site_payment', true );

            if(!empty($mode_sandbox)) {
                $client_id = $sandbox_client_id;
            }else{
                $client_id = $live_client_id;
            }
			
			$value = ( string ) reset( $tag->values );
			$found = 0;
			$html  = '';

			ob_start();

			if ( $contact_form = wpcf7_get_current_contact_form() ) {
				$form_tags = $contact_form->scan_form_tags();
				
				foreach ( $form_tags as $k => $v ) {
				
					if ( $v['type'] == $tag->type ) {
						$found++;
					}

					if ( $v['name'] == $tag->name ) {
						
							$attributes = $tag->options;
							$class = '';
							$id = '';
							foreach ($attributes as $attribute) {
								$parts = explode(':', $attribute);
								$attribute_name = $parts[0];
								$attribute_value = $parts[1];

								if ($attribute_name === 'class') {
									$class = $attribute_value;
								} elseif ($attribute_name === 'id') {
									$id = $attribute_value;
								}
							}
							$id = (!empty($id)) ? 'id="' . $id . '"' : '';
							$class = (!empty($class)) ? 'class="' . $class . '"' : '';

							if ( $found <= 1 ) { 
								if(!empty($enable_on_site_payment) && !empty($use_paypal)) { 
									?>
									<script src="https://www.paypal.com/sdk/js?client-id=<?php echo $client_id; ?>&components=card-fields&currency=<?php echo $currency; ?>"></script>
									<div class="panel">
										<div class="panel-body">
											<div id="paymentResponse" class="hidden"></div>
											<div id="checkout-form">
												<div id="card-name-field-container"></div>
												<div id="card-number-field-container"></div>
												<div id="card-expiry-field-container"></div>
												<div id="card-cvv-field-container"></div>
											</div>
										</div>
									</div>
								<?php } else{
									echo '['.$tag->type. ' ' .$tag->name. ' '  .$class. ']';
								}
							}
						break;
					}
				}
			}
			return ob_get_clean();
		}


		function action__init() {

			include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
			if ( !is_plugin_active( 'contact-form-7/wp-contact-form-7.php' ) ) {
				add_action( 'admin_notices', array( $this, 'action__admin_notices_deactive' ) );
				deactivate_plugins( CF7PE_PLUGIN_BASENAME );
			}
            // Load Paypal SDK on int action
			require __DIR__ . '/lib/sdk/autoload.php';


			/**
			 * Post Type: paypal Add-on.
			 */

			 $labels = array(
				'name' => __( 'Paypal Add-on', 'accept-paypal-payments-using-contact-form-7' ),
				'singular_name' => __( 'Paypal Add-on', 'accept-paypal-payments-using-contact-form-7' ),
				'not_found'          => __( 'No Transactions Found.', 'accept-paypal-payments-using-contact-form-7' ),
			);

			$args = array(
				'label' => __( 'Paypal Add-on', 'accept-paypal-payments-using-contact-form-7' ),
				'labels' => $labels,
				'description' => '',
				'public' => false,
				'publicly_queryable' => false,
				'show_ui' => true,
				'delete_with_user' => false,
				'show_in_rest' => false,
				'rest_base' => '',
				'has_archive' => false,
				'show_in_menu' => 'wpcf7',
				'show_in_nav_menus' => false,
				'exclude_from_search' => true,
				'capability_type' => 'post',
				'capabilities' => array(
					'read' => true,
					'create_posts'  => false,
					'publish_posts' => false,
				),
				'map_meta_cap' => true,
				'hierarchical' => false,
				'rewrite' => false,
				'query_var' => false,
				'supports' => array( 'title' ),
			);

			register_post_type( 'cf7pe_data', $args );
		}

		/**
		 * Load Text Domain
		 * This gets the plugin ready for translation
		 */
		function action__plugins_loaded() {
			global $wp_version;

			// Set filter for plugin's languages directory
			$cf7pe_lang_dir = dirname( CF7PE_PLUGIN_BASENAME ) . '/languages/';
			$cf7pe_lang_dir = apply_filters( 'cf7pe_languages_directory', $cf7pe_lang_dir );

			// Traditional WordPress plugin locale filter.
			$get_locale = get_locale();

			if ( $wp_version >= 4.7 ) {
				$get_locale = get_user_locale();
			}

			// Traditional WordPress plugin locale filter
			$locale = apply_filters( 'plugin_locale',  $get_locale, 'accept-paypal-payments-using-contact-form-7' );
			$mofile = sprintf( '%1$s-%2$s.mo', 'accept-paypal-payments-using-contact-form-7', $locale );

			// Setup paths to current locale file
			$mofile_global = WP_LANG_DIR . '/plugins/' . basename( CF7PE_DIR ) . '/' . $mofile;

			if ( file_exists( $mofile_global ) ) {
				// Look in global /wp-content/languages/plugin-name folder
				load_textdomain( 'accept-paypal-payments-using-contact-form-7', $mofile_global );
			} else {
				// Load the default language files
				load_plugin_textdomain( 'accept-paypal-payments-using-contact-form-7', false, $cf7pe_lang_dir );
			}
		}

		function action__plugin_activation() {

			// Deactivate Pro Version
			if ( is_plugin_active( 'contact-form-7-paypal-addons-pro/contact-form-7-paypal-addons-pro.php' ) ) {
				add_action( 'update_option_active_plugins', array( $this, 'action__update_option_active_plugins' ) );
			}
		}

		/**
		 * Deactivate lite (Free) version of plugin
		 */
		function action__update_option_active_plugins() {
			deactivate_plugins( 'contact-form-7-paypal-addons-pro/contact-form-7-paypal-addons-pro.php', true );
			if(!get_option('_exceed_cfpezw_l')){
				add_option('_exceed_cfpezw_l', 'cfpezw10');
			}
		}

		/**
		 * Function to display admin notice of activated plugin.
		 */
		function action__admin_notices() {

			if ( !is_plugin_active( CF7PE_PLUGIN_BASENAME ) ) {
				return;
			}

			global $pagenow;

			$dir                = WP_PLUGIN_DIR . '/contact-form-7-paypal-addons-pro/contact-form-7-paypal-addons-pro.php';
			$notice_link        = add_query_arg( array( 'message' => 'cf7pe-plugin-notice' ), admin_url( 'plugins.php' ) );
			$notice_transient   = get_transient( 'cf7pe_install_notice' );

			// If PRO plugin is active and free plugin exist
			if (
				false === $notice_transient
				&& 'plugins.php' == $pagenow
				&& file_exists( $dir )
				&& current_user_can( 'install_plugins' )
			) {
				echo '<div class="updated notice is-dismissible" style="position:relative;">' .
					'<p>' .
						'<strong>' .
							sprintf(
								/* translators: Accept PayPal Payments using Contact Form 7 */
								wp_kses( 'Thank you for activating %s', 'accept-paypal-payments-using-contact-form-7' ),
								'Accept PayPal Payments using Contact Form 7- Paypal Add-on'
							) .
						'</strong>.<br/>' .
						sprintf(
							/* translators: Accept PayPal Payments using Contact Form 7 PRO */
							wp_kses( 'It looks like you had PRO version %s of this plugin activated. To avoid conflicts the extra version has been deactivated and we recommend you delete it.', 'accept-paypal-payments-using-contact-form-7' ),
							'<strong>(<em>Accept PayPal Payments using Contact Form 7 PRO</em>)</strong>'
						) .
					'</p>' .
					'<a href="' . esc_url( $notice_link ) . '" class="notice-dismiss" style="text-decoration:none;"></a>' .
				'</div>';
			}
		}

		function action__admin_notices_deactive() {
			$screen = get_current_screen();
			$allowed_screen = array( 'plugins' );
			if ( !in_array( $screen->id, $allowed_screen ) ) {
				return;
			}
			$plugin = plugin_basename( __FILE__ );
			if ( is_plugin_active( $plugin ) ) {
				deactivate_plugins( $plugin );
			}
		}

		/**
		 * AJAX handler for creating PayPal orders
		 */
		function cf7pap_create_order() {
			$response = array('status' => 0, 'msg' => 'Request Failed!');

			// Get and validate form ID
			$form_id = isset($_POST['form_id']) ? intval($_POST['form_id']) : 0;
			if (!$form_id) {
				$response['msg'] = 'Invalid form ID';
				wp_send_json($response);
				return;
			}

			// Get amount
			$amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 10;
			if ($amount <= 0) {
				$response['msg'] = 'Invalid amount';
				wp_send_json($response);
				return;
			}

			// Get PayPal settings
			$mode_sandbox = trim(get_post_meta($form_id, CF7PE_META_PREFIX . 'mode_sandbox', true));
			$sandbox_client_id = get_post_meta($form_id, CF7PE_META_PREFIX . 'sandbox_client_id', true);
			$sandbox_client_secret = get_post_meta($form_id, CF7PE_META_PREFIX . 'sandbox_client_secret', true);
			$live_client_id = get_post_meta($form_id, CF7PE_META_PREFIX . 'live_client_id', true);
			$live_client_secret = get_post_meta($form_id, CF7PE_META_PREFIX . 'live_client_secret', true);
			$currency = get_post_meta($form_id, CF7PE_META_PREFIX . 'currency', true);

			// Set up PayPal API endpoints
			$paypalAuthAPI = !empty($mode_sandbox) ? 
				'https://api-m.sandbox.paypal.com/v1/oauth2/token' : 
				'https://api-m.paypal.com/v1/oauth2/token';
			
			$paypalAPI = !empty($mode_sandbox) ? 
				'https://api-m.sandbox.paypal.com/v2/checkout' : 
				'https://api-m.paypal.com/v2/checkout';

			$paypalClientID = !empty($mode_sandbox) ? $sandbox_client_id : $live_client_id;
			$paypalSecret = !empty($mode_sandbox) ? $sandbox_client_secret : $live_client_secret;

			if (empty($paypalClientID) || empty($paypalSecret)) {
				$response['msg'] = 'PayPal credentials not configured';
				wp_send_json($response);
				return;
			}

			// Generate access token
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $paypalAuthAPI);
			curl_setopt($ch, CURLOPT_HEADER, false);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_USERPWD, $paypalClientID.":".$paypalSecret);
			curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=client_credentials");
			$auth_response = json_decode(curl_exec($ch));
			$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			curl_close($ch);

			if ($http_code != 200 || empty($auth_response->access_token)) {
				$response['msg'] = 'Failed to authenticate with PayPal';
				wp_send_json($response);
				return;
			}

			$accessToken = $auth_response->access_token;

			// Create order
			$rand_reference_id = uniqid('CF7PAP_');
			$postParams = array(
				"intent" => "CAPTURE",
				"purchase_units" => array(
					array(
						"reference_id" => $rand_reference_id,
						"description" => "Payment for Form #" . $form_id,
						"amount" => array(
							"currency_code" => $currency,
							"value" => number_format($amount, 2, '.', '')
						)
					)
				)
			);

			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $paypalAPI.'/orders/');
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array(
				'Content-Type: application/json',
				'Authorization: Bearer '. $accessToken
			));
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postParams));
			$api_resp = curl_exec($ch);
			$api_data = json_decode($api_resp, true);
			$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			curl_close($ch);

			if ($http_code != 200 && $http_code != 201) {
				$response['msg'] = 'Failed to create order: ' . $api_resp;
				wp_send_json($response);
				return;
			}

			if (!empty($api_data)) {
				$response = array(
					'status' => 1,
					'data' => $api_data
				);
			}

			wp_send_json($response);
		}

	}
}

function CF7PE() {
	return CF7PE::instance();
}

CF7PE();
