<?php

class Paymentsense_Checkout_Model_Type_Onepage extends Mage_Checkout_Model_Type_Onepage
{
	/**
     * Create an order for a Direct (API) 3D Secure enabled payment on the callback
     *
     * @param unknown_type $pares
     * @param unknown_type $md
     * @return unknown
     */
	public function saveOrderAfter3dSecure($pares, $md)
   	{
   		$nVersion = Mage::getModel('Paymentsense/direct')->getVersion();
   		$orderId;   		
   		
		$orderId = Mage::getSingleton('checkout/session')->getPaymentsenseOrderId();
		$_order = Mage::getModel('sales/order')->load($orderId);

		if(!$_order->getId())
		{
			Mage::throwException('Could not load order.');
		}
		
		Mage::getSingleton('checkout/session')->setThreedsecurerequired(true)
												->setMd($md)
												->setPares($pares);
		
		$method = Mage::getSingleton('checkout/session')->getRedirectionmethod();
		$_order->getPayment()->getMethodInstance()->{$method}($_order->getPayment(), $pares, $md);
		
		if ($_order->getFailedThreed() !== true &&
			$_order->getPayment()->getMethodInstance()->getCode() == 'Paymentsense' &&
			$_order->getStatus() != 'pending')
		{
			$order_status = Mage::getStoreConfig('payment/Paymentsense/order_status',  Mage::app()->getStore()->getId());
			$_order->addStatusToHistory($order_status);
			$_order->setStatus($order_status);
		}

		$_order->save();

		Mage::getSingleton('checkout/session')->setThreedsecurerequired(null)
												->setMd(null)
												->setPareq(null)
												->setAcsurl(null)
												->setPaymentsenseOrderId(null);
   		
        return $this;
    }
    
    /**
     * Create an order for a Hosted Payment Form/Transparent Redirect payment on the callback
     *
     * @param unknown_type $boIsHostedPaymentAction
     * @param unknown_type $szStatusCode
     * @param unknown_type $szMessage
     * @param unknown_type $szPreviousStatusCode
     * @param unknown_type $szPreviousMessage
     * @param unknown_type $szOrderID
     * @return unknown
     */
	public function saveOrderAfterRedirectedPaymentAction($boIsHostedPaymentAction, $szStatusCode, $szMessage, $szPreviousStatusCode, $szPreviousMessage, $szOrderID, $szCrossReference)
   	{
   		$nVersion = Mage::getModel('Paymentsense/direct')->getVersion();
   		
		$_order = Mage::getModel('sales/order')->load(Mage::getSingleton('checkout/session')->getPaymentsenseOrderId());

		if(!$_order->getId())
		{
			Mage::throwException('Could not load order.');
		}
		
		Mage::getSingleton('checkout/session')->setRedirectedpayment(true)
												->setIshostedpayment($boIsHostedPaymentAction)
												->setStatuscode($szStatusCode)
												->setMessage($szMessage)
												->setPreviousstatuscode($szPreviousStatusCode)
												->setPreviousmessage($szPreviousMessage)
												->setOrderid($szOrderID);
		
		$method = Mage::getSingleton('checkout/session')->getRedirectionmethod();
		$_order->getPayment()->getMethodInstance()->{$method}($_order->getPayment(), $boIsHostedPaymentAction, $szStatusCode, $szMessage, $szPreviousStatusCode, $szPreviousMessage, $szOrderID, $szCrossReference);
		
		if ($_order->getFailedThreed() !== true &&
			$_order->getPayment()->getMethodInstance()->getCode() == 'Paymentsense' &&
			$_order->getStatus() != 'pending')
		{
			$order_status = Mage::getStoreConfig('payment/Paymentsense/order_status',  Mage::app()->getStore()->getId());
			$_order->addStatusToHistory($order_status);
			$_order->setStatus($order_status);
		}

		$_order->save();

		Mage::getSingleton('checkout/session')->setRedirectedpayment(null)
												->setIshostedpayment(null)
												->setStatuscode(null)
												->setMessage(null)
												->setPreviousstatuscode(null)
												->setPreviousmessage(null)
												->setOrderid(null)
												->setPaymentsenseOrderId(null);   		

        return $this;
    }
}