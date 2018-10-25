<?php
/*
Plugin Name: افزونه پرداخت پی‌پینگ برای PRO Vip
Version: 1.0
Description:  افزونه پرداخت پی‌پینگ برای PRO Vip
Plugin URI: https://www.payping.ir/
Author: Erfan Ebrahimi
Author URI: http://erfanebrahimi.ir/
*/

defined('ABSPATH') or exit;

if (!function_exists('payping_pro_vip_gateway_class')) {
    add_action('plugins_loaded', 'payping_pro_vip_gateway_class');

    function payping_pro_vip_gateway_class()
    {
        add_filter('pro_vip_currencies_list', 'currencies_check');

        function currencies_check($list)
        {
            if (!in_array('IRT', $list)) {
                $list['IRT'] = [
                    'name'   => 'تومان ایران',
                    'symbol' => 'تومان',
                ];
            }

            if (!in_array('IRR', $list)) {
                $list['IRR'] = [
                    'name'   => 'ریال ایران',
                    'symbol' => 'ریال',
                ];
            }

            return $list;
        }

        if (class_exists('Pro_VIP_Payment_Gateway') && !class_exists('Pro_VIP_PayPing_Gateway')) {
            class Pro_VIP_PayPing_Gateway extends Pro_VIP_Payment_Gateway
            {
                public $id = 'PayPing',
                        $settings = [],
                        $frontendLabel = 'پی‌پینگ',
                        $adminLabel = 'پی‌پینگ';

                public function __construct()
                {
                    parent::__construct();
                }

                public function payping_proVip_status_message($code) {
	                switch ($code){
		                case 200 :
			                return 'عملیات با موفقیت انجام شد';
			                break ;
		                case 400 :
			                return 'مشکلی در ارسال درخواست وجود دارد';
			                break ;
		                case 500 :
			                return 'مشکلی در سرور رخ داده است';
			                break;
		                case 503 :
			                return 'سرور در حال حاضر قادر به پاسخگویی نمی‌باشد';
			                break;
		                case 401 :
			                return 'عدم دسترسی';
			                break;
		                case 403 :
			                return 'دسترسی غیر مجاز';
			                break;
		                case 404 :
			                return 'آیتم درخواستی مورد نظر موجود نمی‌باشد';
			                break;
	                }
                }

                public function beforePayment(Pro_VIP_Payment $payment)
                {
                    $Amount = intval($payment->price); // Required
                    $orderId = $payment->paymentId; // Required
                    $Description = 'پرداخت فاکتور به شماره ی'.$orderId; // Required
                    $CallbackURL = $this->getReturnUrl(); // $this->getReturnUrl();
                    //$currency = $order->get_order_currency();

                    if (pvGetOption('currency') === 'IRR') {
	                    $Amount /= 10;
                    }


	                $Message = null ;
	                $data = array('Amount' => $Amount, 'returnUrl' => $CallbackURL, 'Description' => $Description , 'clientRefId' => $orderId  );
	                try {
		                $curl = curl_init();
		                curl_setopt_array($curl, array(CURLOPT_URL => "https://api.payping.ir/v1/pay", CURLOPT_RETURNTRANSFER => true, CURLOPT_ENCODING => "", CURLOPT_MAXREDIRS => 10, CURLOPT_TIMEOUT => 30, CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1, CURLOPT_CUSTOMREQUEST => "POST", CURLOPT_POSTFIELDS => json_encode($data), CURLOPT_HTTPHEADER => array("accept: application/json", "authorization: Bearer " . $this->settings['api_key'], "cache-control: no-cache", "content-type: application/json"),));
		                $response = curl_exec($curl);
		                $header = curl_getinfo($curl);
		                $err = curl_error($curl);
		                curl_close($curl);
		                if ($err) {
			                $Message = "cURL Error #:" . $err;
		                } else {
			                if ($header['http_code'] == 200) {
				                $response = json_decode($response, true);
				                if (isset($response["code"]) and $response["code"] != '') {
					                $payment->key = $orderId;
					                $payment->user = get_current_user_id();
					                $payment->save();
					                wp_redirect(sprintf('https://api.payping.ir/v1/pay/gotoipg/%s', $response["code"]));
					                exit;
				                } else {
					                $Message = ' تراکنش ناموفق بود- شرح خطا : عدم وجود کد ارجاع ';
				                }
			                } elseif ($header['http_code'] == 400) {
				                $Message = ' تراکنش ناموفق بود- شرح خطا : ' . implode('. ',array_values (json_decode($response,true))) ;
			                } else {
				                $Message = ' تراکنش ناموفق بود- شرح خطا : ' . $this->status_message($header['http_code']) . '(' . $header['http_code'] . ')';
			                }
		                }
	                } catch (Exception $e){
		                $Message = ' تراکنش ناموفق بود- شرح خطا سمت برنامه شما : ' . $e->getMessage();
	                }

	                if ( $Message != null ){
		                pvAddNotice($Message);
		                return;
	                }
	                return;
                }

                public function afterPayment()
                {
                    if (isset($_GET['clientrefid'])) {
                        $orderId = $_GET['clientrefid'];
                    } else {
                        $orderId = 0;
                    }

                    if ($orderId) {
                        $payment = new Pro_VIP_Payment($orderId);
                        $Amount = intval($payment->price);

                        if (pvGetOption('currency') === 'IRR') {
	                        $Amount /= 10;
                        }


	                    $Message = null ;
	                    $data = array('refId' => $_GET['refid'], 'amount' => $Amount);
	                    try {
		                    $curl = curl_init();
		                    curl_setopt_array($curl, array(
			                    CURLOPT_URL => "https://api.payping.ir/v1/pay/verify",
			                    CURLOPT_RETURNTRANSFER => true,
			                    CURLOPT_ENCODING => "",
			                    CURLOPT_MAXREDIRS => 10,
			                    CURLOPT_TIMEOUT => 30,
			                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			                    CURLOPT_CUSTOMREQUEST => "POST",
			                    CURLOPT_POSTFIELDS => json_encode($data),
			                    CURLOPT_HTTPHEADER => array(
				                    "accept: application/json",
				                    "authorization: Bearer ".$this->settings['api_key'],
				                    "cache-control: no-cache",
				                    "content-type: application/json",
			                    ),
		                    ));
		                    $response = curl_exec($curl);
		                    $err = curl_error($curl);
		                    $header = curl_getinfo($curl);
		                    curl_close($curl);
		                    if ($err) {
			                    $Message = 'خطا در ارتباط به پی‌پینگ : شرح خطا '.$err;
		                    } else {
			                    if ($header['http_code'] == 200) {
				                    $response = json_decode($response, true);
				                    if (isset($_GET["refid"]) and $_GET["refid"] != '') {
					                    pvAddNotice('پرداخت شما با موفقیت انجام شد. کد پیگیری: '.$_GET["refid"], 'success');
					                    $payment->status = 'publish';
					                    $payment->save();

					                    $this->paymentComplete($payment);
				                    } else {
					                    $Message = 'متافسانه سامانه قادر به دریافت کد پیگیری نمی باشد! نتیجه درخواست : ' . $this->status_message($header['http_code']) . '(' . $header['http_code'] . ')' ;
				                    }
			                    } elseif ($header['http_code'] == 400) {
				                    $Message = 'تراکنش ناموفق بود- شرح خطا : ' .  implode('. ',array_values (json_decode($response,true))) ;
			                    }  else {
				                    $Message = ' تراکنش ناموفق بود- شرح خطا : ' . $this->status_message($header['http_code']) . '(' . $header['http_code'] . ')';
			                    }
		                    }
	                    } catch (Exception $e){
		                    $Message = ' تراکنش ناموفق بود- شرح خطا سمت برنامه شما : ' . $e->getMessage();
	                    }

	                    if ( $Message != null ){
		                    pvAddNotice($Message .' ,'.$_GET['refid']);
		                    $this->paymentFailed($payment);

		                    return false;
	                    }
                    }
                }

                public function adminSettings(PV_Framework_Form_Builder $form)
                {
                    $form->textfield('api_key')->label('کلید API');
                }
            }

            Pro_VIP_Payment_Gateway::registerGateway('Pro_VIP_PayPing_Gateway');
        }
    }
}
