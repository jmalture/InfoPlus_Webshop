<?php

require_once plugin_dir_path( dirname( __FILE__ ) ) . 'entities/Project.php';

class CustomerDashboard{

	private $project;
		
	function __construct(){
		$this -> register_api_routes();
	
		$this->project  = new Project();
	}

	/**
	 * Customer whose data may be read: always the logged-in user.
	 * Only shop managers may pass another customer ID in the query string.
	 */
	private function resolve_customer_id($requested){
		if(isset($requested) && current_user_can('manage_woocommerce')){
			return intval($requested);
		}
		return get_current_user_id();
	}

	function permission_logged_in(){
		return is_user_logged_in();
	}
	
	function get_customer_dashboard_data(){
		$id = $this->resolve_customer_id( isset( $_GET['id'] ) ? $_GET['id'] : null );
		
		if(!isset($id)){
			return "Parameter id is required";
		}
		
		$number_of_projects = 0;
		$number_of_orders = 0;
		
		try{
			//Getting projects from IFE
			$customer_projects = $this -> get_project() -> get_projects($id);		
			
			$body = json_decode($customer_projects['body']);
			
			foreach($body as $attribute){
				if(str_contains($attribute, 'project')){
					$number_of_projects += 1;
				}
			}
			
			$orders = $this -> get_customer_orders($id);
			
			$number_of_orders = count($orders);
			
		}catch(Exception $ex){
			return $ex;
		}
		
		return array(
			"customer_projects" => $number_of_projects,
			"customer_orders" => $number_of_orders,
			//@TODO get shared projects
			"customer_shared_projects" => 2
		);
	}
	
	function get_dashboard_customer_orders(){
		$customer_id = $this->resolve_customer_id( isset( $_GET['customerId'] ) ? $_GET['customerId'] : null );
		
		$orders = $this -> get_customer_orders($customer_id);
	
		return $this -> get_dashboard_customer_orders_internal($orders);
	}
	
	function get_dashboard_customer_orders_detailed(){
		$order_id = isset($_GET['orderId']) ? intval($_GET['orderId']) : null;
	
		$result = array();

		$order = wc_get_order( $order_id );
		
		if(empty($order)){
			return $result;
		}

		// Only the order's own customer or a shop manager may read it.
		if((int) $order->get_customer_id() !== get_current_user_id() && !current_user_can('manage_woocommerce')){
			return $result;
		}
		
		$customer_id = $order -> get_customer_id();
		
		$customer = new WC_Customer( $customer_id );
		
		//Get customer adress
		$billing_address = array(
			'first_name' => $customer->get_billing_first_name(),
			'last_name'  => $customer->get_billing_last_name(),
			'company'    => $customer->get_billing_company(),
			'address_1'  => $customer->get_billing_address_1(),
			'address_2'  => $customer->get_billing_address_2(),
			'city'       => $customer->get_billing_city(),
			'postcode'   => $customer->get_billing_postcode(),
			'country'    => $customer->get_billing_country(),
			'state'      => $customer->get_billing_state(),
			'phone'      => $customer->get_billing_phone(),
			'email'      => $customer->get_billing_email()
		);

		$shipping_address = array(
			'first_name' => $customer->get_shipping_first_name(),
			'last_name'  => $customer->get_shipping_last_name(),
			'company'    => $customer->get_shipping_company(),
			'address_1'  => $customer->get_shipping_address_1(),
			'address_2'  => $customer->get_shipping_address_2(),
			'city'       => $customer->get_shipping_city(),
			'postcode'   => $customer->get_shipping_postcode(),
			'country'    => $customer->get_shipping_country(),
			'state'      => $customer->get_shipping_state(),
		);
		
		$result['customer'] = array(
			"id"    => $customer->get_id(),
    		"email" => $customer->get_email(),
   			"name"  => $customer->get_first_name() . ' ' . $customer->get_last_name(),
			"telephone" => $customer -> get_billing_phone(),
			"billingAdress" => $billing_address,
			"shippingAdress" => $shipping_address
		);

		$result['orders'] = $this->get_dashboard_customer_orders_internal(array($order));
		

		return $result;
	}
	
	function get_dashboard_customer_orders_internal($orders){
		
		if(!isset($orders)){
			return "Parameter id is required";
		}
		
		$result = array();
		
		foreach($orders as $order){
			
			//Get items of order
			$items = $order -> get_items();
			
			$items_result = array();
			
			foreach($items as $item){	
				
				$product_id = $item -> get_data()['product_id'];
				
				$product = wc_get_product( $product_id );
				
				$items_result[] = array(
					"productId" => $product_id,	
					"sku" => $product -> get_sku(),
					"quantity" => $item -> get_data()['quantity'],
					"total" => $item -> get_data()['total'],
					"subtotal" => $item -> get_data()['subtotal']
 				);
			}
			
			$result[] = array(
				"id" => $order -> get_id(),
				"status" => $order -> get_status(),
				"modified" => ($order -> get_date_modified()),
				"items" => $items_result,
				"total" => $order -> get_total(),
				"payment" => $order -> get_payment_method(),
				"shippingTotal" => $order -> get_shipping_total(),
				"subTotal" => $order -> get_subtotal()
			);
		}
		
		return $result;
	}
	
	function get_customer_events(){
		$customerId = $this->resolve_customer_id( isset($_GET['customerId']) ? $_GET['customerId'] : null );
		
		if(!isset($customerId)){
			return "Parameter customer id is required";
		}
		
		return $this -> get_project() -> get_newest_customer_events($customerId);
	}
	
	function get_customer_orders($id){
		if(!isset($id)){
			return "Parameter id is required";
		}	
		
		$orders = wc_get_orders( array(
			'customer_id' => $id,
			'limit'       => -1, // Get all orders
			'orderby'     => 'date',
			'order'       => 'DESC',
		) );
		
		return $orders;
	}
	
	function register_api_routes() {
        register_rest_route(
            'infoplus/'. INFOPLUS_API_VERSION, 'customerdashboard',
            array(
                'methods'  => 'GET',
                'permission_callback' => [$this, 'permission_logged_in'],
                'callback' => [$this, 'get_customer_dashboard_data'],
				'id' => array(
                    'required' => true,
                    'type' => 'integer',
                )
            )
        );
		
		 register_rest_route(
            'infoplus/'. INFOPLUS_API_VERSION, 'customerdashboard/orders',
            array(
                'methods'  => 'GET',
                'permission_callback' => [$this, 'permission_logged_in'],
                'callback' => [$this, 'get_dashboard_customer_orders'],
				'customerId' => array(
                    'required' => true,
                    'type' => 'integer',
                )
            )
        );
		
		 register_rest_route(
            'infoplus/'. INFOPLUS_API_VERSION, 'customerdashboard/orders/detailed',
            array(
                'methods'  => 'GET',
                'permission_callback' => [$this, 'permission_logged_in'],
                'callback' => [$this, 'get_dashboard_customer_orders_detailed'],
				'orderId' => array(
                    'required' => true,
                    'type' => 'integer',
                )
            )
        );
		
		register_rest_route(
            'infoplus/'. INFOPLUS_API_VERSION, 'customerdashboard/events',
            array(
                'methods'  => 'GET',
                'permission_callback' => [$this, 'permission_logged_in'],
                'callback' => [$this, 'get_customer_events'],
				'customerId' => array(
                    'required' => true,
                    'type' => 'integer',
                )
            )
        );
    }
	
	function get_project(){
		 return $this -> project;
	}
}