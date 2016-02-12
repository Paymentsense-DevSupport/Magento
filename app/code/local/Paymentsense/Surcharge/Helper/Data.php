<?php

class Paymentsense_Surcharge_Helper_Data extends Mage_Core_Helper_Abstract
{

	public function formatSurcharge($amount){
		return Mage::helper('surcharge')->__('Surcharge');
	}
	
}
