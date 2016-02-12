<?php
class Paymentsense_Payhosted_IndexController extends Mage_Core_Controller_Front_Action
{
	
	        private $m_nStatusCode;
		private $m_szMessage;
		private $m_nPreviousStatusCode;
		private $m_szPreviousMessage;
		private $m_szCrossReference;
		private $m_nAmount;
		private $m_nCurrencyCode;
		private $m_szOrderID;
		private $m_szTransactionType;
		private $m_szTransactionDateTime;
		private $m_szOrderDescription;
		private $m_szCustomerName;
		private $m_szAddress1;
		private $m_szAddress2;
		private $m_szAddress3;
		private $m_szAddress4;
		private $m_szCity;
		private $m_szState;
		private $m_szPostCode;
		private $m_nCountryCode;

	
	
	public function indexAction()
	{

		
		$this->loadLayout();
		$this->renderLayout();
	}

	public function successAction()
	{
		
		$session                     = Mage::getSingleton('customer/session');
		$ResultDeliveryMethod        = "SERVER_PULL";
		$PaymentProcessorDomain      = "paymentsensegateway.com";
		$PreSharedKey                = Mage::getStoreConfig('payment/payhosted/api_key');
                $Password                    = Mage::getStoreConfig('payment/payhosted/api_password');
                $MerchantID                  = trim(Mage::getStoreConfig('payment/payhosted/api_username'));
		$HashMethod                  = "SHA1";
		$model                       = Mage::getModel('payhosted/payhosted');	
		$szPaymentFormResultHandler  = "https://mms.".$PaymentProcessorDomain."/Pages/PublicPages/PaymentFormResultHandler.ashx";
		$boResultValidationSuccessful = $this->validateTransactionResult_SERVER_PULL($MerchantID,  $Password, $PreSharedKey, $HashMethod,$_GET,$szPaymentFormResultHandler,$trTransactionResult,$szValidateErrorMessage);
		$IncOrderID = $_GET["OrderID"];
		Mage::log($IncOrderID);
		$order = Mage::getModel('sales/order')->loadByIncrementId($IncOrderID);
		$payment = $order->getPayment();
	if (!$boResultValidationSuccessful)
	{
		$MessageClass = "ErrorMessage";
		$Message = $szValidateErrorMessage;
	}
	else
	{
	     $statusCode = $this->getStatusCode();
	}  
		
		if (!$session->getCustomerId()) {
		Mage::getSingleton('customer/session')->addError('You are not logged in');
		}
	    try{
			if($statusCode == 4){
				Mage::getSingleton('checkout/session')->addError(Mage::helper('checkout')->__("Card referred"));
				$comment = $order->addStatusHistoryComment('Payment Status : card referred')
				->setIsCustomerNotified(false)
				->save();
				$model->setPaymentAdditionalInformation($payment,$this->getCrossReference(),$this->getTransactionType(),$this->getMessage());
				$order->save();
				$this->_forward('error');
			}
			elseif($statusCode == 5){
			        Mage::getSingleton('checkout/session')->addError(Mage::helper('checkout')->__("Transaction declined : ".$this->getMessage()));
				$comment = $order->addStatusHistoryComment('Payment Status : transaction declined')
				->setIsCustomerNotified(false)
				->save();
				$model->setPaymentAdditionalInformation($payment,$this->getCrossReference(),$this->getTransactionType(),$this->getMessage());
				$order->save();
				$this->_forward('error');
				
				}elseif($statusCode == 30){
			        Mage::getSingleton('checkout/session')->addError(Mage::helper('checkout')->__("Paymentsense input variable error"));
				$comment = $order->addStatusHistoryComment('Payment Status : Paymentsense error')
				->setIsCustomerNotified(false)
				->save();
				$model->setPaymentAdditionalInformation($payment,$this->getCrossReference(),$this->getTransactionType(),$this->getMessage());
				$order->save();
				$this->_forward('error');
				
				}elseif($statusCode == 20){
					
				if($this->getPreviousStatusCode() == 0)
				{
			          
					$comment = $order->sendNewOrderEmail()->addStatusHistoryComment('Payment Status : transaction authorised')
					->setIsCustomerNotified(false)
					->save();
					$payment = $order->getPayment();
					$grandTotal = $order->getBaseGrandTotal();
					$invoice = Mage::getModel('sales/service_order', $order)->prepareInvoice();
					if(isset($request['Transactionid'])){
						//$tid = $request['Transactionid'];
						$tid = $invoice->getIncrementId();
					}
					else {
						$tid = $invoice->getIncrementId();
					}
					
					$payment->setTransactionId($tid)
					->setPreparedMessage("Payment successful Result:")
					->setIsTransactionClosed(0)
					->registerAuthorizationNotification($grandTotal);
					$order->save();


				
					try {
						if(!$order->canInvoice())
						{
							Mage::throwException(Mage::helper('core')->__('Cannot create an invoice.'));
						}
	
						$invoice = Mage::getModel('sales/service_order', $order)->prepareInvoice();
	
						if (!$invoice->getTotalQty()) {
							Mage::throwException(Mage::helper('core')->__('Cannot create an invoice without products.'));
						}
	
						$invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_ONLINE);
						
						//$invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE);
						$invoice->register();
						$transactionSave = Mage::getModel('core/resource_transaction')
						->addObject($invoice)
						->addObject($invoice->getOrder());
	
						$transactionSave->save();
						$message = Mage::helper('pay')->__('Notified customer about invoice #%s.', $invoice->getIncrementId());
						$comment = $order->sendNewOrderEmail()->addStatusHistoryComment($message)
						->setIsCustomerNotified(true)
						->save();
					 }
				       catch (Mage_Core_Exception $e) {

				    }
				
					$url = Mage::getUrl('checkout/onepage/success', array('_secure'=>true));
					Mage::register('redirect_url',$url);
					$this->_redirectUrl($url);

				
				  
				}
				else
				{
					Mage::getSingleton('checkout/session')->addError(Mage::helper('checkout')->__("Duplicate transaction"));
					$comment = $order->sendNewOrderEmail()->addStatusHistoryComment('Payment Status : duplicate transaction')
					->setIsCustomerNotified(false)
					->save();
					$model->setPaymentAdditionalInformation($payment,$this->getCrossReference(),$this->getTransactionType(),$this->getMessage());
				        $order->save();
					$this->_forward('error');
				}
				
			}
			elseif($statusCode == 0){
				
				$comment = $order->sendNewOrderEmail()->addStatusHistoryComment('Payment Status : transaction authorised')
				->setIsCustomerNotified(false)
				->save();
				$payment = $order->getPayment();
				$grandTotal = $order->getBaseGrandTotal();
				if(isset($request['Transactionid'])){
					$tid = $request['Transactionid'];
				}
				else {
					$tid = -1 ;
				}
				$model->setPaymentAdditionalInformation($payment,$this->getCrossReference(),$this->getTransactionType());
				$payment->setTransactionId($tid)
				->setPreparedMessage("Payment Sucessfull Result:")
				->setIsTransactionClosed(1);
				$order->setState(Mage_Sales_Model_Order::STATE_PROCESSING);
			        $order->setStatus('processing');
				$order->save();


				try {
					if(!$order->canInvoice())
					{
						Mage::throwException(Mage::helper('core')->__('Cannot create an invoice.'));
					}

					$invoice = Mage::getModel('sales/service_order', $order)->prepareInvoice();

					if (!$invoice->getTotalQty()) {
						Mage::throwException(Mage::helper('core')->__('Cannot create an invoice without products.'));
					}

					$invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_ONLINE);
					//Or you can use
					//$invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE);
					$invoice->register();
					$transactionSave = Mage::getModel('core/resource_transaction')
					->addObject($invoice)
					->addObject($invoice->getOrder());

					$transactionSave->save();
					$message = Mage::helper('pay')->__('Notified customer about invoice #%s.', $invoice->getIncrementId());
					$comment = $order->sendNewOrderEmail()->addStatusHistoryComment($message)
					->setIsCustomerNotified(true)
					->save();
				}
				catch (Mage_Core_Exception $e) {

				}
				//Mage::getSingleton('checkout/session')->getQuote()->setIsActive(false)->save();
				//$order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, true)->save();
				
				$url = Mage::getUrl('checkout/onepage/success', array('_secure'=>true));
				Mage::register('redirect_url',$url);
				$this->_redirectUrl($url);

				
			
			//$url = Mage::getUrl('checkout/onepage/success', array('_secure'=>true));
			//Mage::register('redirect_url',$url);
			//$this->_redirectUrl($url);
		       }
	        }
		catch(Exception $e)
		{
			Mage::logException($e);
		}
		
	 
	
	}

	protected function _getCheckout()
	{
		return Mage::getSingleton('checkout/session');
	}

	public function errorAction()
	{
		
		
		
		$request = $_REQUEST;
		Mage::log($request, null, 'lps.log');
		$gotoSection = false;
		$session = $this->_getCheckout();
		if ($session->getLastRealOrderId()) {
			$order = Mage::getModel('sales/order')->loadByIncrementId($session->getLastRealOrderId());
			if ($order->getId()) {
				//Cancel order
				if ($order->getState() != Mage_Sales_Model_Order::STATE_CANCELED) {
					$order->registerCancellation($errorMsg)->save();
				}
				$quote = Mage::getModel('sales/quote')
				->load($order->getQuoteId());
				//Return quote
				if ($quote->getId()) {
					$quote->setIsActive(1)
					->setReservedOrderId(NULL)
					->save();
					$session->replaceQuote($quote);
				}

				//Unset data
				$session->unsLastRealOrderId();
				//Redirect to payment step
				$gotoSection = 'payment';
				$url = Mage::getUrl('checkout/cart/index', array('_secure'=>true));
				Mage::register('redirect_url',$url);
				$this->_redirectUrl($url);
			}
		}

		return $gotoSection;
	}
	
	public  function addStringToStringList($szExistingStringList, $szStringToAdd)
		{
			$szReturnString = "";
			$szCommaString = "";

			if (strlen($szStringToAdd) == 0)
			{
				$szReturnString = $szExistingStringList;
			}
			else
			{
				if (strlen($szExistingStringList) != 0)
				{
					$szCommaString = ", ";
				}
				$szReturnString = $szExistingStringList.$szCommaString.$szStringToAdd;
			}

			return ($szReturnString);
		}
		
	public static function generateStringToHash3($szMerchantID,$szPassword, $szCrossReference, $szOrderID, $szPreSharedKey, $szHashMethod)
	{
			$szReturnString = "";

			switch ($szHashMethod)
			{
				case "MD5":
					$boIncludePreSharedKeyInString = true;
					break;
				case "SHA1":
					$boIncludePreSharedKeyInString = true;
					break;
				case "HMACMD5":
					$boIncludePreSharedKeyInString = false;
					break;
				case "HMACSHA1":
					$boIncludePreSharedKeyInString = false;
					break;
			}

			if ($boIncludePreSharedKeyInString)
			{
				$szReturnString = "PreSharedKey=".$szPreSharedKey."&";
			}

			$szReturnString = $szReturnString."MerchantID=".$szMerchantID. "&Password=".$szPassword."&CrossReference=".$szCrossReference."&OrderID=".$szOrderID;

                        return ($szReturnString);
        }
		
		public  function validateTransactionResult_SERVER_PULL($szMerchantID,$szPassword,$szPreSharedKey,$szHashMethod,$aQueryStringVariables,$szPaymentFormResultHandlerURL,&$trTransactionResult,&$szValidateErrorMessage)
			
		{
			$boErrorOccurred = false;
			
			$szValidateErrorMessage = "";
			$trTransactionResult = null;
			
			// read the transaction reference variables from the query string variable list
				if (!$this->getTransactionReferenceFromQueryString($aQueryStringVariables, $szCrossReference, $szOrderID, $szHashDigest, $szOutputMessage))
				{
					$boErrorOccurred = true;
					$szValidateErrorMessage = $szOutputMessage;
				}
				else
				{
				// now need to validate the hash digest
				$szStringToHash = $this->generateStringToHash3($szMerchantID, $szPassword, $szCrossReference, $szOrderID, $szPreSharedKey, $szHashMethod);
				$szCalculatedHashDigest = $this->calculateHashDigest($szStringToHash, $szPreSharedKey, $szHashMethod);
				
				// does the calculated hash match the one that was passed?
					if (strToUpper($szHashDigest) != strToUpper($szCalculatedHashDigest))
					{
						$boErrorOccurred = true;
						$szValidateErrorMessage = "Hash digests don't match - possible variable tampering";
					}
					else
					{
					// use the cross reference and/or the order ID to pull the
					// transaction results out of storage
				
						if (!$this->getTransactionResultFromPaymentFormHandler($szPaymentFormResultHandlerURL,
						$szMerchantID, 
						$szPassword,
						$szCrossReference,
						$trTransactionResult,
						$szOutputMessage))
							{
							$szValidateErrorMessage = "Error querying transaction result [".$szCrossReference."] from [".$szPaymentFormResultHandlerURL."]: ".$szOutputMessage;
							$boErrorOccurred = true;
							}
							else
							{
							$boErrorOccurred = false;
							}
					}
				}
			
		        return (!$boErrorOccurred);
		}
		
		
		public  function getTransactionReferenceFromQueryString($aQueryStringVariables, &$szCrossReference, &$szOrderID, &$szHashDigest, &$szOutputMessage)
		{
			$trTransactionResult = null;
			$szHashDigest = "";
			$szOutputMessage = "";
			$boErrorOccurred = false;			

			try
			{
				// hash digest
				if (isset($aQueryStringVariables["HashDigest"]))
				{
					$szHashDigest = $aQueryStringVariables["HashDigest"];
				}

				// cross reference of transaction
				if (!isset($aQueryStringVariables["CrossReference"]))
				{
					$szOutputMessage = $this->addStringToStringList($szOutputMessage, "Expected variable [CrossReference] not received");
					$boErrorOccurred = true;
				}
				else
				{
					$szCrossReference = $aQueryStringVariables["CrossReference"];
				}
				// order ID (same as value passed into payment form - echoed back out by payment form)
				if (!isset($aQueryStringVariables["OrderID"]))
				{
					$szOutputMessage = $this->addStringToStringList($szOutputMessage, "Expected variable [OrderID] not received");
					$boErrorOccurred = true;
				}
				else
				{
					$szOrderID = $aQueryStringVariables["OrderID"];
				}
			}
			catch (Exception $e)
			{
				$boErrorOccurred = true;
				$szOutputMessage = $e->getMessage();
			}

			return (!$boErrorOccurred);
		}
		public  function calculateHashDigest($szInputString, $szPreSharedKey, $szHashMethod)
		{
			switch ($szHashMethod)
			{
				case "MD5":
				$hashDigest = md5($szInputString);
				break;
				case "SHA1":
				$hashDigest = sha1($szInputString);
				break;
				case "HMACMD5":
				$hashDigest = hash_hmac("md5", $szInputString, $szPreSharedKey);
				break;
				case "HMACSHA1":
				$hashDigest = hash_hmac("sha1", $szInputString, $szPreSharedKey);
				break;
			}
		
		return ($hashDigest);
		}
	
	 public  function getTransactionResultFromPaymentFormHandler($szPaymentFormResultHandlerURL,$szMerchantID,$szPassword,$szCrossReference,&$trTransactionResult,&$szOutputMessage)
		{
			$boErrorOccurred = false;
			$szOutputMessage = "";
			$trTransactionResult = null;
                        $model = Mage::getModel('payhosted/payhosted');
			try
			{
				// use curl to post the cross reference to the
				// payment form to query its status
				$cCURL = curl_init();
				
				// build up the post string
			 	$szPostString = "MerchantID=".urlencode($szMerchantID)."&Password=".urlencode($szPassword)."&CrossReference=".urlencode($szCrossReference);

				curl_setopt($cCURL, CURLOPT_URL, $szPaymentFormResultHandlerURL);
				curl_setopt($cCURL, CURLOPT_POST, true);
				curl_setopt($cCURL, CURLOPT_POSTFIELDS, $szPostString);
				curl_setopt($cCURL, CURLOPT_SSL_VERIFYPEER, false);
				curl_setopt($cCURL, CURLOPT_RETURNTRANSFER, true);

				// read the response
				$szResponse   = curl_exec($cCURL);
				$szErrorNo    = curl_errno($cCURL);
				$ezErrorMsg   = curl_error($cCURL);
				$szHeaderInfo = curl_getinfo($cCURL);

				curl_close($cCURL);
				
				if ($szResponse == "")
				{
					$boErrorOccurred = true;
					$szOutputMessage = "Received empty response from payment form hander";
				}
				else
				{
					try
					{
						/* start Here */
						
						// parse the response into an array
						$aParsedPostVariables = $model->parseNameValueStringIntoArray($szResponse, true);
                                                
						if (!isset($aParsedPostVariables["StatusCode"]) OR
							intval($aParsedPostVariables["StatusCode"]) != 0)
						{
							$boErrorOccurred = true;

							// the message field is expected if the status code is non-zero
							if (!isset($aParsedPostVariables["Message"]))
							{
								$szOutputMessage = "Received invalid response from payment form hander [".$szResponse."]";
							}
							else
							{
								$szOutputMessage = $aParsedPostVariables["Message"];
							}
						}
						else
						{
							// status code is 0, so	get the transaction result
							if (!isset($aParsedPostVariables["TransactionResult"]))
							{
								$boErrorOccurred = true;
								$szOutputMessage = "No transaction result in response from payment form hander [".$szResponse."]";
							}
							else
							{
								// parse the URL decoded transaction result field into a name value array
								$aTransactionResultArray = $model->parseNameValueStringIntoArray(urldecode($aParsedPostVariables["TransactionResult"]), false);
                                                               
								// parse this array into a transaction result object
								
								if (!$this->getTransactionResultFromPostVariables($aTransactionResultArray, $trTransactionResult, $szHashDigest, $szErrorMessage))
								{
									$boErrorOccurred = true;
									$szOutputMessage = "Error [".$szErrorMessage."] parsing transaction result [".urldecode($aParsedPostVariables["TransactionResult"])."] in response from payment form hander [".$szResponse."]";
								}
								else
								{
									$boErrorOccurred = false;
								}
								
							}
						}
					}
					catch (Exception $e)
					{
						$boErrorOccurred = true;
						$szOutputMessage = "Exception [".$e->getMessage()."] when processing response from payment form handler [".$szResponse."]";
					}
				}
			}
			catch (Exception $e)
			{
				$boErrorOccurred = true;
				$szOutputMessage = $e->getMessage();
			}

			return (!$boErrorOccurred);
		}

                
		public static function getTransactionResultFromStorage($szCrossReference,$szOrderID,&$trTransactionResult,&$szOutputMessage)
		{
			$boErrorOccurred = true;
			$szOutputMessage = "Environment specific function getTransactionResultFromStorage() needs to be implemented by merchant developer";
			$trTransactionResult = null;

			return (!$boErrorOccurred);
		}
		
		
		
		  function getTransactionResultFromPostVariables($aFormVariables, &$trTransactionResult, &$szHashDigest, &$szOutputMessage)
		{
			$trTransactionResult = null;
			$szHashDigest = "";
			$szOutputMessage = "";			
			$boErrorOccurred = false;

			try
			{
				// hash digest
				if (isset($aFormVariables["HashDigest"]))
				{
					$szHashDigest = $aFormVariables["HashDigest"];
				}

				// transaction status code
				if (!isset($aFormVariables["StatusCode"]))
				{
					$szOutputMessage = $this->addStringToStringList($szOutputMessage, "Expected variable [StatusCode] not received");
					$boErrorOccurred = true;
				}
				else
				{
					if ($aFormVariables["StatusCode"] == "")
					{
						$nStatusCode = null;
					}
					else
					{
						$nStatusCode = intval($aFormVariables["StatusCode"]);
					}
				}
				// transaction message
				if (!isset($aFormVariables["Message"]))
				{
					$szOutputMessage = $this->addStringToStringList($szOutputMessage, "Expected variable [Message] not received");
					$boErrorOccurred = true;
				}
				else
				{
					$szMessage = $aFormVariables["Message"];

				}
				// status code of original transaction if this transaction was deemed a duplicate
				if (!isset($aFormVariables["PreviousStatusCode"]))
				{
					$szOutputMessage = $this->addStringToStringList($szOutputMessage, "Expected variable [PreviousStatusCode] not received");
					$boErrorOccurred = true;
				}
				else
				{
					if ($aFormVariables["PreviousStatusCode"] == "")
					{
						$nPreviousStatusCode = null;
					}
					else
					{
						$nPreviousStatusCode = intval($aFormVariables["PreviousStatusCode"]);
					}
				}
				// status code of original transaction if this transaction was deemed a duplicate
				if (!isset($aFormVariables["PreviousMessage"]))
				{
					$szOutputMessage = $this->addStringToStringList($szOutputMessage, "Expected variable [PreviousMessage] not received");
					$boErrorOccurred = true;
				}
				else
				{
					$szPreviousMessage = $aFormVariables["PreviousMessage"];
				}
				// cross reference of transaction
				if (!isset($aFormVariables["CrossReference"]))
				{
					$szOutputMessage = $this->addStringToStringList($szOutputMessage, "Expected variable [CrossReference] not received");
					$boErrorOccurred = true;
				}
				else
				{
					$szCrossReference = $aFormVariables["CrossReference"];
				}
				// amount (same as value passed into payment form - echoed back out by payment form)
				if (!isset($aFormVariables["Amount"]))
				{
					$szOutputMessage = $this->addStringToStringList($szOutputMessage, "Expected variable [Amount] not received");
					$boErrorOccurred = true;
				}
				else
				{
					if ($aFormVariables["Amount"] == null)
					{
						$nAmount = null;
					}
					else
					{
						$nAmount = intval($aFormVariables["Amount"]);
					}
				}
				// currency code (same as value passed into payment form - echoed back out by payment form)
				if (!isset($aFormVariables["CurrencyCode"]))
				{
					$szOutputMessage = $this->addStringToStringList($szOutputMessage, "Expected variable [CurrencyCode] not received");
					$boErrorOccurred = true;
				}
				else
				{
					if ($aFormVariables["CurrencyCode"] == null)
					{
						$nCurrencyCode = null;
					}
					else
					{
						$nCurrencyCode = intval($aFormVariables["CurrencyCode"]);
					}
				}
				// order ID (same as value passed into payment form - echoed back out by payment form)
				if (!isset($aFormVariables["OrderID"]))
				{
					$szOutputMessage = $this->addStringToStringList($szOutputMessage, "Expected variable [OrderID] not received");
					$boErrorOccurred = true;
				}
				else
				{
					$szOrderID = $aFormVariables["OrderID"];
				}
				// transaction type (same as value passed into payment form - echoed back out by payment form)
				if (!isset($aFormVariables["TransactionType"]))
				{
					$szOutputMessage = $this->addStringToStringList($szOutputMessage, "Expected variable [TransactionType] not received");
					$boErrorOccurred = true;
				}
				else
				{
					$szTransactionType = $aFormVariables["TransactionType"];
				}
				// transaction date/time (same as value passed into payment form - echoed back out by payment form)
				if (!isset($aFormVariables["TransactionDateTime"]))
				{
					$szOutputMessage = $this->addStringToStringList($szOutputMessage, "Expected variable [TransactionDateTime] not received");
					$boErrorOccurred = true;
				}
				else
				{
					$szTransactionDateTime = $aFormVariables["TransactionDateTime"];
				}
				// order description (same as value passed into payment form - echoed back out by payment form)
				if (!isset($aFormVariables["OrderDescription"]))
				{
					$szOutputMessage = $this->addStringToStringList($szOutputMessage, "Expected variable [OrderDescription] not received");
					$boErrorOccurred = true;
				}
				else
				{
					$szOrderDescription = $aFormVariables["OrderDescription"];
				}
				// customer name (not necessarily the same as value passed into payment form - as the customer can change it on the form)
				if (!isset($aFormVariables["CustomerName"]))
				{
					$szOutputMessage = $this->addStringToStringList($szOutputMessage, "Expected variable [CustomerName] not received");
					$boErrorOccurred = true;
				}
				else
				{
					$szCustomerName = $aFormVariables["CustomerName"];
				}
				// address1 (not necessarily the same as value passed into payment form - as the customer can change it on the form)
				if (!isset($aFormVariables["Address1"]))
				{
					$szOutputMessage = $this->addStringToStringList($szOutputMessage, "Expected variable [Address1] not received");
					$boErrorOccurred = true;
				}
				else
				{
					$szAddress1 = $aFormVariables["Address1"];
				}
				// address2 (not necessarily the same as value passed into payment form - as the customer can change it on the form)
				if (!isset($aFormVariables["Address2"]))
				{
					$szOutputMessage = $this->addStringToStringList($szOutputMessage, "Expected variable [Address2] not received");
					$boErrorOccurred = true;
				}
				else
				{
					$szAddress2 = $aFormVariables["Address2"];
				}
				// address3 (not necessarily the same as value passed into payment form - as the customer can change it on the form)
				if (!isset($aFormVariables["Address3"]))
				{
					$szOutputMessage = $this->addStringToStringList($szOutputMessage, "Expected variable [Address3] not received");
					$boErrorOccurred = true;
				}
				else
				{
					$szAddress3 = $aFormVariables["Address3"];
				}
				// address4 (not necessarily the same as value passed into payment form - as the customer can change it on the form)
				if (!isset($aFormVariables["Address4"]))
				{
					$szOutputMessage = $this->addStringToStringList($szOutputMessage, "Expected variable [Address4] not received");
					$boErrorOccurred = true;
				}
				else
				{
					$szAddress4 = $aFormVariables["Address4"];
				}
				// city (not necessarily the same as value passed into payment form - as the customer can change it on the form)
				if (!isset($aFormVariables["City"]))
				{
					$szOutputMessage = $this->addStringToStringList($szOutputMessage, "Expected variable [City] not received");
					$boErrorOccurred = true;
				}
				else
				{
					$szCity = $aFormVariables["City"];
				}
				// state (not necessarily the same as value passed into payment form - as the customer can change it on the form)
				if (!isset($aFormVariables["State"]))
				{
					$szOutputMessage = $this->addStringToStringList($szOutputMessage, "Expected variable [State] not received");
					$boErrorOccurred = true;
				}
				else
				{
					$szState = $aFormVariables["State"];
				}
				// post code (not necessarily the same as value passed into payment form - as the customer can change it on the form)
				if (!isset($aFormVariables["PostCode"]))
				{
					$szOutputMessage = $this->addStringToStringList($szOutputMessage, "Expected variable [PostCode] not received");
					$boErrorOccurred = true;
				}
				else
				{
					$szPostCode = $aFormVariables["PostCode"];
				}
				// country code (not necessarily the same as value passed into payment form - as the customer can change it on the form)
				if (!isset($aFormVariables["CountryCode"]))
				{
					$szOutputMessage = $this->addStringToStringList($szOutputMessage, "Expected variable [CountryCode] not received");
					$boErrorOccurred = true;
				}
				else
				{
					if ($aFormVariables["CountryCode"] == "")
					{
						$nCountryCode = null;
					}
					else
					{
						$nCountryCode = intval($aFormVariables["CountryCode"]);
					}
				}

				if (!$boErrorOccurred)
				{
					//$trTransactionResult = new TransactionResult();
					$this->setStatusCode($nStatusCode); // transaction status code
					$this->setMessage($szMessage); // transaction message
					$this->setPreviousStatusCode($nPreviousStatusCode); // status code of original transaction if duplicate transaction
					$this->setPreviousMessage($szPreviousMessage); // status code of original transaction if duplicate transaction
					$this->setCrossReference($szCrossReference);	// cross reference of transaction
					$this->setAmount($nAmount); // amount echoed back
					$this->setCurrencyCode($nCurrencyCode); // currency code echoed back
					$this->setOrderID($szOrderID); // order ID echoed back
					$this->setTransactionType($szTransactionType); // transaction type echoed back
					$this->setTransactionDateTime($szTransactionDateTime); // transaction date/time echoed back
					$this->setOrderDescription($szOrderDescription); // order description echoed back
					// the customer details that were actually
					// processed (might be different
					// from those passed to the payment form)
					$this->setCustomerName($szCustomerName);
					$this->setAddress1($szAddress1);
					$this->setAddress2($szAddress2);
					$this->setAddress3($szAddress3);
					$this->setAddress4($szAddress4);
					$this->setCity($szCity);
					$this->setState($szState);
					$this->setPostCode($szPostCode);
					$this->setCountryCode($nCountryCode);
				}
			}
			catch (Exception $e)
			{
				$boErrorOccurred = true;
				$szOutputMessage = $e->getMessage();
			}

			return (!$boErrorOccurred);
		}
		
		
		public function getStatusCode() 
		{ 
			return $this->m_nStatusCode; 
		}
		
		public function setStatusCode($nStatusCode) 
		{ 
			$this->m_nStatusCode = $nStatusCode; 
		}
		
		public function getMessage() 
		{ 
			return $this->m_szMessage; 
		}
		
		public function setMessage($szMessage)
		{
			$this->m_szMessage = $szMessage;
		}
		
		public function getPreviousStatusCode()
		{
			return $this->m_nPreviousStatusCode;
		}
		
		public function setPreviousStatusCode($nPreviousStatusCode)
		{
			$this->m_nPreviousStatusCode = $nPreviousStatusCode;
		}
		
		public function getPreviousMessage()
		{
			return $this->m_szPreviousMessage;
		}
		
		public function setPreviousMessage($szPreviousMessage)
		{
			$this->m_szPreviousMessage = $szPreviousMessage;
		}
		
		public function getCrossReference()
		{
			return $this->m_szCrossReference;
		}
		
		public function setCrossReference($szCrossReference)
		{
			$this->m_szCrossReference = $szCrossReference;
		}
		
		public function getAmount()
		{
			return $this->m_nAmount;
		}
		
		public function setAmount($nAmount)
		{
			$this->m_nAmount = $nAmount;
		}
		
		public function getCurrencyCode()
		{
			return $this->m_nCurrencyCode;
		}
		public function setCurrencyCode($nCurrencyCode)
		{
			$this->m_nCurrencyCode = $nCurrencyCode;
		}
		
		public function getOrderID()
		{
			return $this->m_szOrderID;
		}
		
		public function setOrderID($szOrderID)
		{
			$this->m_szOrderID = $szOrderID;
		}
		
		public function getTransactionType()
		{
			return $this->m_szTransactionType;
		}
		
		public function setTransactionType($szTransactionType)
		{
			$this->m_szTransactionType = $szTransactionType;
		}
		public function getTransactionDateTime()
		{
			return $this->m_szTransactionDateTime;
		}
		public function setTransactionDateTime($szTransactionDateTime)
		{
			$this->m_szTransactionDateTime = $szTransactionDateTime;
		}
		public function getOrderDescription()
		{
			return $this->m_szOrderDescription;
		}
		public function setOrderDescription($szOrderDescription)
		{
			$this->m_szOrderDescription = $szOrderDescription;
		}
		public function getCustomerName()
		{
			return $this->m_szCustomerName;
		}
		public function setCustomerName($szCustomerName)
		{
			$this->m_szCustomerName = $szCustomerName;
		}
		public function getAddress1()
		{
			return $this->m_szAddress1;
		}
		public function setAddress1($szAddress1)
		{
			$this->m_szAddress1 = $szAddress1;
		}
		public function getAddress2()
		{
			return $this->m_szAddress2;
		}
		public function setAddress2($szAddress2)
		{
			$this->m_szAddress2 = $szAddress2;
		}
		public function getAddress3()
		{
			return $this->m_szAddress3;
		}
		public function setAddress3($szAddress3)
		{
			$this->m_szAddress3 = $szAddress3;
		}
		public function getAddress4()
		{
			return $this->m_szAddress4;
		}
		public function setAddress4($szAddress4)
		{
			$this->m_szAddress4 = $szAddress4;
		}
		public function getCity()
		{
			return $this->m_szCity;
		}
		public function setCity($szCity)
		{
			$this->m_szCity = $szCity;
		}
		public function getState()
		{
			return $this->m_szState;
		}
		public function setState($szState)
		{
			$this->m_szState = $szState;
		}
		public function getPostCode()
		{
			return $this->m_szPostCode;
		}
		public function setPostCode($szPostCode)
		{
			$this->m_szPostCode = $szPostCode;
		}
		public function getCountryCode()
		{
			return $this->m_nCountryCode;
		}
		public function setCountryCode($nCountryCode)
		{
			$this->m_nCountryCode = $nCountryCode;
		}
		
}
