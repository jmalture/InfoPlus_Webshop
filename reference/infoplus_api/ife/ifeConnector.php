<?php

class ifeConnector{
	
	public $token = ''; // set via INFOPLUS_IFE_TOKEN in wp-config.php, see getToken()
	
	function testing(){
		return "Hello connector";
	}
	
	function getProjects($customerId){
		
		if(!isset($customerId)){
			return "No customer id";
		}
	
		try{
			$url = 'https://editor.infoplus.de/smp/infoplus/getProjects';
		
			$headers = [
				'Content-Type'  => 'application/json',
				'Accept'        => 'application/json',
				'Authorization' => $this -> getToken()
			];
	
			$body = [
				'id' => $customerId
			];
			
			$response = wp_remote_post( $url, [
				'headers' => $headers,
				'body'    => wp_json_encode( $body ),
			] );

			if ( is_wp_error( $response ) ) {
				error_log( 'API Request failed: ' . $response->get_error_message() );
				return 'API Request failed: ' . $response->get_error_message();
			}

			$body = wp_remote_retrieve_body( $response );
			$data = json_decode( $body, true );
			
			return $response;
		}catch(Exception $e){
			return "Could not synchronize product: ". $product -> id ." ". $e;
		}
		
	}

	function updateProduct($product, $variants, $product_ife_id){
		
		if(!isset($product) || !isset($variants)){
			return null;
		}
		
		try{
			$url = 'https://editor.infoplus.de/smp/infoplus/updateProduct';
		
			$headers = [
				'Content-Type'  => 'application/json',
				'Accept'        => 'application/json',
				'Authorization' => $this -> getToken()
			];
	
			$body = [
				'productId' => $product_ife_id, //'32a5c4be-fbc3-44f4-b3c7-79e772c1fb30', 
				'name' => $product -> get_name(),
				'name1' => $product -> get_name(),
				'description' => $product -> get_description(),
				'sku' => $product -> get_sku(),
				'price' => $product -> get_price(),
				'variants' => $variants,
				'category' => 'magnets'
			];
			
			$response = wp_remote_post( $url, [
				'headers' => $headers,
				'body'    => wp_json_encode( $body ),
				'data_format' => 'body',
				'timeout' => 60
			] );

			if ( is_wp_error( $response ) ) {
				error_log( 'API Request failed: ' . $response->get_error_message() );
				return false;
			}

			$body = wp_remote_retrieve_body( $response );
			$data = json_decode( $body, true );
			
			return $response;
		}catch(Exception $e){
			return "Could not synchronize product: ". $product -> id ." ". $e;
		}
		
	}
	
	function create_uuid_for_product($id, $type, $parent, $modified, $variant_ife_uuid){
		if(!isset($id) || !isset($type)){
			return;
		}
		
		$uuid = wp_generate_uuid4();
		
		global $wpdb;
		
		$wpdb->insert(
			'infoplus_products',
			array(
				'id' => $uuid,
				'woocommerce_ref_id'=> $id,
				'type' => $type,
				'parent' => $parent,
				'modified' => $modified,
				'ife_id' => $variant_ife_uuid
			)
		);
		
		return array('id' => $uuid,
				'woocommerce_ref_id'=> $id,
				'type' => $type,
				'parent' => $parent,
				'modified' => $modified,
				'ife_id' => $variant_ife_uuid
				);
	}
	
	function update_infoplus_product_table($id, $modified, $variant_ife_uuid){
		if (!isset($id)) {
			return;
		}

		global $wpdb;

		return $wpdb->update(
			'infoplus_products',
			array(
				'modified' => $modified,
				'ife_id'  => $variant_ife_uuid
			),
			array( 'woocommerce_ref_id' => $id )
		);
	}
	
	function getToken(){
        if (defined('INFOPLUS_IFE_TOKEN')) { return 'Bearer ' . INFOPLUS_IFE_TOKEN; }
		return $this -> token;
	}
} 


