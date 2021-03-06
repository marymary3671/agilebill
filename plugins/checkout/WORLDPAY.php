<?php
	
/**
 * AgileBill - Open Billing Software
 *
 * This body of work is free software; you can redistribute it and/or
 * modify it under the terms of the Open AgileBill License
 * License as published at http://www.agileco.com/agilebill/license1-4.txt
 * 
 * For questions, help, comments, discussion, etc., please join the
 * Agileco community forums at http://forum.agileco.com/ 
 *
 * @link http://www.agileco.com/
 * @copyright 2004-2008 Agileco, LLC.
 * @license http://www.agileco.com/agilebill/license1-4.txt
 * @author Tony Landis <tony@agileco.com> 
 * @package AgileBill
 * @version 1.4.93
 */
	
/*  
IMPORTANT: Be sure to enter the following into your WorldPay Administration Server account under the
"configuration options" for your installation:

Callback URL:    <WPDISPLAY ITEM=MC_callback>
Callback Enabled?:  Ticked
FuturePay Callback Enabled?: Ticked
Use callback response?: Ticked
*/

if(defined('PATH_MODULES')) include_once(PATH_MODULES.'checkout/base_checkout_plugin.class.php'); else include_once('../../modules/checkout/base_checkout_plugin.class.php');

class plg_chout_WORLDPAY extends base_checkout_plugin
{
	# Get the config values for this checkout plugin:
	function plg_chout_WORLDPAY($checkout_id=false) {

		$this->name 		= 'WORLDPAY';
		$this->type 		= 'redirect'; 			 // redirect, gateway, or other
		$this->recurr_only	= false;
		$this->return_url 	= SSL_URL . 'plugins/checkout/'. $this->name .'.php';
		$this->success_url  = URL . '?_page=invoice:thankyou&_next_page=invoice:user_view&id=';
		$this->decline_url  = URL . '?_page=invoice:user_view&id=';
		$this->support_cur  = Array ('USD', 'GBP', 'EUR', 'CAD', 'JPY');
		$this->getDetails($checkout_id);
	}

	# Validate the user submitted billing details at checkout:
	function validate($VAR) {
		return true;
	}

	# Perform the checkout transaction (new purchase):
	function bill_checkout( $amount, $invoice, $currency_iso, $acct_fields, $total_recurring=false, $recurr_bill_arr=false) {

		if(!$this->validate_currency($currency_iso)) return false;

		$url =	"https://select.worldpay.com/wcc/purchase";
		$vals = Array (
		Array ('instId', 			$this->cfg['instId']),
		Array ('testMode', 			$this->cfg['testMode']),
		Array ('desc', 				"Invoice No:". $invoice),
		Array ('amount', 			$amount),
		Array ('currency',			$currency_iso),
		Array ('cartId', 			$invoice),
		Array ('name', 				$acct_fields['first_name'] . ' ' . $acct_fields['last_name']),
		Array ('address',			$acct_fields['address1']),
		Array ('city', 				$acct_fields['city']),
		Array ('state', 			$acct_fields['state']),
		Array ('postcode', 			$acct_fields['zip']),
		Array ('country', 			$acct_fields['country_id']),
		Array ('email', 			$acct_fields['email']),
		Array ('MC_callback', 		$this->return_url),
		Array ('MC_invoice', 		$invoice),
		Array ('MC_gw', 			"B"),
		Array ('MC_post_type', 		3),
		);
 
		$this->post_vars($url, $vals);
		return true;
	}

	# Stores new billing details, & return account_billing_id (gateway only)
	function store_billing($VAR) {
		return 0;
	}

	# Perform a transaction for an (new invoice):
	function bill_invoice($VAR)   {
		return true;
	}

	# Issue a refund for a paid invoice (captured charges w/gateway)
	function refund($VAR) {
		return true;
	}

	# Void a authorized charge (gateways only)
	function void($VAR)  {
		return true;
	}

	# Postback Validation
	function postback($VAR)
	{
		# needed for return
		$ret['invoice_id']		= $VAR['MC_invoice'];
		$ret['transaction_id']	= $VAR['transId'];
		$ret['amount']	 		= $VAR['authAmount'];
		$ret['currency'] 		= DEFAULT_CURRENCY;
		$ret['status'] 			= true;

		# get the processor details:
		$db = &DB();
		$q  = "SELECT id,active,plugin_data FROM ".AGILE_DB_PREFIX."checkout WHERE
			        site_id 		= ".$db->qstr(DEFAULT_SITE)." AND
			        checkout_plugin	= ".$db->qstr($this->name);	
		$rs = $db->Execute($q);
		while(!$rs->EOF)
		{
			$ret['checkout_id'] = $rs->fields["id"];
			$do = true;
			$this->cfg = unserialize($rs->fields["plugin_data"]);

			if($VAR['transStatus'] != "Y")
			$do = false;

			if($do) {
				include_once(PATH_MODULES.'checkout/checkout.inc.php');
				$checkout = new checkout;
				$checkout->postback($ret);
				echo '<SCRIPT LANGUAGE="JavaScript">
							window.location="'.$this->success_url.$ret['invoice_id'].'";
						  </script>';
				return true;
			}
			$rs->MoveNext();
		}

		echo '<SCRIPT LANGUAGE="JavaScript">
					window.location="'.$this->decline_url.$ret['invoice_id'].'";
				  </script>';			 
	}
}


# Postback Function
if(empty($VAR) && empty($VAR['do']))
{
	include_once('../../config.inc.php');
	require_once(PATH_ADODB  . 'adodb.inc.php');
	require_once(PATH_CORE   . 'database.inc.php');
	require_once(PATH_CORE   . 'setup.inc.php');
	require_once(PATH_CORE   . 'vars.inc.php'); 
	$C_debug 	= new CORE_debugger;
	$C_vars 	= new CORE_vars;
	$VAR        = $C_vars->f;
	$C_db       = &DB();
	$C_setup 	= new CORE_setup; 
	$plg = new plg_chout_WORLDPAY;
	$plg->postback($VAR);
}

?>