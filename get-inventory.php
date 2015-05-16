<?php
/*
	-READ ME-
	Modify `login_user` and `file_name_rs` to what you will use.
	* The script will automatically create a .txt file of `file_name_rs`, which will store the user's ROBLOSECURITY.
	** This is to avoid continuously logging in, which will activate CAPTCHA protection and break the script.
	** And also to increase performance by not obtaining ROBLOSECURITY again when it's still usable.
*/

// Login User Data
$login_user    = 'username=&password=';
$file_name_rs  = 'rs.txt';
$stored_rs     = (file_exists($file_name_rs) ? file_get_contents($file_name_rs) : '');

// Input
$user_id = $_GET['userId'];

// Output
$inventory = array();
$total_rap = 0;


// --------------------------------------


// [Function] Get `ROBLOSECURITY` Cookie
function getRS() {
	global $login_user, $file_name_rs;

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
	file_put_contents($file_name_rs, $rs, true);
	curl_close($get_cookies);

	return $rs;
}

// [Function] Get Inventory's Page Data
function getInvPage($rs, $filter, $page) {
	global $user_id;

	$get_page_data = curl_init("http://www.roblox.com/Trade/InventoryHandler.ashx?userId=$user_id&filter=$filter&page=$page&itemsPerPage=14");
	curl_setopt_array($get_page_data,
		array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPHEADER => array("Cookie: $rs")
		)
	);

	return $get_page_data;
}

// [Function] Organize Item
function organizeItem($item_data) {
	global $total_rap;

	$name = $item_data['Name'];
	$link = $item_data['ItemLink'];
	$id = (preg_match('/\?id=(\d+)$/', $link, $matches) ? $matches[1] : '');
	$serial = $item_data['SerialNumber'];
	$total_serial = $item_data['SerialNumberTotal'];
	$uaid = $item_data['UserAssetID'];
	$is_ulimited = ($serial != '---' && $userId != 1);
	$rap = $item_data['AveragePrice'];
	$total_rap += $rap;

	return array(
		'Name' => $name,
		'AssetId' => $id,
		'Serial' => ($is_ulimited ? $serial : 'NA'),
		'SerialTotal' => ($is_ulimited ? $total_serial : "NA"),
		'RAP' => $rap
	);			
}



// --------------------------------------------------------------------


// [Operation] Start Deriving Inventory


$requests_handler = curl_multi_init();
$requests = array();

$hats_data = curl_exec(getInvPage($stored_rs, 0, 1));

if ($hats_data == "") {
	$rs = getRS();
	$hats_data = curl_exec(getInvPage($rs, 0, 1));
} else {
	$rs = $stored_rs;
}

$gears_data = curl_exec(getInvPage($rs, 1, 1));
$faces_data = curl_exec(getInvPage($rs, 2, 1));

foreach (array($hats_data, $gears_data, $faces_data) as $filter => $filter_data) {
	$filter_data = json_decode($filter_data, true);
	if ($filter_data['msg'] == 'Inventory retreived!') {
		$count = $filter_data['data']['totalNumber'];
		foreach ($filter_data['data']['InventoryItems'] as $index => $item_data) {
		    array_push($inventory, organizeItem($item_data));
		}
		for ($page = 2; $page <= ceil($count/14); $page++) {
			$request = getInvPage($rs, $filter, $page);
			array_push($requests, $request);
			curl_multi_add_handle($requests_handler, $request);
		}
	}
}

do {
	curl_multi_exec($requests_handler, $running);
	curl_multi_select($requests_handler);
} while ($running > 0);


foreach ($requests as $index => $request) {
	$page_data = json_decode(curl_multi_getcontent($request), true);
	foreach ($page_data['data']['InventoryItems'] as $index => $item_data) {
		array_push($inventory, organizeItem($item_data));
	}
	curl_multi_remove_handle($requests_handler, $request);
}


curl_multi_close($requests_handler);



// --------------------------------------------------------------------


// Echo Inventory & TotalRAP
echo json_encode(
	array(
		'TotalRAP' => $total_rap,
		'Inventory' => $inventory
	)
);