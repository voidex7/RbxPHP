<?php
/*
	-READ ME-
	Modify `login_user`, `file_name_rs`, and 'file_name_token' to what you will use.
	* The script will automatically create a .txt file of `file_name_rs`, which will store the user's ROBLOSECURITY.
	** This is to avoid continuously logging in, which will activate CAPTCHA protection and break the script.
	** And also to increase performance by not obtaining ROBLOSECURITY again when it's still usable.
	* The script will automatically create a .txt file of `file_name_token`, which will store the user's TOKEN.
	** To increase performance by not obtaining TOKEN again when it's still usable.
*/

// login user data
$login_user       = 'username=&password=';
$file_name_rs     = 'rs.txt';
$file_name_token  = 'token.txt';
$stored_rs        = (file_exists($file_name_rs) ? file_get_contents($file_name_rs) : '');
$stored_token     = (file_exists($file_name_token) ? file_get_contents($file_name_token) : '');

// input data
$group_id         = $_GET['groupId'];
$new_role_set_id  = $_GET['newRoleSetId'];
$target_user_id   = $_GET['targetUserId'];


// --------------------------------------

// [function] get roblosecurity
function getRS() {
	// globalize vars
	global $login_user, $file_name_rs;

	// set up get_cookies request
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

// [function] change rank
function changeRank($rs, $token) {
	// globalize vars
	global $stored_rs, $stored_token, $group_id, $new_role_set_id, $target_user_id, $file_name_token;
	
	// set up promote_user request
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

	// check if roblosecurity/token is valid
	if (!preg_match('/HTTP\/1.1 200/', $header)) {
		if (preg_match('/HTTP\/1.1 403/', $header) && ($rs != $stored_rs || $token == $stored_token)) {
			// get updated token
			$new_token = (preg_match('/X-CSRF-TOKEN: (\S+)/', $header, $matches) ? $matches[1] : '');
			file_put_contents($file_name_token, $new_token, true);
			$body = changeRank($rs, $new_token);
		} else {
			$body = "error: invalid input/forbidden attempt";
		}
	} else {
		$body_array = json_decode($body, true);
		if ($body_array['success'] == false && $rs == $stored_rs) {
			// get updated roblosecurity
			$body = changeRank(getRS(), $token);
		}
	}

	// close promote_user
	curl_close($promote_user);

	// return results
	return $body;
}


// --------------------------------------

// change rank and echo results
echo changeRank($stored_rs, $stored_token);