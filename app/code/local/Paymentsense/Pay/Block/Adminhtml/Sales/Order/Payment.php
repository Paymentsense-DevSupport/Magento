<?php
class Paymentsense_Pay_Block_Adminhtml_Sales_Order_Payment extends Mage_Adminhtml_Block_Sales_Order_Payment
{
    public function setPayment($payment)
    {
	
    	parent::setPayment($payment);
        $paymentInfoBlock = Mage::helper('payment')->getInfoBlock($payment);

        if ($payment->getMethod() == 'pay')
        {

            $paymentInfoBlock->setTemplate('payment/info/paymentinfo.phtml');
        }

        $this->setChild('info', $paymentInfoBlock);
        $this->setData('payment', $payment);
        return $this;
    }

    protected function _toHtml()
    {
        return $this->getChildHtml('info');
    }

}
