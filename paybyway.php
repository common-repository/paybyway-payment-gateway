<?php
/*
Plugin Name: Paybyway Payment Gateway
Plugin URI: http://www.maksukaista.fi
Description: Paybyway (Maksukaista) Payment Gateway Integration for Woocommerce
Version: 3.1.1
Author: Paybyway Oy
Author URI: http://www.maksukaista.fi
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action('plugins_loaded', 'init_paybyway_gateway', 0);

function woocommerce_add_WC_Gateway_Paybyway($methods)
{
	$methods[] = 'WC_Gateway_Paybyway';
	return $methods;
}
add_filter('woocommerce_payment_gateways', 'woocommerce_add_WC_Gateway_Paybyway');

function init_paybyway_gateway()
{
	load_plugin_textdomain('paybyway', false, dirname(plugin_basename(__FILE__)));

	if(!class_exists('WC_Payment_Gateway'))
		return;

	class WC_Gateway_Paybyway extends WC_Payment_Gateway
	{
		function __construct()
		{
			$this->id = 'paybyway';
			$this->has_fields = false;
			$this->method_title = __( 'Maksukaista Woocommerce', 'paybyway' );
			$this->method_description = __( 'Maksukaista w3-API Payment Gateway integration for Woocommerce', 'paybyway' );

			$this->init_form_fields();
			$this->init_settings();

			$this->enabled = $this->settings['enabled'];
			$this->title = $this->get_option('title');

			$this->api_key = $this->get_option('api_key');
			$this->private_key = $this->get_option('private_key');

			$this->ordernumber_prefix = $this->get_option('ordernumber_prefix');
			$this->description = $this->get_option('paybyway_description');

			$this->banks = $this->get_option('banks');
			$this->ccards = $this->get_option('ccards');
			$this->cinvoices = $this->get_option('cinvoices');
			$this->arvato = $this->get_option('arvato');
			$this->laskuyritykselle = $this->get_option('laskuyritykselle');

			$this->send_items = $this->get_option('send_items');
			$this->send_receipt = $this->get_option('send_receipt');
			$this->embed = $this->get_option('embed');
			$this->dynamic = $this->get_option('dynamic');

			add_action('wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
			add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options' ) );
			add_action('woocommerce_api_wc_gateway_paybyway', array($this, 'check_paybyway_response' ) );
			add_action('woocommerce_admin_order_data_after_billing_address', array($this, 'paybyway_settle_payment'), 1, 1);

			if(!$this->is_valid_currency())
				$this->enabled = false;
		}

		public function admin_notices() 
		{
			if ( $this->enabled == 'no' )
				return;

			// Show message if curl is not installed
			if (!function_exists('curl_init'))
				echo '<div class="error"><p>' . sprintf( __( 'PHP cURL is not installed, install cURL to use Maksukaista payment gateway.', 'paybyway' ), admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ) . '</p></div>';
		}

		function is_valid_currency()
		{
			return in_array(get_option('woocommerce_currency'), array('EUR'));
		}

		function payment_scripts() {
			if (!is_checkout())
				return;

			// CSS Styles
			wp_enqueue_style( 'woocommerce_paybyway', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) . '/assets/css/paybyway.css', '', '', 'all');
			// JS SCRIPTS
			wp_enqueue_script( 'woocommerce_paybyway', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) . '/assets/js/paybyway.js', array( 'jquery' ), '', true );
		}

		function init_form_fields()
		{
			$this->form_fields = array(
				'general' => array(
					'title' => __( 'General options', 'paybyway' ),
					'type' => 'title',
					'description' => '',
				),
				'enabled' => array(
					'title' => __( 'Enable/Disable', 'paybyway' ),
					'type' => 'checkbox',
					'label' => __( 'Enable Maksukaista', 'paybyway' ),					
					'default' => 'yes'
				),
				'title' => array(
					'title' => __( 'Title', 'paybyway' ),
					'type' => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'paybyway' ),
					'default' => __( 'Maksukaista', 'paybyway' )
				),
				'paybyway_description' => array(
					'title' => __( 'Description', 'paybyway' ),
					'description' => __( 'Customer Message', 'paybyway' ),
					'type' => 'textarea',
					'default' => __( 'Maksukaista -palvelussa voit maksaa ostoksesi turvallisesti verkkopankin kautta, luottokortilla tai luottolaskulla.', 'paybyway')
				),
				'private_key' => array(
					'title' => __( 'Private key', 'paybyway' ),
					'type' => 'text',
					'description' => __( 'Private key of the the sub-merchant', 'paybyway' ),
					'default' => ''
				),
				'api_key' => array(
					'title' => __( 'API key', 'paybyway' ),
					'type' => 'text',
					'description' => __( 'API key of the the sub-merchant', 'paybyway' ),
					'default' => ''
				),
				'ordernumber_prefix' => array(
					'title' => __( 'Order number prefix', 'paybyway' ),
					'type' => 'text',
					'description' => __( 'Prefix to avoid order number duplication', 'paybyway' ),
					'default' => ''
				),
				'send_items' => array(
					'title' => __( 'Send products', 'paybyway' ),
					'type' => 'checkbox',
					'label' => __( "Send product breakdown to Maksukaista.", 'paybyway' ),
					'default' => 'yes'
				),
				'send_receipt' => array(
					'title' => __( 'Send payment confirmation', 'paybyway' ),
					'type' => 'checkbox',
					'label' => __( "Send Maksukaista's payment confirmation email to the customer's billing e-mail.", 'paybyway' ),
					'default' => 'yes',
				),
				'embed_options' => array(
					'title' => __( 'Embedded payment options', 'paybyway' ),
					'type' => 'title',
					'description' => ''
				),
				'embed' => array(
					'title' => __( 'Enable embedded payment', 'paybyway' ),
					'type' => 'checkbox',
					'label' => __( "Enable this if you want to use embed the payment methods to the checkout-page.", 'paybyway' ),
					'default' => 'yes',
					'checkboxgroup'	=> 'start',
					'show_if_checked' => 'option'
				),
				'dynamic' => array(
					'type' => 'checkbox',
					'label' => __( "Retrieve enabled payment methods automatically for embedded payment (Recommended)", 'paybyway' ),
					'default' => 'yes',
					'show_if_checked' => 'yes',
					'hide_if_checked' => 'no',
					'checkboxgroup'	=> 'end'
				),
				'limit_options' => array(
					'title' => __( 'Manage payment methods', 'paybyway' ),
					'type' => 'title',
					'description' => __('You can enable all of these if you are using the option to automatically retrieve your enabled payment methods, and the payment methods will automatically be visible on the checkout page if they are available.', 'paybyway' ),
				),
				'banks' => array(
					'title' => __( 'Banks', 'paybyway' ),
					'type' => 'checkbox',
					'label' => __( 'Enable bank payments in the Maksukaista payment page.', 'paybyway' ),
					'default' => 'yes'
				),
				'ccards' => array(
					'title' => __( 'Card payments', 'paybyway' ),
					'type' => 'checkbox',
					'label' => __( 'Enable credit cards in the Maksukaista payment page.', 'paybyway' ),
					'default' => 'yes'
				),
				'cinvoices' => array(
					'title' => __( 'Credit invoices', 'paybyway' ),
					'type' => 'checkbox',
					'label' => __( 'Enable credit invoices in the Maksukaista payment page.', 'paybyway' ),
					'default' => 'yes'
				),
				'arvato' => array(
					'title' => __( 'Maksukaista Lasku', 'paybyway' ),
					'type' => 'checkbox',
					'label' => __( 'Enable Maksukaista Lasku in the Maksukaista payment page. (Only for Maksukaista Konversio customers)', 'paybyway' ),
					'default' => 'no'
				),
				'laskuyritykselle' => array(
					'title' => __( 'Laskuyritykselle', 'paybyway' ),
					'type' => 'checkbox',
					'label' => __( 'Enable Laskuyritykselle in the Maksukaista payment page.', 'paybyway' ),
					'default' => 'no'
				)
			);
		}

		function payment_fields()
		{
			global $woocommerce;

			$total = 0;
			$cart_total = (int)(round($woocommerce->cart->total*100, 0));

			if(get_query_var('order-pay') != '')
			{
				$order = new WC_Order(get_query_var('order-pay'));
				$total = (int)(round($order->order_total*100, 0));
			}

			if ($this->description)
				echo wpautop(wptexturize(__($this->description, 'paybyway')));

			$plugin_url = untrailingslashit(plugins_url(basename(plugin_dir_path(__FILE__)), basename(__FILE__))) . '/';

			if($this->embed == 'yes')
			{
				if($this->dynamic == 'yes')
				{
					include(plugin_dir_path( __FILE__ ).'includes/lib/paybyway_loader.php');
					$payment_methods = new Paybyway\PaybywayWoocommerce($this->api_key, $this->private_key);
					try
					{
						$response = $payment_methods->getDynMethods();
					}
					catch (Paybyway\PaybywayException $e) 
					{
						$logger = new WC_Logger();
						$logger->add( 'paybyway', 'Maksukaista REST::getDynMethods failed, exception: ' . $e->getCode().' '.$e->getMessage());
					}
					echo wpautop(wptexturize(__( 'Choose your payment method and click Pay for Order', 'paybyway' )));
					echo '<div id="paybyway-bank-payments">';

					if(count($response->payment_methods->creditcards) > 0 && $this->ccards == 'yes')
					{
						echo '<div>'.wpautop(wptexturize(__( 'Payment card', 'paybyway' ))).'</div>';

						foreach ($response->payment_methods->creditcards as $key => $value)
						{
							$img = $this->paybyway_save_img($key, $value->img, $value->timestamp);
							echo '<div id="paybyway-button-creditcards" class="bank-button"><img alt="' . $key . '" src="' . $plugin_url.$img . '"/></div>';
						}
					}

					if(count($response->payment_methods->banks) > 0 && $this->banks == 'yes')
					{
						echo '<div>'.wpautop(wptexturize(__( 'Internet banking', 'paybyway' ))).'</div>';

						foreach ($response->payment_methods->banks as $key => $value)
						{
							$img = $this->paybyway_save_img($key, $value->img, $value->timestamp);
							echo '<div id="paybyway-button-' . strtolower($key) . '" class="bank-button"><img alt="' . $key . '" src="' . $plugin_url.$img . '"/></div>';
						}
					}

					if(count($response->payment_methods->creditinvoices) > 0 && ($this->arvato == 'yes' || $this->cinvoices == 'yes'))
					{
						$cinvoices_html = array();
						foreach ($response->payment_methods->creditinvoices as $key => $value)
						{
							$img = $this->paybyway_save_img($key, $value->img, $value->timestamp);
							if($key == 'arvato' || $key == 'laskuyritykselle')
							{
								if(($key == 'arvato' && $this->arvato == 'yes') || ($key == 'laskuyritykselle' && $this->laskuyritykselle == 'yes'))
									$cinvoices_html[] = '<div id="paybyway-button-' . strtolower($key) . '" class="bank-button"><img alt="' . $key . '" src="' . $plugin_url.$img . '"/></div>';
							}
							else if($this->cinvoices == 'yes' && ((!isset($order) && $cart_total >= $value->min_amount && $cart_total <= $value->max_amount) || ($total >= $value->min_amount && $total <= $value->max_amount)))
								$cinvoices_html[] = '<div id="paybyway-button-' . strtolower($key) . '" class="bank-button"><img alt="' . $key . '" src="' . $plugin_url.$img . '"/></div>';
						}
						if(count($cinvoices_html) > 0)
						{
							echo '<div>'.wpautop(wptexturize(__( 'Invoice or part payment', 'paybyway' ))).'</div>';
							foreach ($cinvoices_html as $cinvoice)
							{
								echo $cinvoice;
							}
						}
					}

					echo '</div>';

					echo '<div id="paybyway_bank_checkout_fields" style="display: none;">';
					echo '<input id="paybyway_selected_bank" class="input-hidden" type="hidden" name="paybyway_selected_bank" />';
					echo '</div>';
				}
				else
				{
					
					echo wpautop(wptexturize(__( 'Choose your payment method and click Pay for Order', 'paybyway' )));
					echo '<div id="paybyway-bank-payments">';
					if($this->ccards == 'yes')
					{
						echo '<div>'.wpautop(wptexturize(__( 'Payment card', 'paybyway' ))).'</div>';
						echo '<div id="paybyway-button-creditcards" class="bank-button"><img alt="Visa" src="'. $plugin_url .'assets/images/visa.png"/></div>'; //visa
						echo '<div id="paybyway-button-creditcards" class="bank-button"><img alt="MasterCard" src="'. $plugin_url .'assets/images/mastercard.png"/></div>'; //master
					}
					if($this->banks == 'yes')
					{
						echo '<div>'.wpautop(wptexturize(__( 'Internet banking', 'paybyway' ))).'</div>';
						echo '<div id="paybyway-button-nordea" class="bank-button"><img alt="Nordea" src="'. $plugin_url .'assets/images/nordea.png"/></div>';
						echo '<div id="paybyway-button-op" class="bank-button"><img alt="Osuuspankki" src="'. $plugin_url .'assets/images/osuuspankki.png"/></div>';
						echo '<div id="paybyway-button-danske" class="bank-button"><img alt="Danskebank" src="'. $plugin_url .'assets/images/danskebank.png"/></div>';
						echo '<div id="paybyway-button-saastopankki" class="bank-button"><img alt="Säästöpankki" src="'. $plugin_url .'assets/images/saastopankki.png"/></div>';
						echo '<div id="paybyway-button-paikallisosuuspankki" class="bank-button"><img alt="POP-Pankki" src="'. $plugin_url .'assets/images/paikallisosuuspankki.png"/></div>';
						echo '<div id="paybyway-button-aktia" class="bank-button"><img alt="Aktia" src="'. $plugin_url .'assets/images/aktia.png"/></div>';
						echo '<div id="paybyway-button-handelsbanken" class="bank-button"><img alt="Handelsbanken" src="'. $plugin_url .'assets/images/handelsbanken.png"/></div>';
						echo '<div id="paybyway-button-spankki" class="bank-button"><img alt="S-Pankki" src="'. $plugin_url .'assets/images/spankki.png"/></div>';
						echo '<div id="paybyway-button-alandsbanken" class="bank-button"><img alt="Ålandsbanken" src="'. $plugin_url .'assets/images/alandsbanken.png"/></div>';
						echo '<div id="paybyway-button-omasaastopankki" class="bank-button"><img alt="Oma Säästöpankki" src="'. $plugin_url .'assets/images/omasaastopankki.png"/></div>';
					}

					if($this->arvato == 'yes' || $this->cinvoices == 'yes' || $this->laskuyritykselle == 'yes')
					{
						$cinvoices_html = array();

						$cinvoices = array(
							'euroloan' => array(1000, 10000000),
							'joustoraha' => array(2000, 200000),
							'everyday' => array(500, 200000),
							'arvato' => array(0, 10000000),
							'laskuyritykselle' => array(0, 10000000)
						);

						foreach ($cinvoices as $key => $value)
						{
							if($key == 'arvato' || $key == 'laskuyritykselle')
							{
								if(($key == 'arvato' && $this->arvato == 'yes') || ($key == 'laskuyritykselle' && $this->laskuyritykselle == 'yes'))
									$cinvoices_html[] = '<div id="paybyway-button-' . strtolower($key) . '" class="bank-button"><img alt="' . $key . '" src="' . $plugin_url . 'assets/images/' . $key . '.png"/></div>';
							}
							else if($this->cinvoices == 'yes' && ((!isset($order) && $cart_total >= $value[0] && $cart_total <= $value[1]) || ($total >= $value[0] && $total <= $value[1])))
								$cinvoices_html[] = '<div id="paybyway-button-' . strtolower($key) . '" class="bank-button"><img alt="' . $key . '" src="' . $plugin_url . 'assets/images/' . $key . '.png"/></div>';
						}
						if(count($cinvoices_html) > 0)
						{
							echo '<div>'.wpautop(wptexturize(__( 'Invoice or part payment', 'paybyway' ))).'</div>';
							foreach ($cinvoices_html as $cinvoice)
							{
								echo $cinvoice;
							}
						}
					}

					echo '</div>';

					echo '<div id="paybyway_bank_checkout_fields" style="display: none;">';
					echo '<input id="paybyway_selected_bank" class="input-hidden" type="hidden" name="paybyway_selected_bank" />';
					echo '</div>';
				}
			}
		}

		function process_payment($order_id)
		{
			global $woocommerce;

			if ($_POST['payment_method'] != 'paybyway')
				return false;

			$order = new WC_Order($order_id);
			$paybyway_selected_bank = isset( $_POST['paybyway_selected_bank'] ) ? wc_clean( $_POST['paybyway_selected_bank'] ) : '';
			update_post_meta( $order->id, '_paybyway_selected_bank_', $paybyway_selected_bank );

			$order_number = (strlen($this->ordernumber_prefix)  > 0) ?  $this->get_option('ordernumber_prefix') . '_'  .$order_id : $order_id;
			$order_number .=  '-' . str_pad(time().rand(0,9999), 5, "1", STR_PAD_RIGHT);

			include_once(plugin_dir_path( __FILE__ ).'includes/lib/paybyway_loader.php');

			if (version_compare(WOOCOMMERCE_VERSION, '2.1.0', '>='))
				$redirect_url = $this->get_return_url($order);
			else
				$redirect_url = (get_option('woocommerce_thanks_page_id') != '' ) ? get_permalink(get_option('woocommerce_thanks_page_id')): get_site_url().'/' ;

			$return_url = add_query_arg( array('wc-api' => get_class( $this ) ,'order_id' => $order_id), $redirect_url );

			$amount =  (int)(round($order->order_total*100, 0));			
			update_post_meta($order_id, 'paybyway_is_settled', 1);
			update_post_meta($order_id, 'paybyway_return_code', 99);

			$finn_langs = array('fi-FI', 'fi', 'fi_FI');
			$sv_langs = array('sv-SE', 'sv');

			if(in_array(get_bloginfo('language'), $finn_langs))
				$lang = 'fi';
			else if (in_array(get_bloginfo('language'), $sv_langs))
				$lang = 'sv';
			else
				$lang = 'en';

			$payment = new Paybyway\Paybyway($this->api_key, $this->private_key);

			if($this->send_receipt == 'yes')
				$receipt_mail = $order->billing_email;
			else
				$receipt_mail = '';

			$payment->addCharge(
				array(
					'order_number' => $order_number,
					'amount' => $amount,
					'currency' => get_woocommerce_currency(),
					'email' =>  $receipt_mail
				)
			);

			$payment->addCustomer(
				array(
					'firstname' => htmlspecialchars($order->billing_first_name), 
					'lastname' => htmlspecialchars($order->billing_last_name), 
					'email' => htmlspecialchars($order->billing_email), 
					'address_street' => htmlspecialchars($order->billing_address_1.' '.$order->billing_address_2),
					'address_city' => htmlspecialchars($order->billing_city),
					'address_zip' => htmlspecialchars($order->billing_postcode)
				)
			);

			$products = array();
			$total_amount = 0;
			$order_items = $order->get_items();
			foreach($order_items as $item) {
				$product = array(
					'title' => $item['name'],
					'id' => $item['product_id'],
					'count' => $item['qty'],
					'pretax_price' => (int)(round(($item['line_total']/$item['qty'])*100, 0)),
					'price' => (int)(round((($item['line_total'] + $item['line_tax'] ) / $item['qty'])*100, 0)),
					'tax' => round($item['line_tax']/$item['line_total']*100,0),
					'type' => 1
				);
				$total_amount += $product['price'] * $product['count'];
				array_push($products, $product);
		 	}

		 	$shipping_items = $order->get_items( 'shipping' );
		 	foreach($shipping_items as $s_method){
				$shipping_method_id = $s_method['method_id'] ;
			}
		 	if($order->order_shipping > 0){
			 	$product = array(
					'title' => $order->get_shipping_method(),
					'id' => $shipping_method_id,
					'count' => 1,
					'pretax_price' => (int)(round($order->order_shipping*100, 0)),
					'price' => (int)(round(($order->order_shipping_tax+$order->order_shipping)*100, 0)),
					'tax' => round($order->order_shipping_tax/$order->order_shipping*100,0),
					'type' => 2
				);
				$total_amount += $product['price'] * $product['count'];
				array_push($products, $product);				
			}

			if($order->order_discount > 0){
			 	$product = array(
					'title' => __( 'Order discount', 'paybyway' ),
					'id' => '',
					'count' => 1,
					'pretax_price' => -(int)(round($order->order_discount*100, 0)),
					'price' => -(int)(round($order->order_discount*100, 0)),
					'tax' => '0',
					'type' => 4
				);
				$total_amount += $product['price'] * $product['count'];
				array_push($products, $product);
			}

			if($this->send_items == 'yes' && $total_amount == $amount)
			{
				foreach($products as $product)
				{
					$payment->addProduct(
						array(
							'id' => htmlspecialchars($product['id']),
							'title' => htmlspecialchars($product['title']),
							'count' => $product['count'],
							'pretax_price' => $product['pretax_price'],
							'tax' => $product['tax'],
							'price' => $product['price'],
							'type' => $product['type']
						)
					);
				}
			}

			$mk_selected = '';

			if($this->embed == 'yes' && !empty($paybyway_selected_bank))
				$mk_selected = array($paybyway_selected_bank);
			else
			{
				$mk_selected = array();
				if($this->banks == 'yes')
					$mk_selected[] = 'banks';
				if($this->ccards == 'yes')
					$mk_selected[] = 'creditcards';
				if($this->cinvoices == 'yes')
					$mk_selected[] = 'creditinvoices';
				if($this->arvato == 'yes')
					$mk_selected[] = 'arvato';
				if($this->laskuyritykselle == 'yes')
					$mk_selected[] = 'laskuyritykselle';
			}

			$payment->addPaymentMethod(
				array(
					'type' => 'e-payment', 
					'return_url' => $return_url,
					'notify_url' => $return_url,
					'lang' => $lang,
					'selected' => $mk_selected,
					'token_valid_until' => strtotime('+1 hour')
				)
			);

			try
			{
				$response = $payment->createCharge();

				if($response->result == 0)
				{
					$order_number_text = __('Paybyway order', 'paybyway') . ": " . $order_number . "<br>-<br>" . __('Payment pending. Waiting for result.', 'paybyway');
					$order->add_order_note($order_number_text);
					update_post_meta($order_id, 'paybyway_order_number', $order_number);

					$url = Paybyway\Paybyway::API_URL."/token/".$response->token;
					$woocommerce->cart->empty_cart();
					return array(
						'result'   => 'success',
						'redirect'  => $url
					);
				}
				else
				{
					$errors = '';
					wc_add_notice(__('Payment failed due to an error.', 'paybyway'), 'error');
					$logger = new WC_Logger();
					if(isset($response->errors))
					{
						foreach ($response->errors as $error) 
						{
							$errors .= ' '.$error;
						}
					}
					$logger->add( 'paybyway', 'Maksukaista REST::CreateCharge failed, response: ' . $response->result . ' - Errors:'.$errors);
					return;
				}
			}
			catch (Paybyway\PaybywayException $e) 
			{
				wc_add_notice(__('Payment failed due to an error.', 'paybyway'), 'error');
				$logger = new WC_Logger();
				$logger->add( 'paybyway', 'Maksukaista REST::CreateCharge failed, exception: ' . $e->getCode().' '.$e->getMessage());
				return;
			}
		}

		function get_order_by_id_and_order_number($order_id, $order_number)
		{
			$order = New WC_Order($order_id);
			if($order_number == get_post_meta( $order->id, 'paybyway_order_number', true ))
				return $order;

			return null;
		}

		function check_paybyway_response()
		{
			global $woocommerce;

			if(count($_GET))
			{
				$return_code = isset($_GET['RETURN_CODE']) ? $_GET['RETURN_CODE'] : -999;
				$incident_id = isset($_GET['INCIDENT_ID']) ? $_GET['INCIDENT_ID'] : null;
				$settled = isset($_GET['SETTLED']) ? $_GET['SETTLED'] : null;
				$authcode = isset($_GET['AUTHCODE']) ? $_GET['AUTHCODE'] : null;
				$contact_id = isset($_GET['CONTACT_ID']) ? $_GET['CONTACT_ID'] : null;
				$order_number = isset($_GET['ORDER_NUMBER']) ? $_GET['ORDER_NUMBER'] : null;

				$authcode_confirm = $return_code .'|'. $order_number;

				if(isset($return_code) && $return_code == 0)
				{
					$authcode_confirm .= '|' . $settled;
					if(isset($contact_id) && !empty($contact_id))
						$authcode_confirm .= '|' . $contact_id;
				}
				else if(isset($incident_id) && !empty($incident_id))
					$authcode_confirm .= '|' . $incident_id;

				$authcode_confirm = strtoupper(hash_hmac('sha256', $authcode_confirm, $this->private_key));

				$order_id = isset($_GET['order_id']) ? $_GET['order_id'] : null;
				$order = $this->get_order_by_id_and_order_number($order_id, $order_number);
				$mk_on = get_post_meta($order_id, 'paybyway_order_number', true );

				if($authcode_confirm === $authcode && $order)
				{
					$current_return_code = get_post_meta($order->id, 'paybyway_return_code', true);

					if($order->status != 'processing' && $current_return_code != 0)
					{
						$pbw_extra_info = '';
						update_post_meta($order_id, 'paybyway_return_code', $return_code);
						include_once(plugin_dir_path( __FILE__ ).'includes/lib/paybyway_loader.php');
						$payment = new Paybyway\Paybyway($this->api_key, $this->private_key);
						try
						{
							$result = $payment->checkStatusWithOrderNumber($mk_on);
							if(isset($result->source->object) && $result->source->object === 'card')
							{
								$pbw_extra_info .=  "<br>-<br>" . __('Payment method: Card payment', 'paybyway') . "<br>";
								$pbw_extra_info .=  "<br>-<br>" . __('Card payment info: ', 'paybyway') . "<br>";

								if(isset($result->source->card_verified))
								{
									$pbw_verified = $this->paybyway_translate_verified_code($result->source->card_verified);
									$pbw_extra_info .= isset($pbw_verified) ? __('Verified: ', 'paybyway') . $pbw_verified . "<br>" : '';
								}

								$pbw_extra_info .= isset($result->source->card_country) ? __('Card country: ', 'paybyway') . $result->source->card_country . "<br>" : '';
								$pbw_extra_info .= isset($result->source->client_ip_country) ? __('Client IP country: ', 'paybyway') . $result->source->client_ip_country . "<br>" : '';

								if(isset($result->source->error_code))
								{
									$pbw_error = $this->paybyway_translate_error_code($result->source->error_code);
									$pbw_extra_info .= isset($pbw_error) ? __('Error: ', 'paybyway') . $pbw_error . "<br>" : '';
								}								
							}
							elseif (isset($result->source->brand))
								$pbw_extra_info .=  "<br>-<br>" . __('Payment method: ', 'paybyway') . ' ' . $result->source->brand . "<br>";
						}
						catch(Paybyway\PaybywayException $e)
						{
							$logger = new WC_Logger();
							$message = $e->getMessage();
							$logger->add( 'paybyway', 'Maksukaista REST::checkStatusWithOrderNumber failed, message: ' . $message);
						}

						switch($return_code)
						{							
							case 0:
								if($settled == 0)
								{
									$is_settled = 0;
									update_post_meta($order_id, 'paybyway_is_settled', $is_settled);
									$pbw_note = __('Paybyway order', 'paybyway') . ' ' . $mk_on . "<br>-<br>" . __('Payment is authorized. Use settle option to capture funds.', 'paybyway') . "<br>";
									
								}
								else
									$pbw_note = __('Paybyway order', 'paybyway') . ' ' . $mk_on . "<br>-<br>" . __('Payment accepted.', 'paybyway') . "<br>";

								$order->add_order_note($pbw_note . $pbw_extra_info);
								$order->payment_complete();
								$woocommerce->cart->empty_cart();
								break;

							case 1:
								$pbw_note = __('Payment was not accepted.', 'paybyway') . $pbw_extra_info;
								$order->update_status('failed', $pbw_note);
								break;

							case 4:
								$note = __('Transaction status could not be updated after customer returned from the web page of a bank. Please use the merchant UI to resolve the payment status.', 'paybyway');
								$order->update_status('failed', $note);
								break;

							case 10:
								$note = __('Maintenance break. The transaction is not created and the user has been notified and transferred back to the cancel address.', 'paybyway');
								$order->update_status('failed', $note);
								break;
						}
					}
				}
				else
					die ("MAC check failed");

				wp_redirect($this->get_return_url($order));
				exit('Ok');
			}
		}

		function paybyway_settle_payment($order)
		{
			global $woocommerce;
			$settle_field = get_post_meta( $order->id, 'paybyway_is_settled', true );
			$settle_check = empty($settle_field) && $settle_field == "0";
			if(!$settle_check)
				return;

			$url = admin_url('post.php?post=' . absint( $order->id ) . '&action=edit');

			if(isset($_GET['paybyway_settle']))
			{
				$order_number = get_post_meta( $order->id, 'paybyway_order_number', true );
				$settlement_msg = '';

				if($this->process_settlement($order_number, $settlement_msg))
				{
					update_post_meta($order->id, 'paybyway_is_settled', 1);
					$order->add_order_note(__('Payment settled.', 'paybyway'));
					$settlement_result = '1';
				}
				else
					$settlement_result = '0';

				if(!$settlement_result)
					echo '<div id="message" class="error">'.$settlement_msg.' <p class="form-field"><a href="'.$url.'" class="button button-primary">OK</a></p></div>';
				else
				{
					echo '<div id="message" class="updated fade">'.$settlement_msg.' <p class="form-field"><a href="'.$url.'" class="button button-primary">OK</a></p></div>';
					return;
				}
			}


			$text = __('Settle payment', 'paybyway');
			$url .= '&paybyway_settle';
			$html = "
				<p class='form-field'>
					<a href='$url' class='button button-primary'>$text</a>
				</p>";

			echo $html;
		}

		function process_settlement($order_number, &$settlement_msg)
		{
			include(plugin_dir_path( __FILE__ ).'includes/lib/paybyway_loader.php');
			$successful = false;
			$payment = new Paybyway\Paybyway($this->api_key, $this->private_key);
			try
			{
				$settlement = $payment->settlePayment($order_number);
				$return_code = $settlement->result;

				switch ($return_code)
				{
					case 0:
						$successful = true;
						$settlement_msg = __('Settlement was successful.', 'paybyway');
						break;
					case 1:
						$settlement_msg = __('Settlement failed. Validation failed.', 'paybyway');
						break;
					case 2:
						$settlement_msg = __('Settlement failed. Either the payment has already been settled or the payment gateway refused to settle payment for given transaction.', 'paybyway');
						break;
					default:
						$settlement_msg = __('Settlement failed. Unkown error.', 'paybyway');
						break;
				}
			}
			catch (Paybyway\PaybywayException $e) 
			{
				$message = $e->getMessage();
				$settlement_msg = __('Exception, error: ', 'paybyway') . $message;
			}
			return $successful;
		}

		function paybyway_save_img($key, $img_url, $img_timestamp)
		{
			$img = 'assets/images/'.$key.'.png';
			$timestamp = file_exists(plugin_dir_path( __FILE__ ) . $img) ? filemtime(plugin_dir_path( __FILE__ ) . $img) : 0;
			if(!file_exists(plugin_dir_path( __FILE__ ) . $img) || $img_timestamp > $timestamp)
			{
				if($file = @fopen($img_url, 'r'))
				{
					if(class_exists('finfo'))
					{
						$finfo = new finfo(FILEINFO_MIME_TYPE);
						if(strpos($finfo->buffer($file_content = stream_get_contents($file)), 'image') !== false)
						{
							@file_put_contents(plugin_dir_path( __FILE__ ) . $img, $file_content);
							touch(plugin_dir_path( __FILE__ ) . $img, $img_timestamp);
						}
					}
					else
					{
						@file_put_contents(plugin_dir_path( __FILE__ ) . $img, $file);
						touch(plugin_dir_path( __FILE__ ) . $img, $img_timestamp);
					}
					@fclose($file);
				}
			}
			return $img;
		}

		function paybyway_translate_error_code($pbw_error_code)
		{
			switch ($pbw_error_code)
			{
				case '04':
					return ' 04 - ' . __('The card is reported lost or stolen.', 'paybyway');
				case '05':
					return ' 05 - ' . __('General decline. The card holder should contact the issuer to find out why the payment failed.', 'paybyway');
				case '51':
					return ' 51 - ' . __('Insufficient funds. The card holder should verify that there is balance on the account and the online payments are actived.', 'paybyway');
				case '54':
					return ' 54 - ' . __('Expired card.', 'paybyway');
				case '61':
					return ' 61 - ' . __('Withdrawal amount limit exceeded.', 'paybyway');
				case '62':
					return ' 62 - ' . __('Restricted card. The card holder should verify that the online payments are actived.', 'paybyway');
				case '1000':
					return ' 1000 - ' . __('Timeout communicating with the acquirer. The payment should be tried again later.', 'paybyway');
				default:
					return null;
			}
		}

		function paybyway_translate_verified_code($pbw_verified_code)
		{
			switch ($pbw_verified_code)
			{
				case 'Y':
					return ' Y - ' . __('3-D Secure was used.', 'paybyway');
				case 'N':
					return ' N - ' . __('3-D Secure was not used.', 'paybyway');
				case 'A':
					return ' A - ' . __('3-D Secure was attempted but not supported by the card issuer or the card holder is not participating.', 'paybyway');
				default:
					return null;
			}
		}
	}
}
