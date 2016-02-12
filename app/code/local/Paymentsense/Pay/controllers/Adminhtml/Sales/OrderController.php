<?php
include("Mage/Adminhtml/controllers/Sales/OrderController.php");
class Paymentsense_Pay_Adminhtml_Sales_OrderController extends Mage_Adminhtml_Sales_OrderController
{
    public function voidPaymentAction()
    {
       
        if (!$order = $this->_initOrder()) {
            return;
        }
        try {
            $order->getPayment()->void(
                new Varien_Object() // workaround for backwards compatibility
            );
             if($order->getPayment()->getMethodInstance()->getCode() == 'pay'){
            $order->setstate(Mage_Sales_Model_Order::STATE_CANCELED, true);
             }
            $order->save();
            $this->_getSession()->addSuccess($this->__('The payment has been voided.'));
        } catch (Mage_Core_Exception $e) {
            $this->_getSession()->addError($e->getMessage());
        } catch (Exception $e) {
            $this->_getSession()->addError($this->__('Failed to void the payment.'));
            Mage::logException($e);
        }
        $this->_redirect('*/*/view', array('order_id' => $order->getId()));
    }
}
?>