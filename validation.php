<?php
/*
* 2007-2013 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2013 PrestaShop SA
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

include(dirname(__FILE__). '/../../config/config.inc.php');
include(dirname(__FILE__). '/../../init.php');

/* will include backward file */
include(dirname(__FILE__). '/beanstream.php');

$beanstream = new Beanstream();

/* Does the cart exist and is valid? */
$cart = Context::getContext()->cart;

if (!isset($_POST['x_invoice_num']))
{
	Logger::addLog('Missing x_invoice_num', 4);
	die('An unrecoverable error occured: Missing parameter');
}

if (!Validate::isLoadedObject($cart))
{
	Logger::addLog('Cart loading failed for cart '.(int)$_POST['x_invoice_num'], 4);
	die('An unrecoverable error occured with the cart '.(int)$_POST['x_invoice_num']);
}

if ($cart->id != $_POST['x_invoice_num'])
{
	Logger::addLog('Conflict between cart id order and customer cart id');
	die('An unrecoverable conflict error occured with the cart '.(int)$_POST['x_invoice_num']);
}

$customer = new Customer((int)$cart->id_customer);
$invoiceAddress = new Address((int)$cart->id_address_invoice);
$currency = new Currency((int)$cart->id_currency);

if (!Validate::isLoadedObject($customer) || !Validate::isLoadedObject($invoiceAddress) && !Validate::isLoadedObject($currency))
{
	Logger::addLog('Issue loading customer, address and/or currency data');
	die('An unrecoverable error occured while retrieving you data');
}

  // New Beanstream request processing
  $req = curl_init('https://www.beanstream.com/api/v1/payments');

  $headers = array(
    'Content-Type:application/json',
    'Authorization: Passcode '.
      base64_encode(

        Tools::safeOutput(Configuration::get('BEAN_LOGIN_ID_'.$currency->iso_code)).
        ':'.
        Tools::safeOutput(Configuration::get('BEAN_KEY_'.$currency->iso_code))
      )
  );


  curl_setopt($req,CURLOPT_HTTPHEADER, $headers);
  curl_setopt($req,CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($req,CURLOPT_HEADER, 0);

  $post = array(
    'merchant_id' => Tools::safeOutput(Configuration::get('BEAN_LOGIN_ID_'.$currency->iso_code)),
    'order_number' => (int)$_POST['x_invoice_num'],
    'amount' => number_format((float)$cart->getOrderTotal(true, 3), 2, '.', ''),
    'payment_method' => 'card',
    'card' => array(
      'name' => Tools::safeOutput($customer->firstname .' '. $customer->lastname),
      'number' => Tools::safeOutput($_POST['x_card_num']),
      'expiry_month' => Tools::safeOutput($_POST['x_exp_date_m']),
      'expiry_year' => Tools::safeOutput($_POST['x_exp_date_y']),
      'cvd' => Tools::safeOutput($_POST['x_card_code'])
    )
  );


  curl_setopt($req,CURLOPT_POST, 1);
  curl_setopt($req,CURLOPT_POSTFIELDS, json_encode($post));

  $res_json = curl_exec($req);
  $response = json_decode($res_json);


  curl_close($req);

if (!isset($response->message))
{
	$msg = 'Beanstream returned a malformed response for cart';
	if (isset($response->code))
		$msg .= ' '.(int)$response->code;
	Logger::addLog($msg, 4);
	die('Beanstream returned a malformed response, aborted.');
}

$message = $response->message;
$payment_method = 'Beanstream';
$amnt = isset($response->amount)?$response->amount:$post['amount'];

switch ($response->approved) // Response code
{
	case 1: // Payment accepted
		$beanstream->setTransactionDetail($response, $post);
		$beanstream->validateOrder((int)$cart->id,
			Configuration::get('PS_OS_PAYMENT'), (float)$amnt,
			$payment_method, $message, NULL, NULL, false, $customer->secure_key);
		break ;

	default:
		$error_message = (isset($response->message) && !empty($response->message)) ? urlencode(Tools::safeOutput($response->message)) : '';

		$checkout_type = Configuration::get('PS_ORDER_PROCESS_TYPE') ?
			'order-opc' : 'order';
		$url = _PS_VERSION_ >= '1.5' ?
			'index.php?controller='.$checkout_type.'&' : $checkout_type.'.php?';
		$url .= 'step=3&cgv=1&beanerror=1&message='.$error_message;

		if (!isset($_SERVER['HTTP_REFERER']) || strstr($_SERVER['HTTP_REFERER'], 'order'))
			Tools::redirect($url);
		else if (strstr($_SERVER['HTTP_REFERER'], '?'))
			Tools::redirect($_SERVER['HTTP_REFERER'].'&beanerror=1&message='.$error_message, '');
		else
			Tools::redirect($_SERVER['HTTP_REFERER'].'?beanerror=1&message='.$error_message, '');

		exit;
}

$url = 'index.php?controller=order-confirmation&';
	
$auth_order = new Order($beanstream->currentOrder);
Tools::redirect($url.'id_module='.(int)$beanstream->id.'&id_cart='.(int)$cart->id.'&key='.$auth_order->secure_key);
