<?

$a = [
  ['a'=>1, 'b'=>7],
  ['a'=>2, 'b'=>7],
  ['a'=>3, 'b'=>7],
  ['a'=>4, 'b'=>7],
];

// foreach($a as $val) {
//   $el = $xml->addChild('picture');
//   $el->addChild('a', $val['a']);
//   $el->addChild('b', $val['b']);
// }
// echo $xml->asXML();
// file_put_contents('folders_img.xml', $xml->asXML());

// $xml = simplexml_load_file('folders_img.xml', 'SimpleXMLElement');
// $category_img_xml = $xml->xpath("//pictures/picture");
// $category_img = [];
// foreach($category_img_xml as $val) $category_img [(string)$val->namecategory] = (string)$val->img;
// print_r($category_img);
// echo preg_match('/^(http|https|ftp):[\/]{2}/i', 'http:');
$s = 'sdsdsHasseldbsdd';
echo strpos($s, 'has');

?>