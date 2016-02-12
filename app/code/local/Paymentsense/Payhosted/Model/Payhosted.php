<?php
class Paymentsense_Payhosted_Model_Payhosted extends Mage_Payment_Model_Method_Abstract
{

	protected $_code = 'payhosted';
	protected $_formBlockType = 'payhosted/form_payhosted';
	protected $_infoBlockType = 'payhosted/info_payhosted';
	protected $_isInitializeNeeded      = true;

	//protected $_isGateway             = true;
	protected $_canAuthorize            = false;
	protected $_canCapture              = false;
	//protected $_canCapturePartial     = true;
	protected $_canRefund               = false;


	protected $_canSaveCc = false; //if made try, the actual credit card number and cvv code are stored in database.

	//protected $_canRefundInvoicePartial = true;
	//protected $_canVoid                 = true;
	protected $_canUseInternal          = false;
	


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
	
	public function initialize($paymentAction, $stateObject)
	{
		
		
			$payment = $this->getInfoInstance();
			$order = $payment->getOrder();
			$order->setCanSendNewEmailFlag(false);
			$payment->setAmountAuthorized($order->getTotalDue());
			$payment->setBaseAmountAuthorized($order->getBaseTotalDue());
			
			//$this->_setPaymentFormUrl($payment);
			
			$stateObject->setState(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT);
			$stateObject->setStatus('pending_payment');
			$stateObject->setIsNotified(false);
		
	}

	/** For capture **/
	public function capture(Varien_Object $payment, $amount)
	{
		
		$order = $payment->getOrder();
		$result = $this->callApi($payment,$amount,'authorize');
		if($result === false) {
			$errorCode = 'Invalid Data';
			$errorMsg = $this->_getHelper()->__('Error Processing the request');
		} else {
			Mage::log($result, null, $this->getCode().'.log');
			//process result here to check status etc as per payment gateway.
			// if invalid status throw exception

			if($result['status'] == 1){
				$payment->setTransactionId($result['transaction_id']);
				$payment->setIsTransactionClosed(1);
				$payment->setTransactionAdditionalInfo(Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS,array('key1'=>'value1','key2'=>'value2'));
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
		$result = $this->callApi($payment,$amount,'authorize');
		if($result === false) {
			$errorCode = 'Invalid Data';
			$errorMsg = $this->_getHelper()->__('Error Processing the request');
		} else {
			Mage::log($result, null, $this->getCode().'.log');
			//process result here to check status etc as per payment gateway.
			// if invalid status throw exception

			if($result['status'] == 1){
				$payment->setTransactionId($result['transaction_id']);
				/*
				 * This marks transactions as closed or open
				*/
				$payment->setIsTransactionClosed(1);
				/*
				 * This basically makes order status to be payment review and no invoice is created.
				* and adds a default comment like
				* Authorizing amount of $17.00 is pending approval on gateway. Transaction ID: "1335419269".
				*
				*/
				

				/*
				 * This method is used to display extra informatoin on transaction page
				*/
				$payment->setTransactionAdditionalInfo(Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS,array('key1'=>'value1','key2'=>'value2'));


				$order->addStatusToHistory($order->getStatus(), 'Payment Sucessfully Placed with Transaction ID'.$result['transaction_id'], false);
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
		$result = $this->callApi($payment,$amount,'refund');
		if($result === false) {
			$errorCode = 'Invalid Data';
			$errorMsg = $this->_getHelper()->__('Error Processing the request');
			Mage::throwException($errorMsg);
		}
		return $this;

	}
	public function processCreditmemo($creditmemo, $payment){
		return parent::processCreditmemo($creditmemo, $payment);
	}

	

	
	public function getOrderPlaceRedirectUrl()
	{
		
		if($this->_getOrderAmount() > 0){
			return Mage::getUrl('payhosted/index/index', array('_secure' => true));
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
	
	public  function parseNameValueStringIntoArray($szNameValueString, $boURLDecodeValues)
		{
			// break the reponse into an array
			// first break the variables up using the "&" delimter
			$aPostVariables = explode("&", $szNameValueString);

			$aParsedVariables = array();

			foreach ($aPostVariables as $szVariable)
			{
				// for each variable, split is again on the "=" delimiter
				// to give name/value pairs
				$aSingleVariable = explode("=", $szVariable);
				$szName = $aSingleVariable[0];
				if (!$boURLDecodeValues)
				{
					$szValue = $aSingleVariable[1];
				}
				else
				{
					$szValue = urldecode($aSingleVariable[1]);
				}

				$aParsedVariables[$szName] = $szValue;
			}

			return ($aParsedVariables);
		}
		
	 public function setPaymentAdditionalInformation($payment, $szCrossReference,$szTransactionType,$szmessage)
		{
		    $arAdditionalInformationArray = array();
		    
		    $szTransactionDate = date("Ymd");
		    $arrPrevinfo  = $payment->getAdditionalInformation();
		    $paymentCurrency = Mage::getSingleton('core/session')->getPaymentCurrencyValue();
		    $arAdditionalInformationArray["CrossReference"]      = $szCrossReference;
		    $arAdditionalInformationArray["TransactionType"]     = $szTransactionType;
		    $arAdditionalInformationArray["TransactionDateTime"] = $szTransactionDate;
		    $arAdditionalInformationArray["PaymentMethod"]       = 'Paymentsense Hosted';
		    $arAdditionalInformationArray["PaymentCurrency"]     = $paymentCurrency;
		     $arAdditionalInformationArray["PaymentStatus"]     = $szmessage;
		    $payment->setAdditionalInformation($arAdditionalInformationArray);
		    return;
		}
	
}
?>
