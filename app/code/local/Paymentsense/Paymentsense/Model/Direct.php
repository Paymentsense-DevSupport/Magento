<?php

if (!defined('COMPILER_INCLUDE_PATH')) {
    include_once ("Common/ThePaymentGateway/PaymentSystem.php");
	include_once ("Common/PaymentFormHelper.php");
	include_once ("Common/ISOCurrencies.php");
	include_once ("Common/ISOCountries.php");
	// include some Paymentsense functions
	include_once ("CommonFunctions.php");
} else {
    include_once ("Paymentsense_Paymentsense_Model_Common_ThePaymentGateway_PaymentSystem.php");
	include_once ("Paymentsense_Paymentsense_Model_Common_PaymentFormHelper.php");
	include_once ("Paymentsense_Paymentsense_Model_Common_ISOCurrencies.php");
	include_once ("Paymentsense_Paymentsense_Model_Common_ISOCountries.php");
	include_once ("Paymentsense_Paymentsense_Model_CommonFunctions.php");
}

class Paymentsense_Paymentsense_Model_Direct extends Mage_Payment_Model_Method_Abstract
{
	/**
  	* unique internal payment method identifier
  	*
  	* @var string [a-z0-9_]
  	*/
	protected $_code = 'Paymentsense';
 	protected $_formBlockType = 'Paymentsense/form'; 
 	protected $_infoBlockType = 'Paymentsense/info';

	protected $_isGateway = true;
	protected $_canAuthorize = true;
	protected $_canCapture = true;
	protected $_canCapturePartial = false;
	protected $_canRefund = true;
	protected $_canRefundInvoicePartial = true;
	protected $_canVoid = false;
	protected $_canUseInternal = false;
	protected $_canUseCheckout = true;
	protected $_canUseForMultishipping = true;
	protected $_canSaveCc = false;
	
	/**
	* Assign data to info model instance 
	*  
	* @param   mixed $data 
	* @return  Mage_Payment_Model_Info 
	*/  
 	public function assignData($data)  
	{
	    if (!($data instanceof Varien_Object))
	    {
	        $data = new Varien_Object($data);
	    }
	    
	    $info = $this->getInfoInstance();
	    
	    $info->setCcOwner($data->getCcOwner())
	        ->setCcLast4(substr($data->getCcNumber(), -4))
	        ->setCcNumber($data->getCcNumber())
	        ->setCcCid($data->getCcCid())
	        ->setCcExpMonth($data->getCcExpMonth())
	        ->setCcExpYear($data->getCcExpYear())
            ->setCcSsStartMonth($data->getCcSsStartMonth())
            ->setCcSsStartYear($data->getCcSsStartYear())
            ->setCcSsIssue($data->getCcSsIssue());

	    return $this;
	}
	
	/**
     * Validate payment method information object
     *
     * @param   Mage_Payment_Model_Info $info
     * @return  Mage_Payment_Model_Abstract
     */
	public function validate()
	{
		// NOTE : cancel out the core Magento validator functionality, the payment gateway will overtake this task
		
		return $this;
	}
	
	/**
     * Authorize - core Mage pre-authorization functionality
     *
     * @param   Varien_Object $orderPayment
     * @return  Mage_Payment_Model_Abstract
     */
	public function authorize(Varien_Object $payment, $amount)
	{
		$error = false;
		$mode = $this->getConfigData('mode');
		$nVersion = $this->getVersion();
				
		//Mage::throwException('This payment module only allow capture payments.');
		
		// TODO : need to finish for non Direct API methods
		switch ($mode)
		{
			case Paymentsense_Paymentsense_Model_Source_PaymentMode::PAYMENT_MODE_DIRECT_API:
		  		$error = $this->_runTransaction($payment, $amount);
		  		break;
		  	case Paymentsense_Paymentsense_Model_Source_PaymentMode::PAYMENT_MODE_HOSTED_PAYMENT_FORM:
		  		$error = $this->_runHostedPaymentTransaction($payment, $amount);
		  		break;
		  	case Paymentsense_Paymentsense_Model_Source_PaymentMode::PAYMENT_MODE_TRANSPARENT_REDIRECT:
		  		$error = $this->_runTransparentRedirectTransaction($payment, $amount);
		  		//Mage::throwException('TR not supported');
		  		break;
		  	default:
		  		Mage::throwException('Invalid payment type: '.$this->getConfigData('mode'));
		  		break;
		}
		
		if($error)
		{
			Mage::throwException($error);
		}
		
		return $this;
	}
	
	/**
     * Capture payment - immediate settlement payments
     *
     * @param   Varien_Object $payment
     * @return  Mage_Payment_Model_Abstract
     */
	public function capture(Varien_Object $payment, $amount)
	{
		$error = false;
		$session = Mage::getSingleton('checkout/session');
		$mode = $this->getConfigData('mode');
		$nVersion = $this->getVersion();
		
		if($amount <= 0)
		{
			Mage::throwException(Mage::helper('paygate')->__('Invalid amount for authorization.'));
		}
		else
		{
			if($session->getThreedsecurerequired())
			{
				$md = $session->getMd();
				$pares = $session->getPares();
				
				$session->setThreedsecurerequired(null);
				$this->_run3DSecureTransaction($payment, $pares, $md);
				
				return $this;
			}
			if($session->getRedirectedpayment())
			{
				$szStatusCode = $session->getStatuscode();
				$szMessage = $session->getMessage();
				$szPreviousStatusCode = $session->getPreviousstatuscode();
				$szPreviousMessage = $session->getPreviousmessage();
				$szOrderID = $session->getOrderid();
				// check whether it is a hosted payment or a transparent redirect action
				$boIsHostedPaymentAction = $session->getIshostedpayment();
				
				$session->setRedirectedpayment(null);
				$session->setIshostedpayment(null);
				$this->_runRedirectedPaymentComplete($payment, $boIsHostedPaymentAction, $szStatusCode, $szMessage, $szPreviousStatusCode, $szPreviousMessage, $szOrderID);
				
				return $this;
			}
			
			if($session->getIsCollectionCrossReferenceTransaction())
			{
				// do a CrossReference transaction
				$error = $this->_runCrossReferenceTransaction($payment, "COLLECTION", $amount);
			}
			else 
			{
				// fresh payment request
				$session->setThreedsecurerequired(null)
						->setRedirectedpayment(null)
						->setIshostedpayment(null)
						->setHostedPayment(null)
						->setMd(null)
						->setPareq(null)
						->setAcsurl(null)
						->setPaymentprocessorresponse(null);
				
				$payment->setAmount($amount);
				
			  	switch ($mode)
			  	{
			  		case Paymentsense_Paymentsense_Model_Source_PaymentMode::PAYMENT_MODE_DIRECT_API:
			  			$error = $this->_runTransaction($payment, $amount);
			  			break;
			  		case Paymentsense_Paymentsense_Model_Source_PaymentMode::PAYMENT_MODE_HOSTED_PAYMENT_FORM:
			  			$error = $this->_runHostedPaymentTransaction($payment, $amount);
			  			break;
			  		case Paymentsense_Paymentsense_Model_Source_PaymentMode::PAYMENT_MODE_TRANSPARENT_REDIRECT:
			  			$error = $this->_runTransparentRedirectTransaction($payment, $amount);
			  			break;
			  		default:
			  			Mage::throwException('Invalid payment type: '.$this->getConfigData('mode'));
			  			break;
			  	}
			}
		}
		
		if($error)
		{
			Mage::throwException($error);
		}
		
		return $this;
	}
	
	/**
	 * Processing the transaction using the direct integration
	 * 
	 * @param   Varien_Object $orderPayment
	 * @param   $amount
	 * @return  void
	 */
	public function _runTransaction(Varien_Object $payment, $amount)
	{
		$takePaymentInStoreBaseCurrency = $this->getConfigData('takePaymentInStoreBaseCurrency');
		
		$error = '';
		$session = Mage::getSingleton('checkout/session');
		$nVersion = $this->getVersion();
		
		$MerchantID = $this->getConfigData('merchantid');
		$Password = $this->getConfigData('password');
		$SecretKey = $this->getConfigData('secretkey');
		// assign payment form field values to variables
		$order = $payment->getOrder();
		$szOrderID = $payment->getOrder()->increment_id;
		$szOrderDescription = Mage::app()->getStore()->getName();
		$szCardName = $payment->getCcOwner();
		$szCardNumber = $payment->getCcNumber();
		$szIssueNumber = $payment->getCcSsIssue();
		$szCV2 = $payment->getCcCid();
		$nCurrencyCode;
		$szCurrencyShort = $order->getOrderCurrency()->getCurrencyCode();
		// address details
		$billingAddress = $order->getBillingAddress();
		$szAddress1 = $billingAddress->getStreet1();
		$szAddress2 = $billingAddress->getStreet2();
		$szAddress3 = $billingAddress->getStreet3();
		$szAddress4 = $billingAddress->getStreet4();
		$szCity = $billingAddress->getCity();
		$szState = $billingAddress->getRegion();
		$szPostCode = $billingAddress->getPostcode();
		$szISO2CountryCode = $billingAddress->getCountry();
		$nCountryCode;
		$szEmailAddress = $billingAddress->getCustomerEmail();
		$szPhoneNumber = $billingAddress->getTelephone();
		$nDecimalAmount;
		$szTransactionType;
		
		$iclISOCurrencyList = PS_CSV_ISOCurrencies::getISOCurrencyList();
		$iclISOCountryList = PS_CSV_ISOCountries::getISOCountryList();
		
		
		
		$paymentAction = $this->getConfigData('payment_action');
		if($paymentAction == Mage_Paygate_Model_Authorizenet::ACTION_AUTHORIZE_CAPTURE)
		{
			$szTransactionType = "SALE";
		}
		else if($paymentAction == Mage_Paygate_Model_Authorizenet::ACTION_AUTHORIZE)
		{
			$szTransactionType = "PREAUTH";
		}
		else 
		{
			Mage::throwException('Unknown payment action: '.$paymentAction);
		}
		
	
		if (!$takePaymentInStoreBaseCurrency) {	
			// Take payment in order currency
			$szCurrencyShort = $order->getOrderCurrency()->getCurrencyCode();
			if ($szCurrencyShort != '' && $iclISOCurrencyList->getISOCurrency($szCurrencyShort, $icISOCurrency))
			{
				$nCurrencyCode = $icISOCurrency->getISOCode();
				
			}
			
			// Calculate amount
			$power = pow(10, $icISOCurrency->getExponent());
			$nAmount = round($order->getGrandTotal() * $power,0);			
		} else {
			// Take payment in site base currency
			//$szCurrencyShort = $order->getOrderCurrency()->getCurrencyCode();
			$szCurrencyShort = $order->getBaseCurrencyCode();
			if ($szCurrencyShort != '' && $iclISOCurrencyList->getISOCurrency($szCurrencyShort, $icISOCurrency))
			{
				$nCurrencyCode = $icISOCurrency->getISOCode();
				
			}
			
			// Calculate amount
			$nAmount = $this->_getRoundedAmount($amount, $icISOCurrency->getExponent());			
		}
	
		$szCountryShort = $this->_getISO3Code($szISO2CountryCode);
		if ($iclISOCountryList->getISOCountry($szCountryShort, $icISOCountry))
		{
			
		}
	

		////////////////////////////////////////////////////////////////////////////
		////////////////////////////////////////////////////////////////////////////
		/* php 5.4 fix */
		////////////////////////////////////////////////////////////////////////////
		////////////////////////////////////////////////////////////////////////////
		

		//set transaction process to false and only to true if we have communicated
		$boTransactionProcessed = false;
		
		
		// Populate the variables from the shopping cart, which will then be used for the XML later
		// Only change the varibles on the right hand side with the values from the shopping cart.
		$MerchantID 		= $MerchantID;
		$Password			= $Password;

		$Amount				= $nAmount;
		$CurrencyCode		= $icISOCurrency->getISOCode();
		$OrderID			= $szOrderID;
		$OrderDescription	= $szOrderDescription;
		$TransactionType 	= $szTransactionType;

		$CardName			= $szCardName;
		$CardNumber			= $szCardNumber;
		$ExpMonth			= $payment->getCcExpMonth();
		$ExpYear			= $payment->getCcExpYear();
		$CV2				= $szCV2;
		$IssueNumber		= $szIssueNumber;
		
		$Address1			= $szAddress1;
		$Address2			= $szAddress2;
		$Address3			= $szAddress3;
		$Address4			= $szAddress4;
		$City				= $szCity;
		$State				= $szState;
		$Postcode			= $szPostCode;
		$CountryCode		= $icISOCountry->getISOCode();
		
		$EmailAddress		= $szEmailAddress;
		$PhoneNumber		= $szPhoneNumber;
		$IPAddress			= $_SERVER['REMOTE_ADDR'];



		//XML Headers used in cURL - payment processor domain is set later.... DO NOT ALTER THIS.
		$headers = array(
					'SOAPAction:https://www.thepaymentgateway.net/CardDetailsTransaction',
					'Content-Type: text/xml; charset = utf-8',
					'Connection: close'
				);

		//XML to send to the Gateway. PS_clean it up and make sure it doesnt exceed the characters allowed
		$xml = '<?xml version="1.0" encoding="utf-8"?>
		<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
			<soap:Body>
				<CardDetailsTransaction xmlns="https://www.thepaymentgateway.net/">
					<PaymentMessage>
						<MerchantAuthentication MerchantID="'. trim($MerchantID) .'" Password="'. trim($Password) .'" />
						<TransactionDetails Amount="'. $Amount .'" CurrencyCode="'. $CurrencyCode .'">
							<MessageDetails TransactionType="'.$TransactionType.'" />
							<OrderID>'. PS_clean($OrderID, 50) .'</OrderID>
							<OrderDescription>'. PS_clean($OrderDescription, 256) . '</OrderDescription>
								<TransactionControl>
									<EchoCardType>TRUE</EchoCardType>
									<EchoAVSCheckResult>TRUE</EchoAVSCheckResult>
									<EchoCV2CheckResult>TRUE</EchoCV2CheckResult>
									<EchoAmountReceived>TRUE</EchoAmountReceived>
									<DuplicateDelay>20</DuplicateDelay>
									<CustomVariables>
										<GenericVariable Name="MyInputVariable" Value="Ping" />
									</CustomVariables>
								</TransactionControl>
						</TransactionDetails>
						<CardDetails>
							<CardName>'. PS_clean($CardName, 100) .'</CardName>
							<CardNumber>'. $CardNumber .'</CardNumber>
							<StartDate Month="" Year="" />
							<ExpiryDate Month="'. $ExpMonth .'" Year="'. $ExpYear .'" />
							<CV2>'. $CV2 .'</CV2>
							<IssueNumber>'. $IssueNumber .'</IssueNumber>
						</CardDetails>
						<CustomerDetails>
							<BillingAddress>
								<Address1>'. PS_clean($Address1, 100) .'</Address1>
								<Address2>'. PS_clean($Address2, 50) .'</Address2>
								<Address3>'. PS_clean($Address3, 50) .'</Address3>
								<Address4>'. PS_clean($Address4, 50) .'</Address4>
								<City>'. PS_clean($City, 50) .'</City>
								<State>'. PS_clean($State, 50) .'</State>
								<PostCode>'. PS_clean($Postcode, 50) .'</PostCode>
								<CountryCode>'. $CountryCode .'</CountryCode>
							</BillingAddress>
							<EmailAddress>'. PS_clean($EmailAddress, 100) .'</EmailAddress>
							<PhoneNumber>'. PS_clean($PhoneNumber, 30) .'</PhoneNumber>
							<CustomerIPAddress>'. $IPAddress .'</CustomerIPAddress>
						</CustomerDetails>
						<PassOutData>Some data to be passed out</PassOutData>
					</PaymentMessage>
				</CardDetailsTransaction>
			</soap:Body>
		</soap:Envelope>';

		//set up the gateway configuration for Paymentsense
		$gwId = 1;
		$domain = "paymentsensegateway.com";
		$port = "4430";
		$transattempt = 1;
		$soapSuccess = false;

		while(!$soapSuccess && $gwId <= 3 && $transattempt <= 3) {		
	
			//builds the URL to post to (rather than it being hard coded - means we can loop through all 3 gateway servers)
			$url = 'https://gw'.$gwId.'.'.$domain.':'.$port.'/';
			
			//initialise cURL
			$curl = curl_init();
			
			//set the options
			curl_setopt($curl, CURLOPT_HEADER, false);
			curl_setopt($curl, CURLOPT_HTTPHEADER, $headers); 
			curl_setopt($curl, CURLOPT_POST, true);
			curl_setopt($curl, CURLOPT_URL, $url);
			curl_setopt($curl, CURLOPT_POSTFIELDS, $xml);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($curl, CURLOPT_ENCODING, 'UTF-8');
			curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
			
			//Execute cURL request
			//$ret = returned XML
			$ret = curl_exec($curl);
			//$err = returned error number
			$err = curl_errno($curl);
			//retHead = returned XML header
			$retHead = curl_getinfo($curl);
			
			//echo "<pre><xmp>" .$ret. "</xmp></pre>";
			
			//close cURL connection
			curl_close($curl);
			$curl = null;
			
			//if no error returned
			if($err == 0) {
				//Get the status code
				$StatusCode = PS_GetXMLValue("StatusCode", $ret, "[0-9]+");
				
				// if error occured... return the reason to the user
				if($StatusCode == 30){
					$szMessageDetail = PS_GetXMLValue("Detail", $ret, ".+");
					
					//run the function to get the cause of the error
					$CustomerMessage = PS_getErrorFromGateway($szMessageDetail, $ret);
				}
				
				if(is_numeric($StatusCode)) {
					//request was processed correctly
					
						//set success flag so it will not run the request again.
						$soapSuccess = true;
						
						//grab some of the most commonly used information from the response
						$szMessage = PS_GetXMLValue("Message", $ret, ".+");
						$szAuthCode = PS_GetXMLValue("AuthCode", $ret, ".+");
						$szCrossReference = PS_GetCrossReference($ret);
						$szAddressNumericCheckResult = PS_GetXMLValue("AddressNumericCheckResult", $ret, ".+");
						$szPostCodeCheckResult = PS_GetXMLValue("PostCodeCheckResult", $ret, ".+");
						$szCV2CheckResult = PS_GetXMLValue("CV2CheckResult", $ret, ".+");
						$szThreeDSecureAuthenticationCheckResult = PS_GetXMLValue("ThreeDSecureAuthenticationCheckResult", $ret, ".+");
						
						$securityChecks = "<br />Address Check: $szAddressNumericCheckResult | Post Code Check: $szPostCodeCheckResult | CV2 Check: $szCV2CheckResult |  3D Secure Check: $szThreeDSecureAuthenticationCheckResult";
				}
			}
			
			// attempt to communicate was unsuccessful... increment the transaction attempt if <=2
			if($transattempt <=2) {
				$transattempt++;
			} else {
				//reset transaction attempt to 1 & incremend $gwID (to use next numeric gateway number (eg. use gw2 rather than gw1 now))
				$transattempt = 1;
				$gwId++;
			}			
		}
		
		////////////////////////////////////////////////////////////////////////////
		////////////////////////////////////////////////////////////////////////////
		/* end php 5.4 fix */
		////////////////////////////////////////////////////////////////////////////
		////////////////////////////////////////////////////////////////////////////
		
		if ($soapSuccess == false)
		{
			// could not communicate with the payment gateway
			$error = Paymentsense_Paymentsense_Model_Common_GlobalErrors::ERROR_261;
				
			$szLogMessage = "Couldn't complete transaction. Unable to communicate with the payment gateway.";  //"Couldn't communicate with payment gateway.";
			Mage::log($szLogMessage);
		}
		else
		{
			$szLogMessage = "Transaction could not be completed for OrderID: ".$szOrderID.". Result details: ";
			$szNotificationMessage = 'Payment Processor Response: '.$szMessage;
			
			/* serve out the CrossReference as the TransactionId - this need to be done to enable the "Refund" button 
			   in the Magento CreditMemo internal refund mechanism */
			$payment->setTransactionId($szCrossReference);
			
			switch ($StatusCode)
			{
				case 0:
					// status code of 0 - means transaction successful
					$szLogMessage = "Transaction successfully completed for OrderID: ".$szOrderID.". Response object: ";
					
					// serve out the CrossReference as a TransactionId in the Magento system
					$order->setCustomerNote($szNotificationMessage);
					$this->setPaymentAdditionalInformation($payment, $szCrossReference);
					
					// deactivate the current quote - fixing the cart not emptied bug 
					Mage::getSingleton('checkout/session')->getQuote()->setIsActive(false)->save();
					
					// add the success message
					Mage::getSingleton('core/session')->addSuccess($szNotificationMessage);
					
					break;
				case 3:
					// status code of 3 - means 3D Secure authentication required
					$szLogMessage = "3D Secure Authentication required for OrderID: ".$szOrderID.". Response object: ";
					$szNotificationMessage = '';
					
					$szPaReq = PS_GetXMLValue("PaREQ", $ret, ".+");
					$szACSURL = PS_GetXMLValue("ACSURL", $ret, ".+");
					
					Mage::getSingleton('checkout/session')->setMd($szCrossReference)
	        												->setAcsurl($szACSURL)
			  		   										->setPareq($szPaReq);
					
					Mage::getSingleton('checkout/session')->setRedirectionmethod('_run3DSecureTransaction');
					$order->setIsThreeDSecurePending(true);
					
					//if customstock not enabled re-add the stock as previously deducted.
					$isCustomStockManagementEnabled = Mage::getModel('Paymentsense/direct')->getConfigData('customstockmanagementenabled');
					if(!$isCustomStockManagementEnabled)
					{
						$model = Mage::getModel('Paymentsense/direct');
						$model->addOrderedItemsToStock($order); 
					}
					
					
					break;
				case 5:
					// status code of 5 - means transaction declined
					$error = $szNotificationMessage;
					break;
				case 20:
					// status code of 20 - means duplicate transaction
					$soapPreviousTransactionResult = null;
					$PreviousTransactionResult = null;
						if (preg_match('#<PreviousTransactionResult>(.+)</PreviousTransactionResult>#iU', $ret, $soapPreviousTransactionResult)) {
							$PreviousTransactionResult = $soapPreviousTransactionResult[1];
							
							$PreviousMessage = PS_GetXMLValue("Message", $PreviousTransactionResult, ".+");
							$PreviousStatusCode = PS_GetXMLValue("StatusCode", $PreviousTransactionResult, ".+");
						}
					
					$szPreviousTransactionMessage = $PreviousMessage;
					$szLogMessage = "Duplicate transaction for OrderID: ".$szOrderID.". A duplicate transaction means that a transaction with these details has already been processed by the payment provider. The details of the original transaction: ".$szPreviousTransactionMessage.". Response object: ";
					$szNotificationMessage = $szNotificationMessage.". A duplicate transaction means that a transaction with these details has already been processed by the payment provider. The details of the original transaction - Previous Transaction Response: ".$szPreviousTransactionMessage;
					
					if ($PreviousStatusCode != 0)
					{
						$error = $szNotificationMessage;
					}
					else
					{
						Mage::getSingleton('core/session')->addSuccess($szNotificationMessage);
					}
					break;
				case 30:
					// status code of 30 - means an error occurred
					$CustomerMessage = PS_getErrorFromGateway($szMessageDetail, $ret);
					
					$error = $szNotificationMessage . ".\nReason: " . $CustomerMessage;
					$szLogMessage = "Transaction could not be completed for OrderID: ".$szOrderID.". Error message: ".$CustomerMessage;
					
					break;
				default:
					// unhandled status code
					$error = $szNotificationMessage;
					break;
			}
			
			$szLogMessage = $szLogMessage.print_r($ret, 1);
			Mage::log($szLogMessage);
		}
		
		if($error)
		{
			$payment->setStatus('FAIL')
					->setCcApproval('FAIL');
		}

		return $error;
	}
	
	/**
	 * Processing the transaction using the hosted payment form integration 
	 *
	 * @param Varien_Object $payment
	 * @param unknown_type $amount
	 */
	public function _runHostedPaymentTransaction(Varien_Object $payment, $amount)
	{
		$takePaymentInStoreBaseCurrency = $this->getConfigData('takePaymentInStoreBaseCurrency');
		
		$session = Mage::getSingleton('checkout/session');
		$nVersion = $this->getVersion();
		
		$szMerchantID = $this->getConfigData('merchantid');
		$szPassword = $this->getConfigData('password');
		$szPreSharedKey = $this->getConfigData('presharedkey');
		$hmHashMethod = $this->getConfigData('hashmethod');
		$boCV2Mandatory = 'false';
		$boAddress1Mandatory = 'false';
		$boCityMandatory = 'false';
		$boPostCodeMandatory = 'false';
		$boStateMandatory = 'false';
		$boCountryMandatory = 'false';
		$rdmResultdeliveryMethod = $this->getConfigData('resultdeliverymethod');
		$szServerResultURL = '';
		// set to always true to display the result on the Hosted Payment Form
		$boPaymentFormDisplaysResult = '';
		
		switch($rdmResultdeliveryMethod)
		{
			case Paymentsense_Paymentsense_Model_Source_ResultDeliveryMethod::RESULT_DELIVERY_METHOD_POST:
				$szCallbackURL = Mage::getUrl('Paymentsense/payment/callbackhostedpayment', array('_secure' => true));
				break;
			case Paymentsense_Paymentsense_Model_Source_ResultDeliveryMethod::RESULT_DELIVERY_METHOD_SERVER:
				$szCallbackURL = Mage::getUrl('Paymentsense/payment/callbackhostedpayment', array('_secure' => true));
				$szServerResultURL = Mage::getUrl('Paymentsense/payment/serverresult', array('_secure' => true));
				$boPaymentFormDisplaysResult = 'true';
				break;
			case Paymentsense_Paymentsense_Model_Source_ResultDeliveryMethod::RESULT_DELIVERY_METHOD_SERVER_PULL:
				$szCallbackURL = Mage::getUrl('Paymentsense/payment/serverpullresult', array('_secure' => true));
				break;
		}
		
		$order = $payment->getOrder();
		$billingAddress = $order->getBillingAddress();
		$iclISOCurrencyList = PS_CSV_ISOCurrencies::getISOCurrencyList();
		$iclISOCountryList = PS_CSV_ISOCountries::getISOCountryList();
		$cookie = Mage::getSingleton('core/cookie');
		$arCookieArray = $cookie->get();
		$arCookieKeysArray = array_keys($arCookieArray);
		$nKeysArrayLength = count($arCookieKeysArray);
		$szCookiePath = $cookie->getPath();
		$szCookieDomain = $cookie->getDomain();
		$szServerResultURLCookieVariables = '';
		$szServerResultURLFormVariables = '';
		$szServerResultURLQueryStringVariables = '';
		//ServerResutlURLCookieVariables string format: cookie1=123&path=/&domain=www.domain.com@@cookie2=456&path=/&domain=www.domain.com 
		
		for($nCount = 0; $nCount < $nKeysArrayLength; $nCount++)
		{
			$szEncodedCookieValue = urlencode($arCookieArray[$arCookieKeysArray[$nCount]]);
			$szServerResultURLCookieVariables .= $arCookieKeysArray[$nCount]."=".$szEncodedCookieValue."&path=".$szCookiePath."&domain=".$szCookieDomain;
			if($nCount < $nKeysArrayLength - 1)
			{
				$szServerResultURLCookieVariables .= "@@";
			}
		}
				
		if (!$takePaymentInStoreBaseCurrency) {	
			// Take payment in order currency
			$szCurrencyShort = $order->getOrderCurrency()->getCurrencyCode();
			if ($szCurrencyShort != '' && $iclISOCurrencyList->getISOCurrency($szCurrencyShort, $icISOCurrency))
			{
				$nCurrencyCode = $icISOCurrency->getISOCode();
			}
			
			// Calculate amount
			$power = pow(10, $icISOCurrency->getExponent());
			$nAmount = round($order->getGrandTotal() * $power,0);			
		} else {
			// Take payment in site base currency
			//$szCurrencyShort = $order->getOrderCurrency()->getCurrencyCode();
			$szCurrencyShort = $order->getBaseCurrencyCode();
			if ($szCurrencyShort != '' && $iclISOCurrencyList->getISOCurrency($szCurrencyShort, $icISOCurrency))
			{
				$nCurrencyCode = $icISOCurrency->getISOCode();
			}
			
			// Calculate amount
			$nAmount = $this->_getRoundedAmount($amount, $icISOCurrency->getExponent());			
		}
				
		$szISO2CountryCode = $billingAddress->getCountry();
		$szCountryShort = $this->_getISO3Code($szISO2CountryCode);
		if($iclISOCountryList->getISOCountry($szCountryShort, $icISOCountry))
		{
			$nCountryCode = $icISOCountry->getISOCode();
		}
		
		$szOrderID = $payment->getOrder()->increment_id;
		//date time with 2008-12-01 14:12:00 +01:00 format
		$szTransactionDateTime = date('Y-m-d H:i:s P');
		$szOrderDescription = '';

		//$szTransactionType = "SALE";
		$paymentAction = $this->getConfigData('payment_action');
		if($paymentAction == Mage_Paygate_Model_Authorizenet::ACTION_AUTHORIZE_CAPTURE)
		{
			$szTransactionType = "SALE";
		}
		else if($paymentAction == Mage_Paygate_Model_Authorizenet::ACTION_AUTHORIZE)
		{
			$szTransactionType = "PREAUTH";
		}
		else 
		{
			Mage::throwException('Unknown payment action: '.$paymentAction);
		}
		
		$szCustomerName = $billingAddress->getfirstname();
		if($billingAddress->getfirstname())
		{
			$szCustomerName = $szCustomerName.' '.$billingAddress->getlastname();
		}
		$szCustomerName = substr($szCustomerName, 0,100);
		$szAddress1 = substr($billingAddress->getStreet1(),0,100);
		$szAddress2 = substr($billingAddress->getStreet2(),0,50);
		$szAddress3 = substr($billingAddress->getStreet3(),0,50);
		$szAddress4 = substr($billingAddress->getStreet4(),0,50);
		$szCity 	= substr($billingAddress->getCity(),0,50);
		$szState 	= substr($billingAddress->getRegion(),0,50);
		$szPostCode = substr($billingAddress->getPostcode(),0,50);
		
		if($this->getConfigData('cv2mandatory'))
		{
			$boCV2Mandatory = 'true';
		}
		if($this->getConfigData('address1mandatory'))
		{
			$boAddress1Mandatory = 'true';
		}
		if($this->getConfigData('citymandatory'))
		{
			$boCityMandatory = 'true';
		}
		if($this->getConfigData('postcodemandatory'))
		{
			$boPostCodeMandatory = 'true';
		}
		if($this->getConfigData('statemandatory'))
		{
			$boStateMandatory = 'true';
		}
		if($this->getConfigData('countrymandatory'))
		{
			$boCountryMandatory = 'true';
		}
		if($this->getConfigData('paymentformdisplaysresult'))
		{
			$boPaymentFormDisplaysResult = 'true';
		}

		$szHashDigest = PS_CSV_PaymentFormHelper::calculateHashDigest($szMerchantID, $szPassword, $hmHashMethod, $szPreSharedKey, $nAmount, $nCurrencyCode, $szOrderID, $szTransactionType, $szTransactionDateTime, $szCallbackURL, $szOrderDescription, $szCustomerName, $szAddress1, $szAddress2, $szAddress3, $szAddress4, $szCity, $szState, $szPostCode, $nCountryCode, $boCV2Mandatory, $boAddress1Mandatory, $boCityMandatory, $boPostCodeMandatory, $boStateMandatory, $boCountryMandatory, $rdmResultdeliveryMethod, $szServerResultURL, $boPaymentFormDisplaysResult, $szServerResultURLCookieVariables, $szServerResultURLFormVariables, $szServerResultURLQueryStringVariables);

				$session->setHashdigest($szHashDigest)
	        	->setMerchantid($szMerchantID)
			  	->setAmount($nAmount)
			  	->setCurrencycode($nCurrencyCode)
			  	->setOrderid($szOrderID)
			  	->setTransactiontype($szTransactionType)
			  	->setTransactiondatetime($szTransactionDateTime)
			  	->setCallbackurl($szCallbackURL)
			  	->setOrderdescription($szOrderDescription)
			  	->setCustomername($szCustomerName)
			  	->setAddress1($szAddress1)
			  	->setAddress2($szAddress2)
			  	->setAddress3($szAddress3)
			  	->setAddress4($szAddress4)
			  	->setCity($szCity)
			  	->setState($szState)
			  	->setPostcode($szPostCode)
			  	->setCountrycode($nCountryCode)
			  	->setCv2mandatory($boCV2Mandatory)
			  	->setAddress1mandatory($boAddress1Mandatory)
			  	->setCitymandatory($boCityMandatory)
			  	->setPostcodemandatory($boPostCodeMandatory)
			  	->setStatemandatory($boStateMandatory)
			  	->setCountrymandatory($boCountryMandatory)
			  	->setResultdeliverymethod($rdmResultdeliveryMethod)
			  	->setServerresulturl($szServerResultURL)
			  	->setPaymentformdisplaysresult($boPaymentFormDisplaysResult)
			  	->setServerresulturlcookievariables($szServerResultURLCookieVariables)
			  	->setServerresulturlformvariables($szServerResultURLFormVariables)
			  	->setServerresulturlquerystringvariables($szServerResultURLQueryStringVariables);
			  	
		$session->setRedirectionmethod('_runRedirectedPaymentComplete');
		$payment->getOrder()->setIsHostedPaymentPending(true);
		
		/* serve out a dummy CrossReference as the TransactionId - this need to be done to enable the "Refund" button 
		   in the Magento CreditMemo internal refund mechanism */
		$payment->setTransactionId($szOrderID."_".date('YmdHis'));
	}

	
	/**
	 * Processing the 3D Secure transaction
	 *
	 * @param Varien_Object $payment
	 * @param int $amount
	 * @param string $szPaRes
	 * @param string $szMD
	 */
	public function _run3DSecureTransaction(Varien_Object $payment, $szPaRes, $szMD)
	{
		$error = false;
		$message = '';
		$order = $payment->getOrder();
		$szOrderID = $payment->getOrder()->increment_id;
		$session = Mage::getSingleton('checkout/session');
		$nVersion = $this->getVersion();
		
		$MerchantID = $this->getConfigData('merchantid');
		$Password = $this->getConfigData('password');
		$SecretKey = $this->getConfigData('secretkey');
		
		//////////////////////////////////////////////
		//////////////////////////////////////////////
		// PHP 5.4 fix //
		//////////////////////////////////////////////
		//////////////////////////////////////////////
		
		//XML Headers used in cURL - remember to change the function after thepaymentgateway.net in SOAPAction when changing the XML to call a different function
		$headers = array(
					'SOAPAction:https://www.thepaymentgateway.net/ThreeDSecureAuthentication',
					'Content-Type: text/xml; charset = utf-8',
					'Connection: close'
				);


		//XML to send to the Gateway - put your merchant ID & Password in the appropriate place.
		$xml = '<?xml version="1.0" encoding="utf-8"?>
		<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
		xmlns:xsd="http://www.w3.org/2001/XMLSchema"
		xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
		<soap:Body>
		<ThreeDSecureAuthentication xmlns="https://www.thepaymentgateway.net/">
		<ThreeDSecureMessage>
		<MerchantAuthentication MerchantID="'. trim($MerchantID) .'" Password="'. trim($Password) .'" />
		<ThreeDSecureInputData CrossReference="'. $szMD .'">
		<PaRES>'. $szPaRes .'</PaRES>
		</ThreeDSecureInputData>
		<PassOutData>Some data to be passed out</PassOutData>
		</ThreeDSecureMessage>
		</ThreeDSecureAuthentication>
		</soap:Body>
		</soap:Envelope>';

		$gwId = 1;
		$domain = "paymentsensegateway.com";
		$port = "4430";
		$transattempt = 1;
		$soapSuccess = false;
		
		
		//It will attempt each of the gateway servers (gw1, gw2 & gw3) 3 times each before totally failing
		while(!$soapSuccess && $gwId <= 3 && $transattempt <= 3) {		
			
			//builds the URL to post to (rather than it being hard coded - means we can loop through all 3 gateway servers)
			$url = 'https://gw'.$gwId.'.'.$domain.':'.$port.'/';
			Mage::log($url);
			//initialise cURL
			$curl = curl_init();
			
			//set the options
			curl_setopt($curl, CURLOPT_HEADER, false);
			curl_setopt($curl, CURLOPT_HTTPHEADER, $headers); 
			curl_setopt($curl, CURLOPT_POST, true);
			curl_setopt($curl, CURLOPT_URL, $url);
			curl_setopt($curl, CURLOPT_POSTFIELDS, $xml);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($curl, CURLOPT_ENCODING, 'UTF-8');
			curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
			
			//Execute cURL request
			//$ret = returned XML
			$ret = curl_exec($curl);
			//$err = returned error number
			$err = curl_errno($curl);
			//retHead = returned XML header
			$retHead = curl_getinfo($curl);
			
			//close cURL connection
			curl_close($curl);
			$curl = null;
			
			//if no error returned
			if($err == 0) {		
				//Get the status code
				$StatusCode = PS_GetXMLValue("StatusCode", $ret, "[0-9]+");
				
				if(is_numeric($StatusCode)) {		
					//request was processed correctly
					if( $StatusCode != 30 ) {
						//set success flag so it will not run the request again.
						$soapSuccess = true;
						
						$szMessage = PS_GetXMLValue("Message", $ret, ".+");
						$szCrossReference = PS_GetCrossReference($ret);
						$szAddressNumericCheckResult = PS_GetXMLValue("AddressNumericCheckResult", $ret, ".+");
						$szPostCodeCheckResult = PS_GetXMLValue("PostCodeCheckResult", $ret, ".+");
						$szCV2CheckResult = PS_GetXMLValue("CV2CheckResult", $ret, ".+");
						$szThreeDSecureAuthenticationCheckResult = PS_GetXMLValue("ThreeDSecureAuthenticationCheckResult", $ret, ".+");
						
						$securityChecks = "<br />Address Check: $szAddressNumericCheckResult | Post Code Check: $szPostCodeCheckResult | CV2 Check: $szCV2CheckResult |  3D Secure Check: $szThreeDSecureAuthenticationCheckResult";
					}
				}
			}
			
			//increment the transaction attempt if <=2
			if($transattempt <=2) {
				$transattempt++;
			} else {
				//reset transaction attempt to 1 & incremend $gwID (to use next numeric gateway number (eg. use gw2 rather than gw1 now))
				$transattempt = 1;
				$gwId++;
			}			
		}

		//////////////////////////////////////////////
		//////////////////////////////////////////////
		// end php 5.4 fix //
		//////////////////////////////////////////////
		//////////////////////////////////////////////
		
		if ($soapSuccess == false)
		{
			// could not communicate with the payment gateway
			//set error flag
			$error = true;
			$szLogMessage = Paymentsense_Paymentsense_Model_Common_GlobalErrors::ERROR_431;
			$message = Paymentsense_Paymentsense_Model_Common_GlobalErrors::ERROR_431;
			Mage::log($szLogMessage);
		}
		else
		{
			$message = "Payment Processor Response: ".$szMessage;
			$szLogMessage = "3D Secure transaction could not be completed for OrderID: ".$szOrderID.". Response object: ";
			
			switch ($StatusCode)
			{
				case 0:
					// status code of 0 - means transaction successful
					$szLogMessage = "3D Secure transaction successfully completed for OrderID: ".$szOrderID.". Response object: ";
					// serve out the CrossReference as a TransactionId in the Magento system
					$this->setPaymentAdditionalInformation($payment, $szCrossReference);
					
					// need to store the new CrossReference and only store it against the payment object in the payment controller class
					$session->setNewCrossReference($szCrossReference);
					break;
				case 5:
					// status code of 5 - means transaction declined
					$error = true;
					break;
				case 20:
					// status code of 20 - means duplicate transaction
					$soapPreviousTransactionResult = null;
					$PreviousTransactionResult = null;
					if (preg_match('#<PreviousTransactionResult>(.+)</PreviousTransactionResult>#iU', $ret, $soapPreviousTransactionResult)) {
						$PreviousTransactionResult = $soapPreviousTransactionResult[1];
							
						$PreviousMessage = PS_GetXMLValue("Message", $PreviousTransactionResult, ".+");
						$PreviousStatusCode = PS_GetXMLValue("StatusCode", $PreviousTransactionResult, ".+");
					}
					
					
					$szPreviousTransactionMessage = $PreviousMessage;
					$szLogMessage = "Duplicate transaction for OrderID: ".$szOrderID.". A duplicate transaction means that a transaction with these details has already been processed by the payment provider. The details of the original transaction: ".$szPreviousTransactionMessage.". Response object: ";
					
					if ($PreviousStatusCode == 0)
					{
						$message = $message.". A duplicate transaction means that a transaction with these details has already been processed by the payment provider. The details of the original transaction are - ".$szPreviousTransactionMessage;
					}
					else
					{
						$error = true;
					}
					break;
				case 30:
					$error = true;
					// status code of 30 - means an error occurred 
					
					// status code of 30 - means an error occurred
					$CustomerMessage = PS_getErrorFromGateway($szMessageDetail, $ret);
					
					$szLogMessage = "3D Secure transaction could not be completed for OrderID: ".$szOrderID.". Error message: ".$CustomerMessage;
					
					{
						$szLogMessage = $szLogMessage.".";
						$message = $message."."; 

					}
					break;
				default:
					// unhandled status code 
					$error = true; 
					break;
			}
			
			// log 3DS payment result
			$szLogMessage = $szLogMessage.print_r($ret, 1);
			Mage::log($szLogMessage);
		}
		
		$session->setPaymentprocessorresponse($message);
		if($error == true)
		{
			$message = Mage::helper('Paymentsense')->__($message);
			Mage::throwException($message);
		}
		else
		{
			$payment->setStatus(self::STATUS_APPROVED);
		}
		
		return $this;
	}
	
	public function _runRedirectedPaymentComplete(Varien_Object $payment, $boIsHostedPaymentAction, $szStatusCode, $szMessage, $szPreviousStatusCode, $szPreviousMessage, $szOrderID, $szCrossReference)
	{
		$error = false;
		$message;
		$session = Mage::getSingleton('checkout/session');
		$nVersion = $this->getVersion();
		
		if($boIsHostedPaymentAction == true)
		{
			$szWording = "Hosted Payment Form ";
		}
		else
		{
			$szWording = "Transparent Redirect ";
		}
		
		$message = "Payment Processor Response: ".$szMessage;
		    	
		switch ($szStatusCode)
    	{
    		case "0":
    			Mage::log($szWording."transaction successfully completed. ".$message);

    			// need to store the new CrossReference and only store it against the payment object in the payment controller class
				$session->setNewCrossReference($szCrossReference);
    			break;
    		case "20":
    			Mage::log("Duplicate ".$szWording."transaction. ".$message);
    			$message = $message.". A duplicate transaction means that a transaction with these details has already been processed by the payment provider. The details of the original transaction - Previous Transaction Response: ".$szPreviousMessage;
    			if($szPreviousStatusCode != "0")
    			{
	    			$error = true;
    			}
    			break;
    		case "5":
    		case "30":
    		default:
    			Mage::log($szWording."transaction couldn't be completed. ".$message);
    			$error = true;
    			break;
    	}
    	
    	$session->setPaymentprocessorresponse($message);
    	
    	// store the CrossReference and other data
    	$this->setPaymentAdditionalInformation($payment, $szCrossReference);
		
		if($error == true)
		{
			$message = Mage::helper('Paymentsense')->__($message);
			Mage::throwException($message);
		}
		else
		{
			$payment->setStatus(self::STATUS_APPROVED);
		}
		
		return $this;
	}
	
	/**
	 * Override the core Mage function to get the URL to be redirected from the Onepage
	 *
	 * @return string
	 */
	public function getOrderPlaceRedirectUrl()
    {
    	$result = false;
       	$session = Mage::getSingleton('checkout/session');
     	$nVersion = $this->getVersion();
     	$mode = $this->getConfigData('mode');
     	
       	if($session->getMd() &&
       		$session->getAcsurl() &&
       		$session->getPareq())
       	{
       		// Direct (API) for 3D Secure payments       		
			// need to re-add the ordered item quantity to stock as per not completed 3DS transaction
			if($mode != Paymentsense_Paymentsense_Model_Source_PaymentMode::PAYMENT_MODE_TRANSPARENT_REDIRECT)
			{
				$order = Mage::getModel('sales/order')->load(Mage::getSingleton('checkout/session')->getLastOrderId());
				$this->addOrderedItemsToStock($order);
			}
	    	
	    	
       		$result = Mage::getUrl('Paymentsense/payment/threedsecure', array('_secure' => true));
       	}
       	if($session->getHashdigest())
       	{

			// need to re-add the ordered item quantity to stock as per not completed 3DS transaction
			if(!Mage::getSingleton('checkout/session')->getPares())
			{
				$order = Mage::getModel('sales/order')->load(Mage::getSingleton('checkout/session')->getLastOrderId());
				$this->addOrderedItemsToStock($order);
			}	    	
	    	
       		$result = Mage::getUrl('Paymentsense/payment/redirect', array('_secure' => true));
       	}
        
        return $result;
    }
	
    /**
     * Get the correct payment processor domain
     *
     * @return string
     */
    private function _getPaymentProcessorFullDomain()
    {
    	$szPaymentProcessorFullDomain;
    	
    	// get the stored config setting
    	$szPaymentProcessorDomain = $this->getConfigData('paymentprocessordomain');
		$szPaymentProcessorPort = $this->getConfigData('paymentprocessorport');
    	
    	if ($szPaymentProcessorPort == '443')
		{
			$szPaymentProcessorFullDomain = $szPaymentProcessorDomain."/";
		}
		else
		{
			$szPaymentProcessorFullDomain = $szPaymentProcessorDomain.":".$szPaymentProcessorPort."/";
		}
		
		return $szPaymentProcessorFullDomain;
    }
    
    /**
     * Get the country ISO3 code from the ISO2 code
     *
     * @param ISO2Code
     * @return string
     */
	private function _getISO3Code($szISO2Code)
	{
		$szISO3Code;
		$collection;
		$boFound = false;
		$nCount = 1;
		$item;
		
		$collection = Mage::getModel('directory/country_api')->items();
		
		while ($boFound == false &&
				$nCount < count($collection))
		{
			$item = $collection[$nCount];
			if($item['iso2_code'] == $szISO2Code)
			{
				$boFound = true;
				$szISO3Code = $item['iso3_code'];
			}
			$nCount++;
		}
		
		return $szISO3Code;
	}
	
	/**
	* Transform the string Magento version number into an integer ready for comparison
	*
	* @param unknown_type $magentoVersion
	* @return unknown
	*/
	public function getVersion()
	{
		$magentoVersion = Mage::getVersion();
	   	$pattern = '/[^\d]/';
		$magentoVersion = preg_replace($pattern, '', $magentoVersion);
		
		while(strlen($magentoVersion) < 4)
		{
			$magentoVersion .= '0';
		}
		$magentoVersion = (int)$magentoVersion;
		
		return $magentoVersion;
	}
	
	private function _getRoundedAmount($amount, $nExponent)
	{
		$nDecimalAmount;
		
		// round the amount before use
		$amount = round($amount, $nExponent);
		$power = pow(10, $nExponent);
		$nDecimalAmount = $amount * $power;
		
		return $nDecimalAmount;
	}
	
	/**
	 * Depreciated function - sets the additional_information column data in the sales_flat_order_payment table
	 *
	 * @param unknown_type $payment
	 * @param unknown_type $szCrossReference
	 * @param unknown_type $szTransactionType
	 * @param unknown_type $szTransactionDate
	 */
	public function setPaymentAdditionalInformation($payment, $szCrossReference)
    {
	
    	$arAdditionalInformationArray = array();
    	
    	$paymentAction = $this->getConfigData('payment_action');
		if($paymentAction == Mage_Paygate_Model_Authorizenet::ACTION_AUTHORIZE_CAPTURE)
		{
			$szTransactionType = "SALE";
		}
		else if($paymentAction == Mage_Paygate_Model_Authorizenet::ACTION_AUTHORIZE)
		{
			$szTransactionType = "PREAUTH";
		}
		else 
		{
			Mage::throwException('Unknown payment action: '.$paymentAction);
		}
		
		$szTransactionDate = date("Ymd");
    	
    	$arAdditionalInformationArray["CrossReference"] = $szCrossReference;
    	$arAdditionalInformationArray["TransactionType"] = $szTransactionType;
    	$arAdditionalInformationArray["TransactionDateTime"] = $szTransactionDate;
    	
    	$payment->setAdditionalInformation($arAdditionalInformationArray);
    }
    
    
	 /**
     * Deduct the order items from the stock
     *
     * @param unknown_type $order
     */
    public function subtractOrderedItemsFromStock($order)
    {
    	$nVersion = Mage::getModel('Paymentsense/direct')->getVersion();

	    	$items = $order->getAllItems();
			foreach ($items as $itemId => $item)
			{
				// ordered quantity of the item from stock
				$quantity = $item->getQtyOrdered();
				$productId = $item->getProductId();
				
				$stock = Mage::getModel('cataloginventory/stock_item')->loadByProduct($productId);
				$stockManagement = $stock->getManageStock();
				
				if($stockManagement)
				{
					$stock->setQty($stock->getQty() - $quantity);
					$stock->save();
				}
			}
    }
	
    /**
     * Re-add the order items to the stock to balance the incorrect stock management before a payment is completed
     *
     * @param unknown_type $order
     */
    public function addOrderedItemsToStock($order)
    {

    	$nVersion = Mage::getModel('Paymentsense/direct')->getVersion();

	    	$items = $order->getAllItems();
			foreach ($items as $itemId => $item)
			{
				// ordered quantity of the item from stock
				$quantity = $item->getQtyOrdered();
				$productId = $item->getProductId();
				
				$stock = Mage::getModel('cataloginventory/stock_item')->loadByProduct($productId);
				$stockManagement = $stock->getManageStock();
				
				if($stockManagement)
				{
					$stock->setQty($stock->getQty() + $quantity);
					$stock->save();
				}
			}
 
    }
    
    /**
     * Override the refund function to run a CrossReference transaction
     *
     * @param Varien_Object $payment
     * @param unknown_type $amount
     * @return unknown
     */
    public function refund(Varien_Object $payment, $amount)
    {
	
		//Mage::throwException('This payment module does not support refunds. Please refund from within the Merchant Management System.');
		
        $error = false;
   		$szTransactionType = "REFUND";
   		$orderStatus = 'csv_refunded';
   		$szMessage = 'Payment refunded';
   		$arAdditionalInformationArray;
   		
        if($amount > 0)
        {
            $error = $this->_runCrossReferenceTransaction($payment, $szTransactionType, $amount);
        }
        else
        {
            $error = 'Error in refunding the payment';
        }
        
        if($error === false)
        {
        	$order = $payment->getOrder();
        	$payment = $order->getPayment();
        	$arAdditionalInformationArray = $payment->getAdditionalInformation();
        	
        	$arAdditionalInformationArray["Refunded"] = 1;
        	$payment->setAdditionalInformation($arAdditionalInformationArray);
        	$payment->save();
        	
        	$order->setState('canceled', $orderStatus, $szMessage, false);
        	$order->save();
        }
        else
        {
        	Mage::throwException($error);
        }
		
        return $this;
    }
    
    /**
     * Paymentsense VOID functionality
     * Note: if a transaction (payment) is once voided (canceled) it isn't possible to redo this action
     *
     * @param Varien_Object $payment
     * @return unknown
     */
    public function csvVoid(Varien_Object $payment)
    {
		//Mage::throwException('This payment module does not support voiding transactions. Please void transactions from within the Merchant Management System.');
		
        $error = false;
        $szTransactionType = "VOID";
        $orderStatus = "csv_voided";
        $arAdditionalInformationArray;
        
        // attempt a VOID and accordingly to the last saved transaction id (CrossReference) set the new message 
        $szLastTransId = $payment->getLastTransId();
        $transaction = $payment->getTransaction($szLastTransId);
        $szMagentoTxnType = $transaction->getTxnType();
        $szMessage = "Payment voided";
        
        if($szMagentoTxnType == "capture")
        {
        	$szMessage = "Payment voided";
        }
        else if($szMagentoTxnType == "authorization")
        {
        	$szMessage = "PreAuthorization voided";
        }
        else if($szMagentoTxnType == "refund")
        {
        	$szMessage = "Refund voided";
        }
        else 
        {
        	// general message
        	$szMessage = "Payment voided";
        }
        
        $error = $this->_runCrossReferenceTransaction($payment, $szTransactionType);

        if ($error === false)
        {
        	$order = $payment->getOrder();
        	$invoices = $order->getInvoiceCollection();
        	$payment = $order->getPayment();
        	$arAdditionalInformationArray = $payment->getAdditionalInformation();
        	
        	$arAdditionalInformationArray["Voided"] = 1;
        	$payment->setAdditionalInformation($arAdditionalInformationArray);
        	$payment->save();
        	
        	// cancel the invoices
        	foreach ($invoices as $invoice)
        	{
        		$invoice->cancel();
        		$invoice->save();
        	}
        	
        	// udpate the order
        	$order->setActionBy($payment->getLggdInAdminUname())
		        	->setActionDate(date('Y-m-d H:i:s'))
		            ->setVoided(1)
		            ->setState('canceled', $orderStatus, $szMessage, false);
			$order->save();
			
			$result = "0";
        }
        else
        {
       		$result = $error;
        }

        return $result;
    }
    
    /**
     * Paymentsense COLLECTION functionality (capture called in Magento)
     *
     * @param Varien_Object $payment
     * @param unknown_type $szOrderID
     * @param unknown_type $szCrossReference
     * @return unknown
     */
    public function csvCollection(Varien_Object $payment, $szOrderID, $szCrossReference)
    {
    	$szTransactionType = "COLLECTION";
    	$orderStatus = 'csv_collected';
    	$szMessage = 'Preauthorization successfully collected';
    	$state = Mage_Sales_Model_Order::STATE_PROCESSING;
    	$arAdditionalInformationArray;
    	
    	$error = $this->_captureAuthorizedPayment($payment);
    	
    	if($error === false)
    	{
    		$order = $payment->getOrder();
    		$invoices = $order->getInvoiceCollection();
    		$payment = $order->getPayment();
        	$arAdditionalInformationArray = $payment->getAdditionalInformation();
        	
        	$arAdditionalInformationArray["Collected"] = 1;
        	$payment->setAdditionalInformation($arAdditionalInformationArray);
        	$payment->save();
        	
    		// update the invoices to paid status
        	foreach ($invoices as $invoice)
        	{
        		$invoice->pay()->save();
        	}
        	
        	$order->setActionBy($payment->getLggdInAdminUname())
		        	->setActionDate(date('Y-m-d H:i:s'))
		            ->setState($state, $orderStatus, $szMessage, false);
			$order->save();
    		
    		$result = "0";
    	}
    	else
    	{
    		$result = $error;
    	}
    	
    	return $result;
    }
    
    /**
     * Private capture function for an authorized payment
     *
     * @param Varien_Object $payment
     * @return unknown
     */
    private function _captureAuthorizedPayment(Varien_Object $payment)
    {
	
    	$error = false;
    	$session = Mage::getSingleton('checkout/session');
    	
    	try
    	{
    		// set the COLLECTION variable to true
    		$session->setIsCollectionCrossReferenceTransaction(true);
    		
	    	$invoice = $payment->getOrder()->prepareInvoice();
	        $invoice->register();
	        
	        if ($this->_canCapture)
	        {
	            $invoice->capture();
	        }
	
	        $payment->getOrder()->addRelatedObject($invoice);
	    	$payment->setCreatedInvoice($invoice);
    	}
    	catch(Exception $exc)
    	{
    		$error = "Couldn't capture pre-authorized payment. Message: ". $exc->getMessage();
    		Mage::log($exc->getMessage());
    	}
    	
    	// remove the COLLECTION session variable once finished the COLLECTION attempt
    	$session->setIsCollectionCrossReferenceTransaction(null);
    	
    	return $error;
    }
    
    /**
     * Internal CrossReference function for all VOID, REFUND, COLLECTION transaction types
     *
     * @param Varien_Object $payment
     * @param unknown_type $szTransactionType
     * @param unknown_type $amount
     * @return unknown
     */
    private function _runCrossReferenceTransaction(Varien_Object $payment, $szTransactionType, $amount = false)
    {
		$takePaymentInStoreBaseCurrency = $this->getConfigData('takePaymentInStoreBaseCurrency');
		
    	$error = false;
    	$boTransactionProcessed = false;
    	$PaymentProcessorFullDomain;
    	$rgeplRequestGatewayEntryPointList;
    	$crtCrossReferenceTransaction;
    	$crtrCrossReferenceTransactionResult;
    	$todTransactionOutputData;
    	$szMerchantID = $this->getConfigData('merchantid');
		$szPassword = $this->getConfigData('password');
		//
		$iclISOCurrencyList = PS_CSV_ISOCurrencies::getISOCurrencyList();
		$szAmount;
		$nAmount;
		$szCurrencyShort;
		$iclISOCurrencyList;
    	$power;
    	$nDecimalAmount;
    	$szNewCrossReference;
    	
    	$order = $payment->getOrder();
    	$szOrderID = $order->getRealOrderId();;
    	//$szCrossReference = $payment->getLastTransId();
    	$additionalInformation = $payment->getAdditionalInformation();

    	$szCrossReference = $additionalInformation["CrossReference"];
    	$szCrossReference = $payment->getLastTransId();
    	
    	// check the CrossRference and TransactionType parameters
		if(!$szCrossReference)
		{
			$error = 'Error occurred for '.$szTransactionType.': Missing Cross Reference';
		}
		if(!$szTransactionType)
		{
			$error = 'Error occurred for '.$szTransactionType.': Missing Transaction Type';
		}
		
		if($error === false)
		{
						
			if (!$takePaymentInStoreBaseCurrency) {	

				// Take payment in order currency
				$szCurrencyShort = $order->getOrderCurrency()->getCurrencyCode();
				
				if ($szCurrencyShort != '' && $iclISOCurrencyList->getISOCurrency($szCurrencyShort, $icISOCurrency))
				{
					$nCurrencyCode = $icISOCurrency->getISOCode();
					
				}
			
				// Calculate amount
				$power = pow(10, $icISOCurrency->getExponent());
				$nAmount = round($order->getGrandTotal() * $power,0);
			} else {
				// Take payment in site base currency
				//$szCurrencyShort = $order->getOrderCurrency()->getCurrencyCode();
				$szCurrencyShort = $order->getBaseCurrencyCode();
				
				if ($szCurrencyShort != '' && $iclISOCurrencyList->getISOCurrency($szCurrencyShort, $icISOCurrency))
				{
					$nCurrencyCode = $icISOCurrency->getISOCode();
					
				}
				
					$nAmount = $this->_getRoundedAmount($amount, $icISOCurrency->getExponent());
			}

			$szCurrencyShort = $order->getOrderCurrency()->getCurrencyCode();
			if ($szCurrencyShort != '' && $iclISOCurrencyList->getISOCurrency($szCurrencyShort, $icISOCurrency))
			{
				$nCurrencyCode = new PS_CSV_NullableInt($icISOCurrency->getISOCode());
				
			}
			
			// round the amount before use
			//$nDecimalAmount = $this->_getRoundedAmount($nAmount, $icISOCurrency->getExponent());
				    	
			try
			{
				
				
				///////////////////////////////////////////////
				///             PHP 5.4 fix                 ///
				///////////////////////////////////////////////
				
				//set up the variables
				$CrossReference = $szCrossReference; //CrossReference of Original Transaction

				$Amount = $nAmount; //Amount to charge in this new transaction
				$OrderID = $szOrderID; //Order ID for this new transaction
				$OrderDescription = $szTransactionType . " for Order: " .$OrderID; //Order Description for this new transaction
				$ThreeDEnabled = "FALSE"; //False will turn off 3DS, it should be disabled for customer not present transactions (recurring payments).
				$IPAddress = $_SERVER['REMOTE_ADDR'];
				$TransactionType = $szTransactionType; //type of transaction
				$CurrencyCode = $nCurrencyCode; //currency type
				
				//XML Headers used in cURL - remember to change the function after thepaymentgateway.net in SOAPAction when changing the XML to call a different function
				$headers = array(
							'SOAPAction:https://www.thepaymentgateway.net/CrossReferenceTransaction',
							'Content-Type: text/xml; charset = utf-8',
							'Connection: close'
				);
				
				
				//XML to send to the Gateway
				$xml = '<?xml version="1.0" encoding="utf-8"?>
							<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
								<soap:Body>
									<CrossReferenceTransaction xmlns="https://www.thepaymentgateway.net/">
										<PaymentMessage>
											<MerchantAuthentication MerchantID="'. trim($szMerchantID) .'" Password="'. trim($szPassword) . '" />
											<TransactionDetails Amount="'. $Amount .'" CurrencyCode="'. $CurrencyCode .'">
												<MessageDetails TransactionType="'. $TransactionType .'" NewTransaction="FALSE" CrossReference="'. $CrossReference .'" />
												<OrderID>'. $OrderID .'</OrderID>
												<OrderDescription>'. $OrderDescription .'</OrderDescription>
												<TransactionControl>
													<EchoCardType>TRUE</EchoCardType>
													<EchoAVSCheckResult>TRUE</EchoAVSCheckResult>
													<EchoCV2CheckResult>TRUE</EchoCV2CheckResult>
													<EchoAmountReceived>TRUE</EchoAmountReceived>
													<DuplicateDelay>60</DuplicateDelay>
													<ThreeDSecureOverridePolicy>'. $ThreeDEnabled .'</ThreeDSecureOverridePolicy>
													<CustomVariables>
													<GenericVariable Name="MyInputVariable" Value="Ping" />
													</CustomVariables>
												</TransactionControl>
											</TransactionDetails>
											<CustomerDetails>
												<CustomerIPAddress>' . $IPAddress . '</CustomerIPAddress>
											</CustomerDetails>
											<PassOutData>Some data to be passed out</PassOutData>
										</PaymentMessage>
									</CrossReferenceTransaction>
								</soap:Body>
							</soap:Envelope>';
				
				
				$gwId = 1;
				$domain = "paymentsensegateway.com";
				$port = "4430";
				$transattempt = 1;
				$soapSuccess = false;
								
				
				//It will attempt each of the gateway servers (gw1, gw2 & gw3) 3 times each before totally failing
			while(!$soapSuccess && $gwId <= 3 && $transattempt <= 3) {	
				
				//builds the URL to post to (rather than it being hard coded - means we can loop through all 3 gateway servers)
				$url = 'https://gw'.$gwId.'.'.$domain.':'.$port.'/';
				
				//initialise cURL
				$curl = curl_init();
				
				//set the options
				curl_setopt($curl, CURLOPT_HEADER, false);
				curl_setopt($curl, CURLOPT_HTTPHEADER, $headers); 
				curl_setopt($curl, CURLOPT_POST, true);
				curl_setopt($curl, CURLOPT_URL, $url);
				curl_setopt($curl, CURLOPT_POSTFIELDS, $xml);
				curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($curl, CURLOPT_ENCODING, 'UTF-8');
				curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
				
				//Execute cURL request
				//$ret = returned XML
				$ret = curl_exec($curl);
				//$err = returned error number
				$err = curl_errno($curl);
				//retHead = returned XML header
				$retHead = curl_getinfo($curl);
				
				//close cURL connection
				curl_close($curl);
				$curl = null;
				
				//if no error returned
				if($err == 0) {
					//Get the status code
					$StatusCode = PS_GetXMLValue("StatusCode", $ret, "[0-9]+");
					
					if(is_numeric($StatusCode)) {
						//request was processed correctly
						if( $StatusCode != 50 ) {
							//set success flag so it will not run the request again.
							$soapSuccess = true;
							
							$szMessage = PS_GetXMLValue("Message", $ret, ".+");
							$szCrossReference = PS_GetCrossReference($ret);
							$szAddressNumericCheckResult = PS_GetXMLValue("AddressNumericCheckResult", $ret, ".+");
							$szPostCodeCheckResult = PS_GetXMLValue("PostCodeCheckResult", $ret, ".+");
							$szCV2CheckResult = PS_GetXMLValue("CV2CheckResult", $ret, ".+");
							$szThreeDSecureAuthenticationCheckResult = PS_GetXMLValue("ThreeDSecureAuthenticationCheckResult", $ret, ".+");
							
							switch ($StatusCode) {				
								case 0:
									$boTransactionProcessed = true;
									break;		
												
								case 4:
									$boTransactionProcessed = true;
									break;
								case 5:
									//Card declined
									$boTransactionProcessed = true;
									break;
								case 20:
									$boTransactionProcessed = true;
									break;
								default:
									$szMessageDetail = PS_GetXMLValue("Message", $ret, ".+");
									$boTransactionProcessed = true;
									break;
							}				
						}
					}
				}
				
				//increment the transaction attempt if <=2
				if($transattempt <=2) {
					$transattempt++;
				} else {
					//reset transaction attempt to 1 & increment $gwID (to use next numeric gateway number (eg. use gw2 rather than gw1 now))
					$transattempt = 1;
					$gwId++;
				}			
			}

				
				///////////////////////////////////////////////
				///            end PHP fix                  ///
				///////////////////////////////////////////////
			}
			catch (Exception $exc)
			{
				Mage::log("exception: ".$exc->getMessage());
			}
	    	
			if ($boTransactionProcessed == false)
			{
				// could not communicate with the payment gateway
				$error = "Couldn't complete ".$szTransactionType." transaction. Reason: Unable to communicate with payment gateway. Please check outgoing TCP port 4430 is open on your server.";
				$szLogMessage = $error;
			}
			else
			{
				switch($StatusCode)
				{
					case 0:
						$error = false;
						$szNewCrossReference = $szCrossReference;
						$szLogMessage = $szTransactionType . " CrossReference transaction successfully completed. Response object: ";
						
						$payment->setTransactionId($szNewCrossReference)
								->setParentTransactionId($CrossReference)
								->setIsTransactionClosed(1);
						$payment->save();
						break;
					default:
						$szLogMessage = $szMessageDetail;
						
					
						$error = "Couldn't complete ".$szTransactionType." transaction for CrossReference: " . $szCrossReference . ". Payment Response: ".$szLogMessage;
						$szLogMessage = $szTransactionType . " CrossReference transaction failed. Response object: ";
						break;
				}
				
				$szLogMessage = $szLogMessage.print_r($crtrCrossReferenceTransactionResult, 1);
			}
			
			Mage::log($szLogMessage);
		}
		
		return $error;
    }
}