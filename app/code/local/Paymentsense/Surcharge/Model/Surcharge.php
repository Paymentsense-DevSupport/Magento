<?php
class Paymentsense_Surcharge_Model_Surcharge extends Varien_Object{
	
	//private $surcharge = 0;
	
	public function getSurcharge($quote = false){
		if($quote == false){
			$quote = Mage::getModel('checkout/session')->getQuote();
		}
		$quoteData= $quote->getData();		
		$grandTotal=$quoteData['grand_total'];
		$payment_code =  $quote->getPayment()->getData('method');
	    	
	        /* Check for Surcharge Status Start */
		
		 if(Mage::getStoreConfig('payment/pay/payment_surcharge') == 1 && $payment_code == 'pay'):
		     $cre_rate = Mage::getStoreConfig('payment/pay/surcharge_credit');	
		
		elseif(Mage::getStoreConfig('payment/payhosted/payment_surcharge') == 1 && $payment_code == 'payhosted'):
		      $cre_rate = Mage::getStoreConfig('payment/payhosted/surcharge_credit');	
		
		elseif(Mage::getStoreConfig('payment/paymoto/payment_surcharge') == '1' && $payment_code == 'paymoto'):
		      $cre_rate = Mage::getStoreConfig('payment/paymoto/surcharge_credit');	
		endif;
		
		
		//$surchrage_new = round((($grandTotal * $cre_rate) / 100),2);
		//$credit_sur = ($cre_rate * $cre_rate) / 100;
			if(isset($cre_rate)):
			   $surchrage_new = round(($cre_rate / 100),2);
			   $surchragetotal = $surchrage_new;
			else:
			   $surchragetotal = 0;
			endif;
			
		return $surchragetotal;
	}
	
	public function canApply($address){
		//$quote = Mage::getModel('checkout/session')->getQuote();
	    //$payment_code =  $quote->getPayment()->getData('method');
		//if(Mage::getStoreConfig('payment/pay/payment_surcharge') == 1 && $payment_code == 'pay'){
		        return true;
		//}
		
		//}
	}
}
