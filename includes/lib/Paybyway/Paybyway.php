<?php

namespace Paybyway;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Paybyway
{
	protected $api_key;
	protected $private_key;
	protected $charge;
	protected $connector;
	protected $version;

	const API_URL = 'https://www.paybyway.com/pbwapi';

	public function __construct($api_key, $private_key, $version = 'w3.1', PaybywayConnector $connector = null)
	{
		$this->api_key = $api_key;
		$this->private_key = $private_key;
		$this->connector = $connector ? $connector : new PaybywayCurl();
		$this->version = $version;
		$this->charge = null;
	}

	public function addCharge(array $fields)
	{
		$fields['api_key'] = $this->api_key;
		$this->charge = new PaybywayCharge($fields);
	}	

	public function addCustomer(array $fields)
	{
		$this->charge->setCustomer(new PaybywayCustomer($fields));
	}

	public function addProduct(array $fields)
	{
		$this->charge->setProduct(new PaybywayProduct($fields));
	}

	public function addPaymentMethod(array $fields)
	{
		$this->charge->setPaymentMethod(new PaybywayPaymentMethod($fields));
	}

	public function createCharge()
	{
		$payment = $this->charge->toArray();
		
		$payment['version'] = $this->version;
		$payment['authcode'] = $this->calcAuthcode($this->api_key.'|'.$payment['order_number']);

		$result = $this->connector->request("auth_payment", $payment);

		if($json = json_decode($result))
		{
			if(isset($json->result))
				return $json;
		}

		throw new PaybywayException("Paybyway :: createCharge - response not valid JSON", 2);
	}

	public function chargeWithCardToken()
	{
		$payment = $this->charge->toArray();

		$payment['version'] = $this->version;
		$payment['authcode'] = $this->calcAuthcode($this->api_key.'|'.$payment['order_number'].'|'.$payment['card_token']);

		$result = $this->connector->request("charge_card_token", $payment);

		if($json = json_decode($result))
		{
			if(isset($json->result))
				return $json;
		}

		throw new PaybywayException("Paybyway :: chargeWithCardToken - response not valid JSON", 2);
	}

	protected function checkStatus(array $params)
	{
		$post_arr = array(
			'version' => $this->version,
			'authcode' => $this->calcAuthcode($this->api_key.'|'.reset($params)),
			'api_key' => $this->api_key
		);

		$post_arr = array_merge($post_arr, $params);

		$result = $this->connector->request("check_payment_status", $post_arr);

		if($json = json_decode($result))
		{
			if(isset($json->result))
				return $json;
		}

		throw new PaybywayException("Paybyway :: checkStatus - response not valid JSON", 2);
		
	}

	public function checkStatusWithToken($token)
	{
		return $this->checkStatus(array(
			'token' => $token
		));
	}

	public function checkStatusWithOrderNumber($order_number)
	{
		return $this->checkStatus(array(
			'order_number' => $order_number
		));
	}

	public function settlePayment($order_number)
	{
		$post_arr = array(
			'version' => $this->version,
			'authcode' => $this->calcAuthcode($this->api_key.'|'.$order_number),
			'order_number' => $order_number,
			'api_key' => $this->api_key
			);

		$result = $this->connector->request("capture", $post_arr);

		if($json = json_decode($result))
		{
			if(isset($json->result))
				return $json;
		}

		throw new PaybywayException("Paybyway :: settlePayment - response not valid JSON", 2);	
	}

	public function cancelPayment($order_number)
	{
		$post_arr = array(
			'version' => $this->version,
			'authcode' => $this->calcAuthcode($this->api_key.'|'.$order_number),
			'order_number' => $order_number,
			'api_key' => $this->api_key
			);

		$result = $this->connector->request("cancel", $post_arr);

		if($json = json_decode($result))
		{
			if(isset($json->result))
				return $json;
		}

		throw new PaybywayException("Paybyway :: cancelPayment - response not valid JSON", 2);	
	}

	public function getCardToken($card_token)
	{
		$post_arr = array(
			'version' => $this->version,
			'authcode' => $this->calcAuthcode($this->api_key.'|'.$card_token),
			'card_token' => $card_token,
			'api_key' => $this->api_key
			);

		$result = $this->connector->request("get_card_token", $post_arr);

		if($json = json_decode($result))
		{
			if(isset($json->result))
				return $json;
		}

		throw new PaybywayException("Paybyway :: getCardToken - response not valid JSON", 2);
	}

	public function deleteCardToken($card_token)
	{
		$post_arr = array(
			'version' => $this->version,
			'authcode' => $this->calcAuthcode($this->api_key.'|'.$card_token),
			'card_token' => $card_token,
			'api_key' => $this->api_key
			);

		$result = $this->connector->request("delete_card_token", $post_arr);
		
		if($json = json_decode($result))
		{
			if(isset($json->result))
				return $json;
		}

		throw new PaybywayException("Paybyway :: deleteCardToken - response not valid JSON", 2);	
	}

	// if payment response was valid, returns the return code, otherwise throw an error.
	public function checkReturn($return_data)
	{
		if(array_key_exists('RETURN_CODE', $return_data) && array_key_exists('ORDER_NUMBER', $return_data) && array_key_exists('AUTHCODE', $return_data))
		{
			$mac_input = $return_data['RETURN_CODE'].'|'.$return_data['ORDER_NUMBER'];
			if(array_key_exists('SETTLED', $return_data))
				$mac_input .= '|'.$return_data['SETTLED'];
			if(array_key_exists('CONTACT_ID', $return_data))
				$mac_input .= '|'.$return_data['CONTACT_ID'];
			if(array_key_exists('INCIDENT_ID', $return_data))
				$mac_input .= '|'.$return_data['INCIDENT_ID'];

			if($return_data['AUTHCODE'] == $this->calcAuthcode($mac_input))
			{
				return (object)$return_data;
			}
			else
			{
				throw new PaybywayException("Paybyway :: checkReturn failed - MAC authentication failed", 4);
			}
		}

		throw new PaybywayException("Paybyway :: checkReturn failed - not enough data given", 5);
	}

	protected function calcAuthcode($input)
	{
		return strtoupper(hash_hmac('sha256', $input, $this->private_key));
	}
}