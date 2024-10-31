<?php

namespace Paybyway;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PaybywayCharge extends Paybyway
{
	private $fields;
	private $customer;
	private $products;
	private $payment_method;

	public function __construct(array $fields)
	{
		$this->fields['amount'] = $fields['amount'];
		$this->fields['order_number'] = $fields['order_number'];
		$this->fields['currency'] = $fields['currency'];
		$this->fields['api_key'] = $fields['api_key'];
		
		if(isset($fields['card_token']))
			$this->fields['card_token'] = $fields['card_token'];    
		if(isset($fields['email']))
			$this->fields['email'] = $fields['email'];

		$this->customer = null;
		$this->products = array();
	}

	public function setCustomer($customer)
	{
		$this->customer = $customer;
	}

	public function setProduct($product)
	{
		$this->products[] = $product;
	}
	public function setPaymentMethod($payment_method)
	{
		$this->payment_method = $payment_method;
	}

	public function toArray()
	{
		$array = (array)$this->fields;

		if($this->customer)
			$array['customer'] = $this->customer->getCustomerInfo();
		if($this->payment_method)
			$array['payment_method'] = $this->payment_method->getPaymentMethod();
		
		foreach($this->products as $product)
		{
			$array['products'][] = $product->getProductDetails();
		}
		return $array;
	}
}
