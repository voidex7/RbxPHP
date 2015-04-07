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
$file_name_rs     = 'rs.txt';
$file_name_token  = 'token.txt';
$current_rs       = (file_exists($file_name_rs) ? file_get_contents($file_name_rs) : '');
$current_token    = (file_exists($file_name_token) ? file_get_contents($file_name_token) : '');

// input data
$group_id         = $_GET['groupId'];
$new_role_set_id  = $_GET['newRoleSetId'];
$target_user_id   = $_GET['targetUserId'];


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

// [Function] Change Rank
function changeRank($rs, $token) 
{
	// globalize vars
	global $group_id, $new_role_set_id, $target_user_id, $file_name_token;
	
	// setup promote_user request
	$promote_user = curl_init("http://www.roblox.com/groups/api/change-member-rank?groupId=$group_id&newRoleSetId=$new_role_set_id&targetUserId=$target_user_id");
	curl_setopt_array($promote_user,
		array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_POST => true,
			CURLOPT_HEADER => true,
			CURLOPT_HTTPHEADER => array("Cookie: $rs", "X-CSRF-TOKEN: $token")
		)
	);

	// get request's header & body
	$response = curl_exec($promote_user);
	$header_size = curl_getinfo($promote_user, CURLINFO_HEADER_SIZE);
	$header = substr($response, 0, $header_size);
	$body = substr($response, $header_size);

	// check if RS/Token is valid
	if (preg_match('/HTTP\/1.1 302/', $header)) {
		// get updated RS
		$body = changeRank(getRS(), $token);
	} else if (preg_match('/HTTP\/1.1 403/', $header)) {
		// get updated Token
		$new_token = (preg_match('/X-CSRF-TOKEN: (\S+)/', $header, $matches) ? $matches[1] : '');
		file_put_contents($file_name_token, $new_token, true);
		$body = changeRank($rs, $new_token);
	}

	// close promote_user
	curl_close($promote_user);

	// return results
	return $body;
}


// --------------------------------------

if ((int)($group_id) && (int)($new_role_set_id) && (int)($target_user_id)) {
	// change rank
	echo changeRank($current_rs, $current_token);
} else {
	// error
	echo "Input must be integers";
}