<?php

class Paymentsense_Paymentsense_Model_Source_PaymentMode
{
    // public enum for the payment types
    const PAYMENT_MODE_DIRECT_API = 'direct';
    const PAYMENT_MODE_HOSTED_PAYMENT_FORM = 'hosted';
    const PAYMENT_MODE_TRANSPARENT_REDIRECT = 'transparent';

    public function toOptionArray()
    {
        return array
        (
            array(
                'value' => self::PAYMENT_MODE_DIRECT_API,
                'label' => Mage::helper('Paymentsense')->__('Direct (API)')
            ),
            array(
                'value' => self::PAYMENT_MODE_HOSTED_PAYMENT_FORM,
                'label' => Mage::helper('Paymentsense')->__('Hosted Payment Form')
            ),
            
        );
    }
}