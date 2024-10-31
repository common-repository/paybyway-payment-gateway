<?php

namespace Paybyway;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PaybywayPaymentMethod
{
	private $fields;

	public function __construct(array $fields)
	{
		if(isset($fields['type']))
			$this->fields['type'] = $fields['type'];
		else
			return false;

		if($this->fields['type'] == "e-payment")
		{
			if(isset($fields['return_url']))
				$this->fields['return_url'] = $fields['return_url'];
			if(isset($fields['notify_url']))
				$this->fields['notify_url'] = $fields['notify_url'];
			if(isset($fields['lang']))
				$this->fields['lang'] = $fields['lang'];
			if(isset($fields['token_valid_until']))
				$this->fields['token_valid_until'] = $fields['token_valid_until'];
			if(isset($fields['selected']) && is_array($fields['selected']))
				$this->fields['selected'] = $fields['selected'];
		}
		
		if(isset($fields['register_card_token']))
			$this->fields['register_card_token'] = $fields['register_card_token'];
	}

	public function getPaymentMethod()
	{
		return $this->fields;
	}
}
