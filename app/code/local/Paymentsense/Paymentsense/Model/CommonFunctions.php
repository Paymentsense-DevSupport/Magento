<?php
//Functions to pull result from XML
function PS_GetXMLValue($XMLElement, $XML, $pattern) {
	$soapArray = null;
	$ToReturn = null;
	if (preg_match('#<'.$XMLElement.'>('.$pattern.')</'.$XMLElement.'>#iU', $XML, $soapArray)) {
		$ToReturn = $soapArray[1];
	} else {
		$ToReturn = $XMLElement . " Not Found";
	}
	
	return $ToReturn;
}

function PS_GetCrossReference($XML) {
	$soapArray = null;
	$ToReturn = null;
	if (preg_match('#<TransactionOutputData CrossReference="(.+)">#iU', $XML, $soapArray)) {
		$ToReturn = $soapArray[1];
	} else {
		$ToReturn = "No Data Found";
	}
	
	return $ToReturn;
}

// convert restricted characters to html equivalent and ensure values don�t go over the amount allowed
function PS_clean($string, $numberLimit) {
	// remove restricted characters
	$toReplace = array("#","\\",">","<", "\"", "[", "]");
	$string = str_replace($toReplace, "", $string);

	// remove html special chars and turn into html equivalent value
	$string = htmlspecialchars($string);
	
	// now ensure it doesnt exceed the allowed amount
	$string = substr($string, 0, $numberLimit);
		
	//return PS_clean string
	return $string;
}

//get the error from the gateway and show a friendly message to the user i.e invalid card number
function PS_getErrorFromGateway($szMessageDetail, $ret){
	if ($szMessageDetail == "Invalid card type"){
		// invalid card type/number
		$szMessageError = "Invalid card type/number.";
	}
	else if ($szMessageDetail == "Passed variable (PaymentMessage.CardDetails.CV2) has an invalid value"){
		// invalid CV2
		$szMessageError = "Invalid CV2 number.";
	}
	else if ($szMessageDetail == "Required variable (PaymentMessage.CardDetails.CardNumber) is missing"){
		// no card number entered
		$szMessageError = "No card number entered.";
	}
	else if ($szMessageDetail == "Passed variable (PaymentMessage.CardDetails.CardNumber) has an invalid value"){
		// invalid card number - normally caused by exceeding the length
		$szMessageError = "Invalid card number.";
	}
	else if ($szMessageDetail == "Required variable (PaymentMessage.CardDetails.ExpiryDate.Month) is missing"){
		// missing expiry month
		$szMessageError = "Expiry month missing.";
	}
	else if ($szMessageDetail == "Required variable (PaymentMessage.CardDetails.ExpiryDate.Year) is missing"){
		// missing expiry year
		$szMessageError = "Expiry year missing.";
	}
	else if ($szMessageDetail == "Passed variable (PaymentMessage.CardDetails.ExpiryDate.Year) has an invalid value"){
		// invalid expiry year -  - normally caused by exceeding the length
		$szMessageError = "Invalid expiry year.";
	}
	else if ($szMessageDetail == "Passed variable (PaymentMessage.CardDetails.ExpiryDate.Month) has an invalid value"){
		// invalid expiry month -  - normally caused by exceeding the length
		$szMessageError = "Invalid expiry month.";
	}
	else if ($szMessageDetail == "Passed variable (PaymentMessage.CardDetails.IssueNumber) has an invalid value"){
		// invalid issue number - normally caused by exceeding the length
		$szMessageError = "Invalid issue number.";
	}
	else {
		// other error - return what the gateway advised
		$szMessageError = PS_GetXMLValue("Message", $ret, ".+");
		
		if ($szMessageError == "Invalid merchant details"){
			$szMessageError = "Incorrect ID and/or Password";
		}
	}
	
	//return the error from gateway
	return $szMessageError;
}
?>