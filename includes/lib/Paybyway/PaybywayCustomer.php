<?php

namespace Paybyway;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PaybywayCustomer
{
	private $fields;

	public function __construct(array $fields)
	{
		if(isset($fields['firstname']))
			$this->fields['firstname'] = $fields['firstname'];

		if(isset($fields['lastname']))
			$this->fields['lastname'] = $fields['lastname'];

		if(isset($fields['email']))
			$this->fields['email'] = $fields['email'];

		if(isset($fields['address_street']))
			$this->fields['address_street'] = $fields['address_street'];

		if(isset($fields['address_city']))
			$this->fields['address_city'] = $fields['address_city'];

		if(isset($fields['address_zip']))
			$this->fields['address_zip'] = $fields['address_zip'];
	}

	public function getCustomerInfo()
	{
		return $this->fields;
	}
}
