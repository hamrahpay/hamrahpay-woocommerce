<?php
if (!defined('ABSPATH'))
    exit;


function Load_Hamrahpay_Gateway()
{
	if (class_exists('WC_Payment_Gateway') && !class_exists('WC_Hamrahpay') && !function_exists('Woocommerce_Add_Hamrahpay_Gateway')) {
		
		add_filter('woocommerce_payment_gateways', 'Woocommerce_Add_Hamrahpay_Gateway');

		function Woocommerce_Add_Hamrahpay_Gateway($methods)
		{
			$methods[] = 'WC_Hamrahpay';
			return $methods;
		}
		add_filter('woocommerce_currencies', 'add_IRAN_currencies');

        function add_IRAN_currencies($currencies)
        {
            $currencies['IRR'] = __('ریال', 'woocommerce');
            $currencies['IRT'] = __('تومان', 'woocommerce');
            $currencies['IRHR'] = __('هزار ریال', 'woocommerce');
            $currencies['IRHT'] = __('هزار تومان', 'woocommerce');

            return $currencies;
        }

        add_filter('woocommerce_currency_symbol', 'add_IRAN_currencies_symbol', 10, 2);

        function add_IRAN_currencies_symbol($currency_symbol, $currency)
        {
            switch ($currency) {
                case 'IRR':
                    $currency_symbol = 'ریال';
                    break;
                case 'IRT':
                    $currency_symbol = 'تومان';
                    break;
                case 'IRHR':
                    $currency_symbol = 'هزار ریال';
                    break;
                case 'IRHT':
                    $currency_symbol = 'هزار تومان';
                    break;
            }
            return $currency_symbol;
        }
		
		class WC_Hamrahpay extends WC_Payment_Gateway
		{
			private $API_Key;
            private $failedMassage;
            private $successMassage;
			private $api_version    = "v1";
			private $api_url        = 'https://api.hamrahpay.com/api';
			private $second_api_url = 'https://api.hamrahpay.ir/api';
			//---------------------------------------------------------------------
            public function __construct()
            {
				$this->api_url          .= '/'.$this->api_version;
				$this->second_api_url   .= '/'.$this->api_version;
		
                $this->id = 'WC_Hamrahpay';
                $this->method_title = __('پرداخت با درگاه همراه پِی', 'woocommerce');
                $this->method_description = __('<img src="' . apply_filters('WC_Hamrahpay_logo', WP_PLUGIN_URL . '/' . plugin_basename(__DIR__) . '/assets/images/logo_type_large.png') . '" alt="' . $this->title . '" title="' . $this->title . '" />'.'<br>'.'تنظیمات افزونه درگاه پرداخت آنلاین همراه پِی برای فروشگاه ساز ووکامرس ', 'woocommerce');
                $this->icon = apply_filters('WC_Hamrahpay_logo', WP_PLUGIN_URL . '/' . plugin_basename(__DIR__) . '/assets/images/logo_type.png');
                $this->has_fields = false;

                $this->init_form_fields();
                $this->init_settings();

                $this->title = $this->settings['title'];
                $this->description = $this->settings['description'];

                $this->API_Key = $this->settings['api_key'];

                $this->successMassage = $this->settings['success_massage'];
                $this->failedMassage = $this->settings['failed_massage'];

                if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
                    add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
                } else {
                    add_action('woocommerce_update_options_payment_gateways', array($this, 'process_admin_options'));
                }

                add_action('woocommerce_receipt_' . $this->id . '', array($this, 'Send_to_Hamrahpay_Gateway'));
                add_action('woocommerce_api_' . strtolower(get_class($this)) . '', array($this, 'Return_from_Hamrahpay_Gateway'));
            }
			//---------------------------------------------------------------------
			private function getApiUrl($end_point,$use_emergency_url=false)
			{
				if (!$use_emergency_url)
					return $this->api_url.$end_point;
				else
				{
					return $this->second_api_url.$end_point;
				}
			}
			//---------------------------------------------------------------------
			public function init_form_fields()
            {

				
                $this->form_fields = apply_filters('WC_Hamrahpay_Config', array(
                        'base_config' => array(
                            'title' => __('تنظیمات پایه ای', 'woocommerce'),
                            'type' => 'title',
                            'description' => '',
                        ),
                        'enabled' => array(
                            'title' => __('فعالسازی/غیرفعالسازی', 'woocommerce'),
                            'type' => 'checkbox',
                            'label' => __('فعال سازی درگاه همراه پی', 'woocommerce'),
                            'description' => __('برای فعالسازی درگاه پرداخت آنلاین همراه پِی باید گزینه را تیک بزنید', 'woocommerce'),
                            'default' => 'yes',
                            'desc_tip' => true,
                        ),
                        'title' => array(
                            'title' => __('عنوان درگاه پرداخت', 'woocommerce'),
                            'type' => 'text',
                            'description' => __('عنوان درگاه که در طی خرید به مشتری نمایش داده میشود', 'woocommerce'),
                            'default' => __('درگاه پرداخت همراه پِی', 'woocommerce'),
                            'desc_tip' => true,
                        ),
                        'description' => array(
                            'title' => __('توضیحات درگاه', 'woocommerce'),
                            'type' => 'text',
                            'desc_tip' => true,
                            'description' => __('توضیحاتی که در طی عملیات پرداخت برای درگاه نمایش داده خواهد شد', 'woocommerce'),
                            'default' => __('پرداخت مطمئن به وسیله کلیه کارت های عضو شتاب از طریق درگاه پرداخت همراه پِی', 'woocommerce')
                        ),
                        'account_config' => array(
                            'title' => __('تنظیمات حساب همراه پی', 'woocommerce'),
                            'type' => 'title',
                            'description' => '',
                        ),
                        'api_key' => array(
                            'title' => __('API Key', 'woocommerce'),
                            'type' => 'text',
                            'description' => __('API Key کسب و کار شما در همراه پِی', 'woocommerce'),
                            'default' => '',
                            'desc_tip' => true
                        ),
                        
                        'payment_config' => array(
                            'title' => __('تنظیمات عملیات پرداخت', 'woocommerce'),
                            'type' => 'title',
                            'description' => '',
                        ),
                        'success_massage' => array(
                            'title' => __('پیام پرداخت موفق', 'woocommerce'),
                            'type' => 'textarea',
                            'description' => __('متن پیامی که میخواهید پس از پرداخت موفق به کاربر نمایش دهید را وارد نمایید . همچنین می توانید از شورت کد {transaction_id} برای نمایش کد رهگیری همراه پی استفاده نمایید .', 'woocommerce'),
                            'default' => __('با تشکر از شما . سفارش شما با موفقیت پرداخت شد .', 'woocommerce'),
                        ),
                        'failed_massage' => array(
                            'title' => __('پیام پرداخت ناموفق', 'woocommerce'),
                            'type' => 'textarea',
                            'description' => __('متن پیامی که میخواهید بعد از پرداخت ناموفق به کاربر نمایش دهید را وارد نمایید . همچنین می توانید از شورت کد {fault} برای نمایش دلیل خطای رخ داده استفاده نمایید .', 'woocommerce'),
                            'default' => __('پرداخت شما ناموفق بوده است . لطفا مجددا تلاش نمایید یا در صورت بروز اشکال با پشتیبانی فروشگاه تماس بگیرید .', 'woocommerce'),
                        ),
                    )
                );
            }
			//---------------------------------------------------------------------
			public function process_payment($order_id)
            {
                $order = new WC_Order($order_id);
                return array(
                    'result' => 'success',
                    'redirect' => $order->get_checkout_payment_url(true)
                );
            }
			//---------------------------------------------------------------------
			function post_data($url,$params)
			{
				try
				{
					$ch = curl_init();
					curl_setopt($ch, CURLOPT_URL, $url);
					curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
					curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
					curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
					curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
					curl_setopt($ch, CURLOPT_HTTPHEADER, [
						'Content-Type: application/json',
					]);
					$result = curl_exec($ch);
					curl_close($ch);

					return $result;
				}
				catch(\Exception $e)
				{
					return false;
				}
			}
			//---------------------------------------------------------------------
			public function Send_to_Hamrahpay_Gateway($order_id)
            {
                global $woocommerce;
                $woocommerce->session->order_id_Hamrahpay = $order_id;
                $order = new WC_Order($order_id);
                $currency = $order->get_currency();
                $currency = apply_filters('WC_Hamrahpay_Currency', $currency, $order_id);


                $form = '<form action="" method="POST" class="Hamrahpay-checkout-form" id="Hamrahpay-checkout-form">
						<input type="submit" name="Hamrahpay_submit" class="button alt" id="Hamrahpay-payment-button" value="' . __('پرداخت', 'woocommerce') . '"/>
						<a class="button cancel" href="' . $woocommerce->cart->get_checkout_url() . '">' . __('بازگشت', 'woocommerce') . '</a>
					 </form><br/>';
                $form = apply_filters('WC_Hamrahpay_Form', $form, $order_id, $woocommerce);

                do_action('WC_Hamrahpay_Gateway_Before_Form', $order_id, $woocommerce);
                echo $form;
                do_action('WC_Hamrahpay_Gateway_After_Form', $order_id, $woocommerce);


                $Amount = intval($order->order_total);
                $Amount = apply_filters('woocommerce_order_amount_total_IRANIAN_gateways_before_check_currency', $Amount, $currency);
                if (
					strtolower($currency) == strtolower('IRT') || 
					strtolower($currency) == strtolower('TOMAN') || 
					strtolower($currency) == strtolower('Iran TOMAN') || 
					strtolower($currency) == strtolower('Iranian TOMAN') || 
					strtolower($currency) == strtolower('Iran-TOMAN') || 
					strtolower($currency) == strtolower('Iranian-TOMAN') || 
					strtolower($currency) == strtolower('Iran_TOMAN') || 
					strtolower($currency) == strtolower('Iranian_TOMAN') || 
					strtolower($currency) == strtolower('تومان') || 
					strtolower($currency) == strtolower('تومان ایران')
                    )
						$Amount = $Amount * 10;
                    else if (strtolower($currency) == strtolower('IRHT'))
                        $Amount = $Amount * 10000;
                    else if (strtolower($currency) == strtolower('IRHR'))
                        $Amount = $Amount * 1000;
                    else if (strtolower($currency) == strtolower('IRR'))
                        $Amount = $Amount;


                $Amount = apply_filters('woocommerce_order_amount_total_IRANIAN_gateways_after_check_currency', $Amount, $currency);
                $Amount = apply_filters('woocommerce_order_amount_total_IRANIAN_gateways_irt', $Amount, $currency);
                $Amount = apply_filters('woocommerce_order_amount_total_Hamrahpay_gateway', $Amount, $currency);

                $CallbackUrl = add_query_arg('wc_order', $order_id, WC()->api_request_url('WC_Hamrahpay'));

                $products = array();
                $order_items = $order->get_items();
                foreach ((array)$order_items as $product) {
                    $products[] = $product['name'] . ' (' . $product['qty'] . ') ';
                }
                $products = implode(' - ', $products);

                $Description = 'خریدار : ' . $order->billing_first_name . ' ' . $order->billing_last_name . ' | محصولات : ' . $products;
				if (strlen($Description) > 495)
					$Description = substr($Description, 0, 495) . '...';

                $Mobile = get_post_meta($order_id, '_billing_phone', true) ? get_post_meta($order_id, '_billing_phone', true) : '-';
                $Email = $order->billing_email;
                $CustomerName = $order->billing_first_name . ' ' . $order->billing_last_name;
				$Wages = [];
				$AllowedCards=[];

             
                $Description = apply_filters('WC_Hamrahpay_Description', $Description, $order_id);
                $Mobile = apply_filters('WC_Hamrahpay_Mobile', $Mobile, $order_id);
                $Email = apply_filters('WC_Hamrahpay_Email', $Email, $order_id);
                $CustomerName = apply_filters('WC_Hamrahpay_CustomerName', $CustomerName, $order_id);

                do_action('WC_Hamrahpay_Gateway_Payment', $order_id, $Description, $Mobile);
                $Email = !filter_var($Email, FILTER_VALIDATE_EMAIL) === false ? $Email : '';
                $Mobile = preg_match('/^09[0-9]{9}/i', $Mobile) ? $Mobile : '';

                $data = array(
					'api_key' => $this->API_Key, 
					'amount' => $Amount,
					'customer_name'=>$CustomerName,
					'callback_url' => $CallbackUrl, 
					'description' => $Description,
					'mobile'=>$Mobile,
					'email'=>$Email,
					//'wages'=>json_encode($Wages),
					//'allowed_cards'=>json_encode($AllowedCards);
					);

                $result = $this->post_data($this->getApiUrl('/rest/pg/pay-request'), $data);
                if ($result === false) {
                    echo "cURL Error";
                } else {
					$result = json_decode($result,true);
                    if (!empty($result['status']) && $result['status']==1)
					{
                        wp_redirect($result['pay_url']);
                        exit;
                    } else {
                        $Message = ' تراکنش ناموفق بود- کد خطا : ' . $result["invalid_fields"];
                        $Fault = '';
                    }
                }

                if (!empty($Message) && $Message) {

                    $Note = sprintf(__('خطا در هنگام ارسال به بانک : %s', 'woocommerce'), $Message);
                    $Note = apply_filters('WC_Hamrahpay_Send_to_Gateway_Failed_Note', $Note, $order_id, $Fault);
                    $order->add_order_note($Note);


                    $Notice = sprintf(__('در هنگام اتصال به بانک خطای زیر رخ داده است : <br/>%s', 'woocommerce'), $Message);
                    $Notice = apply_filters('WC_Hamrahpay_Send_to_Gateway_Failed_Notice', $Notice, $order_id, $Fault);
                    if ($Notice)
                        wc_add_notice($Notice, 'error');

                    do_action('WC_Hamrahpay_Send_to_Gateway_Failed', $order_id, $Fault);
                }
            }
			//---------------------------------------------------------------------
			public function Return_from_Hamrahpay_Gateway()
            {
                $PaymentToken = isset($_GET['payment_token']) ? $_GET['payment_token'] : '';
				$pay_status         = isset($_GET['status']) ? $_GET['status'] : '';
                global $woocommerce;


                if (isset($_GET['wc_order'])) {
                    $order_id = $_GET['wc_order'];
                } else if ($PaymentToken) {
                    $order_id = $PaymentToken;
                } else {
                    $order_id = $woocommerce->session->order_id_hamrahpay;
                    unset($woocommerce->session->order_id_hamrahpay);
                }

                if ($order_id) {

                    $order = new WC_Order($order_id);
                    $currency = $order->get_currency();
                    $currency = apply_filters('WC_Hamrahpay_Currency', $currency, $order_id);

                    if ($order->status !== 'completed') {

                       
                        if ($pay_status === 'OK') {

                            $API_Key = $this->API_Key;
                            $Amount = (int)$order->order_total;
                            $Amount = apply_filters('woocommerce_order_amount_total_IRANIAN_gateways_before_check_currency', $Amount, $currency);
                            $strToLowerCurrency = strtolower($currency);
                            if (
                                ($strToLowerCurrency === strtolower('IRT')) ||
                                ($strToLowerCurrency === strtolower('TOMAN')) ||
                                $strToLowerCurrency === strtolower('Iran TOMAN') ||
                                $strToLowerCurrency === strtolower('Iranian TOMAN') ||
                                $strToLowerCurrency === strtolower('Iran-TOMAN') ||
                                $strToLowerCurrency === strtolower('Iranian-TOMAN') ||
                                $strToLowerCurrency === strtolower('Iran_TOMAN') ||
                                $strToLowerCurrency === strtolower('Iranian_TOMAN') ||
                                $strToLowerCurrency === strtolower('تومان') ||
                                $strToLowerCurrency === strtolower('تومان ایران'
                                )
                            )
							$Amount = $Amount * 10;
                            else if (strtolower($currency) == strtolower('IRHT'))
                                $Amount = $Amount * 10000;
                            else if (strtolower($currency) == strtolower('IRHR'))
                                $Amount = $Amount * 1000;
                            else if (strtolower($currency) == strtolower('IRR'))
                                $Amount = $Amount;


                            $data = array('api_key' => $API_Key, 'payment_token' => $PaymentToken);
                            $result = $this->post_data($this->getApiUrl('/rest/pg/verify'),$data);
							$result = json_decode($result,true);
                            if ($result['status'] == 100) {
                                $Status = 'completed';
                                $Transaction_ID = $result['reserve_number'];
                                $Fault = '';
                                $Message = '';
                            } elseif ($result['status'] == 101) {
                                $Message = 'این تراکنش قلا تایید شده است';
								$Status = 'completed';
                                $Notice = wpautop(wptexturize($Message));
                                wp_redirect(add_query_arg('wc_status', 'success', $this->get_return_url($order)));
                                exit;
                            } else {
                                $Status = 'failed';
                                $Fault = $result['error_message'];
                                $Message = 'تراکنش ناموفق بود';
                            }
                        } else {
                            $Status = 'failed';
                            $Fault = '';
                            $Message = 'تراکنش انجام نشد .';
                        }

                        if ($Status === 'completed' && isset($Transaction_ID) && $Transaction_ID !== 0) {
                            update_post_meta($order_id, '_transaction_id', $Transaction_ID);

                            $order->payment_complete($Transaction_ID);
                            $woocommerce->cart->empty_cart();

                            $Note = sprintf(__('پرداخت موفقیت آمیز بود .<br/> کد رهگیری : %s', 'woocommerce'), $Transaction_ID);
                            $Note = apply_filters('WC_Hamrahpay_Return_from_Gateway_Success_Note', $Note, $order_id, $Transaction_ID);
                            if ($Note)
                                $order->add_order_note($Note, 1);


                            $Notice = wpautop(wptexturize($this->successMassage));

                            $Notice = str_replace('{transaction_id}', $Transaction_ID, $Notice);

                            $Notice = apply_filters('WC_Hamrahpay_Return_from_Gateway_Success_Notice', $Notice, $order_id, $Transaction_ID);
                            if ($Notice)
                                wc_add_notice($Notice, 'success');

                            do_action('WC_Hamrahpay_Return_from_Gateway_Success', $order_id, $Transaction_ID);

                            wp_redirect(add_query_arg('wc_status', 'success', $this->get_return_url($order)));
                            exit;
                        }

                        if (($Transaction_ID && ($Transaction_ID != 0))) {
                            $tr_id = ('<br/>شماره پیگیری : ' . $Transaction_ID);
                        } else {
                            $tr_id = '';
                        }

                        $Note = sprintf(__('خطا در هنگام بازگشت از بانک : %s %s', 'woocommerce'), $Message, $tr_id);

                        $Note = apply_filters('WC_Hamrahpay_Return_from_Gateway_Failed_Note', $Note, $order_id, $Transaction_ID, $Fault);
                        if ($Note) {
                            $order->add_order_note($Note, 1);
                        }

                        $Notice = wpautop(wptexturize($this->failedMassage));

                        $Notice = str_replace(array('{transaction_id}', '{fault}'), array($Transaction_ID, $Message), $Notice);
                        $Notice = apply_filters('WC_Hamrahpay_Return_from_Gateway_Failed_Notice', $Notice, $order_id, $Transaction_ID, $Fault);
                        if ($Notice) {
                            wc_add_notice($Notice, 'error');
                        }

                        do_action('WC_Hamrahpay_Return_from_Gateway_Failed', $order_id, $Transaction_ID, $Fault);

                        wp_redirect($woocommerce->cart->get_checkout_url());
                        exit;
                    }

                    $Transaction_ID = get_post_meta($order_id, '_transaction_id', true);

                    $Notice = wpautop(wptexturize($this->successMassage));

                    $Notice = str_replace('{transaction_id}', $Transaction_ID, $Notice);

                    $Notice = apply_filters('WC_Hamrahpay_Return_from_Gateway_ReSuccess_Notice', $Notice, $order_id, $Transaction_ID);
                    if ($Notice) {
                        wc_add_notice($Notice, 'success');
                    }

                    do_action('WC_Hamrahpay_Return_from_Gateway_ReSuccess', $order_id, $Transaction_ID);

                    wp_redirect(add_query_arg('wc_status', 'success', $this->get_return_url($order)));
                    exit;
                }

                $Fault = __('شماره سفارش وجود ندارد .', 'woocommerce');
                $Notice = wpautop(wptexturize($this->failedMassage));
                $Notice = str_replace('{fault}', $Fault, $Notice);
                $Notice = apply_filters('WC_Hamrahpay_Return_from_Gateway_No_Order_ID_Notice', $Notice, $order_id, $Fault);
                if ($Notice) {
                    wc_add_notice($Notice, 'error');
                }

                do_action('WC_Hamrahpay_Return_from_Gateway_No_Order_ID', $order_id, '0', $Fault);

                wp_redirect($woocommerce->cart->get_checkout_url());
                exit;
            }
			//---------------------------------------------------------------------
			//---------------------------------------------------------------------
			//---------------------------------------------------------------------
			//---------------------------------------------------------------------
		}
	}
}
add_action('plugins_loaded', 'Load_Hamrahpay_Gateway', 0);