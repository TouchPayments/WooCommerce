<?php
/*
	Plugin Name: Touch Payments Gateway
	Plugin URI: http://touchpayments.com.au/
	Description: Integrate Touch Payments in your WooCommerce shop.
	Version: 1.0.1
	Author: TouchPayments
	Author URI: http://touchpayments.com.au/
	Requires at least: 3.5
	Tested up to: 3.5
*/

/**
 * Required functions
 */
if ( ! function_exists( 'woothemes_queue_update' ) )
	require_once( 'woo-includes/woo-functions.php' );

require_once __DIR__ . '/lib/Touch/Api.php';

/**
 * Plugin updates
 */
woothemes_queue_update( plugin_basename( __FILE__ ), '557bf07293ad916f20c207c6c9cd15ff', '18596' );

load_plugin_textdomain( 'wc_touch', false, trailingslashit( dirname( plugin_basename( __FILE__ ) ) ) );

add_action( 'plugins_loaded', 'woocommerce_touch_init', 0 );
session_start();
/**
 * Initialize the gateway.
 *
 * @since 1.0.0
 */
function woocommerce_touch_init() {

	if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;

	require_once( plugin_basename( 'classes/touch.class.php' ) );

	add_filter('woocommerce_payment_gateways', 'woocommerce_touch_add_gateway' );
    add_action( 'woocommerce_order_status_completed', 'touch_woocommerce_order_status_completed' );

    add_action( 'woocommerce_calculate_totals', 'recalculate_totals');
    wp_enqueue_script( 'wc-add-extra-charges', plugins_url() . '/' . end(explode('/', __DIR__)) . '/assets/touch.js', array('wc-checkout'), false, true );
}

/**
 * Have to implement this one here as the Payment Gateway will not be instantiated in the admin area
 *
 * @param $order_id
 */
function touch_woocommerce_order_status_completed( $order_id ) {
    $payment = new WC_Gateway_Touch();
    $payment->ship_order($order_id);
}

function recalculate_totals($totals) {
    $payment = new WC_Gateway_Touch();
    $payment->calculate_totals($totals);
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


