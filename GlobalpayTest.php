<?php
//
// Sample PHP GlobalPAY integration
//
$response="";
$GPID = "";//GlobalPAY ID. input with test id for test

//sample data. 
$data = array(
    'GPID' => $GPID,
    'amount' => '1.00',//transaction Amount. Must be a double Value
    'desc'   => 'testDesc', //transaction Description
	'referenceID' => 'zen000122',//Transaction Reference. must be unique for every payment
    'ProductID' => 'test',//Optional
    'CustomerID'   => 'test',//Optional
	'merchantlogo' => 'test',//link to merchant logo
    'merchantName' => 'Test',//Custom display name on payment page. Optional
	'currency'=>'GHS'//Payment Currency
);
//set payment request url
$url = "https://www.zenithbank.com.gh/api.globalpay/Test/SecurePaymentRequest";

//init and set curl options
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, true);//enable post
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);//disable ssl
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);//enable response
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));//add parameters

$server_output = curl_exec ($ch);//execute request and get response
// further processing ....
if(curl_errno($ch))
{
	//something went wrong
}
else 
{ 
	$response = $server_output;//get response
}

curl_close ($ch);//close curl

//form redirect url and redirect customer for payment
$paymentURL = "https://www.zenithbank.com.gh/api.globalpay/Test/PaySecure?GPID=".$GPID."&tid=".$response;
header( "Location: ".$paymentURL  )
//die();
?>

