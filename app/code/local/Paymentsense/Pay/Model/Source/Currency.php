<?php

class Paymentsense_Pay_Model_Source_Currency 
{
	public function toOptionArray()
    {
        return array
        (
        	 // override the core class to ONLY allow capture transactions (immediate settlement)
            array
            (
            	'value' => 'USD',
            	'label' => Mage::helper('pay')->__('USD')
            ),
            array
            (
                'value' => 'GBP',
            	'label' => Mage::helper('pay')->__('GBP')
            ),
            array
            (
                'value' => 'EUR',
            	'label' => Mage::helper('pay')->__('EUR')
            ),
        );
    }
}