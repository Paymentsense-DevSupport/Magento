<?php

class Paymentsense_Paymentsense_Model_Source_ResultDeliveryMethod
{
	// public enum for the payment types
	const RESULT_DELIVERY_METHOD_POST = 'POST';
	const RESULT_DELIVERY_METHOD_SERVER = 'SERVER';
	const RESULT_DELIVERY_METHOD_SERVER_PULL = "SERVER_PULL";

	public function toOptionArray()
    {
        return array
        (
        	array(
                'value' => self::RESULT_DELIVERY_METHOD_SERVER_PULL,
                'label' => Mage::helper('Paymentsense')->__('Server Pull')
            ),
            /*array(
                'value' => self::RESULT_DELIVERY_METHOD_SERVER,
                'label' => Mage::helper('Paymentsense')->__('Server')
            ),*/
            array(
                'value' => self::RESULT_DELIVERY_METHOD_POST,
                'label' => Mage::helper('Paymentsense')->__('Post')
            )
        );
    }
}