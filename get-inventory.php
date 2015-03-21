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
$user_id = $_GET['userId'];

// Output
$inventory = array();
$total_rap = 0;

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

// [Function] Get Inventory's Page Data
function getInvPage($rs, $filter, $page)
{
	global $user_id, $inv_link;

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
function organizeItem($item_data)
{
	global $total_rap;

	// All data
	$name = $item_data['Name'];
	$link = $item_data['ItemLink'];
	$id = (preg_match('/\?id=(\d+)$/', $link, $matches) ? $matches[1] : '');
	$serial = $item_data['SerialNumber'];
	$total_serial = $item_data['SerialNumberTotal'];
	$uaid = $item_data['UserAssetID'];
	$is_ulimited = ($serial != '---' && $userId != 1);
	$rap = $item_data['AveragePrice'];

	// Add up to total rap
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
// ---                            ---//
//      Start deriving inventory 
// ---                            ---//


// Get initial data
$hats_data = curl_exec(getInvPage($current_rs, 0, 1));

if ($hats_data == "") {
	// RS invalid
	$rs = updateRS();
	$hats_data = getInvPage($rs, 0, 1);
} else {
	$rs = $current_rs;
}

$gears_data = curl_exec(getInvPage($rs, 1, 1));
$faces_data = curl_exec(getInvPage($rs, 2, 1));

// Scan through inventory
$multi_requests = curl_multi_init();
$requests = array();

foreach (array($hats_data, $gears_data, $faces_data) as $filter => $filter_data) {
	$filter_data = json_decode($filter_data, true);
	if ($filter_data['msg'] != '#00000') {
		$count = $filter_data['data']['totalNumber'];
		foreach ($filter_data['data']['InventoryItems'] as $index => $item_data) {
		    array_push($inventory, organizeItem($item_data));
		}
		for ($page = 2; $page <= ceil($count/14); $page++) {
			$request = getInvPage($rs, $filter, $page);
			array_push($requests, $request);
			curl_multi_add_handle($multi_requests, $request);
		}
	}
}

do {
	curl_multi_exec($multi_requests, $running);
	curl_multi_select($multi_requests);
} while ($running > 0);

foreach ($requests as $index => $request) {
	$page_data = json_decode(curl_multi_getcontent($request), true);
	foreach ($page_data['data']['InventoryItems'] as $index => $item_data) {
		array_push($inventory, organizeItem($item_data));
	}
	curl_multi_remove_handle($multi_requests, $request);
}

curl_multi_close($multi_requests);


// --------------------------------------------------------------------

// Echo inventory & total RAP
echo json_encode(
	array(
		'TotalRAP' => $total_rap,
		'Inventory' => $inventory
	)
);