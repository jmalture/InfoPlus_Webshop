<?php

/*
Plugin Name: InfoplusAPI
Description: Inforplus custom API.
Version: 1.00
Author: Václav Řežábek
Text Domain: infoplus-api
*/

define('INFOPLUS_API_VERSION', 'v1');

require_once __DIR__ . '/entities/Products.php';
require_once __DIR__ . '/entities/Cart.php';
require_once __DIR__ . '/entities/Order.php';

require_once __DIR__ . '/modules/CustomerDashboard.php';

// Enqueue script to allow CORS headers
add_action( 'rest_api_init', function () {
    add_action( 'rest_pre_serve_request', function () {
        header( 'Access-Control-Allow-Headers: Authorization, Content-Type, X-WP-Wpml-Language', true );
        header("Access-Control-Allow-Origin: *");
    } );
}, 15 );


function initObjects(){
	$products = new Products();
	$cart = new Cart();
	$customerDashboard = new CustomerDashboard();
	//$order = new Order();
}

	function validate_api_key(WP_REST_Request $request) {
		$valid_api_key = 'Bearer ' . (defined('INFOPLUS_API_KEY') ? INFOPLUS_API_KEY : ''); // set INFOPLUS_API_KEY in wp-config.php

		$headers = $request->get_headers();
		if (isset($headers['authorization'])) {
			$api_key = $headers['authorization'][0];

			if ($api_key === $valid_api_key) {
				return true;
			}
		}

    	return new WP_Error('unauthorized', 'Invalid API Key', ['status' => 401]);
	}	

	function try_order_action_again($order_id){
		
		if(!isset($order_id)){
			//Return error because order id is not present
			return;
		}
		
		if($tries > 10){
			//Send some comunication that the order was not successfull
			return;
		}
		
		$order = wc_get_order( $order_id );

		$tries++;
		
		testing();
		
		//new_order_action( $order_id );
	}

	function new_order_action( $order_id ) {
		$order = wc_get_order( $order_id );

		if (!$order) {
			return array('error' => 'Order not found', 'retry' => true);
		}
		
		$attempts = $order->get_meta( 'order_attempts', true );
	
		if(empty($attempts)){
			$order->update_meta_data( 'order_attempts', 1 ); 
			$order->save();
		}
		
		// Billing data
		$billing = array(
			'firstName' => $order->get_billing_first_name(),
			'lastName'  => $order->get_billing_last_name(),
			'company'   => $order->get_billing_company(),
			'address1'  => $order->get_billing_address_1(),
			'address2'  => $order->get_billing_address_2(),
			'city'      => $order->get_billing_city(),
			'state'     => $order->get_billing_state(),
			'postcode'  => $order->get_billing_postcode(),
			'country'   => $order->get_billing_country(),
			'email'     => $order->get_billing_email(),
			'phone'     => $order->get_billing_phone(),
		);

		// Shipping data
		$shipping = array(
			'firstName' => $order->get_shipping_first_name(),
			'lastName'  => $order->get_shipping_last_name(),
			'company'   => $order->get_shipping_company(),
			'address1'  => $order->get_shipping_address_1(),
			'address2'  => $order->get_shipping_address_2(),
			'city'      => $order->get_shipping_city(),
			'state'     => $order->get_shipping_state(),
			'postcode'  => $order->get_shipping_postcode(),
			'country'   => $order->get_shipping_country(),
		);

		//Getting items
		$items = [];

		foreach ( $order->get_items() as $item_id => $item ) {
			$product      = $item->get_product();
			$name         = $item->get_name();
			$quantity     = $item->get_quantity();
			$price        = $product ? intval($product->get_price()) : 0;
			$project_id   = $item->get_meta('project_id'); 

			$items[] = [
				'name'       => $name,
				'price'      => $price * 100,
				'quantity'   => $quantity,
				'projectID' => $project_id ?: null,
			];
		}

		// Main order data
		$data = array(
			'orderID' => $order->get_id(),
			'price'   => $order->get_total() * 100,
			'date'    => $order->get_date_created()->date('Y-m-d H:i:s'),
			'billing' => $billing,
			'shipping'=> $shipping,
			'items' => $items
		);

		//Sending JSON to IFE
		$url = "https://editor.infoplus.de/processOrder";

		$response = wp_remote_post(
			$url,
			[
				'timeout' => 10,
				'headers' => [
					'Content-Type' => 'application/json',
					'User-Agent'   => 'Internal-AddToCart-Request'
				],
				'body' => wp_json_encode($data),
			]
		);
		
		if (is_wp_error($response)) {
		
			$attempts = $order->get_meta( 'order_attempts', true );

			if($attempts > 10){
				//Sending email for vr at this moment
				wp_mail(
					"vr@alture.io",
					"Order retries has been reached!",
					"Order " . $order->get_id()
				);
				return;	
			}

			if (!wp_next_scheduled('testing', array())) {
				as_schedule_single_action(time() + 300, 'woocommerce_new_order_retry', array($testing_order));
				$attempts++;

				$order->update_meta_data( 'order_attempts',  $attempts); 
				$order->save();
			}
		}
	}

function testing(){
	return $current_user = wp_get_current_user();
   return "Hello infoplus";
}

function testing_again($order_id, $tries){
	
}

function get_if_id_by_woocommerce_id($id){
	global $wpdb;
	
	return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wpdb->posts WHERE ID = $d", $id ) );
}

function infoplus_register_api_routes() {
    register_rest_route('infoplus/'. INFOPLUS_API_VERSION, '/testing',
        array(
            'methods'  => 'GET',
            'callback' => 'testing',
        )
    );
}

add_filter(
  'woocommerce_store_api_cart_item_images',
  function ($product_images, $cart_item, $cart_item_key) {

    if (!isset($cart_item['ife_thumbnail_url']) || empty($cart_item['ife_thumbnail_url'])) {
        return $product_images;
    }

    $thumbnail_url = $cart_item['ife_thumbnail_url'];

    return [
        (object) [
            'id'        => 0,
            'src'       => $thumbnail_url,
            'thumbnail' => $thumbnail_url,
            'srcset'    => '',
            'sizes'     => '',
            'name'      => __('Custom product image', 'your-textdomain'),
            'alt'       => __('Custom product image', 'your-textdomain'),
        ],
    ];
  },
  10,
  3
);


//add_filter( 'woocommerce_cart_item_thumbnail', 'ife_custom_cart_item_thumbnail', 10, 3 );

function ife_custom_cart_item_thumbnail($product_thumbnail, $cart_item, $cart_item_key) {
    if (isset($cart_item['ife_thumbnail_url']) && !empty($cart_item['ife_thumbnail_url'])) {
        $thumbnail_url = esc_url($cart_item['ife_thumbnail_url']);
        $product_thumbnail = '<img src="' . $thumbnail_url . '" alt="" style="max-width:80px;height:auto;" />';
    }

    return $product_thumbnail;
}

// Main Cart + Checkout
add_filter( 'woocommerce_cart_item_name', 'ife_custom_cart_item_name', 10, 3 );
function ife_custom_cart_item_name( $product_name, $cart_item, $cart_item_key ) {
    if ( ! empty( $cart_item['display'] ) ) {
        $product_name = esc_html( $cart_item['display'] );
    }
    return $product_name;
}

add_filter( 'woocommerce_store_api_cart_item', function( $response, $cart_item ) {
    if ( empty( $cart_item['ife_price'] ) ) return $response;

    $price = (float) str_replace( ',', '.', (string) $cart_item['ife_price'] );
    // Woo outputs prices in minor units via 'prices->price'
    $minor = (int) round( $price * wc_get_price_decimals_power() ); // e.g., 3.95 -> 395

    if ( isset( $response['prices'] ) ) {
        $response['prices']['price']         = $minor;
        $response['prices']['sale_price']    = $minor;
        $response['prices']['regular_price'] = $minor;
    }
    return $response;
}, 10, 2 );

// When adding to cart
add_filter( 'woocommerce_add_cart_item_data', function( $data, $product_id, $variation_id ) {
    foreach ( ['display','ife_thumbnail_url','project_id','ife_price'] as $k ) {
        if ( isset( $_POST[$k] ) ) {
            $data[$k] = wc_clean( wp_unslash( $_POST[$k] ) );
        }
    }
    // prevent merge when price / project differs
    if ( ! empty( $data['ife_price'] ) || ! empty( $data['project_id'] ) ) {
        $data['infoplus_unique'] = md5( wp_json_encode( [
            $data['ife_price'] ?? '',
            $data['project_id'] ?? '',
            $data['display']   ?? '',
        ] ) . microtime( true ) );
    }
    return $data;
}, 10, 3 );

// Restore custom fields from session so filters see them on full page loads
add_filter( 'woocommerce_get_cart_item_from_session', function( $item, $values ) {
    foreach ( ['display','ife_thumbnail_url','project_id','ife_price','infoplus_unique'] as $k ) {
        if ( isset( $values[$k] ) ) $item[$k] = $values[$k];
    }
    return $item;
}, 10, 2 );

add_action( 'woocommerce_before_calculate_totals', function( WC_Cart $cart ) {
    if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;

    foreach ( $cart->get_cart() as $key => &$item ) {
        if ( empty( $item['ife_price'] ) || empty( $item['data'] ) ) continue;

        // Accept "12.34" or "12,34" or integers
        $raw   = (string) $item['ife_price'];
        $price = is_numeric( $raw ) ? (float) $raw : (float) str_replace( ',', '.', $raw );

        // If you're sending cents like 395 -> €3.95, uncomment:
        // if (ctype_digit($raw) && $price >= 100) $price = $price / 100;

        $item['data']->set_price( wc_format_decimal( $price ) ); // unit price
    }
}, 10 );

add_filter( 'woocommerce_store_api_cart_item', function( $response, $cart_item, $request ) {

    if ( ! empty( $cart_item['display'] ) ) {
        // 1) Main title used by the Cart/Checkout Blocks
        $new_name = wp_strip_all_tags( $cart_item['display'] );
        $response['name'] = $new_name;

        // 2) Also update related title fields some themes/blocks use
        // (prevents ARIA label / tooltip mismatches)
        if ( isset( $response['permalink'] ) ) {
            $response['permalink_title'] = $new_name;
        }

        // 3) Expose it for any JS integrations
        $response['extensions']['infoplus']['display'] = $new_name;
    }

    return $response;
}, 99, 3 );

add_action( 'woocommerce_new_order', 'new_order_action', 10, 1 );

//testing to be removed
add_action( 'woocommerce_new_order_retry', 'new_order_action', 10, 1);

add_action( 'woocommerce_checkout_create_order_line_item', function( $item, $cart_item_key, $values ) {
    foreach ( ['ife_thumbnail_url','display','project_id','ife_price'] as $k ) {
        if ( isset( $values[$k] ) ) $item->add_meta_data( $k, $values[$k], true );
    }
}, 10, 3 );

add_action('woocommerce_new_order_retry', 'try_order_action_again', 10, 1);

add_action( 'rest_api_init', 'infoplus_register_api_routes' );
add_action( 'rest_api_init', 'initObjects');
