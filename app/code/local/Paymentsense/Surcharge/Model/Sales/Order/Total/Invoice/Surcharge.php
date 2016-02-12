<?php
class Paymentsense_Surcharge_Model_Sales_Order_Total_Invoice_Surcharge extends Mage_Sales_Model_Order_Invoice_Total_Abstract
{
	public function collect(Mage_Sales_Model_Order_Invoice $invoice)
	{
		$order = $invoice->getOrder();
		$SurchargeAmountLeft = $order->getSurchargeAmount() - $order->getSurchargeAmountInvoiced();
		$baseSurchargeAmountLeft = $order->getBaseSurchargeAmount() - $order->getBaseSurchargeAmountInvoiced();
		if (abs($baseSurchargeAmountLeft) < $invoice->getBaseGrandTotal()) {
			$invoice->setGrandTotal($invoice->getGrandTotal() + $SurchargeAmountLeft);
			$invoice->setBaseGrandTotal($invoice->getBaseGrandTotal() + $baseSurchargeAmountLeft);
		} else {
			$SurchargeAmountLeft = $invoice->getGrandTotal() * -1;
			$baseSurchargeAmountLeft = $invoice->getBaseGrandTotal() * -1;

			$invoice->setGrandTotal(0);
			$invoice->setBaseGrandTotal(0);
		}
			
		$invoice->setSurchargeAmount($SurchargeAmountLeft);
		$invoice->setBaseSurchargeAmount($baseSurchargeAmountLeft);
		return $this;
	}
}
