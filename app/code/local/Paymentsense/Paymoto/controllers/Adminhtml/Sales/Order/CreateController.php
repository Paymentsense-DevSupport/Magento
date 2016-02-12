<?php
include("Mage/Adminhtml/controllers/Sales/Order/CreateController.php");
class Paymentsense_Paymoto_Adminhtml_Sales_Order_CreateController extends Mage_Adminhtml_Sales_Order_CreateController
{
    /**
     * Saving quote and create order
     */
    public function saveAction()
    {
    	$paymentData = $this->getRequest()->getPost('payment');
        
        try {
            $this->_processData();
            if ($paymentData = $this->getRequest()->getPost('payment')) {

                $this->_getOrderCreateModel()->setPaymentData($paymentData);
                $this->_getOrderCreateModel()->getQuote()->getPayment()->addData($paymentData);
            }

			if($paymentData && $paymentData['method'] == 'paymoto'){
				$result = $this->getDirectModel()->registerTransaction($this->getRequest()->getPost());
				
			}


            $order = $this->_getOrderCreateModel()
            	->setIsValidate(true)
                ->importPostData($this->getRequest()->getPost('order'))
                ->createOrder();

            $this->_getSession()->clear();
            Mage::getSingleton('adminhtml/session')->addSuccess($this->__('The order has been created.'));
            $this->_redirect('adminhtml/sales_order/view', array('order_id' => $order->getId()));
        }
        catch (Mage_Core_Exception $e){
            $message = $e->getMessage();
            if( !empty($message) ) {
                $this->_getSession()->addError($message);
            }
            $this->_redirect('adminhtml/sales_order_create/index');
        }
        catch (Exception $e){
            $this->_getSession()->addException($e, $this->__('Order saving error: %s', $e->getMessage()));
            $this->_redirect('adminhtml/sales_order_create/index');
        }
    }
    
    public function getDirectModel()
    {
    	return Mage::getModel('paymoto/paymoto');
    }
	
}
