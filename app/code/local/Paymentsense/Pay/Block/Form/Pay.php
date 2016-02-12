<?php
class Paymentsense_Pay_Block_Form_Pay extends Mage_Payment_Block_Form_Cc
{
     protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('pay/pay.phtml');
    }
     public function getSsStartYears()
    {
        $years = array();
        $first = date("Y");

        for ($index=10; $index>=0; $index--) {
            $year = $first - $index;
            $years[$year] = $year;
        }
        $years = array(0=>$this->__('Year'))+$years;
        return $years;
    }
}