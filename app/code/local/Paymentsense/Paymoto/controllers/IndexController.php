<?php
class paymentsense_paymoto_IndexController extends Mage_Core_Controller_Front_Action
{
	public function indexAction()
	{
		die;
		$this->loadLayout();
		$this->renderLayout();
	}
        
	public function callbackAction()
	{
		
		$orderStatus = Mage::getModel('paymoto/paymoto')->callback();
		
	 	$order = Mage::getModel('sales/order');
		$order->load(Mage::getSingleton('checkout/session')->getLastOrderId());
		$statusCode = $orderStatus['status'];
	//die;	
		try{
			if($statusCode == 4){
				Mage::getSingleton('checkout/session')->addError(Mage::helper('checkout')->__("Transaction Referred - Please try a different card"));
				
				$comment = $order->sendNewOrderEmail()->addStatusHistoryComment('paymotoment Status : Card referred')
				->setIsCustomerNotified(false)
				->save();
				$this->_forward('error');
				//die;
			}
			elseif($statusCode == 5){
			        Mage::getSingleton('checkout/session')->addError(Mage::helper('checkout')->__("Transaction declined"));
				$comment = $order->sendNewOrderEmail()->addStatusHistoryComment('paymotoment Status : Transaction declined')
				->setIsCustomerNotified(false)
				->save();
				$this->_forward('error');
				
				}
			elseif($statusCode == 20){
			Mage::getSingleton('checkout/session')->addError(Mage::helper('checkout')->__("Transaction declined"));
			$comment = $order->sendNewOrderEmail()->addStatusHistoryComment('paymotoment Status : Duplicate transaction')
			->setIsCustomerNotified(false)
			->save();
			$this->_forward('error');
			
			}
			elseif($statusCode == 0){
				
				$comment = $order->sendNewOrderEmail()->addStatusHistoryComment('paymotoment Status : transaction authorised')
				->setIsCustomerNotified(false)
				->save();
				$paymotoment = $order->getpaymotoment();
				$grandTotal = $order->getBaseGrandTotal();
				if(isset($request['Transactionid'])){
					$tid = $request['Transactionid'];
				}
				else {
					$tid = -1 ;
				}
				$paymotoment->setIsTransactionApproved(true);
				
				$paymotoment->setTransactionId($tid)
				
				->setPreparedMessage("paymotoment Sucessfull Result:")
				->setIsTransactionClosed(0)
				->setIsTransactionApproved(true)
				->registerAuthorizationNotification($grandTotal);
				$order->save();


				/*if ($invoice = $paymotoment->getCreatedInvoice()) {
				 $message = Mage::helper('paymoto')->__('Notified customer about invoice #%s.', $invoice->getIncrementId());
				$comment = $order->sendNewOrderEmail()->addStatusHistoryComment($message)
				->setIsCustomerNotified(true)
				->save();
				}*/
				/*
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
					$message = Mage::helper('paymoto')->__('Notified customer about invoice #%s.', $invoice->getIncrementId());
					$comment = $order->sendNewOrderEmail()->addStatusHistoryComment($message)
					->setIsCustomerNotified(true)
					->save();
				}
				catch (Mage_Core_Exception $e) {

				}
				*/
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
	
	
	public function secureAction()
	{
		$this->loadLayout();    
               // $block = $this->getLayout()->createBlock('Mage_Core_Block_Template','paymoto_index',array('template' => 'paymoto/form/secureform.phtml'));
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
				$comment = $order->sendNewOrderEmail()->addStatusHistoryComment('Bank Status : Declined By Bank')
				->setIsCustomerNotified(false)
				->save();
				$this->_forward('error');
			}
			elseif($request['Status_'] == 90){
				$comment = $order->sendNewOrderEmail()->addStatusHistoryComment('Bank Status : Comm. Failed')
				->setIsCustomerNotified(false)
				->save();
				$this->_forward('error');
			}elseif($request['Status_'] == 00){
				$comment = $order->sendNewOrderEmail()->addStatusHistoryComment('Bank Status : ----')
				->setIsCustomerNotified(false)
				->save();
				$paymotoment = $order->getpaymotoment();
				$grandTotal = $order->getBaseGrandTotal();
				if(isset($request['Transactionid'])){
					$tid = $request['Transactionid'];
				}
				else {
					$tid = -1 ;
				}
					
				$paymotoment->setTransactionId($tid)
				->setPreparedMessage("paymotoment Sucessfull Result:")
				->setIsTransactionClosed(0)
				->registerAuthorizationNotification($grandTotal);
				$order->save();


				/*if ($invoice = $paymotoment->getCreatedInvoice()) {
				 $message = Mage::helper('paymoto')->__('Notified customer about invoice #%s.', $invoice->getIncrementId());
				$comment = $order->sendNewOrderEmail()->addStatusHistoryComment($message)
				->setIsCustomerNotified(true)
				->save();
				}*/
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
					$message = Mage::helper('paymoto')->__('Notified customer about invoice #%s.', $invoice->getIncrementId());
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
				//Redirect to paymotoment step
				$gotoSection = 'paymotoment';
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
            if ($data = $this->getRequest()->getPost('paymotoment', false)) {
                $this->getOnepage()->getQuote()->getpaymotoment()->importData($data);
            }
            $this->getOnepage()->saveOrder();

            $redirectUrl = $this->getOnepage()->getCheckout()->getRedirectUrl();
            $result['success'] = true;
            $result['error']   = false;
        } catch (Mage_paymotoment_Model_Info_Exception $e) {
            $message = $e->getMessage();
            if( !empty($message) ) {
                $result['error_messages'] = $message;
            }
            $result['goto_section'] = 'paymotoment';
            $result['update_section'] = array(
                'name' => 'paymotoment-method',
                'html' => $this->_getpaymotomentMethodsHtml()
            );
        } catch (Mage_Core_Exception $e) {
            Mage::logException($e);
            Mage::helper('checkout')->sendpaymotomentFailedEmail($this->getOnepage()->getQuote(), $e->getMessage());
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
            Mage::helper('checkout')->sendpaymotomentFailedEmail($this->getOnepage()->getQuote(), $e->getMessage());
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
}
