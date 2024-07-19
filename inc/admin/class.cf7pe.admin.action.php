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

			// Save settings of contact form 7 admin
			add_action( 'wpcf7_save_contact_form', array( $this, 'action__wpcf7_save_contact_form' ), 20, 2 );
			add_action( 'manage_cf7sa_data_posts_custom_column', array( $this, 'action__manage_cf7sa_data_posts_custom_column' ), 10, 2 );
			add_action( 'restrict_manage_posts', array( $this, 'action__restrict_manage_posts' ) );

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
			wp_register_script( CF7PE_PREFIX . '_admin_js', CF7PE_URL . 'assets/js/admin.min.js', array( 'jquery-core' ), CF7PE_VERSION );
			wp_register_style( CF7PE_PREFIX . '_admin_css', CF7PE_URL . 'assets/css/admin.min.css', array(), CF7PE_VERSION );
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
				CF7PE_META_PREFIX . 'description',
				CF7PE_META_PREFIX . 'currency',
				CF7PE_META_PREFIX . 'success_returnurl',
				CF7PE_META_PREFIX . 'cancel_returnurl',
			);

			if ( !empty( $form_fields ) ) {
				foreach ( $form_fields as $key ) {
					$keyval = sanitize_text_field( $_REQUEST[ $key ] ); //phpcs:ignore
					update_post_meta( $post_id, $key, $keyval );
				}
			}

		}

         /**
		 * Action: manage_cf7sa_data_posts_custom_column
		 *
		 * @method action__manage_cf7sa_data_posts_custom_column
		 *
		 * @param  string  $column
		 * @param  int     $post_id
		 *
		 * @return string
		 */
		function action__manage_cf7sa_data_posts_custom_column( $column, $post_id ) {
			$data_ct = $this->cfsazw_check_data_ct( sanitize_text_field( $post_id ) );
			switch ( $column ) {

				case 'form_id' :
					if( $data_ct ){
							echo "<a href='".CF7PE_PRODUCT."' target='_blank'>To unlock more features consider upgrading to PRO.</a>";
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
							echo "<a href='".CF7PE_PRODUCT."' target='_blank'>To unlock more features consider upgrading to PRO.</a>";
					}else{
						echo (
							!empty( get_post_meta( $post_id , '_transaction_status', true ) )
							? ucfirst( get_post_meta( $post_id , '_transaction_status', true ) )
							: ''
						);
					}
				break;

				case 'total' :
					if( $data_ct ){
							echo "<a href='".CF7PE_PRODUCT."' target='_blank'>To unlock more features consider upgrading to PRO.</a>";
					}else{

						echo ( !empty( get_post_meta( $post_id , '_total', true ) ) ? get_post_meta( $post_id , '_total', true ) : '' ) .' ' .
							( !empty( get_post_meta( $post_id , '_currency', true ) ) ? strtoupper( get_post_meta( $post_id , '_currency', true ) ) : '' );
					}
				break;

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

			if ( 'cf7pl_data' != $post_type ) {
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
			echo '<option value="all">' . __( 'All Forms', 'contact-form-7-stripe-addon' ) . '</option>';
			foreach ( $posts as $post ) {
				echo '<option value="' . $post->ID . '" ' . selected( $selected, $post->ID, false ) . '>' . $post->post_title  . '</option>';
			}
			echo '</select>';

			echo '<input type="submit" id="doaction2" name="export_csv" class="button action" value="Export CSV">';

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


	}

	add_action( 'plugins_loaded' , function() {
		CF7PE()->admin->action = new CF7PE_Admin_Action;
	} );
}
