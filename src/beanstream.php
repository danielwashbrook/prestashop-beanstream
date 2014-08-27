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

if (!defined('_PS_VERSION_'))
	exit;

class Beanstream extends PaymentModule
{
	public function __construct()
	{
		$this->name = 'beanstream';
		$this->tab = 'payments_gateways';
		$this->version = '1.0';
		$this->author = 'AperoCD';
		$this->bean_available_currencies = array('USD','AUD','CAD','EUR','GBP','NZD');

		parent::__construct();

		$this->displayName = 'Beanstream payment gateway';
		$this->description = $this->l('Receive payment with Beanstream');


		/* For 1.4.3 and less compatibility */
		$updateConfig = array(
			'PS_OS_CHEQUE' => 1,
			'PS_OS_PAYMENT' => 2,
			'PS_OS_PREPARATION' => 3,
			'PS_OS_SHIPPING' => 4,
			'PS_OS_DELIVERED' => 5,
			'PS_OS_CANCELED' => 6,
			'PS_OS_REFUND' => 7,
			'PS_OS_ERROR' => 8,
			'PS_OS_OUTOFSTOCK' => 9,
			'PS_OS_BANKWIRE' => 10,
			'PS_OS_PAYPAL' => 11,
			'PS_OS_WS_PAYMENT' => 12);

		foreach ($updateConfig as $u => $v)
			if (!Configuration::get($u) || (int)Configuration::get($u) < 1)
			{
				if (defined('_'.$u.'_') && (int)constant('_'.$u.'_') > 0)
					Configuration::updateValue($u, constant('_'.$u.'_'));
				else
					Configuration::updateValue($u, $v);
			}

		/* Check if cURL is enabled */
		if (!is_callable('curl_exec'))
			$this->warning = $this->l('cURL extension must be enabled on your server to use this module.');

		//$this->checkForUpdates();
	}

	public function install()
	{
		return parent::install() &&
			$this->registerHook('orderConfirmation') &&
			$this->registerHook('payment') &&
			$this->registerHook('header') &&
			$this->registerHook('backOfficeHeader') &&
			Configuration::updateValue('BEAN_SANDBOX', 1) &&
			Configuration::updateValue('BEAN_TEST_MODE', 0) &&
			Configuration::updateValue('BEAN_HOLD_REVIEW_OS', _PS_OS_ERROR_);
	}

	public function uninstall()
	{
		Configuration::deleteByName('BEAN_SANDBOX');
		Configuration::deleteByName('BEAN_TEST_MODE');
		Configuration::deleteByName('BEAN_CARD_VISA');
		Configuration::deleteByName('BEAN_CARD_MASTERCARD');
		Configuration::deleteByName('BEAN_CARD_DISCOVER');
		Configuration::deleteByName('BEAN_CARD_AX');
		Configuration::deleteByName('BEAN_HOLD_REVIEW_OS');

		/* Removing credentials configuration variables */
		$currencies = Currency::getCurrencies(false, true);
		foreach ($currencies as $currency)
			if (in_array($currency['iso_code'], $this->bean_available_currencies))
			{
				Configuration::deleteByName('BEAN_LOGIN_ID_'.$currency['iso_code']);
				Configuration::deleteByName('BEAN_KEY_'.$currency['iso_code']);
			}

		return parent::uninstall();
	}

	public function hookOrderConfirmation($params)
	{
		if ($params['objOrder']->module != $this->name)
			return;

		if ($params['objOrder']->getCurrentState() != Configuration::get('PS_OS_ERROR'))
		{
			Configuration::updateValue('BEAN_CONFIGURATION_OK', true);
			$this->context->smarty->assign(array('status' => 'ok', 'id_order' => intval($params['objOrder']->id)));
		}
		else
			$this->context->smarty->assign('status', 'failed');

		return $this->display(__FILE__, 'views/templates/hook/orderconfirmation.tpl');
	}

	public function hookBackOfficeHeader()
	{
		$this->context->controller->addJQuery();
		$this->context->controller->addJqueryPlugin('fancybox');
		$this->context->controller->addCSS($this->_path.'css/beanstream.css');
	}

	public function getContent()
	{
		$html = '';

		if (Tools::isSubmit('submitModule'))
		{
			$beanstream_mode = (int)Tools::getvalue('beanstream_mode');
			// Sandbox environment
			if ($beanstream_mode == 2)
			{
				Configuration::updateValue('BEAN_TEST_MODE', 0);
				Configuration::updateValue('BEAN_SANDBOX', 1);
			}
			// Production environment + test mode
			else if ($beanstream_mode == 1)
			{
				Configuration::updateValue('BEAN_TEST_MODE', 1);
				Configuration::updateValue('BEAN_SANDBOX', 0);
			}
			// Production environment
			else
			{
				Configuration::updateValue('BEAN_TEST_MODE', 0);
				Configuration::updateValue('BEAN_SANDBOX', 0);
			}

			Configuration::updateValue('BEAN_CARD_VISA', Tools::getvalue('bean_card_visa'));
			Configuration::updateValue('BEAN_CARD_MASTERCARD', Tools::getvalue('bean_card_mastercard'));
			Configuration::updateValue('BEAN_CARD_DISCOVER', Tools::getvalue('bean_card_discover'));
			Configuration::updateValue('BEAN_CARD_AX', Tools::getvalue('bean_card_ax'));
			Configuration::updateValue('BEAN_HOLD_REVIEW_OS', Tools::getvalue('bean_hold_review_os'));

			/* Updating credentials for each active currency */
			foreach ($_POST as $key => $value)
			{
				if (strstr($key, 'bean_login_id_'))
					Configuration::updateValue('BEAN_LOGIN_ID_'.str_replace('bean_login_id_', '', $key), $value);
				elseif (strstr($key, 'bean_key_'))
					Configuration::updateValue('BEAN_KEY_'.str_replace('bean_key_', '', $key), $value);
			}

			$html .= $this->displayConfirmation($this->l('Configuration updated'));
		}

		// For "Hold for Review" order status
		$currencies = Currency::getCurrencies(false, true);
		$order_states = OrderState::getOrderStates((int)$this->context->cookie->id_lang);

		$this->context->smarty->assign(array(
			'available_currencies' => $this->bean_available_currencies,
			'currencies' => $currencies,
			'module_dir' => $this->_path,
			'order_states' => $order_states,

			'BEAN_TEST_MODE' => (bool)Configuration::get('BEAN_TEST_MODE'),
			'BEAN_SANDBOX' => (bool)Configuration::get('BEAN_SANDBOX'),

			'BEAN_CARD_VISA' => Configuration::get('BEAN_CARD_VISA'),
			'BEAN_CARD_MASTERCARD' => Configuration::get('BEAN_CARD_MASTERCARD'),
			'BEAN_CARD_DISCOVER' => Configuration::get('BEAN_CARD_DISCOVER'),
			'BEAN_CARD_AX' => Configuration::get('BEAN_CARD_AX'),
			'BEAN_HOLD_REVIEW_OS' => (int)Configuration::get('BEAN_HOLD_REVIEW_OS'),
		));

		/* Determine which currencies are enabled on the store and supported by Authorize.net & list one credentials section per available currency */
		foreach ($currencies as $currency)
		{
			if (in_array($currency['iso_code'], $this->bean_available_currencies))
			{
				$configuration_id_name = 'BEAN_LOGIN_ID_'.$currency['iso_code'];
 				$configuration_key_name = 'BEAN_KEY_'.$currency['iso_code'];
				$this->context->smarty->assign($configuration_id_name, Configuration::get($configuration_id_name));
				$this->context->smarty->assign($configuration_key_name, Configuration::get($configuration_key_name));
			}
		}

		return $this->context->smarty->fetch(dirname(__FILE__).'/views/templates/admin/configuration.tpl');
	}

	public function hookPayment($params)
	{
		$currency = Currency::getCurrencyInstance($this->context->cookie->id_currency);

		if (!Validate::isLoadedObject($currency))
			return false;

		//if (Configuration::get('PS_SSL_ENABLED') || (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) != 'off'))
    if (1==1)
		{
			$isFailed = Tools::getValue('beanerror');

			$cards = array();
			$cards['visa'] = Configuration::get('BEAN_CARD_VISA') == 'on';
			$cards['mastercard'] = Configuration::get('BEAN_CARD_MASTERCARD') == 'on';
			$cards['discover'] = Configuration::get('BEAN_CARD_DISCOVER') == 'on';
			$cards['ax'] = Configuration::get('BEAN_CARD_AX') == 'on';

			if (method_exists('Tools', 'getShopDomainSsl'))
				$url = 'https://'.Tools::getShopDomainSsl().__PS_BASE_URI__.'/modules/'.$this->name.'/';
			else
				$url = 'https://'.$_SERVER['HTTP_HOST'].__PS_BASE_URI__.'modules/'.$this->name.'/';

			$this->context->smarty->assign('x_invoice_num', (int)$params['cart']->id);
			$this->context->smarty->assign('cards', $cards);
			$this->context->smarty->assign('isFailed', $isFailed);
			$this->context->smarty->assign('new_base_dir', $url);
			$this->context->smarty->assign('currency', $currency);

			return $this->display(__FILE__, 'views/templates/hook/beanstream.tpl');
		}
	}

	public function hookHeader()
	{
		if (_PS_VERSION_ < '1.5')
			Tools::addJS(_PS_JS_DIR_.'jquery/jquery.validate.creditcard2-1.0.1.js');
		else
			$this->context->controller->addJqueryPlugin('validate-creditcard');
	}

	/**
	 * Set the detail of a payment - Call before the validate order init
	 * correctly the pcc object
	 * See Authorize documentation to know the associated key => value
	 * @param array fields
	 */
	public function setTransactionDetail($response, $post)
	{
		// If Exist we can store the details
		if (isset($this->pcc))
		{
			$this->pcc->transaction_id = (string)$response->id;

			// 50 => Card number (XXXX0000)
			$this->pcc->card_number = (string)$response->card->last_four;

			// 51 => Card Mark (Visa, Master card)
			$this->pcc->card_brand = (string)$response->card->card_type;

			$this->pcc->card_expiration = (string)Tools::getValue('x_exp_date');

			// 68 => Owner name
			$this->pcc->card_holder = (string)$post['card']['name'];
		}
	}
}
