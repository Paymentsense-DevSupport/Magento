<?php
class Paymentsense_Pay_IndexController extends Mage_Core_Controller_Front_Action
{
	public function indexAction()
	{

		$this->loadLayout();
		$this->renderLayout();
	}
	
	public function porttestAction()
	{
		$this->loadLayout();
		$this->renderLayout();
	}
        
	public function callbackAction()
	{
		
		$orderStatus = Mage::getModel('pay/pay')->callback();
		
	 	$order = Mage::getModel('sales/order');
		$order->load(Mage::getSingleton('checkout/session')->getLastOrderId());
	   	$statusCode = $orderStatus['status'];
	        $payment = $order->getPayment();
		Mage::getModel('pay/pay')->setPaymentAdditionalInformation($payment,$orderStatus['CrossReference'],$orderStatus['paymentstatus']);
		$payment->save();
		  	
		try{
			if($statusCode == 4){
				Mage::getSingleton('checkout/session')->addError(Mage::helper('checkout')->__("Transaction Referred - Please try a different card"));
				
				$comment = $order->addStatusHistoryComment('Payment Status : Card referred')
				->setIsCustomerNotified(false)
				->save();
				$this->_forward('error');
				//die;
			}
			elseif($statusCode == 5){
			        Mage::getSingleton('checkout/session')->addError(Mage::helper('checkout')->__("Transaction declined : ".$orderStatus['paymentstatus']));
				$comment = $order->addStatusHistoryComment('Payment Status : Transaction declined')
				->setIsCustomerNotified(false)
				->save();
				$this->_forward('error');
				
				}
			elseif($statusCode == 20){
			Mage::getSingleton('checkout/session')->addError(Mage::helper('checkout')->__("Transaction declined : ".$orderStatus['paymentstatus']));
			$comment = $order->sendNewOrderEmail()->addStatusHistoryComment('Payment Status : Duplicate transaction')
			->setIsCustomerNotified(false)
			->save();
			$this->_forward('error');
			
			}
			elseif($statusCode == 30){
			Mage::getSingleton('checkout/session')->addError(Mage::helper('checkout')->__("Paymentsense input variable error"));
			$comment = $order->sendNewOrderEmail()->addStatusHistoryComment('Payment Status : Transaction declined')
			->setIsCustomerNotified(false)
			->save();
			$this->_forward('error');
			
			}
			elseif($statusCode == 0){
				
				$comment = $order->sendNewOrderEmail()->addStatusHistoryComment('Payment Status : transaction authorised')
				->setIsCustomerNotified(false)
				->save();
				$payment = $order->getPayment();
				$grandTotal = $order->getBaseGrandTotal();
				
				$payment->setIsTransactionApproved(true);
				
				$payment->setTransactionId($tid)
				
				->setPreparedMessage("Payment successful Result:")
				->setIsTransactionClosed(0)
				->setIsTransactionApproved(true)
				->registerAuthorizationNotification($grandTotal);
				
			     foreach ($order->getInvoiceCollection() as $inv)
				$invIncrementIDs = $inv->getIncrementId(); // get last invoice increment id
				
				if($invIncrementIDs)
				{
					$invoice = Mage::getModel('sales/order_invoice')->loadByIncrementId($invIncrementIDs);
					
					
					$invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE);
                                        //$invoice->setState(2);
					Mage::getModel('core/resource_transaction')
					->addObject($invoice)
					->addObject($invoice->getOrder())
					->save();
					$invoice->save();
					$payment->registerCaptureNotification($grandTotal);
					$payment->save();
				}
			     $order->setNewTransactionId($invIncrementIDs);
				
			      $order->save();


			
			$url = Mage::getUrl('checkout/onepage/success', array('_secure'=>true));
			Mage::register('redirect_url',$url);
			$this->_redirectUrl($url);
		       }
	        }
		catch(Exception $e)
		{
			Mage::logException($e);
		}
		
		
		
	}
	
	
	public function secureAction()
	{
		$this->loadLayout();    
               // $block = $this->getLayout()->createBlock('Mage_Core_Block_Template','pay_index',array('template' => 'pay/form/secureform.phtml'));
               // $this->getLayout()->getBlock('content')->append($block);
                $this->renderLayout();
	}
	
	
	public function successAction()
	{
		
		
		$request = $_REQUEST;
		Mage::log($request, null, 'lps.log');
		$orderIncrementId = $request['Merchant_ref_number'];
		Mage::log($orderIncrementId);
		$order = Mage::getModel('sales/order')->loadByIncrementId($orderIncrementId);
		Mage::log($order->getId());
		Mage::log($order->getId(), null, 'lps.log');
		try{
			if($request['Status_'] == 05){
				$comment = $order->addStatusHistoryComment('Transaction declined')
				->setIsCustomerNotified(false)
				->save();
				$this->_forward('error');
			}
			elseif($request['Status_'] == 90){
				$comment = $order->addStatusHistoryComment('Transaction declined')
				->setIsCustomerNotified(false)
				->save();
				$this->_forward('error');
			}elseif($request['Status_'] == 00){
				$comment = $order->addStatusHistoryComment('')
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
					
					//$order->registerCancellation($errorMsg)->save();
					$order->cancel();
					$order->setStatus('canceled');
					$order->setState(Mage_Sales_Model_Order::STATE_CANCELED,true);
					$order->save();
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
	
	public function saveOrderAction()
    {
	
	
        if ($this->_expireAjax()) {
            return;
        }

        $result = array();
        try {
            if ($requiredAgreements = Mage::helper('checkout')->getRequiredAgreementIds()) {
                $postedAgreements = array_keys($this->getRequest()->getPost('agreement', array()));
                if ($diff = array_diff($requiredAgreements, $postedAgreements)) {
                    $result['success'] = false;
                    $result['error'] = true;
                    $result['error_messages'] = $this->__('Please agree to all the terms and conditions before placing the order.');
                    $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
                    return;
                }
            }
            if ($data = $this->getRequest()->getPost('payment', false)) {
                $this->getOnepage()->getQuote()->getPayment()->importData($data);
            }
            $this->getOnepage()->saveOrder();

            $redirectUrl = $this->getOnepage()->getCheckout()->getRedirectUrl();
            $result['success'] = true;
            $result['error']   = false;
        } catch (Mage_Payment_Model_Info_Exception $e) {
            $message = $e->getMessage();
            if( !empty($message) ) {
                $result['error_messages'] = $message;
            }
            $result['goto_section'] = 'payment';
            $result['update_section'] = array(
                'name' => 'payment-method',
                'html' => $this->_getPaymentMethodsHtml()
            );
        } catch (Mage_Core_Exception $e) {
            Mage::logException($e);
            Mage::helper('checkout')->sendPaymentFailedEmail($this->getOnepage()->getQuote(), $e->getMessage());
            $result['success'] = false;
            $result['error'] = true;
            $result['error_messages'] = $e->getMessage();

            if ($gotoSection = $this->getOnepage()->getCheckout()->getGotoSection()) {
                $result['goto_section'] = $gotoSection;
                $this->getOnepage()->getCheckout()->setGotoSection(null);
            }

            if ($updateSection = $this->getOnepage()->getCheckout()->getUpdateSection()) {
                if (isset($this->_sectionUpdateFunctions[$updateSection])) {
                    $updateSectionFunction = $this->_sectionUpdateFunctions[$updateSection];
                    $result['update_section'] = array(
                        'name' => $updateSection,
                        'html' => $this->$updateSectionFunction()
                    );
                }
                $this->getOnepage()->getCheckout()->setUpdateSection(null);
            }
        } catch (Exception $e) {
            Mage::logException($e);
            Mage::helper('checkout')->sendPaymentFailedEmail($this->getOnepage()->getQuote(), $e->getMessage());
            $result['success']  = false;
            $result['error']    = true;
            $result['error_messages'] = $this->__('There was an error processing your order. Please contact us or try again later.');
        }
        $this->getOnepage()->getQuote()->save();
        /**
         * when there is redirect to third party, we don't want to save order yet.
         * we will save the order in return action.
         */
        if (isset($redirectUrl)) {
            $result['redirect'] = $redirectUrl;
        }
       
        return $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
    }
    
    public function collectionAction()
    {
	$parameters = $this->getRequest()->getParams();
    	$szOrderID = $parameters['OrderID'];
    	$szCrossReference = $parameters['CrossReference'];
    	
	$order = Mage::getModel('sales/order')->loadByIncrementId((int)$szOrderID);
    	$payment = $order->getPayment();
    	
    	$result = Mage::getModel('pay/pay')->payCollection($payment,$amount=false);
    	
    	return $this->getResponse()->setBody($result);
    }
    
    public function voidAction()
    {
    	$model = Mage::getSingleton('paymentsensegateway/direct');
    	$parameters = $this->getRequest()->getParams();
    	$szOrderID = $parameters['OrderID'];
    	$szCrossReference = $parameters['CrossReference'];
    	
    	$order = Mage::getModel('sales/order')->loadByIncrementId((int)$szOrderID);
    	$payment = $order->getPayment();
    	
    	$result = Mage::getModel('pay/pay')->Void($payment);
    	
    	
    	return $this->getResponse()->setBody($result);
    }
    
     public function payrefundAction()
    {
    	$model = Mage::getSingleton('paymentsensegateway/direct');
    	$parameters = $this->getRequest()->getParams();
    	$szOrderID = $parameters['OrderID'];
    	$szCrossReference = $parameters['CrossReference'];
    	
    	$order = Mage::getModel('sales/order')->loadByIncrementId((int)$szOrderID);
    	$payment = $order->getPayment();
    	
    	$result = Mage::getModel('pay/pay')->refund($payment);
    	
    	
    	return $this->getResponse()->setBody($result);
    }
    
    
}
