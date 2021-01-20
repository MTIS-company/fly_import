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

// импортируем товары на основании массива $_POST['items']
if (array_key_exists('status', $_POST) && $_POST['status'] == 'load_products'){

$items = json_decode($_POST['items']);

$xml = simplexml_load_file(XML_URL, 'SimpleXMLElement', LIBXML_NOCDATA);


$cat_assoc = []; // масс. привязки id категории по ее name
$list = CIBlockSection::GetList([], ["IBLOCK_ID"=>IBLOCK, "CODE"=>SECTION_PREFIX."%"], true);  
while($el = $list->GetNext()) {
	$cat_assoc[$el['NAME']] = (int)$el['ID'];
}

$items_imported = 0;
$result = [];
$result [] = "Результат импорта:";
$items_from_xml = $xml->xpath("//shop/itemlist/item");
foreach($items_from_xml as $i) { // перебор массива импортируемых товаров, полученного через $_POST
	//$i = $xml->xpath("//shop/itemlist/item[idproduct=$val]")[0];

if (array_search((int)$i->idproduct, $items) === false) continue;
	if (isset($cat_assoc[(string)$i->namecategoryproduct])) {
		$i->idcategory = $cat_assoc[(string)$i->namecategoryproduct];
	}
	else {
		$i->idcategory = SECTION;
		$result[] = "Товар ".$i->nameproduct." будет импортирован в корневой раздел";
	};

		// импорт одного товара:
  $arFields = [
    "IBLOCK_ID" => IBLOCK,
    "IBLOCK_SECTION_ID"=>$i->idcategory,
    "NAME" => $i->nameproduct,
    "XML_ID" => $i->idproduct, // входящий id
    "ACTIVE"=>$i->aviability ? "Y" : "N",
    "CODE"=> ITEM_PREFIX.$i->idproduct, // добавляем входящий id к символьному коду раздела (создаем уникальный код во избежание дублирования при последующем импорте)
    "DETAIL_TEXT"=>$i->descriptionproduct,
    "DETAIL_TEXT_TYPE"=>'html',
  ];

	$ID = $b_el->Add($arFields);
	$items_imported ++;
	if ($ID) {
		$result[] = "Импортирован: ".$i->nameproduct;
		$photo = $file->MakeFileArray($i->pictureproduct);
		$pictures = [];
		foreach($i->picturesproduct->picturesproduct as $val) $pictures[] = $file->MakeFileArray($val);

		$b_el->Update($ID, [
  			'PREVIEW_PICTURE'=>$photo,
        'DETAIL_PICTURE'=>$photo,
        'PROPERTY_VALUES' => [
          'MORE_PHOTO'=>$pictures,
          'BRAND'=>$i->namebrand,
          'CML2_ARTICLE'=>$i->articulproduct,
          'code_element'=>$i->codeproduct,
        ]
    ]);
  
		$ar_prices = [
      "PRODUCT_ID" => $ID,
			"CATALOG_GROUP_ID" => 1,
      "PRICE" => (int)$i->price2value,
      "CURRENCY" => "UAH",
    ];
    $PRID = CPrice::Add($ar_prices);
		if (!$PRID) $result[] = "Ошибка импорта цен: ".$i->nameproduct.' '.$APPLICATION->GetException();

    $price_list=$price->GetList([],[
      "PRODUCT_ID" => $ID,
      "CATALOG_GROUP_ID" => 1
    ]);

    if (!CCatalogProduct::IsExistProduct($ID)) {
      CCatalogProduct::Add([
        "ID" => $ID,
				"PURCHASING_PRICE"=>(int)$i->price1value,
				"PURCHASING_CURRENCY"=>'UAH'
      ]);
    }
  }
  if ($b_el->LAST_ERROR) $result[] = $b_el->LAST_ERROR." ".$i->nameproduct;
}; // end foreach

exit (json_encode($result, JSON_UNESCAPED_UNICODE));

}; //endif
?>
