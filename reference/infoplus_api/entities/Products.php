<?php

require_once plugin_dir_path( dirname( __FILE__ ) ) . 'ife/ifeConnector.php';

class Products{
	
	//woocommerce keys
	private $cosnumer_key = 'ck_REDACTED_ROTATE_THIS_KEY';
    private $consumer_secret = 'cs_REDACTED_ROTATE_THIS_KEY';
	
	//Ife connector
	private $connector;
	
	//Email report of synchronization
	private $recipients = [
		'vr@alture.io'
	];

    function __construct() {
        $this -> register_api_routes();
		
		 $this->connector = new IfeConnector();
    }
	
	//Products functions
    function getProducts() {
	
		$products = $this -> get_products();
		
		$product_uuids = $this -> get_woocommerce_products_uuid($products);
		
		$data = [];
		
		global $wpdb;

		foreach ( $products as $product ) {
		
			$ife_id = $wpdb -> get_row('SELECT ife_id FROM infoplus_products WHERE woocommerce_ref_id = '. $product -> get_id());
			
			$default_design_id = '';//wp_generate_uuid4();
			$compatible_products = array();
			$price = $product->get_price();
			$display = $product->get_name();
			$name = 'infoplus'. '.' .$product->get_slug(). '.' . 'v1';
			$vat = 19;
			$description = $product->get_description();
			$designs = array();
			$type = $product -> get_type();
			$sku = $product -> get_sku();
			$min_qty = $product->get_meta('min_quantity');
			$last_modified = $product->get_date_modified() -> date_i18n( 'Y-m-d H:i:s' );
	
			$image_id  = $product->get_image_id();
			$image_url = wp_get_attachment_url( $image_id );
			
			$category = get_the_terms( $product->get_id(), 'product_cat')[0] -> slug;
			
			//Strip the name
			$strip_name = str_replace(' ', '', $name);
			
			$last_synchronization = $this -> get_latest_synchronization($product -> get_id());
			
			//0 -> synchronized, 1 -> changed, 2 -> never synchronized
			$state;
			
			if(empty($ife_id) || !isset($ife_id)){
				$state = 2;
			}else if($last_synchronization && strtotime( $last_modified ) < strtotime( $last_synchronization -> endTime ) )
				$state = 0; 
			else {
				$state = 1;
			}
			
			//Variants
			$variants = $this -> get_variants_for_product_internal_by_id($product -> get_id(), $woocomerce_product -> id);
			

			$data[] = [
				'id' => $product -> get_id(),
				'printApiId' => $ife_id -> ife_id,
				//'defaultDesignId'    => $default_design_id,
				//'compatibleProducts'  => $compatible_products,
				'price' => $price,
				'display' => $display,
				'name' => $strip_name,
				//'vat' => $vat,
				//'minQuantity' => $min_qty,
				//'description' => $description,
				//'designs' => $designs,
				'type' => $type,
				'sku' => $sku,
				'category' => $category,
				'lastModified' => $last_modified,
				'imageURL' => $image_url,
				'synchronized' => $state,
				'lastSynchronized' => $last_synchronization -> endTime,
				'variants' => $variants
			];
			
		}

		return rest_ensure_response($data);
	}
	
	function get_product_by_id($id){
		$product = wc_get_product($id);
		
		return $product;
	}
	
	function get_published_products(){
		return $products = wc_get_products([
			'status' => 'publish'
		]);
	}
	
	function get_products() {
    return wc_get_products( array(
        'limit'  => -1,
    ) );
}
	
	function get_product_by_sku($sku, $published){
		if(!isset($sku) && !is_string($sku)){
			return;
		}
		
		if(!is_bool($published)){
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
					'sku' => $variant['sku'],
					'id' => $variant['variation_id'],
					'parent_id' => $product -> get_id()
					);
				}
			}
		}
		
		return array();
	}
	
	//Price rules functions
	
	function get_bulk_price_rules_for_product() {
		$sku = isset( $_GET['sku'] ) ? strval( $_GET['sku'] ) : null;
		
		return $this -> get_bulk_price_rules_for_product_internal($sku);
	}
	
	function get_bulk_price_rules_for_product_internal($sku){
		global $wpdb;
		
		$rules = $wpdb -> get_results('SELECT * FROM wp_wdp_rules');
		
		foreach($rules as $rule){
			$filters_serialized = $rule->filters;

			$filters_array = maybe_unserialize($filters_serialized);

			if (is_array($filters_array)) {
				foreach ($filters_array as $filter) {
					if (isset($filter['value']) &&is_array($filter['value'])) {
						if(in_array($sku, $filter['value'])){
							$bulk_prices = $rule->bulk_adjustments;
							
							$bulk_array = maybe_unserialize($bulk_prices);
							
							return $bulk_array['ranges'];
						}
					}
				}
			}
		}
		
		return json_encode(array());
	}
	
	//Variants functions
	
	function get_variants_for_product(){
		$product_id = isset( $_GET['productId'] ) ? strval( $_GET['productId'] ) : null;
		
		$products = $this -> get_published_products();
		
		$product_uuids = $this -> get_woocommerce_products_uuid($products);
		
		return $this -> get_variants_for_product_internal_by_id($product_id, $product_uuids);
	}
	
	//$id = WC id example: 45m, $uuids example: 9c8119a9-9d61-4d18-a204-ea668e49a4d3
	function get_variants_for_product_internal_by_id($id, $uuids){
		
		$product = $this -> get_product_by_id($id);
		
		if(!isset($product)){
			return "No product found.";
		}
		
		$children = $product -> get_children();

		if(empty($children)){
			return array();
		}
		
		$variants = $product -> get_available_variations();
	
		$data = [];
		
		$typeIndex = 1;
		
		foreach($variants as $variant){
			
			$attributes = $variant['attributes'];
			
			$variantIndex = 'V'. $typeIndex;
			
			$width = array(
				'type' => 'ValueAnnotation',
				'key' => 'width',
				'value' => empty($variant['dimensions']['width']) ? 0 : $variant['dimensions']['width']
			);
			
			$height = array(
				'type' => 'ValueAnnotation',
				'key' => 'height',
				'value' => empty($variant['dimensions']['height']) ? 0 : $variant['dimensions']['height']		
			);
			
			$sku = $variant['sku'];
			
			$bulk_prices = $this -> get_bulk_price_rules_for_product_internal($sku);
			
			$modifiers = [];
			
			$priceIndex = 1;
	
			foreach($bulk_prices as $price){
				
				$priceIndexString = 'V'. $priceIndex;
				
				$modifiers[] = [
					'type' => 'Quantity'. $priceIndexString,
					'sku' => $sku,
					'min' => $price['from'],
					'price' => $price['value']
				];
				
				$priceIndex++;
			}
			
			$id;
			
	
			foreach($uuids as $uuid){
				if($uuid -> woocommerce_ref_id == $variant['variation_id']){
					$id = $uuid -> id;
					
					break;
				}
			}
			
			if(!isset($id)){
				continue;
			}

			$infoplus_variant = $this -> get_infoplus_product_by_woocommerce_id($variant['variation_id']);
			
			$ife_id = null;
			
			if(!empty($infoplus_variant)){
				$ife_id= $infoplus_variant -> ife_id;
			}
			
			$data[] = [
				'id' => $variant['variation_id'],
				'type' => $attributes['attribute_pa_model'] .'-'. $variantIndex,
				'name' => 'infoplus'. '.' .$attributes['attribute_pa_model'] .'.' .$attributes['attribute_pa_befestigung']. '.' .$attributes['attribute_pa_format']. '.' .$attributes['attribute_pa_oberflache']. '.' .$variantIndex,
				'display' => $attributes['attribute_pa_model'] . ' ' .$attributes['attribute_pa_format'],
				'description' => $variant['variation_description'],
				'price' => $variant['display_price'],
				'sku' => $sku,
				'annotations' => array($width, $height),
				'modifiers' => $modifiers,
				'ifeId' => $ife_id,
				'attributes' => $variant['attributes']
			];
		
			$typeIndex++;
		}
		
		return $data;
	}
	
	function get_all_existing_variants_ids(){
		global $wpdb;
		
		$ids = $wpdb -> get_results('SELECT woocommerce_ref_id FROM infoplus_products WHERE parent IS NOT NULL');
		
		$result = array();
		
		foreach($ids as $id){
			$result[] = $id -> woocommerce_ref_id;
		}
		
		return $result;
	}
	
	function get_infoplus_product_by_woocommerce_id($id){
		if(empty($id)){
			return "Id can not be empty";
		}
		
		global $wpdb;
		
		$sql = $wpdb->prepare(
			"SELECT * 
			 FROM infoplus_products
			 WHERE woocommerce_ref_id = %s  
			 LIMIT 1",
			$id
		);
		
		return $wpdb->get_row($sql);
	}
	
	//Synchronization functions 
	function start_synchronization(){
		$productId = isset( $_GET['productId'] ) ? intval( $_GET['productId'] ) : null;
		
		$products = array();
		
		$synchronization_type;
		
		if(!isset($productId)){
			$products = $this -> get_products();
			
			$synchronization_type = 'all';
		}else{
			$products[] =  $this -> get_product_by_id($productId);
			
			$synchronization_type = 'single';
		}
		
		$startTime = date('Y-m-d H:i:s');
		
		global $wpdb;
		
		$result = array();

		$email_message = "";
		
		$variantsIds = $this -> get_all_existing_variants_ids();
		
		foreach($products as $product){
			$response = $this -> start_single_synchronization($product -> get_id(), $variantsIds);
			
			$endTime = date('Y-m-d H:i:s');
			
			$body = json_decode($response['body'], true);
			
			$email_message .= "<br>Product id: {$product -> get_id()} <br> Start time: {$startTime} <br> End time: {$endTime} <br> Message: {$response['response']['message']} <br> Code: {$response['response']['code']} <br> Action: {$body['state']} <br> Variants:";
			
			foreach($body['variants'] as $variant){
				$email_message .= '<br>'. $variant['variantId'] . ' ' . $variant['variantIfeId'];
			}
			
			$result[] = $response;
			
			//Create new row in the synchronization table
			$synchronization = $this -> create_new_synchronization_row($startTime, $result, "single", $product -> get_id());
		}
		
		$product -> get_id();
		
		//Create new row in the synchronization table
		$synchronization = $this -> create_new_synchronization_row($startTime, $result, $synchronization_type, $product -> get_id());
		
		//Send email
		
		$subject = 'Infoplus synchronization ' . $startTime;  
		$headers = ['Content-Type: text/html; charset=UTF-8'];
		
		//ob_start();
		//include plugin_dir_path( dirname( __FILE__ ) ). 'assets/templates/email-report-synchronization.html';
			
		wp_mail( $this -> getRecipients(), $subject, $email_message, $headers );
			
		return $result;
	}
	
	function start_single_synchronization($id, $variantsIds){
		
		$startTime = $date = date('Y-m-d H:i:s');
		
		$result;
		
		try{
			if(!isset($id)){
				status_header(404);
				die("Id is missing");
			}
			
			//Product
			$product = $this -> get_product_by_id($id);
		
			if(!isset($product)){
				status_header(404);
				die('Product with id: '. $id .' does not exist.');
			}

			global $wpdb;
		
			$sql = $wpdb->prepare(
				"SELECT * FROM infoplus_products WHERE woocommerce_ref_id = %d",
				$id
			);

			$entry = $wpdb->get_row( $sql, ARRAY_A );
			
			if(empty($entry)){
				$entry =  $this -> getConnector() -> create_uuid_for_product($product -> get_id(), 'product', null, $product->get_date_modified() -> date_i18n( 'Y-m-d H:i:s' ), null);
				
				if(!isset($entry)){
					return "New row in product table could not be created.";
				}
			}
			
			$product_ife_id = $entry['ife_id'];
			
			//Getting variants
			$variants = $this -> get_variants_for_product_internal_by_id($product -> get_id(), $entry['id']);
	
			//Requesting synchronization on IFE side
			$ife_result = $this -> getConnector() -> updateProduct($product, $variants, $product_ife_id);
			
			//Synchronization on woocomerce side from IFE response
			$body = json_decode($ife_result['body'], true);
			
			//Sychnronize the ife id
			if($body['productId'] !== $product_ife_id){
				$product_ife_id = $body['productId'];
			
				$this -> getConnector() -> update_infoplus_product_table($product -> get_id(),  $product->get_date_modified() -> date_i18n( 'Y-m-d H:i:s' ), $product_ife_id);
			}
			
			//Synchronize the variants of the product
			$variants_ife_ids = $body['variants'];
			
			foreach($variants_ife_ids as $variant){
				if(!in_array($variant['variantId'], $variantsIds)){
					$this -> getConnector() -> create_uuid_for_product($variant['variantId'], 'variant', $product -> get_id(), $product->get_date_modified() -> date_i18n( 'Y-m-d H:i:s' ), $variant['variantIfeId']);
				}else{
					$this -> getConnector() -> update_infoplus_product_table($variant['variantId'],  $product->get_date_modified() -> date_i18n( 'Y-m-d H:i:s' ), $variant['variantIfeId']);
				}
			}
			
			//Update IFE field on product
			update_post_meta($id, '_ife_uuid', $product_ife_id);
			
			$result = $ife_result;
		}catch(Exception $ex){
			$result = "Could not synchronize following product: ". $product -> get_id() . " with following error". $ex -> get_message();
		}
		
		return $result;
	}
	
	function synchronization_of_variant($start_time, $product_id, $variant, $ife_result){
		$customData = array(
				"ife_result" => $ife_result,
				"product_id" => $product_id,
				"variant_id" => $variant['id'],
				"start_time" => $start_time,
				"end_time" =>  $date = date('Y-m-d H:i:s')
			);
		
		$this -> set_synchronized($variant['id']);

		$synchronization = $this -> create_new_synchronization_row($start_time, $customData, 'single', $variant['id']);
		
		return $customData;
	}
	
	function get_latest_synchronization($id) {
		global $wpdb;
		
		$sql = $wpdb->prepare(
			"SELECT endTime 
			 FROM infoplus_synchronizations 
			 WHERE product_id = %s 
			 ORDER BY endTime DESC 
			 LIMIT 1",
			$id
		);

		return $wpdb->get_row($sql);
	}

	function set_synchronized( $id ) {
		global $wpdb;

		$id = $id;

		$now = current_time( 'mysql' );

		$table = 'infoplus_products';

		return $wpdb->update(
			$table,
			array( 'last_synchronized' => $now ), 
			array( 'woocommerce_ref_id' => $id ),
			array( '%s' ),                        
			array( '%d' )                          
		);
	}
	
	function create_new_synchronization_row($start_time, $custom_data, $type, $product_id){
		$params = compact('start_time', 'custom_data', 'type', '$product_id');
		$missing = [];

		foreach ($params as $name => $value) {
			if (!isset($value) || $value === '') {
				$missing[] = $name;
			}
		}

		if (!empty($missing)) {
			return 'Could not create new synchronization: missing parameter(s): ' . implode(', ', $missing);
		}
		
		$endTime = $date = date('Y-m-d H:i:s');
		
		global $wpdb;
		
		$result = $wpdb->insert(
			'infoplus_synchronizations',
			array(
				'id' => wp_generate_uuid4(),
				'startTime' => $start_time,
				'endTime' => $endTime,
				'type' => $type,
				'customData' => json_encode($custom_data),
				'product_id' => $product_id
			)
		);
		
		return $result;
	}

	//Custom table infoplus_products functions
	
	function get_woocommerce_products_uuid($products){
		if(empty($products) || !isset($products)){
			return array();
		}
		
		$ids = [];
		
		foreach($products as $product){
			$ids[] = $product -> get_id();
			$children = $product -> get_children();
			
			foreach($children as $child){
				$ids[] = $child;
			}
		}
		
		if(empty($ids)){
			return array();
		}
		
		return $join_ids = join(',', $ids);
		
		global $wpdb;
		
		return $wpdb -> get_results('SELECT * FROM infoplus_p('. $join_ids. ')');
	}
	
	
    function register_api_routes() {
        register_rest_route(
            'infoplus/'. INFOPLUS_API_VERSION, 'products',
            array(
                'methods'  => 'GET',
                'callback' => [$this, 'getProducts'],
            )
        );
		
		register_rest_route(
            'infoplus/'. INFOPLUS_API_VERSION, 'products/prices',
            array(
                'methods'  => 'GET',
                'callback' => [$this, 'get_bulk_price_rules_for_product'],
				'permission_callback' => '__return_true',
				'args' => array(
                'sku' => array(
                    'required' => true,
                    'type' => 'string',
                )
            ),
            )
        );
		
		register_rest_route(
            'infoplus/'. INFOPLUS_API_VERSION, 'products/variants',
            array(
                'methods'  => 'GET',
                'callback' => [$this, 'get_variants_for_product'],
				'permission_callback' => '__return_true',
				'args' => array(
                'productId' => array(
                    'required' => true,
                    'type' => 'integer',
                )
            ),
            )
        );
		
		//Synchronization
		register_rest_route(
            'infoplus/'. INFOPLUS_API_VERSION, 'products/synchronization',
            array(
                'methods'  => 'GET',
                'callback' => [$this, 'start_synchronization'],
				'permission_callback' => '__return_true',
				'productId' => array(
                    'required' => false,
                    'type' => 'integer',
                )
            )
        );
    }
	
	
	//Get/Set
	function getConnector(){
		 return $this -> connector;
	}
	
	function getRecipients(){
		return $this -> recipients;
	}
}
