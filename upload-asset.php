<?php
/*
	-READ ME-
	Modify `login_user` and `file_name_rs` to what you will use.
	* The script will automatically create a .txt file of `file_name_rs`, which will store the user's ROBLOSECURITY.
	* This is to avoid continuously logging in, which will activate CAPTCHA protection and break the script.
	* And also to increase performance by not obtaining ROBLOSECURITY again when it's still usable.
*/

// login user data
$login_user    = 'username=&password=';
$file_name_rs  = 'rs.txt';
$current_rs    = (file_exists($file_name_rs) ? file_get_contents($file_name_rs) : '');

// input data
$asset_id   = $_GET['id'];
$post_body  = file_get_contents('php://input');
$asset_xml  = (ord(substr($post_body,0,1)) == 31 ? gzinflate(substr($post_body,10,-8)) : $post_body); // if gzipped, decode


// --------------------------------------

// [Function] Get ROBLOSECURITY
function getRS()
{
	// globalize vars
	global $login_user, $file_name_rs;

	// setup get_cookies request
	$get_cookies = curl_init('https://www.roblox.com/newlogin');
	curl_setopt_array($get_cookies,
		array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HEADER => true,
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => $login_user
		)
	);

	// get roblosecurity
	$rs = (preg_match('/(\.ROBLOSECURITY=.*?);/', curl_exec($get_cookies), $matches) ? $matches[1] : '');

	// store roblosecurity to file_name_rs
	file_put_contents($file_name_rs, $rs, true);

	// close get_cookies
	curl_close($get_cookies);

	// return roblosecurity
	return $rs;
}

// [Function] Upload Asset
function uploadAsset($rs)
{
	// globalize vars
	global $asset_id, $asset_xml;
	
	// setup upload_xml request
	$upload_xml = curl_init("http://www.roblox.com/Data/Upload.ashx?assetid=$asset_id");
	curl_setopt_array($upload_xml,
		array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_POST => true,
			CURLOPT_HEADER => true,
			CURLOPT_HTTPHEADER => array('User-Agent: Roblox/WinINet', "Cookie: $rs"),
			CURLOPT_POSTFIELDS => $asset_xml
		)
	);

	// get header & body of request
	$response = curl_exec($upload_xml);
	$header_size = curl_getinfo($upload_xml, CURLINFO_HEADER_SIZE);
	$header = substr($response, 0, $header_size);
	$body = substr($response, $header_size);
	
	// check if RS is valid
	if (preg_match('/HTTP\/1.1 302/', $header)) {
		// get new RS
		$body = uploadAsset( getRS() );
	} else {
		// error msg
		$body = "XML is invalid/ID is invalid";
	}

	// close upload_xml
	curl_close($upload_xml);

	// return avid
	return $body;
}


// --------------------------------------

if ((int)($asset_id)) {
	// upload asset and echo avid
	echo uploadAsset($current_rs);
} else {
	// error msg
	echo "ID MUST BE INTEGER";
}