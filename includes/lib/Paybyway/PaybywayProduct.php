<?php

namespace Paybyway;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PaybywayProduct
{
	private $fields;
	public function __construct(array $fields)
	{
		$this->fields['id'] = $fields['id'];
		$this->fields['title'] = $fields['title'];
		$this->fields['count'] = $fields['count'];
		$this->fields['pretax_price'] = $fields['pretax_price'];
		$this->fields['tax'] = $fields['tax'];
		$this->fields['price'] = $fields['price'];
		$this->fields['type'] = $fields['type'];

		if(isset($fields['merchant_id']))
			$this->fields['merchant_id'] = $fields['merchant_id'];
		
		if(isset($fields['cp']))
			$this->fields['cp'] = $fields['cp'];
	}

	public function getProductDetails()
	{
		return $this->fields;
	}
}