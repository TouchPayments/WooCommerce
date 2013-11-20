<?php
/*
	Plugin Name: Touch Payments Gateway
	Plugin URI: http://touchpayments.com.au/
	Description: Integrate Touch Payments in your WooCommerce shop.
	Version: 1.0.0
	Author: TouchPayments
	Author URI: http://touchpayments.com/
	Requires at least: 3.5
	Tested up to: 3.5
*/

/**
 * Required functions
 */
if ( ! function_exists( 'woothemes_queue_update' ) )
	require_once( 'woo-includes/woo-functions.php' );

/**
 * Plugin updates
 */
woothemes_queue_update( plugin_basename( __FILE__ ), '557bf07293ad916f20c207c6c9cd15ff', '18596' );

load_plugin_textdomain( 'wc_touch', false, trailingslashit( dirname( plugin_basename( __FILE__ ) ) ) );

add_action( 'plugins_loaded', 'woocommerce_touch_init', 0 );

/**
 * Initialize the gateway.
 *
 * @since 1.0.0
 */
function woocommerce_touch_init() {

	if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;

	require_once( plugin_basename( 'classes/touch.class.php' ) );

	add_filter('woocommerce_payment_gateways', 'woocommerce_touch_add_gateway' );

}

/**
 * Add the gateway to WooCommerce
 *
 * @since 1.0.0
 */
function woocommerce_touch_add_gateway( $methods ) {
	$methods[] = 'WC_Gateway_Touch';
	return $methods;
}
