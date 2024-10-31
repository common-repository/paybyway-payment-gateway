<?php

namespace Paybyway;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PaybywayCurl implements PaybywayConnector
{
	public function request($url, $post_arr)
	{
		if (function_exists('curl_init'))
			return $this->curlRequest($url, $post_arr);
	}

	protected function curlRequest($url, $post_arr)
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, Paybyway::API_URL . "/" . $url);
		curl_setopt($ch, CURLOPT_POST, 1); //method == post
		curl_setopt($ch, CURLOPT_HEADER, 0); //do not return headers
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Content-Length: ' . strlen(json_encode($post_arr))));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 60);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_arr));
		$curl_response = curl_exec ($ch);
		if (!$curl_response)
		{
			throw new PaybywayException("Error processing request: " . curl_error($ch) . " error code: " . curl_errno($ch), 3);
			curl_close ($ch);
		}

		curl_close ($ch);

		return $curl_response;
	}
}
