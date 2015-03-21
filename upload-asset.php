<?php
/*
	-READ ME-
	Create a .txt file for storing ROBLOSECURITY.
		To increase performance by not obtaining it again when it's still usable.
	Look at `Login User Data` and modify it to what you will use.
*/

// Login User Data
$login_user    = 'username=&password=';
$file_path_rs  = 'rs.txt';
$current_rs    = file_get_contents($file_path_rs);

// Input
$asset_id   = $_GET['id'];
$post_body  = file_get_contents('php://input');
$asset_xml  = (ord(substr($post_body,0,1)) == 31 ? gzinflate(substr($post_body,10,-8)) : $post_body); // if gzipped, decode

// [Function] Update ROBLOSECRUITY
function updateRS()
{
	global $login_user, $file_path_rs;

	$get_cookies = curl_init('https://www.roblox.com/newlogin');
	curl_setopt_array($get_cookies,
		array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HEADER => true,
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => $login_user
		)
	);

	$rs = (preg_match('/(\.ROBLOSECURITY=.*?);/', curl_exec($get_cookies), $matches) ? $matches[1] : '');
	file_put_contents($file_path_rs, $rs, true);
	curl_close($get_cookies);

	return $rs;
}

// [Function] Upload Asset
function uploadAsset($rs) 
{
	global $asset_id, $asset_xml;
	
	$upload_xml = curl_init("http://www.roblox.com/Data/Upload.ashx?assetid=$asset_id");
	curl_setopt_array($upload_xml,
		array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_POST => true,
			CURLOPT_HTTPHEADER => array('User-Agent: Roblox/WinINet', "Cookie: $rs"),
			CURLOPT_POSTFIELDS => $asset_xml
		)
	);

	$avid = curl_exec($upload_xml);
	if (preg_match('/RobloxDefaultErrorPage/', $avid)) {
		// RS invalid
		$avid = uploadAsset(updateRS());
	}

	curl_close($upload_xml);

	return $avid;
}

// Upload asset and echo AVID
$avid = uploadAsset($current_rs);
echo (is_numeric($avid) ? $avid : 0);