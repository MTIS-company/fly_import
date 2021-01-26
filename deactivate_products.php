<?
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
CModule::IncludeModule("iblock");
CModule::IncludeModule("catalog");

$b_el = new CIBlockElement;
set_time_limit(0);

if (!isset($_POST['items'])) exit(json_encode(['Ошибка '=> ' не передан массив данных'], JSON_UNESCAPED_UNICODE));
if (!isset($_POST['active'])) exit(json_encode(['Ошибка '=> ' не передано значение active'], JSON_UNESCAPED_UNICODE));

$result = [];
// деактивируем товары на основании массива $_POST['items']
  $items = json_decode($_POST['items']);
	foreach($items as $val) {
    $b_el->Update((int)$val, ["ACTIVE"=>$_POST['active']]);
    $result[] = [$val, $_POST['active']];
		if ($b_el->LAST_ERROR) $result[] = $b_el->LAST_ERROR." ".$val;
	}

exit (json_encode($result, JSON_UNESCAPED_UNICODE));
?>