<?php
/**
 * Plugin Name: Accept PayPal Payments using Contact Form 7
 * Plugin URL: https://wordpress.org/plugins/accept-paypal-payments-using-contact-form-7/
 * Description: This plugin will integrate PayPal submit button which redirects you to PayPal website for making your payments after submitting the form. <strong>PRO Version is available now.</strong>
 * Version: 4.0.3
 * Author: ZealousWeb
 * Author URI: https://www.zealousweb.com
 * Developer: The Zealousweb Team
 * Developer E-Mail: opensource@zealousweb.com
 * Text Domain: accept-paypal-payments-using-contact-form-7
 * Domain Path: /languages
 *
 * Copyright: © 2009-2019 ZealousWeb Technologies.
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

/**
 * Basic plugin definitions
 *
 * @package Accept PayPal Payments using Contact Form 7
 * @since 4.0.3
 */

if ( !defined( 'CF7PE_VERSION' ) ) {
	define( 'CF7PE_VERSION', '4.0.3' ); // Version of plugin
}

if ( !defined( 'CF7PE_FILE' ) ) {
	define( 'CF7PE_FILE', __FILE__ ); // Plugin File
}

if ( !defined( 'CF7PE_DIR' ) ) {
	define( 'CF7PE_DIR', dirname( __FILE__ ) ); // Plugin dir
}

if ( !defined( 'CF7PE_URL' ) ) {
	define( 'CF7PE_URL', plugin_dir_url( __FILE__ ) ); // Plugin url
}

if ( !defined( 'CF7PE_PLUGIN_BASENAME' ) ) {
	define( 'CF7PE_PLUGIN_BASENAME', plugin_basename( __FILE__ ) ); // Plugin base name
}

if ( !defined( 'CF7PE_META_PREFIX' ) ) {
	define( 'CF7PE_META_PREFIX', 'cf7pe_' ); // Plugin metabox prefix
}

if ( !defined( 'CF7PE_PREFIX' ) ) {
	define( 'CF7PE_PREFIX', 'cf7pe' ); // Plugin prefix
}

if ( !defined( 'CF7PE_PRODUCT' ) ) {
	define( 'CF7PE_PRODUCT', 'https://store.zealousweb.com/accept-paypal-payments-using-contact-form-7-pro' );
}

if ( !defined( 'CF7PE_SANDBOX_TKL' ) ) {
	define( 'CF7PE_SANDBOX_TKL', 'https://api.sandbox.paypal.com/v1/oauth2/token' );
}

if ( !defined( 'CF7PE_SANDBOX_PMT' ) ) {
	define( 'CF7PE_SANDBOX_PMT', 'https://api.sandbox.paypal.com/v1/payments/payment/' );
}

if ( !defined( 'CF7PE_SANDBOX_SALE' ) ) {
	define( 'CF7PE_SANDBOX_SALE', 'https://api.sandbox.paypal.com/v1/payments/sale/' );
}
if ( !defined( 'CF7PE_LIVE_TKL' ) ) {
	define( 'CF7PE_LIVE_TKL', 'https://api.paypal.com/v1/oauth2/token' );
}

if ( !defined( 'CF7PE_LIVE_PMT' ) ) {
	define( 'CF7PE_LIVE_PMT', 'https://api.paypal.com/v1/payments/payment/' );
}

if ( !defined( 'CF7PE_LIVE_SALE' ) ) {
	define( 'CF7PE_LIVE_SALE', 'https://api.paypal.com/v1/payments/sale/' );
}


/**
 * Initialize the main class
 */
if ( !function_exists( 'CF7PE' ) ) {

	//Initialize all the things.
	require_once( CF7PE_DIR . '/inc/class.' . CF7PE_PREFIX . '.php' );

	if ( is_admin() ) {
		require_once( CF7PE_DIR . '/inc/admin/class.' . CF7PE_PREFIX . '.admin.php' );
		require_once( CF7PE_DIR . '/inc/admin/class.' . CF7PE_PREFIX . '.admin.action.php' );
		require_once( CF7PE_DIR . '/inc/admin/class.' . CF7PE_PREFIX . '.admin.filter.php' );
	} else {
		require_once( CF7PE_DIR . '/inc/front/class.' . CF7PE_PREFIX . '.front.php' );
		require_once( CF7PE_DIR . '/inc/front/class.' . CF7PE_PREFIX . '.front.action.php' );
		require_once( CF7PE_DIR . '/inc/front/class.' . CF7PE_PREFIX . '.front.filter.php' );
	}

	require_once( CF7PE_DIR . '/inc/lib/class.' . CF7PE_PREFIX . '.lib.php' );
}
