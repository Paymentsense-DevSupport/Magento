<?php
class Paymentsense_Pay_Model_Pay extends Mage_Payment_Model_Method_Cc
{
	protected $_code                     = 'pay';
	protected $_formBlockType            = 'pay/form_pay';
	protected $_infoBlockType            = 'pay/info_pay';
	protected $_canAuthorize             = true;
	protected $_canCapture               = true;
	protected $_canCapturePartial        = true;
	protected $_canRefund                = true;
        protected $_canSaveCc                = false; //if made try, the actual credit card number and cvv code are stored in database.
        protected $_canVoid                  = true;
	protected $_canUseInternal           = false;
	protected $_canUseCheckout           = true;
	//protected $_canUseForMultishipping = true;
	protected $_canFetchTransactionInfo  = true;
	protected $_canReviewPayment         = true;


	public function process($data){

		if($data['cancel'] == 1){
		 $order->getPayment()
		 ->setTransactionId(null)
		 ->setParentTransactionId(time())
		 ->void();
		 $message = 'Unable to process Payment';
		 $order->registerCancellation($message)->save();
		}
	}
        
	/** For capture **/
	public function capture(Varien_Object $payment, $amount)
	{
		
		
		
		$order = $payment->getOrder();
		$paymentAction = $this->getConfigData('payment_action');
		if($paymentAction == Mage_Paygate_Model_Authorizenet::ACTION_AUTHORIZE_CAPTURE)
		{
			$szTransactionType = "SALE";
		}
		else if($paymentAction == Mage_Paygate_Model_Authorizenet::ACTION_AUTHORIZE)
		{
			$szTransactionType = "PREAUTH";
		}
                
		
		
		
		$result = $this->callApi($payment,$amount,$szTransactionType);
		
		
		
		$errorMsg = $result['message'];

		if($result === false) {
			$errorCode = 'Invalid Data';
			$errorMsg = $this->_getHelper()->__('Error Processing the request');
		}
		/*elseif($result == 0)
		{
			
			$arAdditionalInformationArray = $payment->getAdditionalInformation();
        	        $arAdditionalInformationArray["collect"] = 1;
		        $payment->setAdditionalInformation($arAdditionalInformationArray);
			$payment->setIsTransactionClosed(1);
			$order->save();
			//echo "die;";
		}*/
		else {
			 Mage::log($result, null, $this->getCode().'.log');
			//process result here to check status etc as per payment gateway.
			// if invalid status throw exception

			if($result['status'] == 1){
				
				/*
				 * This marks transactions as closed or open
				*/
				if($result['data']['PaReq'])
				{
				 
				 $payment->setIsTransactionPending(true);
				 $order->setState(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT,true);
				 $payment->setTransactionId($result['transaction_id']);
				


				}
				else{
					$payment->setTransactionId($result['transaction_id']);
					$payment->setIsTransactionClosed(1);
				
				}
                                
				$this->setPaymentAdditionalInformation($payment,$result['CrossReference'],$result['paymenstatus']);
				$order->addStatusToHistory('Pending Authorized', 'Payment Successfully Placed with Transaction ID'.$result['transaction_id'], false);
				$order->save();
				
			}else{
				Mage::throwException($errorMsg);
			}

			// Add the comment and save the order
		}
		if($errorMsg){
			Mage::throwException($errorMsg);
		}
              
         		return $this;
	}


	/** For authorization **/
	
	public function authorize(Varien_Object $payment, $amount)
	{
		
		$order = $payment->getOrder();
                $paymentAction = $this->getConfigData('payment_action');
                if($paymentAction == Mage_Paygate_Model_Authorizenet::ACTION_AUTHORIZE_CAPTURE)
		{
			$szTransactionType = "SALE";
		}
		else if($paymentAction == Mage_Paygate_Model_Authorizenet::ACTION_AUTHORIZE)
		{
			$szTransactionType = "PREAUTH";
		}
             
                $result = $this->callApi($payment,$amount,$szTransactionType);
                
                $errorMsg = $result['message'];
              
		if($result === false) {
			$errorCode = 'Invalid Data';
			$errorMsg = $this->_getHelper()->__('Error Processing the request');
		} else {
			Mage::log($result, null, $this->getCode().'.log');
			//process result here to check status etc as per payment gateway.
			// if invalid status throw exception

			if($result['status'] == 1){
				
				/*
				 * This marks transactions as closed or open
				*/
				if($result['data']['PaReq'])
				{
				 $payment->setIsTransactionPending(true);
				 $order->setState(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT,true);
				


				}
				else{
					$payment->setTransactionId($result['transaction_id']);
					$payment->setIsTransactionClosed(1);
				
				}
                                
				$this->setPaymentAdditionalInformation($payment,$result['CrossReference'],$result['paymenstatus']);
				$order->addStatusToHistory('Pending Authorized', 'Payment Successfully Placed with Transaction ID'.$result['transaction_id'], false);
				$order->save();
				
			}else{
				Mage::throwException($errorMsg);
			}

			// Add the comment and save the order
		}
		if($errorMsg){
			Mage::throwException($errorMsg);
		}

		return $this;
	
	}

	public function processBeforeRefund($invoice, $payment){
		return parent::processBeforeRefund($invoice, $payment);
	}
	
	
	public function refund(Varien_Object $payment, $amount){
		$order = $payment->getOrder();
		$result = $this->ProcessApi($payment,'REFUND');
		
		$statusCode  = $result['status'];
		$crossReference  = $result['crossReference'];
        if ($statusCode === 0)
        {
        	$order = $payment->getOrder();
        	$invoices = $order->getInvoiceCollection();
        	$payment = $order->getPayment();
        	$arAdditionalInformationArray = $payment->getAdditionalInformation();
        	
        	$arAdditionalInformationArray["Voided"] = 1;
		$arAdditionalInformationArray["CrossReference"] = $crossReference;
		$paymentMethod =   $arAdditionalInformationArray['PaymentMethod'];
        	$arAdditionalInformationArray["PaymentMethod"]  = $paymentMethod;
		//$this->setPaymentAdditionalInformation($payment,$crossReference);
		$payment->setAdditionalInformation($arAdditionalInformationArray);
		$payment->save();
        	
        	// cancel the invoices
        	foreach ($invoices as $invoice)
        	{
                    
        		$invoice->cancel();
        		$invoice->save();
        	}
        	
        	// udpate the order
        	        $order->cancel();
			$order->setstatus(Mage_Sales_Model_Order::STATE_CANCELED, true);
			$order->save();
			Mage::getSingleton('core/session')->addSuccess('Payment voided successfully .');
			//echo "<pre>";print_r($order->getData());die();
			$result = 0;
        }
        else
        {
       		$result = $result['message'];
        }

        return $result;

	}
	
	public function processCreditmemo($creditmemo, $payment){
		return parent::processCreditmemo($creditmemo, $payment);
	}

	private function callApi(Varien_Object $payment, $amount,$transcition_type){

	  
		//call your authorize api here, incase of error throw exception.
		//only example code written below to show flow of code

		
		$order = $payment->getOrder();		
		$types = Mage::getSingleton('payment/config')->getCcTypes();
		//echo '<pre>';print_r($order->getData());die('>>>');
		if (isset($types[$payment->getCcType()])) {
		$type = $types[$payment->getCcType()];
		}
		if(strlen($payment->getCcExpMonth()) == 1)
		{			 
			$card_expiredate = '0'.$payment->getCcExpMonth();
		}
		else{
			$card_expiredate = $payment->getCcExpMonth();
		}
		if(strlen($payment->getCcSsStartMonth()) == 1){
			
			$card_startdate = '0'.$payment->getCcSsStartMonth();
		}
		else{
			$card_startdate = $payment->getCcSsStartMonth();
		}
		$startyear = substr($payment->getCcSsStartYear(), -2);
		$expireyear = substr($payment->getCcExpYear(), -2);
		$paymentAction = $this->getConfigData('payment_action');
		
		
		$billingaddress = $order->getBillingAddress();
				 
		$countrycode = $this->getcountrycode($billingaddress->getData('country_id'));		
		//$totals = strval(($amount)*100); 
		$orderId = $order->getIncrementId();
		$currencyDesc = $order->getBaseCurrencyCode();
		
		$baseCurrency_code    = Mage::app()->getBaseCurrencyCode();
		$storeId              = Mage::app()->getStore()->getId();
		$SelectedCurrency     = Mage::app()->getStore($storeId)->getCurrentCurrencyCode(); 
		$module_currency_code = $this->getConfigData('payment_currency');
		$paymentSenseSelectedCurrency = explode(',',$module_currency_code);
		$curArray = array('USD', 'GBP', 'EUR');

		if(in_array($SelectedCurrency,$paymentSenseSelectedCurrency))
			{
			    $currcode = $SelectedCurrency;	
			}
		else if(in_array($baseCurrency_code,$paymentSenseSelectedCurrency))
			{
			    $currcode = $baseCurrency_code;
			}
			else if(in_array($baseCurrency_code, $curArray))
			{
				$currcode = $paymentSenseSelectedCurrency[0];
			}
		else
			{
			  return array('status'=>0,'transaction_id' => time() , 'fraud' => rand(0,1),'message'=>'Currency Error','data'=>'','CrossReference'=>'');
			}
		
		$arAdditionalInformationArray["PaymentCurrency"]   = $currcode;
    	        $payment->setAdditionalInformation($arAdditionalInformationArray);
		
		$allowedCurrencies = Mage::getModel('directory/currency')
		->getConfigAllowCurrencies();
		
		$currencyRates = Mage::getModel('directory/currency')
		->getCurrencyRates($baseCurrency_code, array_values($allowedCurrencies));
		
		//$baseCurrnecyrate  =  1/$currencyRates[Mage::app()->getStore()->getCurrentCurrencyCode()];
		 $grandTotal = $order->getData('base_grand_total');
		 $baseCurrnecyrate  =  $currencyRates[$currcode];
    
	
		if($currcode == 'EUR'){
		
				if($currcode = $baseCurrency_code && $baseCurrency_code !='')
				{
				//$newprice = number_format((float)($order->getGrandTotal()*$baseCurrnecyrate),'2', '.', '');
				$newprice = number_format((float)($grandTotal*$baseCurrnecyrate),'2', '.', '');
				
				}
				else
				{
				//$newprice = Mage::helper('directory')->currencyConvert($order->getGrandTotal(),'EUR',Mage::app()->getStore()->getCurrentCurrencyCode());
				$newprice = Mage::helper('directory')->currencyConvert($grandTotal,'EUR',Mage::app()->getStore()->getCurrentCurrencyCode());
				
				}
		               $currdes = '978';
		}
		elseif($currcode == 'GBP'){
		
			if($currcode = $baseCurrency_code && $baseCurrency_code !='')
			{
			//$newprice = number_format((float)($order->getGrandTotal()*$baseCurrnecyrate),'2', '.', '');
			$newprice = number_format((float)($grandTotal*$baseCurrnecyrate),'2', '.', '');
			
			}
			else
			{
			//$newprice = Mage::helper('directory')->currencyConvert($order->getGrandTotal(),Mage::app()->getStore()->getCurrentCurrencyCode(),'GBP');
			 $newprice = Mage::helper('directory')->currencyConvert($order->getGrandTotal(),Mage::app()->getStore()->getCurrentCurrencyCode(),'GBP');		
			
		   }
		   
			$currdes = '826';	
		
		   }
		else{
		
			if($currcode = $baseCurrency_code && $baseCurrency_code !='')
			{
			
			$newprice = number_format((float)($grandTotal*$baseCurrnecyrate),'2', '.', '');
			
			
			}
			else
			{
			
			$newprice = Mage::helper('directory')->currencyConvert($grandTotal,'USD',Mage::app()->getStore()->getCurrentCurrencyCode());
			}
			
			$currdes = '840';
		}
		

		$surcharge = $this->getConfigData('payment_surcharge');
		
				
		$credit_surcharge = $this->getConfigData('surcharge_debit');
		$newprice = round($newprice,2);
		$totals = strval(($newprice)*100);		
		$url = $this->getConfigData('gateway_url');
		$fields = array(
				'MerchantID'=> $this->getConfigData('api_username'),
				'MerchantPassword'=> $this->getConfigData('api_password'),
				'PhoneNumber'=>  $billingaddress->getData('telephone'),
				'EmailAddress'=> $billingaddress->getData('email'),
				'customer_ipaddress'=> $_SERVER['REMOTE_ADDR'],
				'Address1'=> $billingaddress->getStreet1(),
		        'Address2'=> $billingaddress->getStreet2(),
				'City'=>  $billingaddress->getData('city'),
				'CountryCode'=> $countrycode,
				'State'=> $billingaddress->getData('region'),
				'PostCode'=> $billingaddress->getData('postcode'),
				'CardName'=>$payment->getCcOwner(),
				'ExpiryDateMonth'=> $card_expiredate,
				'ExpiryDateYear'=> $expireyear,
				'CardNumber'=> $payment->getCcNumber(),
				'StartDateMonth'=> $card_startdate,
				'StartDateYear'=> $startyear,
				'IssueNumber'=>'',
				'TransactionType'=>$transcition_type,
// 				'AVSPolicy'=>'EFFP',
// 				'CV2Policy'=>'FP',
				//'customer_cc_type'=> strtoupper($type),
				'Description'=>'Order'.$order->getIncrementId(),
				'CV2'=> $payment->getCcCid(),
				'OrderID'=> $order->getIncrementId(),
				'currencydesc'=>$currdes,
				'Amount'=>$totals
		);
              // print_r($fields);die();

	
		$json = array();
		
		$headers = array(
			'SOAPAction:https://www.thepaymentgateway.net/CardDetailsTransaction',
			'Content-Type: text/xml; charset = utf-8',
			'Connection: close'
		);
		
		$xml = '<?xml version="1.0" encoding="utf-8"?>';
		$xml .= '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">';
		$xml .= '<soap:Body>';
		$xml .= '<CardDetailsTransaction xmlns="https://www.thepaymentgateway.net/">';
		$xml .= '<PaymentMessage>';
		$xml .= '<MerchantAuthentication MerchantID="'.$fields['MerchantID'].'" Password="'.$fields['MerchantPassword'].'" />';
		$xml .= '<TransactionDetails Amount="'.$fields['Amount'].'" CurrencyCode="'. $fields['currencydesc'] .'">';
		$xml .= '<MessageDetails TransactionType="'. $fields['TransactionType'] .'" />';
		$xml .= '<OrderID>'.$fields['OrderID'].'</OrderID>';
		$xml .= '<OrderDescription>'. $fields['Description'] .'</OrderDescription>';
		$xml .= '<TransactionControl>';
		$xml .= '<EchoCardType>TRUE</EchoCardType>';
		$xml .= '<EchoAVSCheckResult>TRUE</EchoAVSCheckResult>';
		$xml .= '<EchoCV2CheckResult>TRUE</EchoCV2CheckResult>';
		$xml .= '<EchoAmountReceived>TRUE</EchoAmountReceived>';
		$xml .= '<DuplicateDelay>20</DuplicateDelay>';
		//$xml .= '<AVSOverridePolicy>'. $fields['AVSPolicy'] .'</AVSOverridePolicy>';
		//$xml .= '<CV2OverridePolicy>'. $fields['CV2Policy'] .'</CV2OverridePolicy>';
		$xml .= '<CustomVariables>';
		$xml .= '<GenericVariable Name="MyInputVariable" Value="Ping" />';
		$xml .= '</CustomVariables>';
		$xml .= '</TransactionControl>';
		$xml .= '</TransactionDetails>';
		$xml .= '<CardDetails>';
		$xml .= '<CardName>'.$fields['CardName'].'</CardName>';
		$xml .= '<CardNumber>'.$fields['CardNumber'].'</CardNumber>';
		if ($fields['ExpiryDateMonth'] != "") $xml .= '<ExpiryDate Month="'.$fields['ExpiryDateMonth'].'" Year="'.$fields['ExpiryDateYear'].'" />';
		if ($fields['StartDateMonth'] != "") $xml .= '<StartDate Month="'.$fields['StartDateMonth'].'" Year="'.$fields['StartDateYear'].'" />';
		$xml .= '<CV2>'.$fields['CV2'].'</CV2>';
		if ($fields['IssueNumber'] != "") $xml .= '<IssueNumber>'.$fields['IssueNumber'].'</IssueNumber>';
		$xml .= '</CardDetails>';
		$xml .= '<CustomerDetails>';
		$xml .= '<BillingAddress>';
		$xml .= '<Address1>'.$fields['Address1'].'</Address1>';
		if (isset($fields['Address2']) && $fields['Address2'] != "") 
		{
		    $xml .= '<Address2>'.$fields['Address2'].'</Address2>';
		}
		if (isset($fields['Address3']) && $fields['Address3'] != "") $xml .= '<Address3>'.$fields['Address3'].'</Address3>';
		if (isset($fields['Address4']) && $fields['Address4'] != "") $xml .= '<Address4>'.$fields['Address4'].'</Address4>';
		$xml .= '<City>'.$fields['City'].'</City>';
		if ($fields['State'] != "") $xml .= '<State>'.$fields['State'].'</State>';
		$xml .= '<PostCode>'.$fields['PostCode'].'</PostCode>';
		$xml .= '<CountryCode>'. $fields['CountryCode'] .'</CountryCode>';
		$xml .= '</BillingAddress>';
		$xml .= '<EmailAddress>'.$fields['EmailAddress'].'</EmailAddress>';
		$xml .= '<PhoneNumber>'.$fields['PhoneNumber'].'</PhoneNumber>';
		//$xml .= '<CustomerIPAddress>'.$fields['CustomerIPAddress'].'</CustomerIPAddress>';
		$xml .= '</CustomerDetails>';
		$xml .= '<PassOutData>Some data to be passed out</PassOutData>';
		$xml .= '</PaymentMessage>';
		$xml .= '</CardDetailsTransaction>';
		$xml .= '</soap:Body>';
		$xml .= '</soap:Envelope>';
		
		
		$gwId = 1;
		$domain = "paymentsensegateway.com";
		$port = "4430";
		$transattempt = 1;
		$soapSuccess = false;
		
		
		
		while(!$soapSuccess && $gwId <= 3 && $transattempt <= 3) {		
						
			$url = 'https://gw'.$gwId.'.'.$domain.':'.$port.'/';
			
			
			
			//=================================================================================
			
			$curl = curl_init();

			curl_setopt($curl, CURLOPT_HEADER, false);
			curl_setopt($curl, CURLOPT_HTTPHEADER, $headers); 
			curl_setopt($curl, CURLOPT_POST, true);
			curl_setopt($curl, CURLOPT_URL, $url);
			curl_setopt($curl, CURLOPT_POSTFIELDS, $xml);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($curl, CURLOPT_ENCODING, 'UTF-8');
			curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
	                
			$caInfoSetting = ini_get("curl.cainfo");
			
			if(empty($caInfoSetting))
			{
				curl_setopt($curl, CURLOPT_CAINFO,Mage::getModuleDir('', 'Paymentsense') . DS . 'lib' . DS ."cacert.pem");
			}
	                
			$ret = curl_exec($curl);
			$err = curl_errno($curl);
			$retHead = curl_getinfo($curl);
			
			curl_close($curl);
			$curl = null;
			//echo "<pre>";print_r($ret);die;
			
			//$json['error'] .= "\r\rerr=". $err ."\r\r"."response=".$ret;
			
			if( $err == 0 ) {
				$StatusCode = null;
				$soapStatusCode = null;

				if( preg_match('#<StatusCode>([0-9]+)</StatusCode>#iU', $ret, $soapStatusCode) ) {
					$StatusCode = (int)$soapStatusCode[1];
					$AuthCode = null;
					$soapAuthCode = null;
					
					$CrossReference = null;
					$soapCrossReference = null;
					
					$Message = null;
					$soapMessage = null;
					if( preg_match('#<AuthCode>([a-zA-Z0-9]+)</AuthCode>#iU', $ret, $soapAuthCode) ) {
						$AuthCode = $soapAuthCode[1];
					}
					
					if( preg_match('#<TransactionOutputData.*CrossReference="([a-zA-Z0-9]+)".*>#iU', $ret, $soapCrossReference) ) {
						$CrossReference = $soapCrossReference[1];
					}
					
					if( preg_match('#<Message>(.+)</Message>#iU', $ret, $soapMessage) ) {
						$Message = $soapMessage[1];
					}
                                        if( $StatusCode != 3 )
					{
					Mage::getSingleton('core/session')->setJsonValue(''); 	
					}
					if( $StatusCode != 50 ) {
						$soapSuccess = true;
						switch( $StatusCode ) {
							case 0:
								$status = 1;
								$json['error']='';
								/*
								 *$this->model_checkout_order->confirm($this->session->data['order_id'], $this->config->get('config_order_status_id'));
								*/
								if( preg_match('#<AddressNumericCheckResult>(.+)</AddressNumericCheckResult>#iU', $ret, $soapAVSCheck) ) {
									$AVSCheck = $soapAVSCheck[1];
								}
								
								if( preg_match('#<PostCodeCheckResult>(.+)</PostCodeCheckResult>#iU', $ret, $soapPostCodeCheck) ) {
									$PostCodeCheck = $soapPostCodeCheck[1];
								}
								
								if( preg_match('#<CV2CheckResult>(.+)</CV2CheckResult>#iU', $ret, $soapCV2Check) ) {
									$CV2Check = $soapCV2Check[1];
								}
								
								$successmessage = 'AuthCode: ' . $AuthCode . " || " . 'CrossReference: ' . $CrossReference . " || " . 'AVS Check: ' . $AVSCheck . " || " . 'Postcode Check: ' . $PostCodeCheck . " || " . 'CV2 Check: ' . $CV2Check;					
								$json['error'] = '';
								//$this->model_checkout_order->update($this->session->data['order_id'], $this->config->get('paymentsense_direct_order_status_id'), $successmessage, false);
								//$json['success'] = $this->url->link('checkout/success', '', 'SSL');
								break;

							case 3:
								$status = 1;
								if( preg_match('#<ThreeDSecureOutputData>.*<PaREQ>(.+)</PaREQ>.*<ACSURL>(.+)</ACSURL>.*</ThreeDSecureOutputData>#iU', $ret, $soap3DSec) ) {
									$PaREQ = $soap3DSec[1];
									$ACSurl = $soap3DSec[2];
									
									$json['ACSURL'] = $ACSurl;
									$json['MD'] = $CrossReference;
									$json['PaReq'] = $PaREQ;
									$json['TermUrl'] = Mage::getUrl('pay/index/callback', array('_secure' => true));
									$json['error'] = '';
									Mage::getSingleton('core/session')->setJsonValue($json); 					//$this->secureAuthorisation($json);
									
									
									
								} else {
									$json['error'] = 'Incorrect 3DSecure data.';
									$do = false;
								}

								break;
							
							case 4:
								// Referred
								$json['error'] = 'Your card has been referred - please try a different card';
								$do = false;
								$status = 0;
								break;
							
							case 5:
								// Declined
								$json['error'] = 'Your card has been declined - ';
								$status = 0;
								if( preg_match('#<AddressNumericCheckResult>(.+)</AddressNumericCheckResult>#iU', $ret, $soapAVSCheck) ) {
									$AVSCheck = $soapAVSCheck[1];
								}
								
								$failedreasons = "";
								
								if ($AVSCheck == "FAILED") {
									if ($failedreasons <> "") {
										$failedreasons .= " + AVS";
									} else {
										$failedreasons = "Billing address";
									}
								}
								
								if( preg_match('#<PostCodeCheckResult>(.+)</PostCodeCheckResult>#iU', $ret, $soapPostCodeCheck) ) {
									$PostCodeCheck = $soapPostCodeCheck[1];
								}
								
								if ($PostCodeCheck == "FAILED") {
									if ($failedreasons <> "") {
										$failedreasons .= " + Postcode";
									} else {
										$failedreasons = "Postcode";
									}
								}
								
								if( preg_match('#<CV2CheckResult>(.+)</CV2CheckResult>#iU', $ret, $soapCV2Check) ) {
									$CV2Check = $soapCV2Check[1];
								}
								
								if ($CV2Check == "FAILED") {
									if ($failedreasons <> "") {
										$failedreasons .= " + CV2";
									} else {
										$failedreasons = "CV2";
									}
								}
								
								if ($failedreasons <> "") {
									$json['error'] .= $failedreasons . " checks failed. ";
								}
																
								$json['error'] .= 'Please check your billing address and card details and try again';
								$do = false;
								break;

							case 20:
								// Duplicate
								// check the previous status in order to know if the transaction was a success
								$status = 1;
								if( preg_match('#<PreviousTransactionResult>.*<StatusCode>([0-9]+)</StatusCode>#iU', $ret, $soapStatus2) ) {
									if( $soapStatus2[1] == '0' ) {
										//$this->model_checkout_order->confirm($this->session->data['order_id'], $this->config->get('config_order_status_id'));
										if( preg_match('#<AddressNumericCheckResult>(.+)</AddressNumericCheckResult>#iU', $ret, $soapAVSCheck) ) {
											$AVSCheck = $soapAVSCheck[1];
										}
										
										if( preg_match('#<PostCodeCheckResult>(.+)</PostCodeCheckResult>#iU', $ret, $soapPostCodeCheck) ) {
											$PostCodeCheck = $soapPostCodeCheck[1];
										}
										
										if( preg_match('#<CV2CheckResult>(.+)</CV2CheckResult>#iU', $ret, $soapCV2Check) ) {
											$CV2Check = $soapCV2Check[1];
										}
										
										$successmessage = 'AuthCode: ' . $AuthCode . " || " . 'CrossReference: ' . $CrossReference . " || " . 'AVS Check: ' . $AVSCheck . " || " . 'Postcode Check: ' . $PostCodeCheck . " || " . 'CV2 Check: ' . $CV2Check . ' || ' . '3D Secure: PASSED';					
				
										//$this->model_checkout_order->update($this->session->data['order_id'], $this->config->get('paymentsense_direct_order_status_id'), $successmessage, false);
										//$json['success'] = $this->url->link('checkout/success', '', 'SSL');
										$json['error']='';
										break;
									} else if( $soapStatus2[1] == '4' ) {
										$json['error'] = 'Your card has been referred - please try a different card';
										$do = false;
										break;
									} else if( $soapStatus2[1] == '5' ) {
										$json['error'] = 'Your card has been declined - ' . str_replace("Card declined: ","",$Message) . ' checks failed.\nPlease check your billing address and card details and try again';
										$do = false;
										break;
									} else {
										$json['error'] = 'Duplicate transaction';
										$do = false;
									}
								} else {
									$json['error'] = 'Duplicate transaction';
									$do = false;
								}
								break;

							case 30:
							default:
								$status = 0;
								// generic error
								// read error message
								if( preg_match('#<Message>(.*)</Message>#iU', $ret, $msg) ) {
									$msg = $msg[1];
								} else {
									$msg = '';
								}
								$json['error'] = 'PaymentSense Error ('.$StatusCode.') :' . $msg;
								$do = false;
								break;
						}
					}
				}
			}
			
			if($transattempt <=3) {
				$transattempt++;
			} else {
				$transattempt = 1;
				$gwId++;
			}			
		}
		
		
		
		
	return array('status'=>$status,'transaction_id' => time() , 'fraud' => rand(0,1),'message'=>$json['error'],'data'=>$json,'CrossReference'=>$CrossReference,'paymenstatus'=>$message);
	}
        
	
	
	
	
	public function getOrderPlaceRedirectUrl()
	{
		$json = Mage::getSingleton('core/session')->getJsonValue(); 
		if($this->_getOrderAmount() > 0){
			
			if(isset($json['ACSURL']) && $json['ACSURL']!='')
			{
				//echo Mage::getUrl('pay/index/secure', array('_secure' => true));
				//die;
			return Mage::getUrl('pay/index/secure', array('_secure' => true));	
			}
			else{
			return false;	
			}
			
		}else{
			return false;
		}
		
	}
	private function _getOrderAmount()
	{
		$info = $this->getInfoInstance();
		if ($this->_isPlacedOrder()) {
			return (double)$info->getOrder()->getQuoteBaseGrandTotal();
		} else {
			return (double)$info->getQuote()->getBaseGrandTotal();
		}
	}
	private function _isPlacedOrder()
	{
		$info = $this->getInfoInstance();
		if ($info instanceof Mage_Sales_Model_Quote_Payment) {
			return false;
		} elseif ($info instanceof Mage_Sales_Model_Order_Payment) {
			return true;
		}
	}
	

	public function getcountrycode($count){		
		$country_codes = array(
					'AF'=>'4',
					'AL'=>'8',
					'DZ'=>'12',
					'AS'=>'16',
					'AD'=>'20',
					'AO'=>'24',
					'AI'=>'660',
					'AQ'=>'',
					'AG'=>'28',
					'AR'=>'32',
					'AM'=>'51',
					'AW'=>'533',
					'AU'=>'36',
					'AT'=>'40',
					'AZ'=>'31',
					'BS'=>'44',
					'BH'=>'48',
					'BD'=>'50',
					'BB'=>'52',
					'BY'=>'112',
					'BE'=>'56',
					'BZ'=>'84',
					'BJ'=>'204',
					'BM'=>'60',
					'BT'=>'64',
					'BO'=>'68',
					'BA'=>'70',
					'BW'=>'72',
					'BR'=>'76',
					'BN'=>'96',
					'BG'=>'100',
					'BF'=>'854',
					'BI'=>'108',
					'KH'=>'116',
					'CM'=>'120',
					'CA'=>'124',
					'CV'=>'132',
					'KY'=>'136',
					'CF'=>'140',
					'TD'=>'148',
					'CL'=>'152',
					'CN'=>'156',
					'CO'=>'170',
					'KM'=>'174',
					'Congo'=>'178',
					'CG'=> '178',
					'CD'=> '178',
					'CK'=>'180',
					'CR'=>'184',
					'CI'=>'188',
					'HR'=>'384',
					'CU'=>'191',
					'CY'=>'192',
					'CZ'=>'196',
					'Democratic Republic of Congo'=>'203',
					'DK'=>'208',
					'DJ'=>'262',
					'DM'=>'212',
					'DO'=>'214',
					'EC'=>'218',
					'EG'=>'818',
					'SV'=>'222',
					'GQ'=>'226',
					'ER'=>'232',
					'EE'=>'233',
					'ET'=>'231',
					'FK'=>'238',
					'FO'=>'234',
					'FJ'=>'242',
					'FI'=>'246',
					'FR'=>'250',
					'GF'=>'254',
					'PF'=>'258',
					'TF'=>'',
					'GA'=>'266',
					'GM'=>'270',
					'GE'=>'268',
					'DE'=>'276',
					'GH'=>'288',
					'GI'=>'292',
					'GR'=>'300',
					'GL'=>'304',
					'GD'=>'308',
					'GP'=>'312',
					'GU'=>'316',
					'GT'=>'320',
					'GN'=>'324',
					'GW'=>'624',
					'GY'=>'328',
					'HT'=>'332',
					'HN'=>'340',
					'HK'=>'344',
					'HU'=>'348',
					'IS'=>'352',
					'IN'=>'356',
					'ID'=>'360',
					'IR'=>'364',
					'IQ'=>'368',
					'IE'=>'372',
					'IL'=>'376',
					'IT'=>'380',
					'JM'=>'388',
					'JP'=>'392',
					'JO'=>'400',
					'KZ'=>'398',
					'KE'=>'404',
					'KI'=>'296',
					'KP'=>'410',
					'KW'=>'414',
					'KG'=>'417',
					'Lao People\'s Democratic Republic'=>'418',
					'LA'=>'418',
					'LV'=>'428',
					'LB'=>'422',
					'LS'=>'426',
					'LR'=>'430',
					'Libyan Arab Jamahiriya'=>'434',
					'LY'=>'434',
					'LI'=>'438',
					'LT'=>'440',
					'LU'=>'442',
					'MO'=>'446',
					'MK'=>'807',
					'MG'=>'450',
					'MW'=>'454',
					'MY'=>'458',
					'MV'=>'462',
					'ML'=>'466',
					'MT'=>'470',
					'MH'=>'584',
					'MQ'=>'474',
					'MR'=>'478',
					'MU'=>'480',
					'MX'=>'484',
					'FM'=>'583',
					'MD'=>'498',
					'MC'=>'492',
					'MN'=>'496',
					'MS'=>'500',
					'MA'=>'504',
					'MZ'=>'508',
					'MM'=>'104',
					'NA'=>'516',
					'NR'=>'520',
					'NP'=>'524',
					'NL'=>'528',
					'AN'=>'530',
					'NC'=>'540',
					'NZ'=>'554',
					'NI'=>'558',
					'NE'=>'562',
					'NG'=>'566',
					'NU'=>'570',
					'NF'=>'574',
					'MP'=>'580',
					'NO'=>'578',
					'OM'=>'512',
					'PK'=>'586',
					'PW'=>'585',
					'PA'=>'591',
					'PG'=>'598',
					'PY'=>'600',
					'PE'=>'604',
					'PH'=>'608',
					'PN'=>'612',
					'PL'=>'616',
					'PT'=>'620',
					'PR'=>'630',
					'QA'=>'634',
					'RE'=>'638',
					'RO'=>'642',
					'RU'=>'643',
					'RW'=>'646',
					'KN'=>'659',
					'LC'=>'662',
					'VC'=>'670',
					'WS'=>'882',
					'SM'=>'674',
					'ST'=>'678',
					'SA'=>'682',
					'SN'=>'686',
					'SC'=>'690',
					'SL'=>'694',
					'SG'=>'702',
					'SK'=>'703',
					'SI'=>'705',
					'SB'=>'90',
					'SO'=>'706',
					'ZA'=>'710',
					'ES'=>'724',
					'LK'=>'144',
					'SD'=>'736',
					'SR'=>'740',
					'SJ'=>'744',
					'SZ'=>'748',
					'SE'=>'752',
					'CH'=>'756',
					'SY'=>'760',
					'TW'=>'158',
					'TJ'=>'762',
					'TZ'=>'834',
					'TH'=>'764',
					'TG'=>'768',
					'TK'=>'772',
					'TO'=>'776',
					'TT'=>'780',
					'TN'=>'788',
					'TR'=>'792',
					'TM'=>'795',
					'TC'=>'796',
					'TV'=>'798',
					'UG'=>'800',
					'UA'=>'804',
					'AE'=>'784',
					'GB'=>'826',
					'US'=>'840',
					'UY'=>'858',
					'UZ'=>'860',
					'VU'=>'548',
					'VA'=>'336',
					'VE'=>'862',
					'VN'=>'704',
					'Virgin Islands (British)'=>'92',
					'VG'=>'92',
					'Virgin Islands (U.S.)'=>'850',
					'VI'=>'850',
					'WF'=>'876',
					'EH'=>'732',
					'YE'=>'887',
					'ZM'=>'894',
					'ZW'=>'716'
		);
		foreach($country_codes as $c=>$v){
			if($count == $c){
				return $v;
			}	
			
		}
	}
	
	public function callback() {
		
		//$this->load->model('checkout/order');
		Mage::getSingleton('core/session')->setJsonValue('');
		$headers = array(
			'SOAPAction:https://www.thepaymentgateway.net/ThreeDSecureAuthentication',
			'Content-Type: text/xml; charset = utf-8',
			'Connection: close'
		);
		
		$MerchantID = $this->getConfigData('api_username');
		$Password = $this->getConfigData('api_password');
		$CrossReference =  $_POST['MD'];
		$PaRES =           $_POST['PaRes'];
		
		
		$xml = '<?xml version="1.0" encoding="utf-8"?>';
		$xml .= '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">';
		$xml .= '<soap:Body>';
		$xml .= '<ThreeDSecureAuthentication xmlns="https://www.thepaymentgateway.net/">';
		$xml .= '<ThreeDSecureMessage>';
		$xml .= '<MerchantAuthentication MerchantID="'. $MerchantID .'" Password="'. $Password .'" />';
		$xml .= '<ThreeDSecureInputData CrossReference="'. $CrossReference .'">';
		$xml .= '<PaRES>'. $PaRES .'</PaRES>';
		$xml .= '</ThreeDSecureInputData>';
		$xml .= '<PassOutData>Some data to be passed out</PassOutData>';
		$xml .= '</ThreeDSecureMessage>';
		$xml .= '</ThreeDSecureAuthentication>';
		$xml .= '</soap:Body>';
		$xml .= '</soap:Envelope>';
		
		$gwId = 1;
		$domain = "paymentsensegateway.com";
		$port = "4430";
		$transattempt = 1;
		$soapSuccess = false;
		

		while(!$soapSuccess && $gwId <= 3 && $transattempt <= 3) {		
		
			//$url = 'https://gw1.paymentsensegateway.com:4430/';
						
			$url = 'https://gw'.$gwId.'.'.$domain.':'.$port.'/';
			
			//=================================================================================
			
			$curl = curl_init();

			curl_setopt($curl, CURLOPT_HEADER, false);
			curl_setopt($curl, CURLOPT_HTTPHEADER, $headers); 
			curl_setopt($curl, CURLOPT_POST, true);
			curl_setopt($curl, CURLOPT_URL, $url);
			curl_setopt($curl, CURLOPT_POSTFIELDS, $xml);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($curl, CURLOPT_ENCODING, 'UTF-8');
			curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
	 
	                $caInfoSetting = ini_get("curl.cainfo");
			
			if(empty($caInfoSetting))
			{
				curl_setopt($curl, CURLOPT_CAINFO,Mage::getModuleDir('', 'Paymentsense') . DS . 'lib' . DS ."cacert.pem");
			}
			$ret = curl_exec($curl);
			$err = curl_errno($curl);
			$retHead = curl_getinfo($curl);
			
                       
			curl_close($curl);
			$curl = null;
			
			if( $err == 0 ) {
				$StatusCode = null;
				$soapStatusCode = null;

				if( preg_match('#<StatusCode>([0-9]+)</StatusCode>#iU', $ret, $soapStatusCode) ) {
					$StatusCode = (int)$soapStatusCode[1];
					$AuthCode = null;
					$soapAuthCode = null;
					
					$CrossReference = null;
					$soapCrossReference = null;
					
					$Message = null;
					$soapMessage = null;
					if( preg_match('#<AuthCode>([a-zA-Z0-9]+)</AuthCode>#iU', $ret, $soapAuthCode) ) {
						$AuthCode = $soapAuthCode[1];
					}
					
					if( preg_match('#<TransactionOutputData.*CrossReference="([a-zA-Z0-9]+)".*>#iU', $ret, $soapCrossReference) ) {
						$CrossReference = $soapCrossReference[1];
					}
					if( preg_match('#<Message>(.+)</Message>#iU', $ret, $soapMessage) ) {
						$Message = $soapMessage[1];
					}


					if( $StatusCode != 50 ) {
						$soapSuccess = true;
						switch( $StatusCode ) {
							case 0:
								//$this->model_checkout_order->confirm($this->session->data['order_id'], $this->config->get('config_order_status_id'));
								
								if( preg_match('#<AddressNumericCheckResult>(.+)</AddressNumericCheckResult>#iU', $ret, $soapAVSCheck) ) {
									$AVSCheck = $soapAVSCheck[1];
								}
								
								if( preg_match('#<PostCodeCheckResult>(.+)</PostCodeCheckResult>#iU', $ret, $soapPostCodeCheck) ) {
									$PostCodeCheck = $soapPostCodeCheck[1];
								}
								
								if( preg_match('#<CV2CheckResult>(.+)</CV2CheckResult>#iU', $ret, $soapCV2Check) ) {
									$CV2Check = $soapCV2Check[1];
								}
								
								$successmessage = 'AuthCode: ' . $AuthCode . " || " . 'CrossReference: ' . $CrossReference . " || " . 'AVS Check: ' . $AVSCheck . " || " . 'Postcode Check: ' . $PostCodeCheck . " || " . 'CV2 Check: ' . $CV2Check . ' || ' . '3D Secure: PASSED';					
								$json['error'] = '';
								
							        break;
													
							case 4:
								// Referred
								$json['error'] = 'Transaction Referred - Please try a different card';
								$do = false;
								break;
							
							case 5:
								// Declined
								$json['error'] = 'Transaction Declined';
								$do = false;
								break;
								
							case 20:
								// Duplicate
								// check the previous status in order to know if the transaction was a success
								
								if( preg_match('#<PreviousTransactionResult>.*<StatusCode>([0-9]+)</StatusCode>#iU', $ret, $soapStatus2) ) {
									if( $soapStatus2[1] == '0' ) {
										//$this->model_checkout_order->confirm($this->session->data['order_id'], $this->config->get('config_order_status_id'));
										
										if( preg_match('#<AddressNumericCheckResult>(.+)</AddressNumericCheckResult>#iU', $ret, $soapAVSCheck) ) {
											$AVSCheck = $soapAVSCheck[1];
										}
										
										if( preg_match('#<PostCodeCheckResult>(.+)</PostCodeCheckResult>#iU', $ret, $soapPostCodeCheck) ) {
											$PostCodeCheck = $soapPostCodeCheck[1];
										}
										
										if( preg_match('#<CV2CheckResult>(.+)</CV2CheckResult>#iU', $ret, $soapCV2Check) ) {
											$CV2Check = $soapCV2Check[1];
										}
										
										$successmessage = 'AuthCode: ' . $AuthCode . " || " . 'CrossReference: ' . $CrossReference . " || " . 'AVS Check: ' . $AVSCheck . " || " . 'Postcode Check: ' . $PostCodeCheck . " || " . 'CV2 Check: ' . $CV2Check . ' || ' . '3D Secure: PASSED';					
										$json['error'] = '';				
										break;
									} elseif( $soapStatus2[1] == '4' ) {
										$json['error'] = 'Transaction Referred - Please try a different card';
										$do = false;
										break;
									} elseif( $soapStatus2[1] == '5' ) {
										$json['error'] = 'Transaction Declined' . $Message;
										$do = false;
										break;
									} else {
										$json['error'] = 'Duplicate transaction';
										$do = false;
										break;
									}
								} else {
									$json['error'] = 'Duplicate transaction';
									$do = false;
								}
								break;						

							case 30:
							default:
								// generic error
								// read error message
								if( preg_match('#<Message>(.*)</Message>#iU', $ret, $msg) ) {
									$msg = $msg[1];
								} else {
									$msg = '';
								}
								$json['error'] = 'PaymentSense Error ('.$StatusCode.')';
								$do = false;
								break;
						}
					}
				}
			}
			
			if($transattempt <=3) {
				$transattempt++;
			} else {
				$transattempt = 1;
				$gwId++;
			}
			
			
			return array('status'=>$StatusCode,'transaction_id' => time() , 'fraud' => rand(0,1),'message'=>$json['error'],'data'=>$json,'CrossReference'=>$CrossReference,'paymentstatus'=>$Message);
		}
		
	}
	
	public function Void(Varien_Object $payment)
      {
	
        $error = false;
        $szTransactionType = "VOID";
        $orderStatus = "void";
        $arAdditionalInformationArray;
        $szMessage = "Payment voided";
        
       
       
        $result = $this->ProcessApi($payment,$szTransactionType);
	$crossReference  = $result['crossReference'];
	$statusCode  = $result['status'];
	
        if ($statusCode === 0)
        {
        	$order = $payment->getOrder();
        	$invoices = $order->getInvoiceCollection();
        	$payment = $order->getPayment();
		//$this->setPaymentAdditionalInformation($payment,$crossReference);
        	$arAdditionalInformationArray = $payment->getAdditionalInformation();
        	$paymentMethod =   $arAdditionalInformationArray['PaymentMethod'];
        	$arAdditionalInformationArray["PaymentMethod"]  = $paymentMethod;
		$arAdditionalInformationArray["Voided"] = 1;
		$arAdditionalInformationArray["CrossReference"] = $crossReference;
		$arAdditionalInformationArray["PaymentStatus"] = $result['paymenstatus'];
		$payment->setAdditionalInformation($arAdditionalInformationArray);
        	$payment->save();
        	
        	// cancel the invoices
        	foreach ($invoices as $invoice)
        	{
                    
        		$invoice->cancel();
        		$invoice->save();
        	}
        	
        	// udpate the order
        	        $order->cancel();
			$order->setstatus(Mage_Sales_Model_Order::STATE_CANCELED, true);
			$order->save();
			
			Mage::getSingleton('adminhtml/session')->addSuccess('Payment voided successfully .'); 
			//echo "<pre>";print_r($order->getData());die();
			$result = 0;
        }
        else
        {
       		$result = $result['message'];
        }

        return $result;
    }
	
                                                        
    private function ProcessApi(Varien_Object $payment,$transcition_type,$amount = false){

	  
		//call your authorize api here, incase of error throw exception.
		//only example code written below to show flow of code

		
		$order = $payment->getOrder();		
		$paymentAction = $this->getConfigData('payment_action');
		$totals = strval(($amount)*100); 
		$orderId = $order->getIncrementId();
		$currencyDesc = $order->getBaseCurrencyCode();
		$order = $payment->getOrder();
		$szOrderID = $order->getRealOrderId();
		//$szCrossReference = $payment->getLastTransId();
		$additionalInformation = $payment->getAdditionalInformation();
		
		$szCrossReference = $additionalInformation["CrossReference"];
		$szPaymentCurrency  = $additionalInformation["PaymentCurrency"];
		//$szCrossReference = $payment->getLastTransId();
		
		// check the CrossRference and TransactionType parameters
		if(!$szCrossReference)
		{
		$error = 'Error occurred for '.$szTransactionType.': Missing Cross Reference';
		}
		if(!$szTransactionType)
		{
		$error = 'Error occurred for '.$szTransactionType.': Missing Transaction Type';
		}
		
		/* Checking Payment method */
		
		$payment_method_code = $order->getPayment()->getMethodInstance()->getCode();
		if($payment_method_code=='payhosted')
		{
	    $MerchantPassword = Mage::getStoreConfig('payment/payhosted/api_password');
            $MerchantID       = trim(Mage::getStoreConfig('payment/payhosted/api_username'));
	     $module_currency_code = Mage::getStoreConfig('payment/payhosted/payment_currency');
			
		}
		elseif($payment_method_code=='pay')
		{
			$MerchantPassword = Mage::getStoreConfig('payment/pay/api_password');
            $MerchantID       = trim(Mage::getStoreConfig('payment/pay/api_username'));
	     $module_currency_code = Mage::getStoreConfig('payment/pay/payment_currency');
			
		}
		elseif($payment_method_code=='paymoto')
		{
			$MerchantPassword = Mage::getStoreConfig('payment/paymoto/api_password');
            $MerchantID       = trim(Mage::getStoreConfig('payment/paymoto/api_username'));
	     $module_currency_code = Mage::getStoreConfig('payment/paymoto/payment_currency');
			
		}
		
		// Set Currency and Rates 
		
		$baseCurrency_code    = Mage::app()->getBaseCurrencyCode();
		$storeId              = Mage::app()->getStore()->getId();
		$SelectedCurrency     = Mage::app()->getStore($storeId)->getCurrentCurrencyCode(); 
		//$currency_code        = Mage::app()->getStore()->getCurrentCurrencyCode();
	        $currcode = $order->getOrderCurrencyCode();
		$paymentSenseSelectedCurrency = explode(',',$module_currency_code);
		$curArray = array('USD', 'GBP', 'EUR');
		
		if($szPaymentCurrency==''){
		
				if(in_array($SelectedCurrency,$paymentSenseSelectedCurrency))
				{
				$currcode = $SelectedCurrency;	
				}
				else if(in_array($baseCurrency_code,$paymentSenseSelectedCurrency))
				{
				$currcode = $baseCurrency_code;
				}
				else if(in_array($baseCurrency_code, $curArray))
				{
				$currcode = $paymentSenseSelectedCurrency[0];
				}
				else
				{
				return array('status'=>0,'transaction_id' => time() , 'fraud' => rand(0,1),'message'=>'Currency Error','data'=>'','CrossReference'=>'');
				}
		
		}
		else
		{
			
			        $currcode = $szPaymentCurrency;
		}
		
		
		$grandTotal = $order->getData('base_grand_total');
		
		$allowedCurrencies = Mage::getModel('directory/currency')
		                     ->getConfigAllowCurrencies();
		
		$currencyRates =  Mage::getModel('directory/currency')
		                  ->getCurrencyRates($baseCurrency_code, array_values($allowedCurrencies));
		
		//$baseCurrnecyrate  =  1/$currencyRates[Mage::app()->getStore()->getCurrentCurrencyCode()];
		
                $baseCurrnecyrate  =  $currencyRates[$currcode];
		
	        if($currcode == 'EUR'){
		
				if($currcode = $baseCurrency_code && $baseCurrency_code !='')
				{
				//$newprice = number_format((float)($order->getGrandTotal()*$baseCurrnecyrate),'2', '.', '');
				$newprice = number_format((float)($grandTotal*$baseCurrnecyrate),'2', '.', '');
				
				}
				else
				{
				//$newprice = Mage::helper('directory')->currencyConvert($order->getGrandTotal(),'EUR',Mage::app()->getStore()->getCurrentCurrencyCode());
				$newprice = Mage::helper('directory')->currencyConvert($grandTotal,'EUR',Mage::app()->getStore()->getCurrentCurrencyCode());
				
				}
		               $currdes = '978';
		}
		elseif($currcode == 'GBP'){
		
			if($currcode = $baseCurrency_code && $baseCurrency_code !='')
			{
			//$newprice = number_format((float)($order->getGrandTotal()*$baseCurrnecyrate),'2', '.', '');
			$newprice = number_format((float)($grandTotal*$baseCurrnecyrate),'2', '.', '');
			
			}
			else
			{
			//$newprice = Mage::helper('directory')->currencyConvert($order->getGrandTotal(),Mage::app()->getStore()->getCurrentCurrencyCode(),'GBP');
			 $newprice = Mage::helper('directory')->currencyConvert($order->getGrandTotal(),Mage::app()->getStore()->getCurrentCurrencyCode(),'GBP');		
			
		   }
		   
			$currdes = '826';	
		
		   }
		else{
		
			if($currcode = $baseCurrency_code && $baseCurrency_code !='')
			{
			//$newprice = number_format((float)($order->getGrandTotal()*$baseCurrnecyrate),'2', '.', '');
			$newprice = number_format((float)($grandTotal*$baseCurrnecyrate),'2', '.', '');
			
			
			}
			else
			{
			//$newprice = Mage::helper('directory')->currencyConvert($order->getGrandTotal(),'USD',Mage::app()->getStore()->getCurrentCurrencyCode());
			$newprice = Mage::helper('directory')->currencyConvert($grandTotal,'USD',Mage::app()->getStore()->getCurrentCurrencyCode());
			}
			
			$currdes = '840';
		}
		
		$newprice = round($newprice,2);
	
	        //die;
                 if($amount === false)
			{
				$nAmount = ($newprice*100);
			}
			else
			{
				$nAmount = ($amount*100);
			}



		$url = $this->getConfigData('gateway_url');
		$fields = array(
				'MerchantID'=>$MerchantID,
				'MerchantPassword'=> $MerchantPassword,
				'TransactionType'=>$transcition_type,
				'OrderID'=> $szOrderID,
				'CrossReference'=>$szCrossReference,
				'CurrencyCode'=>$currdes,
				'Amount'=>$nAmount
		);
		
		
		
		
		$headers = array(
			'SOAPAction:https://www.thepaymentgateway.net/CrossReferenceTransaction',
			'Content-Type: text/xml; charset = utf-8',
			'Connection: close'
		);
		
		$xml  = '<?xml version="1.0" encoding="utf-8" ?>';
		$xml .= '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">';
		$xml .= '<soap:Body>';
		$xml .= '<CrossReferenceTransaction xmlns="https://www.thepaymentgateway.net/">';
		$xml .= '<PaymentMessage>';
		$xml .= '<TransactionDetails Amount="'.$fields['Amount'].'" CurrencyCode="'.$fields['CurrencyCode'].'">';
		$xml .= '<MessageDetails TransactionType="'.$fields['TransactionType'].'" CrossReference="'.$fields['CrossReference'].'" />';
		$xml .= '<OrderID>'.$fields['OrderID'].'</OrderID>';
		$xml .= '</TransactionDetails>';
		$xml .= '<MerchantAuthentication MerchantID="'.$fields['MerchantID'].'" Password="'.$fields['MerchantPassword'].'" />';
		$xml .= '</PaymentMessage>';
		$xml .= ' </CrossReferenceTransaction>';
		$xml .= '</soap:Body>';
		$xml .= '</soap:Envelope>';
		
	
		
		
		$gwId = 1;
		$domain = "paymentsensegateway.com";
		$port = "4430";
		$transattempt = 1;
		$soapSuccess = false;
		
		
		while(!$soapSuccess && $gwId <= 3 && $transattempt <= 3) {		
						
			 $url = 'https://gw'.$gwId.'.'.$domain.':'.$port.'/';
			
			//$url = 'https://gw1.paymentsensegateway.com:4430/';
			
			//=================================================================================
			
			$curl = curl_init();
			
			curl_setopt($curl, CURLOPT_HEADER, false);
			curl_setopt($curl, CURLOPT_HTTPHEADER, $headers); 
			curl_setopt($curl, CURLOPT_POST, true);
			curl_setopt($curl, CURLOPT_URL, $url);
			curl_setopt($curl, CURLOPT_POSTFIELDS, $xml);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($curl, CURLOPT_ENCODING, 'UTF-8');
			curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
	                
			$caInfoSetting = ini_get("curl.cainfo");
			
			if(empty($caInfoSetting))
			{
				curl_setopt($curl, CURLOPT_CAINFO,Mage::getModuleDir('', 'Paymentsense') . DS . 'lib' . DS ."cacert.pem");
			}
			
			$ret = curl_exec($curl);
			$err = curl_errno($curl);
			$retHead = curl_getinfo($curl);
			
			curl_close($curl);
			$curl = null;
			
			

			if( $err == 0 ) {
				$StatusCode = null;
				$soapStatusCode = null;
				
				preg_match('/<Message>(.*)<\/Message>/',$ret,$message);
				
				$message = $message[1];
				$json['error'] = $message;
				if( preg_match('#<AuthCode>([a-zA-Z0-9]+)</AuthCode>#iU', $ret, $soapAuthCode) ) {
				       $AuthCode = $soapAuthCode[1];
				}
				$json['authCode'] = $AuthCode;	
				if( preg_match('#<TransactionOutputData.*CrossReference="([a-zA-Z0-9]+)".*>#iU', $ret, $soapCrossReference) ) {
				        $CrossReference = $soapCrossReference[1];
				}
				$json['crossReference'] = $CrossReference;
				
				if(preg_match('#<StatusCode>([0-9]+)</StatusCode>#iU', $ret, $soapStatusCode) ) {
					$StatusCode  = (int)$soapStatusCode[1];
					$Message = null;
					$soapMessage = null;
					
					if( $StatusCode != 50 ) {
						$soapSuccess = true;
						switch( $StatusCode ) {
							
							case 0:
								
								$successmessage = 'AuthCode: ' . $AuthCode . " || " . 'CrossReference: ' . $CrossReference . " || " . 'AVS Check: ' . $AVSCheck . " || " . 'Postcode Check: ' . $PostCodeCheck . " || " . 'CV2 Check: ' . $CV2Check . ' || ' . '3D Secure: PASSED';					
								$json['error'] = '';
								break;
													
							case 30:
								// Referred
								$json['error'] = $message;
								
								break;
							default:
							$json['error'] = $message;
							
						}
						
						
					}
					
					
				}
			}
				
					
					
			
			if($transattempt <=3) {
				$transattempt++;
			} else {
				$transattempt = 1;
				$gwId++;
			}
			return array('status'=>$StatusCode,'fraud' => rand(0,1),'message'=>$json['error'],'crossReference'=>$json['crossReference'],'paymenstatus'=>$message);
			
			
		}
		
		
		
		
	//return array('status'=>$status,'transaction_id' => time() , 'fraud' => rand(0,1),'message'=>$json['error'],'data'=>$json);
	}
	
	
    public function setPaymentAdditionalInformation($payment, $szCrossReference,$szmessage)
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
		
    	$arAdditionalInformationArray = $payment->getAdditionalInformation();
	$arrPrevinfo  = $payment->getAdditionalInformation();
    	$arAdditionalInformationArray["CrossReference"] = $szCrossReference;
    	$arAdditionalInformationArray["TransactionType"] = $szTransactionType;
    	$arAdditionalInformationArray["TransactionDateTime"] = $szTransactionDate;
    	$arAdditionalInformationArray["PaymentMethod"]   = 'PaymentSense Direct';
	$arAdditionalInformationArray["PaymentCurrency"]   = $arrPrevinfo['PaymentCurrency'];
	$arAdditionalInformationArray["PaymentStatus"]     = $szmessage;
    	$payment->setAdditionalInformation($arAdditionalInformationArray);
	return;
    }
    
    public function payCollection(Varien_Object $payment, $szOrderID, $szCrossReference)
    {
    	$szTransactionType = "COLLECTION";
    	$orderStatus = 'pys_collected';
    	$szMessage = 'Preauthorization successfully collected';
    	$state = Mage_Sales_Model_Order::STATE_PROCESSING;
    	$arAdditionalInformationArray;
    	
    	$result          = $this->ProcessApi($payment,$szTransactionType);;
    	$statusCode      = $result['status'];
	$crossReference  = $result['crossReference'];
	
	
    	if($statusCode === 0)
    	{
    		$order = $payment->getOrder();
    		$invoices = $order->getInvoiceCollection();
        	$payment = $order->getPayment();
		
        	$arAdditionalInformationArray = $payment->getAdditionalInformation();
        	
        	$arAdditionalInformationArray["Collected"] = 1;
		$arAdditionalInformationArray["CrossReference"] = $crossReference;
		$paymentMethod =   $arAdditionalInformationArray['PaymentMethod'];
        	$arAdditionalInformationArray["PaymentMethod"]  = $paymentMethod;
		$arAdditionalInformationArray["PaymentStatus"] = $result['paymenstatus'];
        	$payment->setAdditionalInformation($arAdditionalInformationArray);
		//$this->setPaymentAdditionalInformation($payment,$crossReference);
        	$payment->save();
        	
    		// update the invoices to paid status
        	foreach ($invoices as $invoice)
        	{
        		$invoice->pay()->save();
        	}
        	
        	$order->setActionBy($payment->getLggdInAdminUname())
		        	->setActionDate(date('Y-m-d H:i:s'));
		           // ->setState($state, $orderStatus, $szMessage, false);
			$order->save();
    		
		
		Mage::getSingleton('adminhtml/session')->addSuccess('Authorized payment collected successfully.'); 
    		$result = "0";
    	}
    	else
    	{
    		$result = $result['message'];
    	}
    	
    	return $result;
    }
    
    
    
    
}
?>
