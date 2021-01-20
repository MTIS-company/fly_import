<?

$a = [
  ['a'=>1, 'b'=>7],
  ['a'=>2, 'b'=>7],
  ['a'=>3, 'b'=>7],
  ['a'=>4, 'b'=>7],
];

function filter ($val) {
  if ($val['a'] == 2) return true;
};
print_r(array_filter($a, 'filter'));
?>