<?php
/*
	-READ ME-
	Create two .txt file for storing ROBLOSECURITY and Token.
		To increase performance by not obtaining them again when they're still usable.
	Look at `Login User Data` and modify it to what you will use.
*/

// Login User Data
$login_user       = 'username=&password=';
$file_path_rs     = 'rs.txt';
$file_path_token  = 'token.txt';
$current_rs       = file_get_contents($file_path_rs);
$current_token    = file_get_contents($file_path_token);

// Input
$group_id         = $_GET['groupId'];
$new_role_set_id  = $_GET['newRoleSetId'];
$target_user_id   = $_GET['targetUserId'];

// [Function] Get ROBLOSECRUITY
function getRS()
{
	global $login_user, $file_path_rs;

	$get_cookies = curl_init('https://www.roblox.com/newlogin');
	curl_setopt_array($get_cookies,
		array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HEADER => true,
			CURLOPT_POST => true,
			// CURLOPT_HTTPHEADER => array("Content-Length: " . strlen($login_user)),
			CURLOPT_POSTFIELDS => $login_user
		)
	);

	$rs = (preg_match('/(\.ROBLOSECURITY=.*?);/', curl_exec($get_cookies), $matches) ? $matches[1] : '');
	file_put_contents($file_path_rs, $rs, true);
	curl_close($get_cookies);

	return $rs;
}

// [Function] Change Rank
function changeRank($rs, $token) 
{
	global $group_id, $new_role_set_id, $target_user_id, $file_path_token;
	
	$promote_user = curl_init("http://www.roblox.com/groups/api/change-member-rank?groupId=$group_id&newRoleSetId=$new_role_set_id&targetUserId=$target_user_id");
	curl_setopt_array($promote_user,
		array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_POST => true,
			CURLOPT_HEADER => true,
			CURLOPT_HTTPHEADER => array("Cookie: $rs", "X-CSRF-TOKEN: $token")
		)
	);

	$resp = curl_exec($promote_user);
	$resp_header_size = curl_getinfo($promote_user, CURLINFO_HEADER_SIZE);
	$resp_header = substr($resp, 0, $resp_header_size);
	$resp_body = substr($resp, $resp_header_size);

	if (preg_match('/GuestData/', $resp_header)) {
		// RS invalid
		$resp_body = changeRank( getRS(), $token );
	} else if (preg_match('/Token Validation Failed/', $resp_header)) {
		// Token invalid
		$new_token = (preg_match('/X-CSRF-TOKEN: (\S+)/', $resp_header, $matches) ? $matches[1] : '');
		file_put_contents($file_path_token, $new_token, true);
		$resp_body = changeRank( $rs, $new_token );
	}

	curl_close($promote_user);

	return $resp_body;
}

// Change rank and echo results
echo changeRank($current_rs, $current_token);
