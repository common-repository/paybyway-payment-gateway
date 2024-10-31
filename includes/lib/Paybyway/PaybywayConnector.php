<?php

namespace Paybyway;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface PaybywayConnector
{
	public function request($url, $post_arr);
}