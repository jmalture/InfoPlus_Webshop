<?php


class Cart{

	function __construct() {
        $this -> register_api_routes();
    }
	
	//API call for UpdateCart endpoint
	function update_cart(){
		//Should have the parameters in the future (this is currently hard coded):
		//itemID (reference to WooCommerce Cart Item - existing = Update Cart Item
		//projectID = UUID of line item in IFE
		//display = String = Name of the product
		//price = integer in cents
		//thumbnail = URL that frontend download from backend displayed in the cart
		//productID = SKU or something similar
	
		//Getting parameters
		$itemID = isset( $_GET['itemID'] ) ? strval( $_GET['itemID'] ) : null;
		$projectID = isset( $_GET['projectID'] ) ? strval( $_GET['projectID'] ) : null;
		$display = isset( $_GET['display'] ) ? strval( $_GET['display'] ) : null;
		$price = isset( $_GET['price'] ) ? strval( $_GET['price'] ) : null;
		$thumbnail_url = isset( $_GET['thumbnail_url'] ) ? strval( $_GET['thumbnail_url'] ) : null;
		$productID = isset( $_GET['productID'] ) ? strval( $_GET['productID'] ) : null;
		$quantity = isset( $_GET['quantity'] ) ? strval( $_GET['quantity'] ) : null;
		$coupon = isset( $_GET['coupon'] ) ? strval( $_GET['coupon'] ) : null;
		
		if(!isset($productID)){
			return new WP_Error('no_item_id', 'No parameter itemID found', array('status' => 400));
		}
		
		//$id = $this -> get_product_by_item_id($itemID);
		
		//$wc_product =  wc_get_product($id['woocommerce_ref_id']);
		//
	
		$product = $this -> get_published_product_by_sku($productID);
		
		if(!isset($product)){
			status_header( 404 );
			die("Product does not exist");
		}

		//Thumbnail section
		/*if (!empty($_FILES['file'])) {
        	$file = $_FILES['file'];
			

			$allowed_types = array('jpg', 'jpeg', 'png');
    		$file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
			
			if (in_array($file_ext, $allowed_types)) {
				$upload_dir = wp_upload_dir();
				$custom_dir = $upload_dir['basedir'] . '/infoplus-order-images/';
				$custom_url = $upload_dir['baseurl'] . '/infoplus-order-images/';

				if (!file_exists($custom_dir)) {
					wp_mkdir_p($custom_dir);
				}
				
				 // Generate unique filename
				$filename = wp_unique_filename($custom_dir, $file['name']);
				$file_path = $custom_dir . $filename;
				
				if (move_uploaded_file($file['tmp_name'], $file_path)) {
					chmod($file_path, 0644);

					array(
						'success' => true,
						'message' => 'File uploaded successfully',
						'filename' => $filename,
						'file_path' => $file_path,
						'file_url' => $custom_url . $filename
					);
					
					$thumbnail_url = $custom_url . $filename;
				}
			}
    	}else{
			$thumbnail_url = $product['variant']['image']['url'];
		}*/
		
		
		$cookies = [];
		
		//Getting the wc cookie
		foreach ($_COOKIE as $name => $value) {
			if (strpos($name, 'wp_woocommerce_session_') === 0) {
				$cookies[] = new WP_Http_Cookie([
					'name'  => $name,
					'value' => $value,
				]);
			}
		}

		foreach ($_COOKIE as $name => $value) {
			if (strpos($name, 'wordpress_logged_in_') === 0) {
				$cookies[] = new WP_Http_Cookie([
					'name'  => $name,
					'value' => $value,
				]);
			}
		}
		
		/*if(empty($cookies)){
			status_header( 400 );
			die("No cookie present");
		}*/
		
		// If guest has no session cookie yet
		if ( ! WC()->session ) {
			wc_load_cart();
		}

		WC()->session->init();
		
		if ( ! WC()->session->has_session() ) {
			WC()->session->set_customer_session_cookie( true );
		}
		
		//Generate the request
		$parentId = $product['parentId'];     
		$variantId = $product['variant']['variation_id'];     
		$attributes = $product['variant']['attributes']; 
	
		$ife_price = $price / 100;
		
		$updateItem = $this -> project_already_exists($projectID);
				
		$thumbnail_url;
		
		if(!isset($thumbnail_url) || empty($thumbnail_url)){
			$thumbnail_url = $product['variant']['image']['url'];
		}else{
			$thumbnail_url = $thumbnail_url . "&size=100x100";
		}
		
		if($updateItem){
			
			WC()->cart->cart_contents[ $updateItem['key'] ]['ife_thumbnail_url'] = $thumbnail_url;
			
			WC()->cart->set_session();
		}
		
		WC()->cart->get_cart_from_session();
		
		$display_modified = $display ." ". $quantity ."x";
		
		$custom_data = [
			'ife_thumbnail_url' => $thumbnail_url,
			'display'        => $display_modified,
			'project_id' => $projectID,
			'ife_price' => $ife_price
		];
		
		//Adding to cart
		 $cart_item_key = WC()->cart->add_to_cart($parentId, $quantity, $variantId, $attributes, $custom_data);
		
		if ( $cart_item_key ) {
			$cart_item = WC()->cart->get_cart_item( $cart_item_key );
		}
	
		//check coupon
		if(isset($coupon)){
			WC()->cart->add_discount($coupon);
			WC()->cart->calculate_totals();
		}

		$baseUrl = 'https://navajowhite-rhinoceros-679020.hostingersite.com/cart';

		/*$params = [
			'add-to-cart'  => $parentId,
			'variation_id' => $variantId,
			'quantity'     => 1,
		];

		$params = array_merge($params, $attributes);

		$add_to_cart_url = $baseUrl . '?' . http_build_query($params);
	
		$response = wp_remote_get($add_to_cart_url, [
			'timeout' => 30,
			'cookies' => $cookies,
			'headers' => [
				'User-Agent' => 'Internal-AddToCart-Request'
			]
		]);*/
		
		wp_safe_redirect($baseUrl);
		exit;
	}
	
	function get_published_products(){
		return $products = wc_get_products([
			'limit' => '-1'
		]);
	}
	
	function get_published_product_by_sku($sku){
		if(!isset($sku)){
			return;
		}
		
		$products = $this -> get_published_products();
			
		foreach($products as $product){
			if(!$product->is_type( 'variable' )){
				continue;
			}
			
			$variants = $product -> get_available_variations();
			
			foreach($variants as $variant){
				if($variant['sku'] == $sku){
					return array(
						'variant' => $variant,
						'parentId' => $product -> get_id(),
					);
				}
			}
		}
	}
	
	function get_product_by_item_id($uuid) {
		global $wpdb;
		
		$table = 'infoplus_products';

		$query = $wpdb->prepare("SELECT * FROM $table WHERE id = '". $uuid ."'");

		return $wpdb->get_row($query, ARRAY_A); 
	}
	
	function project_already_exists($projectID){
		if (isset($projectID) && WC()->cart ) {
			$cart_items = WC()->cart->get_cart();

			foreach ( $cart_items as $cart_item_key => $cart_item ) {
				$id = isset( $cart_item['project_id'] ) ? $cart_item['project_id'] : null;
				
				if($id === $projectID){
					return $cart_item;
				}
			}
		}
		
		return null;
	}
	
	function testing(){
		$sku = isset( $_GET['sku'] ) ? strval( $_GET['sku'] ) : null;
		
		if(!isset($sku)){
			return;
		}
		
		$products = wc_get_products([
			'limit' => '-1'
		]);
		
		$ids = array();
		
		foreach($products as $product){
			if(!$product->is_type( 'variable' )){
				continue;
			}
			
			$variants = $product -> get_available_variations();
			
			foreach($variants as $variant){
				$ids[] = $variant['sku'];
				if($variant['sku'] == $sku){
					/*return array(
						'variant' => $variant,
						'parentId' => $product -> get_id(),
					);*/
				}
			}
		}
		
		return $ids;
	}
	
	function register_api_routes() {
		register_rest_route(
            'infoplus/'. INFOPLUS_API_VERSION, 'cart/updateCart',
            array(
                'methods'  => 'GET',
                'callback' => [$this, 'update_cart'],
				'args' => array(
					'itemID' => array(
						'required' => false,
						'type' => 'string',
					),
					'projectID' => array(
						'required' => true,
						'type' => 'string',
					),
					'display' => array(
						'required' => true,
						'type' => 'string',
					),
					'price' => array(
						'required' => true,
						'type' => 'string',
					),
					'thumbnail_url' => array(
						'required' => false,
						'type' => 'string',
					),
					'productID' => array(
						'required' => true,
						'type' => 'string',
					),
					'quantity' => array(
						'required' => true,
						'type' => 'string',
					),
					'coupon' => array(
						'required' => false,
						'type' => 'string',
					),
            	),
				'permission_callback' => '__return_true',
            )
        );
		
		register_rest_route(
            'infoplus/'. INFOPLUS_API_VERSION, 'cart/testing',
            array(
                'methods'  => 'GET',
                'callback' => [$this, 'testing'],
				'args' => array(
					'sku' => array(
						'required' => true,
						'type' => 'string',
					),
            	),
				'permission_callback' => '__return_true',
            )
        );
	}
}

