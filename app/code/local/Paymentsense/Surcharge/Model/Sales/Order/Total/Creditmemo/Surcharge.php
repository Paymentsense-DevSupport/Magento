<?php
class Paymentsense_Surcharge_Model_Sales_Order_Total_Creditmemo_Surcharge extends Mage_Sales_Model_Order_Creditmemo_Total_Abstract
{
	public function collect(Mage_Sales_Model_Order_Creditmemo $creditmemo)
	{
		$order = $creditmemo->getOrder();
		$SurchargeAmountLeft = $order->getSurchargeAmountInvoiced() - $order->getSurchargeAmountRefunded();
		$baseSurchargeAmountLeft = $order->getBaseSurchargeAmountInvoiced() - $order->getBaseSurchargeAmountRefunded();
		if ($baseSurchargeAmountLeft > 0) {
			$creditmemo->setGrandTotal($creditmemo->getGrandTotal() + $SurchargeAmountLeft);
			$creditmemo->setBaseGrandTotal($creditmemo->getBaseGrandTotal() + $baseSurchargeAmountLeft);
			$creditmemo->setSurchargeAmount($SurchargeAmountLeft);
			$creditmemo->setBaseSurchargeAmount($baseSurchargeAmountLeft);
		}
		return $this;
	}
}
