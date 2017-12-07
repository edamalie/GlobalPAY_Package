<?php
/**
 * GlobalPAY Payment Gateway
 *
 * Provides a GlobalPAY Payment Gateway.
 *
 * @class 		woocommerce_globalpay
 * @package		WooCommerce
 * @category	Payment Gateways
 * @author		Robby Adwini
 */
class WC_Gateway_GlobalPAY extends WC_Payment_Gateway {

	/**
	 * Version
	 * @var string
	 */
	public $version = '1.2.8';

	/**
	 * Constructor
	 */
	public function __construct() {
        $this->id			        = 'globalpay';
        $this->method_title         = __( 'GlobalPAY', 'woocommerce-gateway-globalpay' );
		$this->method_description   = sprintf( __( 'GlobalPAY works by sending the user to %sGlobalPAY%s to enter their payment information.', 'woocommerce-gateway-globalpay' ), '<a href="http://globalpay.com.gh/">', '</a>' );
        // $this->icon 		        = WP_PLUGIN_URL . "/" . plugin_basename( dirname( dirname( __FILE__ ) ) ) . '/assets/images/icon1.png';
        $this->debug_email 	        = get_option( 'admin_email' );

		// Setup available countries.
		$this->available_countries  = array( 'GH' );

		// Setup available currency codes.
		$this->available_currencies = array( 'GHS' );

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Setup constants.
		if ( ! is_admin() ) {
			$this->setup_constants();
		}

		// Setup default merchant data.
		$this->merchant_id      = $this->get_option( 'merchant_id' );
		$this->url              = 'https://www.zenithbank.com.gh/api.globalpay/WooComerce/PaymentRequest';
		$this->validate_url     = 'https://www.zenithbank.com.gh/api.globalpay/WooComerce/PaymentRequest';
		$this->title            = $this->get_option( 'title' );
		$this->response_url	    = add_query_arg( 'wc-api', 'WC_Gateway_GlobalPAY', home_url( '/' ) );
		$this->send_debug_email = 'yes' === $this->get_option( 'send_debug_email' );

		// Setup the test data, if in test mode.
		if ( 'yes' === $this->get_option( 'testmode' ) ) {
			$this->url          = 'https://www.zenithbank.com.gh/api.globalpay/Service/TestPayment';
			$this->validate_url = 'https://www.zenithbank.com.gh/api.globalpay/Service/TestPayment';
			$this->merchant_id  = $this->get_option( 'merchant_id' );
			$this->add_testmode_admin_settings_notice();
		} else {
			$this->send_debug_email = false;
		}

		$this->description = $this->get_option( 'description' );

		add_action( 'woocommerce_api_wc_gateway_globalpay', array( $this, 'check_itn_response' ) );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_receipt_globalpay', array( $this, 'receipt_page' ) );

		// Check if the base currency supports this gateway.
		if ( ! $this->is_valid_for_use() ) {
			$this->enabled = false;
		}
    }

	/**
     * Initialise Gateway Settings Form Fields
     *
     * @since 1.0.0
     */
    public function init_form_fields () {
    	$this->form_fields = array(
			'enabled' => array(
				'title'       => __( 'Enable/Disable', 'woocommerce-gateway-globalpay' ),
				'label'       => __( 'Enable GlobalPAY', 'woocommerce-gateway-globalpay' ),
				'type'        => 'checkbox',
				'description' => __( 'This controls whether or not this gateway is enabled within WooCommerce.', 'woocommerce-gateway-globalpay' ),
				'default'     => 'yes',
				'desc_tip'    => true
			),
			'title' => array(
				'title'       => __( 'Title', 'woocommerce-gateway-globalpay' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-gateway-globalpay' ),
				'default'     => __( 'GlobalPAY', 'woocommerce-gateway-globalpay' ),
				'desc_tip'    => true
			),
			'description' => array(
				'title'       => __( 'Description', 'woocommerce-gateway-globalpay' ),
				'type'        => 'text',
				'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-gateway-globalpay' ),
				'default'     => '',
				'desc_tip'    => true
			),
			'testmode' => array(
				'title'       => __( 'GlobalPAY Sandbox', 'woocommerce-gateway-globalpay' ),
				'type'        => 'checkbox',
				'description' => __( 'Place the payment gateway in development mode.', 'woocommerce-gateway-globalpay' ),
				'default'     => 'yes'
			),
			'merchant_id' => array(
				'title'       => __( 'Merchant ID', 'woocommerce-gateway-globalpay' ),
				'type'        => 'text',
				'description' => __( 'This is the merchant ID, received from GlobalPAY.', 'woocommerce-gateway-globalpay' ),
				'default'     => ''
			),
			'send_debug_email' => array(
				'title'   => __( 'Send Debug Emails', 'woocommerce-gateway-globalpay' ),
				'type'    => 'checkbox',
				'label'   => __( 'Send debug e-mails for transactions through the GlobalPAY gateway (sends on successful transaction as well).', 'woocommerce-gateway-globalpay' ),
				'default' => 'yes'
			),
			'debug_email' => array(
				'title'       => __( 'Who Receives Debug E-mails?', 'woocommerce-gateway-globalpay' ),
				'type'        => 'text',
				'description' => __( 'The e-mail address to which debugging error e-mails are sent when in test mode.', 'woocommerce-gateway-globalpay' ),
				'default'     => get_option( 'admin_email' )
			)
		);
    }

    /**
     * add_testmode_admin_settings_notice()
     * Add a notice to the merchant_id fields when in test mode.
     *
     * @since 1.0.0
     */
    public function add_testmode_admin_settings_notice() {
    	$this->form_fields['merchant_id']['description']  .= ' <strong>' . __( 'Sandbox Merchant ID currently in use', 'woocommerce-gateway-globalpay' ) . ' ( ' . esc_html( $this->merchant_id ) . ' ).</strong>';
    }

    /**
     * is_valid_for_use()
     *
     * Check if this gateway is enabled and available in the base currency being traded with.
     *
     * @since 1.0.0
     */
	public function is_valid_for_use() {
		$is_available          = false;
        $is_available_currency = true;//in_array( get_woocommerce_currency(), $this->available_currencies );

		if ( $is_available_currency && $this->merchant_id) {
			$is_available = true;
		}
        return $is_available;
	}

	/**
	 * Admin Panel Options
	 * - Options for bits like 'title' and availability on a country-by-country basis
	 *
	 * @since 1.0.0
	 */
	public function admin_options() {

	    parent::admin_options();
    	/*if ( in_array( get_woocommerce_currency(), $this->available_currencies ) ) {
			parent::admin_options();
		} else {
    		?>
			<h3><?php _e( 'GlobalPAY', 'woocommerce-gateway-globalpay' ); ?></h3>
			<div class="inline error"><p><strong><?php _e( 'Gateway Disabled', 'woocommerce-gateway-globalpay' ); ?></strong> <?php echo sprintf( __( 'Choose South African Rands as your store currency in %1$sPricing Options%2$s to enable the GlobalPAY Gateway.', 'woocommerce-gateway-globalpay' ), '<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=general' ) ) . '">', '</a>' ); ?></p></div>
    		<?php
		}*/
    }

	/**
	 * Generate the GlobalPAY button link.
	 *
	 * @since 1.0.0
	 */
    public function generate_globalpay_form( $order_id ) {
		$order         = wc_get_order( $order_id );
		$shipping_name = explode( ' ', $order->shipping_method );

		// Construct variables for post
	    $this->data_to_send = array(
	        // Merchant details
	        'GPID'      => $this->merchant_id,
	        'return_url'       => $this->get_return_url( $order ),
	        'cancel_url'       => $order->get_cancel_order_url(),
	        'notify_url'       => $this->response_url,

			// Billing details
			'name_first'       => $order->billing_first_name,
			'name_last'        => $order->billing_last_name,
			'email_address'    => $order->billing_email,

	        // Item details
	        'order_number'     => ltrim( $order->get_order_number(), __( '#', 'hash before order number', 'woocommerce-gateway-globalpay' ) ),
	        'amount'           => $order->get_total(),
	    	'item_name'        => get_bloginfo( 'name' ) . ' - ' . $order->get_order_number(),
	    	'desc' => sprintf( __( 'New order from %s', 'woocommerce-gateway-globalpay' ), get_bloginfo( 'name' ) ),

	    	// Custom strings
	    	'referenceID'      => $order->order_key."--".$order->id,
	    	'custom_str2'      => 'WooCommerce/' . WC_VERSION . '; ' . get_site_url(),
	    	'custom_str3'      => $order->id,
	    	'source'           => 'WooCommerce-Free-Plugin',
	    	'Status'           => 'ok',
			'CustomerID'      => $order->order_key."--".$order->id,
			'ProductID'       => get_bloginfo( 'name' ) . ' - ' . $order->get_order_number(),
			'merLogo'         =>'https://www.ashfoamghana.com/images/ashfoam_logo_alt.png',
			'_currency'        =>'GHS'
	   	);

		$globalpay_args_array = array();

		foreach( $this->data_to_send as $key => $value) {
			$globalpay_args_array[] = '<input type="hidden" name="' . esc_attr( $key ) .'" value="' . esc_attr( $value ) . '" />';
		}

		return '<form action="' . esc_url( $this->url ) . '" method="post" id="globalpay_payment_form">
				' . implode( '', $globalpay_args_array ) . '
				<input type="submit" class="button-alt" id="submit_globalpay_payment_form" value="' . __( 'Pay via GlobalPAY', 'woocommerce-gateway-globalpay' ) . '" /> <a class="button cancel" href="' . $order->get_cancel_order_url() . '">' . __( 'Cancel order &amp; restore cart', 'woocommerce-gateway-globalpay' ) . '</a>
				<script type="text/javascript">
					jQuery(function(){
						jQuery("body").block(
							{
								message: "' . __( 'Thank you for your order. We are now redirecting you to GlobalPAY to make payment.', 'woocommerce-gateway-globalpay' ) . '",
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
						jQuery( "#submit_globalpay_payment_form" ).click();
					});
				</script>
			</form>';
	}

	/**
	 * Process the payment and return the result.
	 *
	 * @since 1.0.0
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );
		return array(
			'result' 	 => 'success',
			'redirect'	 => $order->get_checkout_payment_url( true )
		);
	}

	/**
	 * Reciept page.
	 *
	 * Display text and a button to direct the user to GlobalPAY.
	 *
	 * @since 1.0.0
	 */
	public function receipt_page( $order ) {
		echo '<p>' . __( 'Thank you for your order, please click the button below to pay with GlobalPAY.', 'woocommerce-gateway-globalpay' ) . '</p>';
		echo $this->generate_globalpay_form( $order );
	}

	/**
	 * Check GlobalPAY ITN validity.
	 *
	 * @param array $data
	 * @since 1.0.0
	 */
	public function handle_itn_request( $data ) {

		global $woocommerce;

		$temp = explode("--", $data["ref"]);

		$data['custom_str1'] = $temp[0];
		$data['custom_str3'] = $temp[1];

		

		$pfError        = false;
		$pfDone         = false;
		$pfDebugEmail   = $this->get_option( 'debug_email', get_option( 'admin_email' ) );
		$sessionid      = $data['custom_str1'];
        $transaction_id = $data['pf_payment_id'];
        $vendor_name    = get_bloginfo( 'name' );
        $vendor_url     = home_url( '/' );
		$order_id       = absint( $data['custom_str3'] );
		$order_key      = wc_clean( $sessionid );
		$order          = wc_get_order( $order_id );
		$data_string    = '';
		$data_array     = array();

		





		// Dump the submitted variables and calculate security signature
	    foreach( $data as $key => $val ) {
	    	if ( $key !== 'signature' ) {
	    		$data_string       .= $key . '=' . urlencode( $val ) . '&';
	    		$data_array[ $key ] = $val;
	    	}
	    }





	    // Remove the last '&' from the parameter string
	    $data_string = substr( $data_string, 0, -1 );
	    $signature   = md5( $data_string );

		$this->log( "\n" . '----------' . "\n" . 'GlobalPAY ITN call received' );

		// Notify GlobalPAY that information has been received
        /*if ( ! $pfError && ! $pfDone ) {
            header( 'HTTP/1.0 200 OK' );
            flush();
        }*/


        // Get data sent by GlobalPAY
       /* if ( ! $pfError && ! $pfDone ) {
        	$this->log( 'Get posted data' );
            $this->log( 'GlobalPAY Data: '. print_r( $data, true ) );



            if ( $data === false ) {
                $pfError  = true;
                $pfErrMsg = PF_ERR_BAD_ACCESS;
            }
        }*/



        // Verify security signature
        /*if ( ! $pfError && ! $pfDone ) {
            $this->log( 'Verify security signature' );



            // If signature different, log for debugging
            if( ! $this->validate_signature( $data, $signature ) ) {

                $pfError  = true;
                $pfErrMsg = PF_ERR_INVALID_SIGNATURE;
            }
        }*/





        // Verify source IP (If not in debug mode)
        /*if ( ! $pfError && ! $pfDone && $this->get_option( 'testmode' ) != 'yes' ) {
            $this->log( 'Verify source IP' );

             

            if( ! $this->validate_ip( $_SERVER['REMOTE_ADDR'] ) ) {
                $pfError  = true;
                $pfErrMsg = PF_ERR_BAD_SOURCE_IP;
            }
        }

        // Get internal order and verify it hasn't already been processed
        if ( ! $pfError && ! $pfDone ) {
            $this->log( "Purchase:\n". print_r( $order, true ) );

            // Check if order has already been processed
            if( $order->status === 'completed' ) {
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
            if ( ! $this->amounts_equal( $data['amount_gross'], $order->order_total ) ) {
                $pfError  = true;
                $pfErrMsg = PF_ERR_AMOUNT_MISMATCH;
            }
            // Check session ID
            elseif( strcasecmp( $data['custom_str1'], $order->order_key ) != 0 ) {
                $pfError  = true;
                $pfErrMsg = PF_ERR_SESSIONID_MISMATCH;
            }
        }*/



        switch ( strtolower( $data['status'] ) ) {
                case 'approved':

                	 

                    $this->log( '- Complete' );
					$order->add_order_note( __( 'ITN payment completed', 'woocommerce-gateway-globalpay' ) );
					$order->payment_complete();

					header("location: {$this->get_return_url( $order )}");
					exit();

                    if ( $this->send_debug_email ) {
                        $subject = "GlobalPAY ITN on your site";
                        $body =
                            "Hi,\n\n".
                            "A GlobalPAY transaction has been completed on your website\n".
                            "------------------------------------------------------------\n".
                            "Site: ". $vendor_name ." (". $vendor_url .")\n".
                            "Purchase ID: ". $data['m_payment_id'] ."\n".
                            "GlobalPAY Transaction ID: ". $data['pf_payment_id'] ."\n".
                            "GlobalPAY Payment Status: ". $data['payment_status'] ."\n".
                            "Order Status Code: ". $order->status;
                        wp_mail( $pfDebugEmail, $subject, $body );
                    }
                break;
    			case 'failed':
                    $this->log( '- Failed' );
                    $order->update_status( 'failed', sprintf( __( 'Payment %s via ITN.', 'woocommerce-gateway-globalpay' ), strtolower( sanitize_text_field( $data['payment_status'] ) ) ) );

					if ( $this->send_debug_email ) {
	                    $subject = "GlobalPAY ITN Transaction on your site";
	                    $body =
	                        "Hi,\n\n".
	                        "A failed GlobalPAY transaction on your website requires attention\n".
	                        "------------------------------------------------------------\n".
	                        "Site: ". $vendor_name ." (". $vendor_url .")\n".
	                        "Purchase ID: ". $order->id ."\n".
	                        "User ID: ". $order->user_id ."\n".
	                        "GlobalPAY Transaction ID: ". $data['pf_payment_id'] ."\n".
	                        "GlobalPAY Payment Status: ". $data['payment_status'];
	                    wp_mail( $pfDebugEmail, $subject, $body );
                    }
        			break;
    			case 'pending':
                    $this->log( '- Pending' );
                    // Need to wait for "Completed" before processing
        			$order->update_status( 'on-hold', sprintf( __( 'Payment %s via ITN.', 'woocommerce-gateway-globalpay' ), strtolower( sanitize_text_field( $data['payment_status'] ) ) ) );
        			break;
    			default:
                    // If unknown status, do nothing (safest course of action)
    			break;
            }


  //       // If an error occurred
  //       if ( $pfError ) {
  //           $this->log( 'Error occurred: '. $pfErrMsg );

  //           if ( $this->send_debug_email ) {
	 //            $this->log( 'Sending email notification' );

	 //             // Send an email
	 //            $subject = "GlobalPAY ITN error: ". $pfErrMsg;
	 //            $body =
	 //                "Hi,\n\n".
	 //                "An invalid GlobalPAY transaction on your website requires attention\n".
	 //                "------------------------------------------------------------\n".
	 //                "Site: ". $vendor_name ." (". $vendor_url .")\n".
	 //                "Remote IP Address: ".$_SERVER['REMOTE_ADDR']."\n".
	 //                "Remote host name: ". gethostbyaddr( $_SERVER['REMOTE_ADDR'] ) ."\n".
	 //                "Purchase ID: ". $order->id ."\n".
	 //                "User ID: ". $order->user_id ."\n";
	 //            if( isset( $data['pf_payment_id'] ) )
	 //                $body .= "GlobalPAY Transaction ID: ". $data['pf_payment_id'] ."\n";
	 //            if( isset( $data['payment_status'] ) )
	 //                $body .= "GlobalPAY Payment Status: ". $data['payment_status'] ."\n";
	 //            $body .=
	 //                "\nError: ". $pfErrMsg ."\n";

	 //            switch( $pfErrMsg ) {
	 //                case PF_ERR_AMOUNT_MISMATCH:
	 //                    $body .=
	 //                        "Value received : ". $data['amount_gross'] ."\n".
	 //                        "Value should be: ". $order->order_total;
	 //                    break;

	 //                case PF_ERR_ORDER_ID_MISMATCH:
	 //                    $body .=
	 //                        "Value received : ". $data['custom_str3'] ."\n".
	 //                        "Value should be: ". $order->id;
	 //                    break;

	 //                case PF_ERR_SESSION_ID_MISMATCH:
	 //                    $body .=
	 //                        "Value received : ". $data['custom_str1'] ."\n".
	 //                        "Value should be: ". $order->id;
	 //                    break;

	 //                // For all other errors there is no need to add additional information
	 //                default:
	 //                    break;
	 //            }

	 //            wp_mail( $pfDebugEmail, $subject, $body );
  //           }
  //       } elseif ( ! $pfDone ) {

		// 	$this->log( 'Check status and update order' );

		// 	if ( $order->order_key !== $order_key ) {
		// 		exit;
		// 	}

  //   		switch ( strtolower( $data['payment_status'] ) ) {
  //               case 'complete':
  //                   $this->log( '- Complete' );
		// 			$order->add_order_note( __( 'ITN payment completed', 'woocommerce-gateway-globalpay' ) );
		// 			$order->payment_complete();

  //                   if ( $this->send_debug_email ) {
  //                       $subject = "GlobalPAY ITN on your site";
  //                       $body =
  //                           "Hi,\n\n".
  //                           "A GlobalPAY transaction has been completed on your website\n".
  //                           "------------------------------------------------------------\n".
  //                           "Site: ". $vendor_name ." (". $vendor_url .")\n".
  //                           "Purchase ID: ". $data['m_payment_id'] ."\n".
  //                           "GlobalPAY Transaction ID: ". $data['pf_payment_id'] ."\n".
  //                           "GlobalPAY Payment Status: ". $data['payment_status'] ."\n".
  //                           "Order Status Code: ". $order->status;
  //                       wp_mail( $pfDebugEmail, $subject, $body );
  //                   }
  //               break;
  //   			case 'failed':
  //                   $this->log( '- Failed' );
  //                   $order->update_status( 'failed', sprintf( __( 'Payment %s via ITN.', 'woocommerce-gateway-globalpay' ), strtolower( sanitize_text_field( $data['payment_status'] ) ) ) );

		// 			if ( $this->send_debug_email ) {
	 //                    $subject = "GlobalPAY ITN Transaction on your site";
	 //                    $body =
	 //                        "Hi,\n\n".
	 //                        "A failed GlobalPAY transaction on your website requires attention\n".
	 //                        "------------------------------------------------------------\n".
	 //                        "Site: ". $vendor_name ." (". $vendor_url .")\n".
	 //                        "Purchase ID: ". $order->id ."\n".
	 //                        "User ID: ". $order->user_id ."\n".
	 //                        "GlobalPAY Transaction ID: ". $data['pf_payment_id'] ."\n".
	 //                        "GlobalPAY Payment Status: ". $data['payment_status'];
	 //                    wp_mail( $pfDebugEmail, $subject, $body );
  //                   }
  //       			break;
  //   			case 'pending':
  //                   $this->log( '- Pending' );
  //                   // Need to wait for "Completed" before processing
  //       			$order->update_status( 'on-hold', sprintf( __( 'Payment %s via ITN.', 'woocommerce-gateway-globalpay' ), strtolower( sanitize_text_field( $data['payment_status'] ) ) ) );
  //       			break;
  //   			default:
  //                   // If unknown status, do nothing (safest course of action)
  //   			break;
  //           }
		// }

    	return $pfError;
    }

	/**
	 * Check GlobalPAY ITN response.
	 *
	 * @since 1.0.0
	 */
	public function check_itn_response() {

		$this->handle_itn_request( stripslashes_deep( $_GET ) );
	}

	/**
	 * Setup constants.
	 *
	 * Setup common values and messages used by the GlobalPAY gateway.
	 *
	 * @since 1.0.0
	 */
	public function setup_constants() {
		//// Create user agent string
		define( 'PF_SOFTWARE_NAME', 'WooCommerce' );
		define( 'PF_SOFTWARE_VER', WC_VERSION );
		define( 'PF_MODULE_NAME', 'WooCommerce-GlobalPAY-Free' );
		define( 'PF_MODULE_VER', $this->version );

		// Features
		// - PHP
		$pfFeatures = 'PHP ' . phpversion() .';';

		// - cURL
		if ( in_array( 'curl', get_loaded_extensions() ) ) {
		    define( 'PF_CURL', '' );
		    $pfVersion = curl_version();
		    $pfFeatures .= ' curl '. $pfVersion['version'] .';';
		} else {
		    $pfFeatures .= ' nocurl;';
		}

		// Create user agrent
		define( 'PF_USER_AGENT', PF_SOFTWARE_NAME .'/'. PF_SOFTWARE_VER .' ('. trim( $pfFeatures ) .') '. PF_MODULE_NAME .'/'. PF_MODULE_VER );

		// General Defines
		define( 'PF_TIMEOUT', 15 );
		define( 'PF_EPSILON', 0.01 );

		// Messages
		// Error
		define( 'PF_ERR_AMOUNT_MISMATCH', __( 'Amount mismatch', 'woocommerce-gateway-globalpay' ) );
		define( 'PF_ERR_BAD_ACCESS', __( 'Bad access of page', 'woocommerce-gateway-globalpay' ) );
		define( 'PF_ERR_BAD_SOURCE_IP', __( 'Bad source IP address', 'woocommerce-gateway-globalpay' ) );
		define( 'PF_ERR_CONNECT_FAILED', __( 'Failed to connect to GlobalPAY', 'woocommerce-gateway-globalpay' ) );
		define( 'PF_ERR_INVALID_SIGNATURE', __( 'Security signature mismatch', 'woocommerce-gateway-globalpay' ) );
		define( 'PF_ERR_MERCHANT_ID_MISMATCH', __( 'Merchant ID mismatch', 'woocommerce-gateway-globalpay' ) );
		define( 'PF_ERR_NO_SESSION', __( 'No saved session found for ITN transaction', 'woocommerce-gateway-globalpay' ) );
		define( 'PF_ERR_ORDER_ID_MISSING_URL', __( 'Order ID not present in URL', 'woocommerce-gateway-globalpay' ) );
		define( 'PF_ERR_ORDER_ID_MISMATCH', __( 'Order ID mismatch', 'woocommerce-gateway-globalpay' ) );
		define( 'PF_ERR_ORDER_INVALID', __( 'This order ID is invalid', 'woocommerce-gateway-globalpay' ) );
		define( 'PF_ERR_ORDER_NUMBER_MISMATCH', __( 'Order Number mismatch', 'woocommerce-gateway-globalpay' ) );
		define( 'PF_ERR_ORDER_PROCESSED', __( 'This order has already been processed', 'woocommerce-gateway-globalpay' ) );
		define( 'PF_ERR_PDT_FAIL', __( 'PDT query failed', 'woocommerce-gateway-globalpay' ) );
		define( 'PF_ERR_PDT_TOKEN_MISSING', __( 'PDT token not present in URL', 'woocommerce-gateway-globalpay' ) );
		define( 'PF_ERR_SESSIONID_MISMATCH', __( 'Session ID mismatch', 'woocommerce-gateway-globalpay' ) );
		define( 'PF_ERR_UNKNOWN', __( 'Unkown error occurred', 'woocommerce-gateway-globalpay' ) );

		// General
		define( 'PF_MSG_OK', __( 'Payment was successful', 'woocommerce-gateway-globalpay' ) );
		define( 'PF_MSG_FAILED', __( 'Payment has failed', 'woocommerce-gateway-globalpay' ) );
		define( 'PF_MSG_PENDING',
		    __( 'The payment is pending. Please note, you will receive another Instant', 'woocommerce-gateway-globalpay' ).
		    __( ' Transaction Notification when the payment status changes to', 'woocommerce-gateway-globalpay' ).
		    __( ' "Completed", or "Failed"', 'woocommerce-gateway-globalpay' ) );

		do_action( 'woocommerce_gateway_globalpay_setup_constants' );
	}

	/**
	 * Log system processes.
	 * @since 1.0.0
	 */
	public function log( $message ) {
		if ( 'yes' === $this->get_option( 'testmode' ) ) {
			if ( ! $this->logger ) {
				$this->logger = new WC_Logger();
			}
			$this->logger->add( 'globalpay', $message );
		}
	}

	/**
	 * validate_signature()
	 *
	 * Validate the signature against the returned data.
	 *
	 * @param array $data
	 * @param string $signature
	 * @since 1.0.0
	 */
	public function validate_signature ( $data, $signature ) {
	    $result = $data['signature'] === $signature;
	    $this->log( 'Signature = '. ( $result ? 'valid' : 'invalid' ) );
	    return $result;
	}

	/**
	 * validate_ip()
	 *
	 * Validate the IP address to make sure it's coming from GlobalPAY.
	 *
	 * @param array $data
	 * @since 1.0.0
	 */
	public function validate_ip( $sourceIP ) {
	    // Variable initialization
	    $validHosts = array(
	        'www.zenithbank.com.gh'
	    );

	    $validIps = array();

	    foreach( $validHosts as $pfHostname ) {
	        $ips = gethostbynamel( $pfHostname );

	        if ( $ips !== false ) {
	            $validIps = array_merge( $validIps, $ips );
			}
	    }

	    // Remove duplicates
	    $validIps = array_unique( $validIps );

	    $this->log( "Valid IPs:\n". print_r( $validIps, true ) );

	    return in_array( $sourceIP, $validIps );
	}

	/**
	 * validate_response_data()
	 *
	 * @param $post_data String Parameter string to send
	 * @param $proxy String Address of proxy to use or NULL if no proxy
	 * @since 1.0.0
	 */
	public function validate_response_data( $post_data, $pfProxy = null ) {
	    $this->log( 'Host = '. $this->validate_url );
	    $this->log( 'Params = '. print_r( $post_data, true ) );

		if ( ! is_array( $post_data ) ) {
			return false;
		}

		$response = wp_remote_post( $this->validate_url, array(
			'body'       => $post_data,
			'timeout'    => 70,
			'user-agent' => PF_USER_AGENT
		));

		if ( is_wp_error( $response ) || empty( $response['body'] ) ) {
			return false;
		}

		parse_str( $response['body'], $parsed_response );

		$response = $parsed_response;

	    $this->log( "Response:\n" . print_r( $response, true ) );

	    // Interpret Response
	    if ( is_array( $response ) && in_array( 'VALID', array_keys( $response ) ) ) {
	    	return true;
	    } else {
	    	return false;
	    }
	}

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
	public function amounts_equal ( $amount1, $amount2 ) {
		return ! ( abs( floatval( $amount1 ) - floatval( $amount2 ) ) > PF_EPSILON );
	}
}
