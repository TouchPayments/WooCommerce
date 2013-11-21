<?php
/**
 * Touch Payments Payment Gateway
 *
 * Provides a Touch Payments Payment Gateway.
 *
 * @class 		woocommerce_touch
 * @package		WooCommerce
 * @category	Payment Gateways
 * @author		Touch Payments
 *
 *
 * Table Of Contents
 *
 * __construct()
 * init_form_fields()
 * add_testmode_admin_settings_notice()
 * plugin_url()
 * add_currency()
 * add_currency_symbol()
 * is_valid_for_use()
 * admin_options()
 * payment_fields()
 * generate_touch_form()
 * process_payment()
 * receipt_page()
 * check_itn_request_is_valid()
 * check_itn_response()
 * successful_request()
 * setup_constants()
 * log()
 * validate_signature()
 * validate_ip()
 * validate_response_data()
 * amounts_equal()
 */

require_once __DIR__ . '/../lib/Touch/Address.php';
require_once __DIR__ . '/../lib/Touch/Client.php';
require_once __DIR__ . '/../lib/Touch/Customer.php';
require_once __DIR__ . '/../lib/Touch/Item.php';
require_once __DIR__ . '/../lib/Touch/Order.php';
require_once __DIR__ . '/../lib/Touch/Api.php';

class WC_Gateway_Touch extends WC_Payment_Gateway {

	public $version = '1.0.0';

    /**
     * @var Touch_Api null
     */
    protected $api  = null;

    protected $redirect_url = '';

	public function __construct() {
        $this->id			= 'touch';
        $this->method_title = __( 'Touch', 'woothemes' );
        $this->icon 		= $this->plugin_url() . '/assets/images/icon.png';
        $this->has_fields 	= true;

		// Setup available countries.
		$this->available_countries = array( 'AU' );

		// Setup available currency codes.
		$this->available_currencies = array( 'AUD' );

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Setup constants.
		$this->setup_constants();

		// Setup default merchant data.
		$this->merchant_key = $this->settings['merchant_key'];
		$this->url = 'https://app.touchpayments.com.au/api';
		$this->validate_url = 'https://app.touchpayments.com.au/api';
		$this->redirect_url = 'https://app.touchpayments.com.au/check/index/token/';
		$this->title = $this->settings['title'];

		// Setup the test data, if in test mode.
		if ( $this->settings['testmode'] == 'yes' ) {
			$this->url = 'https://test.touchpayments.com.au/api';
			$this->validate_url = 'https://test.touchpayments.com.au/api';
			$this->redirect_url = 'https://test.touchpayments.com.au/check/index/token/';
		}

        // Test for now
        if ( $this->settings['testmode'] == 'yes' ) {
			$this->url = 'http://fatty.git/api';
			$this->validate_url = 'http://fatty.git/api';
			$this->redirect_url = 'http://fatty.git/check/index/token/';
		}

		$this->response_url	= add_query_arg( 'wc-api', 'WC_Gateway_Touch', home_url( '/' ) );

        $this->api = new Touch_Api($this->url, $this->merchant_key, $this->response_url);

		add_action( 'woocommerce_api_wc_gateway_touch', array( $this, 'check_itn_response' ) );
		add_action( 'valid-touch-standard-itn-request', array( $this, 'successful_request' ) );

		/* 1.6.6 */
		add_action( 'woocommerce_update_options_payment_gateways', array( $this, 'process_admin_options' ) );

		/* 2.0.0 */
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

		add_action( 'woocommerce_receipt_touch', array( $this, 'receipt_page' ) );

		// Check if the base currency supports this gateway.
		if ( ! $this->is_valid_for_use() )
			$this->enabled = false;
    }

	/**
     * Initialise Gateway Settings Form Fields
     *
     * @since 1.0.0
     */
    function init_form_fields () {

    	$this->form_fields = array(
    						'enabled' => array(
											'title' => __( 'Enable/Disable', 'woothemes' ),
											'label' => __( 'Enable Touch Payments', 'woothemes' ),
											'type' => 'checkbox',
											'description' => __( 'This controls whether or not this gateway is enabled within WooCommerce.', 'woothemes' ),
											'default' => 'yes'
										),
    						'title' => array(
    										'title' => __( 'Title', 'woothemes' ),
    										'type' => 'text',
    										'description' => __( 'This controls the title which the user sees during checkout.', 'woothemes' ),
    										'default' => __( 'Touch Payments', 'woothemes' )
    									),
							'description' => array(
											'title' => __( 'Description', 'woothemes' ),
											'type' => 'text',
											'description' => __( 'This controls the description which the user sees during checkout.', 'woothemes' ),
											'default' => __( 'Checkout with Touch Payments and pay after you receive your products.', 'woothemes' )
										),
							'testmode' => array(
											'title' => __( 'Touch Sandbox', 'woothemes' ),
											'type' => 'checkbox',
											'description' => __( 'Place the payment gateway in development mode.', 'woothemes' ),
											'default' => 'yes'
										),
							'merchant_key' => array(
											'title' => __( 'Merchant API Key', 'woothemes' ),
											'type' => 'text',
											'description' => __( 'This is the merchant key, received from Touch Payments.', 'woothemes' ),
											'default' => ''
										)
							);

    } // End init_form_fields()

    /**
	 * Get the plugin URL
	 *
	 * @since 1.0.0
	 */
	function plugin_url() {
		if( isset( $this->plugin_url ) )
			return $this->plugin_url;

		if ( is_ssl() ) {
			return $this->plugin_url = str_replace( 'http://', 'https://', WP_PLUGIN_URL ) . "/" . plugin_basename( dirname( dirname( __FILE__ ) ) );
		} else {
			return $this->plugin_url = WP_PLUGIN_URL . "/" . plugin_basename( dirname( dirname( __FILE__ ) ) );
		}
	} // End plugin_url()

    /**
     * is_valid_for_use()
     *
     * Check if this gateway is enabled and available in the base currency being traded with.
     *
     * @since 1.0.0
     */
	function is_valid_for_use() {
		global $woocommerce;

		$is_available = false;

        $user_currency = get_option( 'woocommerce_currency' );

        $is_available_currency = in_array( $user_currency, $this->available_currencies );

		if ( $is_available_currency && $this->enabled == 'yes' && $this->settings['merchant_key'] != '' )
			$is_available = true;

        return $is_available;
	} // End is_valid_for_use()

	/**
	 * Admin Panel Options
	 * - Options for bits like 'title' and availability on a country-by-country basis
	 *
	 * @since 1.0.0
	 */
	public function admin_options() {
		// Make sure to empty the log file if not in test mode.
		if ( $this->settings['testmode'] != 'yes' ) {
			$this->log( '' );
			$this->log( '', true );
		}

    	?>
    	<h3><?php _e( 'Touch Payments', 'woothemes' ); ?></h3>
    	<p><?php printf( __( 'The customer will be redirected to %sTouch Payments%s to complete the checkout process.', 'woothemes' ), '<a href="http://touchpayments.com.au/">', '</a>' ); ?></p>

    	<?php
    	if ( 'AUD' == get_option( 'woocommerce_currency' ) ) {
    		?><table class="form-table"><?php
			// Generate the HTML For the settings form.
    		$this->generate_settings_html();
    		?></table><!--/.form-table--><?php
		} else {
			?>
			<div class="inline error"><p><strong><?php _e( 'Gateway Disabled', 'woothemes' ); ?></strong> <?php echo sprintf( __( 'Choose Australian Dollars as your store currency in <a href="%s">Pricing Options</a> to enable the Touch Payments Gateway.', 'woocommerce' ), admin_url( '?page=woocommerce&tab=catalog' ) ); ?></p></div>
		<?php
		} // End check currency
		?>
    	<?php
    } // End admin_options()

    /**
	 * There are no payment fields for Touch, but we want to show the description if set.
	 *
	 * @since 1.0.0
	 */
    function payment_fields() {
    	if ( isset( $this->settings['description'] ) && ( '' != $this->settings['description'] ) ) {
    		echo wpautop( wptexturize( $this->settings['description'] ) );
    	}
    } // End payment_fields()

	/**
	 * Generate the Touch button link.
	 *
	 * @since 1.0.0
	 */
    public function generate_touch_form( $order_id ) {

		global $woocommerce;

		$order = new WC_Order( $order_id );

        // Construct variables for post
        $this->data_to_send = $this->getTouchOrder($order);

        $response = $this->api->generateOrder($this->data_to_send);
        if(isset($response->error)) {
            throw new Exception($response->error->message);
        }
        // We need to persist the order id so we can retrieve it from token when calling back
        session_start();
        $_SESSION[$response->result->token] = $order->id;

        /**
         * also put the token on the order
         */
        //$this->data_to_send->setTouchToken($response->result->token);
        $redirectUrl = $this->redirect_url . $response->result->token;





        $touch_args_array = array();

		return '<form action="' . $redirectUrl . '" method="post" id="touch_payment_form">
				' . implode('', $touch_args_array) . '
				<input type="submit" class="button-alt" id="submit_touch_payment_form" value="' . __( 'Pay via Touch Payments', 'woothemes' ) . '" /> <a class="button cancel" href="' . $order->get_cancel_order_url() . '">' . __( 'Cancel order &amp; restore cart', 'woothemes' ) . '</a>
				<script type="text/javascript">
					jQuery(function(){
						jQuery("body").block(
							{
								message: "<img src=\"' . $woocommerce->plugin_url() . '/assets/images/ajax-loader.gif\" alt=\"Redirecting...\" />' . __( 'Thank you for your order. We are now redirecting you to Touch Payments to make payment.', 'woothemes' ) . '",
								overlayCSS:
								{
									background: "#fff",
									opacity: 0.6
								},
								css: {
							        padding:        20,
							        textAlign:      "center",
							        color:          "#555",
							        border:         "3px solid #aaa",
							        backgroundColor:"#fff",
							        cursor:         "wait"
							    }
							});
						jQuery( "#submit_touch_payment_form" ).click();
					});
				</script>
			</form>';

	} // End generate_touch_form()

    function getTouchOrder(WC_Order $order)
    {

        $addressShipping = new Touch_Address();

        $addressShipping->addressOne = $order->shipping_address_1;
        $addressShipping->addressTwo = $order->shipping_address_2;
        $addressShipping->suburb = $order->shipping_city;
        $addressShipping->state = $order->shipping_state;
        $addressShipping->postcode = $order->shipping_postcode;
        $addressShipping->firstName = $order->shipping_first_name;
        $addressShipping->lastName = $order->shipping_last_name;


        $addressBilling = new Touch_Address();
        $addressBilling->addressOne = $order->billing_address_1;
        $addressBilling->addressTwo = $order->billing_address_2;
        $addressBilling->suburb = $order->billing_city;
        $addressBilling->state = $order->billing_state;
        $addressBilling->postcode = $order->billing_postcode;
        $addressBilling->firstName = $order->billing_first_name;
        $addressBilling->lastName = $order->billing_last_name;

        $items = $order->get_items();

        $touchItems = array();

        /**
         * $item Mage_Sales_Model_Quote_Item
         */
        foreach($items as $item) {
            $product = get_product($item['product_id']);

            $touchItem = new Touch_Item();
            $touchItem->sku = $product->get_sku();
            $touchItem->quantity = $item['qty'];
            $touchItem->description = $item['name'];
            $touchItem->price = $item['line_total'];

            preg_match('/< *img[^>]*src *= *["\']?([^"\']*)/i', $product->get_image(), $image);
            if (count($image) > 1) {
                $touchItem->image = $image[1];
            }
            $touchItems[] = $touchItem;

        }

        $customer            = new Touch_Customer();
        $customer->email     = $order->billing_email;
        $customer->firstName = $order->billing_first_name;
        $customer->lastName  = $order->billing_last_name;

        $touchOrder = new Touch_Order();
        $touchOrder->addressBilling = $addressBilling;
        $touchOrder->addressShipping = $addressShipping;
        $grandTotal = $order->order_total; //getGrandTotal() - $order->getTouchBaseFeeAmount();

        $touchOrder->grandTotal = $grandTotal;
        $touchOrder->shippingCosts = $order->order_shipping;
        $touchOrder->gst = array_sum($order->get_tax_totals());
        $touchOrder->items = $touchItems;
        $touchOrder->customer = $customer;

        return $touchOrder;

    }

	/**
	 * Process the payment and return the result.
	 *
	 * @since 1.0.0
	 */
	function process_payment( $order_id ) {

		$order = new WC_Order( $order_id );

		return array(
			'result' 	=> 'success',
			'redirect'	=> add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(get_option('woocommerce_pay_page_id'))))
		);

	}

	/**
	 * Reciept page.
	 *
	 * Display text and a button to direct the user to Touch.
	 *
	 * @since 1.0.0
	 */
	function receipt_page( $order ) {
		echo '<p>' . __( 'Thank you for your order, please click the button below to pay with Touch Payments.', 'woothemes' ) . '</p>';

		echo $this->generate_touch_form( $order );
	} // End receipt_page()

    /**
     * Check Touch Response validity.
     *
     * @param array $data
     *
     * @return bool
     * @since 1.0.0
     */
	function check_itn_request_is_valid( $data ) {
        session_start();

        if (empty($data['token']) && empty($_SESSION[$data['token']])) {
            // Invalid request
            return false;
        }

        $result = $this->api->getOrderByTokenStatus($data['token']);
        $order  = new WC_Order($_SESSION[$data['token']]);

        if (isset($result->error)) {
            //@todo How to handle errors in this platform??
            $this->_redirect('checkout/onepage/failure/');
            return false;
        }
        if ($result->result->status != 'pending') {
            $message = null;
            if (isset($result->reasonCancelled)) {
                $message = 'Touch Payments returned and said:' . $result->reasonCancelled;
            } else {
                $message = 'Got an error:' . var_export($result, true);
            }

            $order->update_status(
                'failed',
                sprintf(
                    __('Payment via Touch Payments failed with status: %s (%s)', 'woothemes'),
                    $result->result->status,
                    $message
                )
            );
        } else {

            /**
             * @TODO: All the fee stuff in Wordpress...
             * adjust the touch fee that comes back from
             * the API in case the fee has changed
             */
//            if ((float) $result->result->fee > 0) {
//                if ($order->getTouchFeeAmount() != $result->result->fee) {
//                    $order->setGrandTotal($order->getGrandTotal() - $order->getTouchFeeAmount() + $result->result->fee);
//                    $order->setTouchFeeAmount((float) $result->result->fee);
//                    $order->setTouchBaseFeeAmount((float) $result->result->fee);
//                    $order->save();
//                }
//            }
            /**
             * - Approve the order in touch
             * - set a transaction ID
             * - set Order to paid
             * - take care of invoice shit
             */
            $apprReturn = $this->api->approveOrder($data['token'], $order->id, $order->get_total());


            if ($apprReturn->result->status == 'approved') {

                $order->add_order_note( __( 'Touch Payments checkout completed', 'woothemes' ) );
                $order->payment_complete();

                return true;

            } else {
                /*
                 * @TODO: Handle error
                 */
                return false;
            }
        }

return;




		$pfError = false;
		$pfDone = false;

		$sessionid = $data['custom_str1'];
        $transaction_id = $data['pf_payment_id'];
        $vendor_name = get_option( 'blogname' );
        $vendor_url = home_url( '/' );

		$order_id = (int) $data['custom_str3'];
		$order_key = esc_attr( $sessionid );
		$order = new WC_Order( $order_id );

		$data_string = '';
		$data_array = array();

		// Dump the submitted variables and calculate security signature
	    foreach( $data as $key => $val ) {
	    	if( $key != 'signature' ) {
	    		$data_string .= $key .'='. urlencode( $val ) .'&';
	    		$data_array[$key] = $val;
	    	}
	    }

	    // Remove the last '&' from the parameter string
	    $data_string = substr( $data_string, 0, -1 );
	    $signature = md5( $data_string );

		$this->log( "\n" . '----------' . "\n" . 'Touch ITN call received' );

		// Notify Touch that information has been received
        if( ! $pfError && ! $pfDone ) {
            header( 'HTTP/1.0 200 OK' );
            flush();
        }

        // Get data sent by Touch
        if ( ! $pfError && ! $pfDone ) {
        	$this->log( 'Get posted data' );

            $this->log( 'Touch Data: '. print_r( $data, true ) );

            if ( $data === false ) {
                $pfError = true;
                $pfErrMsg = PF_ERR_BAD_ACCESS;
            }
        }

        // Verify security signature
        if( ! $pfError && ! $pfDone ) {
            $this->log( 'Verify security signature' );

            // If signature different, log for debugging
            if( ! $this->validate_signature( $data, $signature ) ) {
                $pfError = true;
                $pfErrMsg = PF_ERR_INVALID_SIGNATURE;
            }
        }

        // Verify source IP (If not in debug mode)
        if( ! $pfError && ! $pfDone && $this->settings['testmode'] != 'yes' ) {
            $this->log( 'Verify source IP' );

            if( ! $this->validate_ip( $_SERVER['REMOTE_ADDR'] ) ) {
                $pfError = true;
                $pfErrMsg = PF_ERR_BAD_SOURCE_IP;
            }
        }

        // Get internal order and verify it hasn't already been processed
        if( ! $pfError && ! $pfDone ) {

            $this->log( "Purchase:\n". print_r( $order, true )  );

            // Check if order has already been processed
            if( $order->status == 'completed' ) {
                $this->log( 'Order has already been processed' );
                $pfDone = true;
            }
        }

        // Verify data received
        if( ! $pfError ) {
            $this->log( 'Verify data received' );

            $pfValid = $this->validate_response_data( $data_array );

            if( ! $pfValid ) {
                $pfError = true;
                $pfErrMsg = PF_ERR_BAD_ACCESS;
            }
        }

        // Check data against internal order
        if( ! $pfError && ! $pfDone ) {
            $this->log( 'Check data against internal order' );

            // Check order amount
            if( ! $this->amounts_equal( $data['amount_gross'], $order->order_total ) ) {
                $pfError = true;
                $pfErrMsg = PF_ERR_AMOUNT_MISMATCH;
            }
            // Check session ID
            elseif( strcasecmp( $data['custom_str1'], $order->order_key ) != 0 )
            {
                $pfError = true;
                $pfErrMsg = PF_ERR_SESSIONID_MISMATCH;
            }
        }

        // Check status and update order
        if( ! $pfError && ! $pfDone ) {
            $this->log( 'Check status and update order' );

		if ( $order->order_key !== $order_key ) { exit; }

    		switch( $data['payment_status'] ) {
                case 'COMPLETE':
                    $this->log( '- Complete' );

                   // Payment completed
					$order->add_order_note( __( 'ITN payment completed', 'woothemes' ) );
					$order->payment_complete();

                    break;

    			case 'FAILED':
                    $this->log( '- Failed' );

                    $order->update_status( 'failed', sprintf(__('Payment %s via ITN.', 'woothemes' ), strtolower( sanitize( $data['payment_status'] ) ) ) );
        			break;

    			case 'PENDING':
                    $this->log( '- Pending' );

                    // Need to wait for "Completed" before processing
        			$order->update_status( 'pending', sprintf(__('Payment %s via ITN.', 'woothemes' ), strtolower( sanitize( $data['payment_status'] ) ) ) );
        			break;

    			default:
                    // If unknown status, do nothing (safest course of action)
    			break;
            }
        }

        // If an error occurred
        if( $pfError ) {
            $this->log( 'Error occurred: '. $pfErrMsg );
        }

        // Close log
        $this->log( '', true );

    	return $pfError;
    } // End check_itn_request_is_valid()

	/**
	 * Check Touch ITN response.
	 *
	 * @since 1.0.0
	 */
	function check_itn_response() {
		$_REQUEST = stripslashes_deep( $_REQUEST );

		if ( $this->check_itn_request_is_valid( $_REQUEST ) ) {
			do_action( 'valid-touch-standard-itn-request', $_REQUEST );
		}
	} // End check_itn_response()

	/**
	 * Successful Payment!
	 *
	 * @since 1.0.0
	 */
	function successful_request( $posted ) {

		$order_id = $_SESSION[$posted['token']];
		$order = new WC_Order( $order_id );


		if ( $order->status !== 'completed' ) {
			// We are here so lets check status and do actions
			switch ( strtolower($order->status) ) {
				case 'completed' :
					// Payment completed
					$order->add_order_note( __( 'ITN payment completed', 'woothemes' ) );
					$order->payment_complete();
				break;
				case 'denied' :
				case 'expired' :
				case 'failed' :
				case 'voided' :
					// Failed order
                    // @TODO: uncomment
					//$order->update_status( 'failed', sprintf(__('Payment %s via ITN.', 'woothemes' ), strtolower( $order->status ) ) );
				break;
				default:
					// Hold order
					//$order->update_status( 'on-hold', sprintf(__('Payment %s via ITN.', 'woothemes' ), strtolower( sanitize( $posted['payment_status'] ) ) ) );
				break;
			} // End SWITCH Statement

			wp_redirect( add_query_arg( 'key', $order->order_key, add_query_arg( 'order', $order->id, get_permalink( get_option( 'woocommerce_thanks_page_id' ) ) ) ) );
			exit;
		} // End IF Statement

		exit;
	}

	/**
	 * Setup constants.
	 *
	 * Setup common values and messages used by the Touch gateway.
	 *
	 * @since 1.0.0
	 */
	function setup_constants () {
		global $woocommerce;
		//// Create user agent string
		// User agent constituents (for cURL)
		define( 'PF_SOFTWARE_NAME', 'WooCommerce' );
		define( 'PF_SOFTWARE_VER', $woocommerce->version );
		define( 'PF_MODULE_NAME', 'WooCommerce-Touch' );
		define( 'PF_MODULE_VER', $this->version );

		// Features
		// - PHP
		$pfFeatures = 'PHP '. phpversion() .';';

		// - cURL
		if( in_array( 'curl', get_loaded_extensions() ) )
		{
		    define( 'PF_CURL', '' );
		    $pfVersion = curl_version();
		    $pfFeatures .= ' curl '. $pfVersion['version'] .';';
		}
		else
		    $pfFeatures .= ' nocurl;';

		// Create user agrent
		define( 'PF_USER_AGENT', PF_SOFTWARE_NAME .'/'. PF_SOFTWARE_VER .' ('. trim( $pfFeatures ) .') '. PF_MODULE_NAME .'/'. PF_MODULE_VER );

		// General Defines
		define( 'PF_TIMEOUT', 15 );
		define( 'PF_EPSILON', 0.01 );

		// Messages
		    // Error
		define( 'PF_ERR_AMOUNT_MISMATCH', __( 'Amount mismatch', 'woothemes' ) );
		define( 'PF_ERR_BAD_ACCESS', __( 'Bad access of page', 'woothemes' ) );
		define( 'PF_ERR_BAD_SOURCE_IP', __( 'Bad source IP address', 'woothemes' ) );
		define( 'PF_ERR_CONNECT_FAILED', __( 'Failed to connect to Touch Payments', 'woothemes' ) );
		define( 'PF_ERR_INVALID_SIGNATURE', __( 'Security signature mismatch', 'woothemes' ) );
		define( 'PF_ERR_NO_SESSION', __( 'No saved session found for ITN transaction', 'woothemes' ) );
		define( 'PF_ERR_ORDER_ID_MISSING_URL', __( 'Order ID not present in URL', 'woothemes' ) );
		define( 'PF_ERR_ORDER_ID_MISMATCH', __( 'Order ID mismatch', 'woothemes' ) );
		define( 'PF_ERR_ORDER_INVALID', __( 'This order ID is invalid', 'woothemes' ) );
		define( 'PF_ERR_ORDER_NUMBER_MISMATCH', __( 'Order Number mismatch', 'woothemes' ) );
		define( 'PF_ERR_ORDER_PROCESSED', __( 'This order has already been processed', 'woothemes' ) );
		define( 'PF_ERR_PDT_FAIL', __( 'PDT query failed', 'woothemes' ) );
		define( 'PF_ERR_PDT_TOKEN_MISSING', __( 'PDT token not present in URL', 'woothemes' ) );
		define( 'PF_ERR_SESSIONID_MISMATCH', __( 'Session ID mismatch', 'woothemes' ) );
		define( 'PF_ERR_UNKNOWN', __( 'Unkown error occurred', 'woothemes' ) );

		    // General
		define( 'PF_MSG_OK', __( 'Payment was successful', 'woothemes' ) );
		define( 'PF_MSG_FAILED', __( 'Payment has failed', 'woothemes' ) );
		define( 'PF_MSG_PENDING',
		    __( 'The payment is pending. Please note, you will receive another Instant', 'woothemes' ).
		    __( ' Transaction Notification when the payment status changes to', 'woothemes' ).
		    __( ' "Completed", or "Failed"', 'woothemes' ) );
	} // End setup_constants()

	/**
	 * log()
	 *
	 * Log system processes.
	 *
	 * @since 1.0.0
	 */

	function log ( $message, $close = false ) {
		if ( ( $this->settings['testmode'] != 'yes' && ! is_admin() ) ) { return; }

		static $fh = 0;

		if( $close ) {
            @fclose( $fh );
        } else {
            // If file doesn't exist, create it
            if( !$fh ) {
                $pathinfo = pathinfo( __FILE__ );
                $dir = str_replace( '/classes', '/logs', $pathinfo['dirname'] );
                $fh = @fopen( $dir .'/touch.log', 'w' );
            }

            // If file was successfully created
            if( $fh ) {
                $line = $message ."\n";

                fwrite( $fh, $line );
            }
        }
	} // End log()

	/**
	 * validate_signature()
	 *
	 * Validate the signature against the returned data.
	 *
	 * @param array $data
	 * @param string $signature
	 * @since 1.0.0
	 */

	function validate_signature ( $data, $signature ) {

	    $result = ( $data['signature'] == $signature );

	    $this->log( 'Signature = '. ( $result ? 'valid' : 'invalid' ) );

	    return( $result );
	} // End validate_signature()

	/**
	 * validate_ip()
	 *
	 * Validate the IP address to make sure it's coming from Touch.
	 *
	 * @param array $data
	 * @since 1.0.0
	 */

	function validate_ip( $sourceIP ) {
	    // Variable initialization
	    $validHosts = array(
	        'touchpayments.com.au',
	        'app.touchpayments.com.au',
	        'test.touchpayments.com.au',
	        );

	    $validIps = array();

	    foreach( $validHosts as $pfHostname ) {
	        $ips = gethostbynamel( $pfHostname );

	        if( $ips !== false )
	            $validIps = array_merge( $validIps, $ips );
	    }

	    // Remove duplicates
	    $validIps = array_unique( $validIps );

	    $this->log( "Valid IPs:\n". print_r( $validIps, true ) );

	    if( in_array( $sourceIP, $validIps ) ) {
	        return( true );
	    } else {
	        return( false );
	    }
	} // End validate_ip()

	/**
	 * validate_response_data()
	 *
	 * @param $pfHost String Hostname to use
	 * @param $pfParamString String Parameter string to send
	 * @param $proxy String Address of proxy to use or NULL if no proxy
	 * @since 1.0.0
	 */
	function validate_response_data( $pfParamString, $pfProxy = null ) {
		global $woocommerce;
	    $this->log( 'Host = '. $this->validate_url );
	    $this->log( 'Params = '. print_r( $pfParamString, true ) );

		if ( ! is_array( $pfParamString ) ) { return false; }

		$post_data = $pfParamString;

		$url = $this->validate_url;

		$response = wp_remote_post( $url, array(
       				'method' => 'POST',
        			'body' => $post_data,
        			'timeout' => 70,
        			'sslverify' => true,
        			'user-agent' => PF_USER_AGENT //'WooCommerce/' . $woocommerce->version . '; ' . get_site_url()
    			));

		if ( is_wp_error( $response ) ) throw new Exception( __( 'There was a problem connecting to the payment gateway.', 'woothemes' ) );

		if( empty( $response['body'] ) ) throw new Exception( __( 'Empty Touch Payments response.', 'woothemes' ) );

		parse_str( $response['body'], $parsed_response );

		$response = $parsed_response;

	    $this->log( "Response:\n". print_r( $response, true ) );

	    // Interpret Response
	    if ( is_array( $response ) && in_array( 'VALID', array_keys( $response ) ) ) {
	    	return true;
	    } else {
	    	return false;
	    }
	} // End validate_responses_data()

	/**
	 * amounts_equal()
	 *
	 * Checks to see whether the given amounts are equal using a proper floating
	 * point comparison with an Epsilon which ensures that insignificant decimal
	 * places are ignored in the comparison.
	 *
	 * eg. 100.00 is equal to 100.0001
	 *
	 * @author Jonathan Smit
	 * @param $amount1 Float 1st amount for comparison
	 * @param $amount2 Float 2nd amount for comparison
	 * @since 1.0.0
	 */
	function amounts_equal ( $amount1, $amount2 ) {
		if( abs( floatval( $amount1 ) - floatval( $amount2 ) ) > PF_EPSILON ) {
			return( false );
		} else {
			return( true );
		}
	} // End amounts_equal()

} // End Class
