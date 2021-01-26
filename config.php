<?
  const XML_URL = "https://portal.flytechnology.ua/media/export/product/flytech.xml";
  const IBLOCK = 21; // инфоблок каталога flytechnologies
  const SECTION = 6146; // id корневого раздела для данного каталога товаров
  const CATALOG_PREFIX = 'flytechnology'; // префикс поля xml_id и символьного кода разделов. Служит для идентификации принадлежности категорий и товаров к каталогу flytechnology
  const ITEM_PREFIX = 'flytechnology_item'; // префикс символьного кода товаров

  function parse_int ($str) { // возв. числовую часть строки
    return preg_replace('/[^0-9]/', '', $str);
  };
  
?>