<?php

namespace Paybyway;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PaybywayWoocommerce extends Paybyway
{
	public function getDynMethods()
	{
		$post_arr = array(
			'authcode' => $this->calcAuthcode($this->api_key),
			'api_key' => $this->api_key
			);

		$result = $this->connector->request("get_merchant_payment_methods", $post_arr);

		if($json = json_decode($result))
		{
			if(isset($json->result))
				return $json;
		}

		throw new PaybywayException("Paybyway :: getDynMethods - response not valid JSON", 2);
	}
}