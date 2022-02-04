<?php
/**
 * Plugin Name: WooCommerce Simple Tester Gateway
 * Plugin URI: https://github.com/gerharddt/
 * Description: Testing gateway to return all possible outcomes. Not for production.
 * Author: gerharddt
 * Author URI: https://github.com/gerharddt/
 * Version: 1.0.0
 * Text Domain: woocommerce-gateway-simple-tester
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package   WC-Gateway-Simple-Tester
 * @author    gerharddt
 * @category  Admin
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 *
 */
 
defined( 'ABSPATH' ) or exit;


// make sure woocommerce is active
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	return;
}


// add the dropdown to the payment_box
add_filter( 'woocommerce_gateway_description', 'wc_simple_tester_gateway_custom_fields', 20, 2 );
function wc_simple_tester_gateway_custom_fields( $description, $payment_id ){

	ob_start();

	echo '<div  class="simple-tester-fields" style="padding:0;">';

		woocommerce_form_field( 'simple_transaction_response', array(
			'type'          => 'select',
			'label'         => __("Please select the desired response:", "woocommerce"),
			'class'         => array('form-row-wide'),
			'required'      => false,
			'options'       => array(
				'choice-success'  => __("Success (Processing)", "woocommerce"),
				'choice-failed'  => __("Declined Transaction (Failed)", "woocommerce"),
				'choice-pending' => __("Pending Payment (Pending)", "woocommerce"),
				'choice-hold' => __("Awaiting Payment (On Hold)", "woocommerce"),
				'choice-cancelled' => __("Cancelled Payment (Cancelled)", "woocommerce"),
			),
		), '');

	echo '<div>';

	$description .= ob_get_clean(); // Append buffered content

	return $description;
}


// save "Udfyld EAN" number to the order as custom meta data
function wc_simple_tester_udfyld_ean_to_order_meta_data( $order, $data ) {

    if( $data['payment_method'] === 'simple_tester_gateway' && isset( $_POST['simple_transaction_response'] ) ) {

        $order->update_meta_data( '_simple_transaction_response', sanitize_text_field( $_POST['simple_transaction_response'] ) );
    }
}
add_action('woocommerce_checkout_create_order', 'wc_simple_tester_udfyld_ean_to_order_meta_data', 10, 4 );


// add the gateway to woocommerce available gateways
function wc_simple_tester_add_to_gateways( $gateways ) {
	$gateways[] = 'WC_Gateway_simple_Tester';
	return $gateways;
}
add_filter( 'woocommerce_payment_gateways', 'wc_simple_tester_add_to_gateways' );


// adds plugin page links
function wc_simple_tester_gateway_plugin_links( $links ) {

	$plugin_links = array(
		//'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=offline_gateway' ) . '">' . __( 'Configure', 'wc-gateway-simple-tester' ) . '</a>'
	);

	return array_merge( $plugin_links, $links );
}
//add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wc_simple_tester_gateway_plugin_links' );


// simple tester payment gateway
function wc_simple_tester_gateway_init() {

	class WC_Gateway_SIMPLE_Tester extends WC_Payment_Gateway {

		/**
		 * Constructor for the gateway.
		 */
		public function __construct() {

			$this->id                 = 'simple_tester_gateway';
			$this->icon               = apply_filters('woocommerce_simple_tester_gateway_icon', '');
			$this->has_fields         = false;
			$this->method_title       = __( 'SIMPLE Tester', 'wc-gateway-simple-tester' );
			$this->method_description = __( 'Allows for test payments. Allow to choose payment outcome for testing.', 'wc-gateway-simple-tester' );

			// Load the settings.
			$this->init_form_fields();
			$this->init_settings();

			// Define user set variables
			$this->title        = $this->get_option( 'title' );
			//$this->description  = "ewewew"; //$this->get_option( 'description' );
			$this->instructions = $this->get_option( 'instructions', $this->description );
		  
			// Actions
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
		  
			// Customer Emails
			add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
		}
	
	
		/**
		 * Initialize Gateway Settings Form Fields
		 */
		public function init_form_fields() {
	  
			$this->form_fields = apply_filters( 'wc_simple_tester_form_fields', array(
		  
				'enabled' => array(
					'title'   => __( 'Enable/Disable', 'wc-gateway-simple-tester' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable SIMPLE Tester Payment', 'wc-gateway-simple-tester' ),
					'default' => 'yes'
				),
				
				'title' => array(
					'title'       => __( 'Title', 'wc-gateway-simple-tester' ),
					'type'        => 'text',
					'description' => __( 'This controls the title for the payment method the customer sees during checkout.', 'wc-gateway-simple-tester' ),
					'default'     => __( 'SIMPLE Tester Payment', 'wc-gateway-simple-tester' ),
					'desc_tip'    => true,
				),
				
				'description' => array(
					'title'       => __( 'Description', 'wc-gateway-simple-tester' ),
					'type'        => 'textarea',
					'description' => __( 'Payment method description that the customer will see on your checkout.', 'wc-gateway-simple-tester' ),
					'default'     => __( 'Please remit payment to Store Name upon pickup or delivery.', 'wc-gateway-simple-tester' ),
					'desc_tip'    => true,
				),
				
				'instructions' => array(
					'title'       => __( 'Instructions', 'wc-gateway-simple-tester' ),
					'type'        => 'textarea',
					'description' => __( 'Instructions that will be added to the thank you page and emails.', 'wc-gateway-simple-tester' ),
					'default'     => '',
					'desc_tip'    => true,
				),
			) );
		}
	
	
		/**
		 * Output for the order received page.
		 */
		public function thankyou_page() {
			if ( $this->instructions ) {
				//echo wpautop( wptexturize( $this->instructions ) );
			}
		}
	
	
		/**
		 * Add content to the WC emails.
		 *
		 * @access public
		 * @param WC_Order $order
		 * @param bool $sent_to_admin
		 * @param bool $plain_text
		 */
		public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
		
			if ( $this->instructions && ! $sent_to_admin && $this->id === $order->payment_method && $order->has_status( 'on-hold' ) ) {
				//echo wpautop( wptexturize( $this->instructions ) ) . PHP_EOL;
			}
		}
	
	
		/**
		 * Process the payment and return the result
		 *
		 * @param int $order_id
		 * @return array
		 */
		public function process_payment( $order_id ) {
	
			$order = wc_get_order( $order_id );
			$simpleres = $order->get_meta('_simple_transaction_response');

			if ($simpleres == 'choice-failed') {
				$order->update_status( 'failed' );

				//wc_add_notice( __('Payment error:', 'woothemes') . $error_message, 'error' );
				//return;

			} else if ($simpleres == 'choice-success') {
				$order->update_status( 'processing' );

				// Reduce stock levels
				$order->reduce_order_stock();

				// Remove cart
				WC()->cart->empty_cart();

				// Return thankyou redirect
				return array(
					'result' 	=> 'success',
					'redirect'	=> $this->get_return_url( $order )
				);

			} else if ($simpleres == 'choice-pending') {
				$order->update_status( 'pending' );

				// Return thankyou redirect
				return array(
					'result' 	=> 'pending',
					'redirect'	=> $this->get_return_url( $order )
				);

			} else if ($simpleres == 'choice-hold') {
				$order->update_status('on-hold', __( 'Awaiting cheque payment', 'woocommerce' ));

				// Return thankyou redirect
				return array(
					'result' 	=> 'hold',
					'redirect'	=> $this->get_return_url( $order )
				);
			} else if ($simpleres == 'choice-cancelled') {
				$order->update_status('cancelled', __( 'Cancelled payment', 'woocommerce' ));
				// Return thankyou redirect
				return array(
					'result' 	=> 'cancelled',
					'redirect'	=> $this->get_return_url( $order )
				);
			}

		}
	
  } // end \WC_Gateway_SIMPLE_Tester class
}
add_action( 'plugins_loaded', 'wc_simple_tester_gateway_init', 11 );