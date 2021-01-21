<?
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
CModule::IncludeModule("iblock");
CModule::IncludeModule("catalog");

$bs = new CIBlockSection;
$b_el = new CIBlockElement;
$file = new CFile;
$price = new CPrice;
require "config.php";
set_time_limit(0);

$result = [];
// деактивируем товары на основании массива $_POST['items']
if (array_key_exists('status', $_POST) && $_POST['status'] == 'active_products'){
  $items = json_decode($_POST['items']);
	foreach($items as $val) {
		$b_el->Update($val, ["ACTIVE"=>$_POST['active']]);
		if ($b_el->LAST_ERROR) $result[] = $b_el->LAST_ERROR." ".$val;
	}
}

exit (json_encode($result, JSON_UNESCAPED_UNICODE));
?>