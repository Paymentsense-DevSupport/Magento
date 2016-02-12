<?php
class Paymentsense_Payhosted_Block_Form_Payhosted extends Mage_Payment_Block_Form
{
  public function calculateHashDigest($szInputString, $szPreSharedKey, $szHashMethod)
        {
            switch ($szHashMethod)
            {
                    case "MD5":
            $hashDigest = md5($szInputString);
                            break;
                    case "SHA1":
            $hashDigest = sha1($szInputString);
                            break;
                    case "HMACMD5":
            $hashDigest = hash_hmac("md5", $szInputString, $szPreSharedKey);
                            break;
                    case "HMACSHA1":
            $hashDigest = hash_hmac("sha1", $szInputString, $szPreSharedKey);
                            break;
            }

            return ($hashDigest);
        }
        public function generateStringToHash($szMerchantID,
                                                $szPassword,
                                                $szAmount,
                                                $szCurrencyCode,
                                                $szOrderID,
                                                $szTransactionType,
                                                $szTransactionDateTime,
                                                $szCallbackURL,
                                                $szOrderDescription,
                                                $szCustomerName,
                                                $szAddress1,
                                                $szAddress2,
                                                $szAddress3,
                                                $szAddress4,
                                                $szCity,
                                                $szState,
                                                $szPostCode,
                                                $szCountryCode,
                                                $szCV2Mandatory,
                                                $szAddress1Mandatory,
                                                $szCityMandatory,
                                                $szPostCodeMandatory,
                                                $szStateMandatory,
                                                $szCountryMandatory,
                                                $szResultDeliveryMethod,
                                                $szServerResultURL,
                                                $szPaymentFormDisplaysResult,
                                                $szPreSharedKey,
                                                $szHashMethod)
        {
            $szReturnString = "";

            switch ($szHashMethod)
            {
                    case "MD5":
                            $boIncludePreSharedKeyInString = true;
                            break;
                    case "SHA1":
                            $boIncludePreSharedKeyInString = true;
                            break;
                    case "HMACMD5":
                            $boIncludePreSharedKeyInString = false;
                            break;
                    case "HMACSHA1":
                            $boIncludePreSharedKeyInString = false;
                            break;
            }

            if ($boIncludePreSharedKeyInString)
            {
                    $szReturnString = "PreSharedKey=".$szPreSharedKey."&";
            }

            $szReturnString = $szReturnString."MerchantID=".$szMerchantID.
                                "&Password=".$szPassword.
                                "&Amount=".$szAmount.
                                "&CurrencyCode=".$szCurrencyCode.
                                "&OrderID=".$szOrderID.
                                "&TransactionType=".$szTransactionType.
                                "&TransactionDateTime=".$szTransactionDateTime.
                                "&CallbackURL=".$szCallbackURL.
                                "&OrderDescription=".$szOrderDescription.
                                "&CustomerName=".$szCustomerName.
                                "&Address1=".$szAddress1.
                                "&Address2=".$szAddress2.
                                "&Address3=".$szAddress3.
                                "&Address4=".$szAddress4.
                                "&City=".$szCity.
                                "&State=".$szState.
                                "&PostCode=".$szPostCode.
                                "&CountryCode=".$szCountryCode.
                                "&CV2Mandatory=".$szCV2Mandatory.
                                "&Address1Mandatory=".$szAddress1Mandatory.
                                "&CityMandatory=".$szCityMandatory.
                                "&PostCodeMandatory=".$szPostCodeMandatory.
                                "&StateMandatory=".$szStateMandatory.
                                "&CountryMandatory=".$szCountryMandatory.
                                "&ResultDeliveryMethod=".$szResultDeliveryMethod.
                                "&ServerResultURL=".$szServerResultURL.
                                "&PaymentFormDisplaysResult=".$szPaymentFormDisplaysResult;

            return ($szReturnString);
        }
}

