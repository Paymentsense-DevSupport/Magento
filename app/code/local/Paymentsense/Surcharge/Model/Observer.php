<?php
class Paymentsense_Surcharge_Model_Observer{
	public function invoiceSaveAfter(Varien_Event_Observer $observer)
	{
		$invoice = $observer->getEvent()->getInvoice();
		if ($invoice->getBaseSurchargeAmount()) {
			$order = $invoice->getOrder();
			$order->setSurchargeAmountInvoiced($order->getSurchargeAmountInvoiced() + $invoice->getSurchargeAmount());
			$order->setBaseSurchargeAmountInvoiced($order->getBaseSurchargeAmountInvoiced() + $invoice->getBaseSurchargeAmount());
		}
		return $this;
	}
	public function creditmemoSaveAfter(Varien_Event_Observer $observer)
	{
		/* @var $creditmemo Mage_Sales_Model_Order_Creditmemo */
		$creditmemo = $observer->getEvent()->getCreditmemo();
		if ($creditmemo->getSurchargeAmount()) {
			$order = $creditmemo->getOrder();
			$order->setSurchargeAmountRefunded($order->getSurchargeAmountRefunded() + $creditmemo->getSurchargeAmount());
			$order->setBaseSurchargeAmountRefunded($order->getBaseSurchargeAmountRefunded() + $creditmemo->getBaseSurchargeAmount());
		}
		return $this;
	}
	public function updatePaypalTotal($evt){
		$cart = $evt->getPaypalCart();
		$cart->updateTotal(Mage_Paypal_Model_Cart::TOTAL_SUBTOTAL,$cart->getSalesEntity()->getSurchargeAmount());
	}
}
