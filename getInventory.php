<?php


/*
	Gets user's whole inventory. Paramaters: userId and category
	category includes: Heads, Faces, Gears, Hats, TShirts, Shirts, Pants, Decals, Models, PLugins, Animations, Places, GamePasses, Audio, Badges, LeftArms
		RightArms, LeftLegs, RightLegs, Torsos, Packages
*/

$userId = $_GET['userId'];
$category = $_GET['category'];

$categoriesNo = array(
	'Heads' => '00',
	'Faces' => '01',
	'Gears' => '02',
	'Hats' => '03',
	'TShirts' => '04',
	'Shirts' => '05',
	'Pants' => '06',
	'Decals' => '07',
	'Models' => '08',
	'Plugins' => '09',
	'Animations' => '10',
	'Places' => '11',
	'GamePasses' => '12',
	'Audio' => '13',
	'Badges' => '14',
	'LeftArms' => '15',
	'RightArms' => '16',
	'LeftLegs' => '17',
	'RightLegs' => '18',
	'Torsos' => '19',
	'Packages' => '20'
);

$requestHeaders = array(
	"User-Agent: Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/44.0.2403.157 Safari/537.36",
	"Content-Type: application/x-www-form-urlencoded; charset=UTF-8"
);

$categoryEventTarget = ('ctl00$cphRoblox$rbxUserAssetsPane$AssetCategoryRepeater$ctl' . $categoriesNo[$category] . '$AssetCategorySelector');
$nextEventTarget = 'ctl00$cphRoblox$rbxUserAssetsPane$FooterPageSelector_Next';

$items = array();
$totalItems = 0;

// --------------------------------------------------

function getFormData($pageData, $targetEvent, $isInitial) {
	if ($isInitial) {
		$__VIEWSTATE = (preg_match('/id="__VIEWSTATE" value="(.*?)"/', $pageData, $matches) ? $matches[1] : '');
		$__VIEWSTATEGENERATOR = (preg_match('/id="__VIEWSTATEGENERATOR" value="(.*?)"/', $pageData, $matches) ? $matches[1] : '');
		$__EVENTVALIDATION = (preg_match('/id="__EVENTVALIDATION" value="(.*?)"/', $pageData, $matches) ? $matches[1] : '');
	} else {
		$__VIEWSTATE = (preg_match('/\|__VIEWSTATE\|(.*?)\|/', $pageData, $matches) ? $matches[1] : '');
		$__VIEWSTATEGENERATOR = (preg_match('/\|__VIEWSTATEGENERATOR\|(.*?)\|/', $pageData, $matches) ? $matches[1] : '');
		$__EVENTVALIDATION = (preg_match('/\|__EVENTVALIDATION\|(.*?)\|/', $pageData, $matches) ? $matches[1] : '');
	}

	return http_build_query(
		array(
			'__EVENTTARGET' => $targetEvent,
			'__EVENTARGUMENT' => '',
			'__LASTFOCUS' => '',
			'__VIEWSTATE' => $__VIEWSTATE,
			'__VIEWSTATEENCRYPTED' => '',
			'__VIEWSTATEGENERATOR' => $__VIEWSTATEGENERATOR,
			'__EVENTVALIDATION' => $__EVENTVALIDATION,
			'__ASYNCPOST' => 'true'
		)
	);
}

function getPageData($prevPageData, $targetEvent, $isPrevInitial) {
	global $userId, $requestHeaders;

	$getPage = curl_init('http://www.roblox.com/User.aspx?id=' . $userId);
	curl_setopt_array($getPage,
		array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_POST => true,
			CURLOPT_HTTPHEADER => $requestHeaders,
			CURLOPT_POSTFIELDS => getFormData($prevPageData, $targetEvent, $isPrevInitial)
		)
	);

	return curl_exec($getPage);
}

function organizeItemData($itemData) {
	global $items, $totalItems;

	$name = (preg_match('/AssetNameHyperLink".*?>(.*?)</', $itemData, $matches) ? $matches[1] : NULL);
	$assetId = (preg_match('/AssetNameHyperLink".*?\-item\?id=(\d+)">/', $itemData, $matches) ? $matches[1] : NULL);
	$creatorName = (preg_match('/AssetCreatorHyperLink".*?>(.*?)</', $itemData, $matches) ? $matches[1] : NULL);
	$creatorId = (preg_match('/AssetCreatorHyperLink".*?(\d+)/', $itemData, $matches) ? $matches[1] : NULL);
	$limitedOriginalPrice = (preg_match('/<span class="PriceInRobux notranslate">\D+Was\D+([\d,]+)/', $itemData, $matches) ? str_replace(",","",$matches[1]) : NULL);
	$isLimitedU = (preg_match('/\/images\/assetIcons\/limitedunique\.png/', $itemData, $matches) ? true : false);
	$isLimited = (preg_match('/\/images\/assetIcons\/limited\.png/', $itemData, $matches) ? true : false);
	$isExpired = (preg_match('/ctl00_cphRoblox_rbxUserAssetsPane_UserAssetsDataList_ctl.._Expired/', $itemData, $matches) ? true : false);

	if (!$limitedOriginalPrice) {
		$priceInTickets = (preg_match('/<span class="PriceInTickets notranslate">\D+([\d,]+)/', $itemData, $matches) ? str_replace(",","",$matches[1]) : NULL);
		$priceInRobux = (preg_match('/<span class="PriceInRobux notranslate">\D+([\d,]+)/', $itemData, $matches) ? str_replace(",","",$matches[1]) : NULL);
		$isOnSale = ($priceInTickets || $priceInRobux);
	} else {
		$priceInTickets = NULL;
		$priceInRobux = NULL;
		$isOnSale = NULL;
	}

	$totalItems++;

	$item = array(
		"Name" => $name,
		"AssetId" => $assetId,
		"CreatorName" => $creatorName,
		"CreatorId" => $creatorId,
		"IsOnSale" => $isOnSale,
		"IsLimitedU" => $isLimitedU,
		"IsLimited" => $isLimited,
		"IsExpired" => $isExpired,
		"PriceInTickets" => $priceInTickets,
		"PriceInRobux" => $priceInRobux,
		"LimitedOriginalPrice" => $limitedOriginalPrice
	);

	array_push($items, $item);
}

function scanForItems($pageData) {
	$itemsData = (preg_match('/<table id="ctl00_cphRoblox_rbxUserAssetsPane_UserAssetsDataList"([\s\S]*?)<\/table>/', $pageData, $matches) ? $matches[1] : '');
	$itemsArray = (preg_match_all('/<div style="padding: 5px">([\s\S]*?)<\/div>\s+<\/div>\s+<\/div>/', $itemsData, $matches) ? $matches[1] : false);
	if ($itemsArray) {
		foreach ($itemsArray as $itemData) {
			organizeItemData($itemData);
		}
	}
}

function getTotalPageNo($pageData) {
	return (preg_match('/ctl00_cphRoblox_rbxUserAssetsPane_FooterPagerLabel.*?Page 1 of (\d+)/', $pageData, $matches) ? $matches[1] : 1);
}

// --------------------------------------------------

$getInitialPage = curl_init('http://www.roblox.com/User.aspx?ID=' . $userId);
curl_setopt($getInitialPage, CURLOPT_RETURNTRANSFER, true);
$pageData = curl_exec($getInitialPage);

if ($category == "Hats") {
	scanForItems($pageData);
	$totalPageNo = getTotalPageNo($pageData);
	for ($page = 2; ($page<=$totalPageNo); $page++) {
		$pageData = getPageData($pageData, $nextEventTarget, ($page==2));
		scanForItems($pageData);
	}
} else {
	$pageData = getPageData($pageData, $categoryEventTarget, true);
	scanForItems($pageData);
	$totalPageNo = getTotalPageNo($pageData);
	for ($page = 2; ($page<=$totalPageNo); $page++) {
		$pageData = getPageData($pageData, $nextEventTarget, false);
		scanForItems($pageData);
	}
}

echo json_encode(
	array(
		'TotalItems' => $totalItems,
		'Items' => $items
	)
);