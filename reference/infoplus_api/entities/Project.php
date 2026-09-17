<?php

require_once plugin_dir_path( dirname( __FILE__ ) ) . 'ife/ifeConnector.php';

class Project{
	
	private $connector;
	
	private $events = array(
		"project_created" => "Project created: ",
		"project_updated" => "Project updated: ",
		"project_shared" => "Project shared: ",
		"order_created" => "Order Created: ",
		"order_updated" => "Order updated: "
	);
	
	function __construct() {
		$this -> connector = new IfeConnector();
    }
	
	function get_projects($id){
		$projects = $this -> get_connector() -> getProjects($id);
		
		return $projects;
	}
	
	function get_newest_customer_events($customer_id){
		
		if(!isset($customer_id)){
			return "customer id is empty.";	
		}
		
		$orders = wc_get_orders(array(
			'customer_id' => $id,
			'limit'       => 3,
			'orderby'     => 'date',
			'order'       => 'DESC',
		));
		
		//@TODO Get project events from IFE
		//$projects ...
		
		$result = array();
			
		//Testing for now
		foreach($orders as $order){
			$result[] = array(
				"order" => $order,
				"message" => ($this -> events)['order_created']
			);
		}
		
		//@TODO compare the dates of create/update for getting newest events
		
		return $result;
	}
	
	function get_connector(){
		return $this -> connector;
	}
	
	function get_events(){
		return $this -> events;
	}
	
	function testing(){
		return "Hello projects";
	}

}