<?php
class Paymentsense_Surcharge_Model_Sales_Quote_Address_Total_Surcharge extends Mage_Sales_Model_Quote_Address_Total_Abstract{
	protected $_code = 'surcharge';
	

	public function collect(Mage_Sales_Model_Quote_Address $address)
	{
		
		 $surchargeStatus = false;
		
		
		
		parent::collect($address);

		$this->_setAmount(0);
		$this->_setBaseAmount(0);
                
		
		
		
		$items = $this->_getAddressItems($address);
		if (!count($items)) {
			return $this; //this makes only address type shipping to come through
		}


		$quote          = $address->getQuote();
		$ModelSurcharge = Mage::getModel('surcharge/surcharge');

		if($ModelSurcharge->canApply($address)){
			$exist_amount = $quote->getSurchargeAmount();
			//echo $exist_amount;
			$Surcharge = $ModelSurcharge->getSurcharge($quote);
			
			$balance = $Surcharge - $exist_amount;
			// 			$balance = $Surcharge;

			
			//$this->_setBaseAmount($balance);
			$payment_code =  $quote->getPayment()->getData('method');
			
				/* Check for Surcharge Status Start */
				
				if(Mage::getStoreConfig('payment/pay/payment_surcharge') == 1 && $payment_code == 'pay'):
				        $surchargeStatus = true;
				elseif(Mage::getStoreConfig('payment/payhosted/payment_surcharge') == 1 && $payment_code == 'payhosted'):
				        $surchargeStatus = true;
				elseif(Mage::getStoreConfig('payment/paymoto/payment_surcharge') == 1 && $payment_code == 'paymoto'):
				        $surchargeStatus = true;
				endif;
				
				
				/* Check for Surcharge Status End */
			
			
			if(Mage::app()->getRequest()->getControllerName() != 'cart' && $surchargeStatus = 1){
				$address->setSurchargeAmount($balance);
				$address->setBaseSurchargeAmount($balance);
		
				$quote->setSurchargeAmount($balance);
				$address->setGrandTotal($address->getGrandTotal() + $address->getSurchargeAmount());
				$address->setBaseGrandTotal($address->getBaseGrandTotal() + $address->getBaseSurchargeAmount());
			}
		}
	}

	public function fetch(Mage_Sales_Model_Quote_Address $address)
	{
		$surchargeStatus = false;
	
				
	    //$quote = Mage::getModel('checkout/session')->getQuote();
	    $quote = $address->getQuote();
	    
	    $payment_code =  $quote->getPayment()->getData('method');
	    
	        /* Check for Surcharge Status Start */
		
		 if(Mage::getStoreConfig('payment/pay/payment_surcharge') == 1 && $payment_code == 'pay'):
		     $surchargeStatus = true;
		
		elseif(Mage::getStoreConfig('payment/payhosted/payment_surcharge') == 1 && $payment_code == 'payhosted'):
		     $surchargeStatus = true;
		
		elseif(Mage::getStoreConfig('payment/paymoto/payment_surcharge') == 1 && $payment_code == 'paymoto'):
		   $surchargeStatus = true;
		endif;
		
		/* Check for Surcharge Status End */
		
	     if(Mage::app()->getRequest()->getControllerName() != 'cart' && $surchargeStatus==1){
				
				$amt = $address->getSurchargeAmount();
				$address->addTotal(array(
						'code'=>$this->getCode(),
						'title'=>Mage::helper('surcharge')->__('Surcharge'),
						'value'=> $amt
				));
				
				return $this;
		}
	}
}
