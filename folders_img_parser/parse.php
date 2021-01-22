<?
include_once 'phpQuery.php';
// ! - парсим русскоязычный контент /ru
$html = phpQuery::newDocument(file_get_contents('https://flytechnology.ua/ru'));
$main_menu = [];
$dom = $html->find('#oct-menu-dropdown-menu a.oct-menu-a');
foreach($dom as $val) {
  $i = pq($val);
  $main_menu [] = [
    'href'=>$i->attr('href'),
    'name'=>$i->text()
  ];
}

$pictures = [];
function get_low_level ($link, &$res) { // рекурсивная ф-я для получения объектов след. уровня вложенности разделов меню
  // сохраняет результат в глобальном массиве $pictures
  $html = phpQuery::newDocument(file_get_contents($link)); // получаем html по ссылке $link
  $sub_menu = $html->find('a.subcat-item'); // массив разделов текущего уровня
  if (count($sub_menu) < 1) return;
  foreach($sub_menu as $val) { // перебор разделов тек. уровня
    $i = pq($val);
    $img = $i->find('img.subcat-item-img');
    $img_src = $img->attr('src');
    
    $res [] = ['namecategory'=>$i->text(), 'img'=>$img_src];
    get_low_level($i->attr('href'), $res);
  }
}

foreach($main_menu as $link) { // перебор эл-тов гл.меню
  get_low_level($link['href'], $pictures);
}

echo count($pictures)."<br>";
// foreach($pictures as $val) echo $val['namecategory']."<img src='{$val['img']}'><br>";

$xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><pictures/>');
foreach($pictures as $val) {
  $el = $xml->addChild('picture');
  $el->addChild('namecategory', trim($val['namecategory']));
  $el->addChild('img', trim($val['img']));
}
echo $xml->asXML();
file_put_contents('folders_img.xml', $xml->asXML());

?>


