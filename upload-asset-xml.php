<?php

// Input
$id = $_GET['id'];
$body = file_get_contents('php://input');
$xml = (ord(substr($body,0,1)) == 31 ? gzinflate(substr($body,10,-8)) : $body); // if gzipped, decode

// Login User Data
$login_user = 'username=&password=';
$roblosecurity = file_get_contents('access.txt');

// [Function] Update ROBLOSECRUITY
function updateROBLOSECRUITY()
{
	global $login_user;

	$get_cookies = curl_init('https://www.roblox.com/newlogin');
	curl_setopt_array($get_cookies,
		array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HEADER => true,
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => $login_user
		)
	);

	$roblosecurity = (preg_match('/(\.ROBLOSECURITY=.*?);/', curl_exec($get_cookies), $matches) ? $matches[1] : '');
	file_put_contents('access.txt', $roblosecurity, true);
	curl_close($get_cookies);

	return $roblosecurity;
}

// [Function] Upload XML
function uploadXML($roblosecurity) 
{
	global $id, $xml;
	
	$upload_xml = curl_init("http://www.roblox.com/Data/Upload.ashx?assetid=$id");
	curl_setopt_array($upload_xml,
		array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_POST => true,
			CURLOPT_HTTPHEADER => array('User-Agent: Roblox/WinINet', "Cookie: $roblosecurity"),
			CURLOPT_POSTFIELDS => $xml
		)
	);

	$avid = curl_exec($upload_xml);
	curl_close($upload_xml);

	return $avid;
}

// Upload XML
$avid = uploadXML($roblosecurity);
if (preg_match('/RobloxDefaultErrorPage/', $avid)) { // update cookie if errored
	$avid = uploadXML(updateROBLOSECRUITY());
}

// Echo AVID
echo (is_numeric($avid) ? $avid : 0);