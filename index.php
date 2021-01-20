<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Импорт flytechnology");
$cmain = new CMain;
$cmain->SetAdditionalCSS($cmain->GetCurPage().'styles.css');
?>

<?
  require "config.php";
  set_time_limit(0);

  CModule::IncludeModule("iblock");
  CModule::IncludeModule("catalog");
  $bs = new CIBlockSection;
  $b_el = new CIBlockElement;
  $file = new CFile;
  $price = new CPrice;
?>

<h2>Импорт разделов и товаров из каталога flytechnology.ua</h2>

<?if (!array_key_exists('status', $_POST)): // первичная загрузка страницы?> 

<form action="" name="ft-import-form" method="POST">
    <span>URL каталога:</span>
    <input type="text" value=<?=XML_URL?> name="xml_url" action="">
    <input type="hidden" name="status" value="xml_loaded">
    <button id="import-section" type="submit">Импорт</button>
  </form>
  <img id="loading" style="opacity:0" src="loading.gif" alt="">
  <script>
    document.querySelector('#import-section').addEventListener('click', ()=>{document.querySelector('#loading').style.opacity=1});
  </script>
<?endif;?>

<?if (array_key_exists('status', $_POST) && $_POST['status'] == 'xml_loaded'): // обработка нажатия кнопки импорт

$read_xml = simplexml_load_file($_POST['xml_url'], 'SimpleXMLElement');
if (!$read_xml):?>
	<h2>Ошибка чтения файла <?=$_POST['xml_url']?> </h2>
	<? 
	exit();
endif;

  $categories = $read_xml->xpath("//shop/categories/category");
  //импорт разделов
  $cat_assoc = []; // для замены name на id
  foreach ($categories as &$item) {
    $item->idcategory = trim((string)$item->idcategory);
    $item->namecategory = trim((string)$item->namecategory);
    $item->parentcategory = trim((string)$item->parentcategory);
    $cat_assoc[(string)$item->namecategory] = (int)$item->idcategory; 
  }
  
  $new_sections = []; // новые разделы

  foreach ($categories as $i) { // импортируем разделы в таблицы бд
    $arFields = [
    "IBLOCK_ID" => IBLOCK,
    "IBLOCK_SECTION_ID"=> SECTION,
    "NAME" => $i->namecategory,
    "XML_ID" => $i->idcategory, // входящий id
    "CODE" => SECTION_PREFIX.$i->idcategory // добавляем входящий id к символьному коду раздела (во избежание дублирования при последующем импорте)
    ];

  
    $ID = $bs->Add($arFields); // создаем новую запись
    // если новый раздел был создан - добавляем запись в масив $new_sections:
    if ($ID) $new_sections [] = ['id'=>$ID, 'xmlparentcategory'=>$i->idparentcategory];
  }
  
  $parent_change = []; // массив для замены родительских id новыми значениями:
  // выборка всех папок включая вложеные с символьным кодом SECTION_PREFIX 
  $list = CIBlockSection::GetList([], ["IBLOCK_ID"=>IBLOCK, "CODE"=>SECTION_PREFIX."%"], true);  
  while($el = $list->GetNext()) $parent_change[$el['XML_ID']] = (int)$el['ID'] ? (int)$el['ID'] : SECTION;

  // заменяем входящие id разделов реальными id в новосозданных записях:
  foreach ($new_sections as $value) {
    $bs->Update($value['id'], ["IBLOCK_SECTION_ID"=>$parent_change[(int)$value['xmlparentcategory']]]);
  }

// -------------------------------- конец обработки списка разделов товаров (папок) ----------------------
?> 

<ul id="ft-sectionsinfo">
  <li class="bold">Всего разделов в каталоге Flytechnology: <?=count($categories)?></li>
  <li class="bold">Импортировано разделов из каталога Flytechnology: <?=count($new_sections)?></li>
</ul>

<? // --------------------------- обработка списка товаров -------------------------

$existing_items_request = $b_el->GetList( // создаем список товаров, уже существующих в базе
 [(int)"XML_ID"=>"ASC"],
 ["IBLOCK_ID"=>IBLOCK, "CODE"=>ITEM_PREFIX."%"],
 false,
 false,
 ["ID", "IBLOCK_ID", "XML_ID", "NAME", "IBLOCK_SECTION_ID", "ACTIVE"]
);
$existing_items_list = []; // товары, существующие в базе
$navi_items_list = []; // все товары navi
while ($el = $existing_items_request->GetNext()) {
  $existing_items_list [] = (int)$el['XML_ID'];
  $navi_items_list[] = 
    [ 
      'xml_id' => (int)$el['XML_ID'],
      'name' => (string)$el['NAME'],
      'id' => (int)$el['ID'],
      'parent' => (int)$el['IBLOCK_SECTION_ID'],
      'active' => (string)$el['ACTIVE']
    ];
}
sort($existing_items_list);

$items_xml = simplexml_load_file($_POST['xml_url'], 'SimpleXMLElement', LIBXML_NOCDATA)->xpath("//shop/itemlist/item[idproduct>=50]");

$items_xml_id = []; // для поиска товаров, не существующих в xml но существующих в базе (для деактивации)
foreach($items_xml as $val) $items_xml_id[] = (int)$val->idproduct;
$deactivate = array_diff($existing_items_list, $items_xml_id); // товары, отсутствующие в xml
// unset ($items_xml_id);

// актуализируем массив активации/деактивации:
$activate_list = [];
$deactivate_list = [];
foreach($navi_items_list as &$val) { // перебор всех товаров navi
  if (array_search($val['xml_id'], $deactivate) !== false && strtoupper($val['active']) != 'N') $deactivate_list[] = $val;
  if ($val['active'] == 'N' && array_search($val['xml_id'], $items_xml_id) != false) $activate_list[] = $val;
}
unset($navi_items_list);

$activate_count = count($activate_count);
$deactivate_count = count($deactivate_count);

$items = []; // список товаров из каталога xml для вывода на экран
$new_products = 0;
foreach ($items_xml as $val) {
	$exists = array_search( (int)$val->idproduct, $existing_items_list)===false ? 0 : 1;
	if (!$exists) $new_products ++;
	if ($val) $items[] = ['nameproduct'=>$val->nameproduct, 'idproduct'=>(int)$val->idproduct, 'exists'=>$exists];
}

//сортируем по полю exists (desc)
function compare_sort($a, $b){
  if ($a == $b) return 0;
  return ($a['exists'] < $b['exists']) ? -1 : 1;
};

usort($items, 'compare_sort');

unset($items_xml);

?>


<? // деактивация:
if ($deactivate_count > 0):?>
	<hr>
  <ul id="ft-deactivate">
    <li class="bold">Товары, отсутствующие в каталоге flytechnology: <?=$deactivate_count?></li>
    <li>
      <button id="ft-import-deactivate-view">Показать список</button>
      <button id="ft-import-deactivate-total">Выбрать все</button>
      <button id="ft-import-deactivate-clear">Отменить все</button>
      <button id="ft-import-deactivate-selected" disabled="">Деактивировать выбранные</button>
    </li>
	  <li>Выбрано: <span id="deactivate-selection">0</span> товаров</li>
  </ul>
  <ul id="ft-import-deactivate-list" style="display: none">
    <? foreach($deactivate_list as $val):?>
			<li>
				<label>
				<input type="checkbox" class="deactivate-item" data-id="<?=$val['id']?>">
				<?=$val['name']?>
				<a href="/bitrix/admin/iblock_element_edit.php?IBLOCK_ID=<?=IBLOCK?>&type=aspro_next_catalog&lang=ru&ID=<?=$val['id']?>&find_section_section=<?=$val['parent']?>&WF=Y" target="blanc">
					<img class="ft-product-view" src="view.png" title="Просмотр товара">
				</a>
				</label>
			</li>
    <?endforeach;?>
  </ul>
<?endif; // деактивация.?>

<? // активация:

function unactive_filter($val) {
  if ($val['active'] == 'N') return true;
  return false;
};

if ($activate_count > 0):?>
	<hr>
  <ul id="ft-activate">
    <li class="bold">Неактивные товары в каталоге NAVI: <?=$activate_count?></li>
    <li>
      <button id="ft-import-activate-view">Показать список</button>
      <button id="ft-import-activate-total">Выбрать все</button>
      <button id="ft-import-activate-clear">Отменить все</button>
      <button id="ft-import-activate-selected" disabled="">Активировать выбранные</button>
    </li>
	  <li>Выбрано: <span id="activate-selection">0</span> товаров</li>
  </ul>
  <ul id="ft-import-activate-list" style="display: none">
    <? foreach($activate_list as $val):?>
		<?if ($val['to_activate']):?>
			<li>
				<label>
				<input type="checkbox" class="activate-item" data-id="<?=$val['id']?>">
				<?=$val['name']?>
				<a href="/bitrix/admin/iblock_element_edit.php?IBLOCK_ID=<?=IBLOCK?>&type=aspro_next_catalog&lang=ru&ID=<?=$val['id']?>&find_section_section=<?=$val['parent']?>&WF=Y" target="blanc">
					<img class="ft-product-view" src="view.png" title="Просмотр товара">
				</a>
				</label>
			</li>

	  <?endif;?>
    <?endforeach;?>
  </ul>
<?endif; // активация.?>


<hr>
<ul id="ft-products-import">
  <li class="bold">Товары в каталоге flytechnology: <?=count($items)?></li>
  <li class="bold">Товары в каталоге NAVI: <?=count($existing_items_list);?></li>

<?
	foreach($items as $val) {

if (array_search((int)$val['idproduct'], $deactivate)) echo $val['nameproduct'];
	}
?>
	<li class="bold">Новые товары в каталоге flytechnology: <?=$new_products;?></li>
  <li>
    <button id="ft-import-read-more">Показать список</button>
    <button id="ft-import-select">Выбрать все</button>
    <button id="ft-import-unselect">Отменить все</button>
    <button id="ft-import-products" disabled="">Импортировать выбранные</button>
  </li>
  <li>Выбрано: <span id="selection">0</span> товаров</li>
</ul>
<ul id="ft-import-products-list">
  <span class="bold">Товары в каталоге flytechnology (<?=count($items)?>):</span>
  <?foreach ($items as $key=>$val):?>
    <li>
      <?$disabled = $val['exists'] ? ' disabled=""':'';?>
      <label <?=$disabled;?>>
        <input  <?=$disabled;?> class="item" type="checkbox" data-id=<?=$val[idproduct]?> >
        <span><?=$val['nameproduct']?></span>
        <?if ($disabled):?>
          <a href="/bitrix/admin/iblock_element_edit.php?IBLOCK_ID=<?=IBLOCK?>&type=aspro_next_catalog&lang=ru&ID=<?=$val['id']?>&find_section_section=<?=$val['parent']?>&WF=Y" target="blanc">
            <img class="ft-product-view" src="view.png" title="Просмотр товара">
          </a>
          <span class="ft-import-product-exists"> есть в каталоге NAVI </span>
          <?endif;?>
      </label>
    </li> 
  <?endforeach;?>
</ul>

<script>
let itemsListVisible = false;
let itemList = document.querySelectorAll('.item');

const checkedCalc = (elemsSelector, countElem, submitElem)=>{
  // подсчитывает к-во элементов по селектору elemList, помещает результат в элемент countElem и активирует/деактивирует кнопку submitElem если есть хоть один элемент, соответствующий классу elemList
  let len = document.querySelectorAll(elemsSelector).length;
  document.querySelector(countElem).innerHTML = len;
  let activateBtn = document.querySelector(submitElem);
  if (len) {activateBtn.removeAttribute('disabled')}
  else activateBtn.setAttribute('disabled', "");
}

const activateProcess = (arraySelector, messageSelector, title, finishTitle, value) => {
    // активация/деактивация выбранных эл-тов
    // arraySelector - массив DOM-элементов с аттрибутом data-id для передачи в ajax.
    // messageSelector - DOM-элемент для отображения статуса процесса
    // title - текст статуса начала процесса
    // finishTitle - текст статуса завершения процесса
    // value - значение поля ACTIVE ('Y' или 'N')
    let itemSelected = document.querySelectorAll(arraySelector);
    let selected = [];
    itemSelected.forEach((i)=>selected.push(i.dataset.id));
    let deactivateSection = document.querySelector(messageSelector);
    deactivateSection.innerHTML = `<h2>${title}</h2>`;
    deactivateSection.innerHTML += `<img src="loading.gif" alt="">`;
    deactivateSection.innerHTML += `<h3 id="activate-progress"> </h3>`;
    let progress = document.querySelector('#activate-progress');
    let index = 0;
    let success = true;
    let interval = setInterval(()=>{
      if (success) {
        success = false;
              BX.ajax({
                  url: 'deactivate_products.php',
                  data: {
                    "status" : 'active_products',
                    "active" : value,
                    "items": JSON.stringify(selected.slice(index, index+5)),
                    "xml_url" : "<?=$_POST['xml_url']?>"
                  },
                  method: 'POST',
                  dataType: 'json',
                  onsuccess: function(data){
                    index += 5;
                    success = true;
                    if (index >= selected.length) {
                      index = selected.length;
                      clearInterval(interval);
                      deactivateSection.innerHTML = `<h2>${finishTitle}</h2>`;
                      deactivateSection.innerHTML += `Обработано: ${index} товаров`;
                      console.log("Response: ", data);
                    } else
                    {
                      console.log('Processed: ', index, ' of ', selected.length);
                      progress.innerHTML = `Обработано ${index} из ${selected.length} товаров`;
                    }
                  }
              });
        } // if success
    }, 1000); // setInterval
  }

const itemsCheckedCalc = ()=> {
  checkedCalc('.item:checked', '#selection', '#ft-import-products');
}

itemList.forEach((i)=>i.addEventListener('change', itemsCheckedCalc));

document.querySelector('#ft-import-read-more').addEventListener('click', (e)=>{
  let el = document.querySelector('#ft-import-products-list');
  if (!itemsListVisible) {
    el.style.display = "block";
    e.target.innerHTML = "Скрыть список";
  } 
  else {
    el.style.display = "none";
    e.target.innerHTML = "Показать список";
  }
  itemsListVisible = !itemsListVisible;
})

document.querySelector('#ft-import-select').addEventListener('click', ()=>
{
  itemList.forEach((i)=> {if (i.getAttribute('disabled') === null) i.checked=true});
  itemsCheckedCalc();
})
document.querySelector('#ft-import-unselect').addEventListener('click', ()=>{
  itemList.forEach((i)=>i.checked=false);
  itemsCheckedCalc();
})

document.querySelector('#ft-import-products').addEventListener('click', ()=>{
  let itemSelected = document.querySelectorAll('.item:checked');
  let selected = [];
  itemSelected.forEach((i)=>selected.push(i.dataset.id));
  document.querySelector("#ft-sectionsinfo").innerHTML = "";
  let mainSection = document.querySelector('#ft-products-import');
  mainSection.innerHTML = "<h2> Импортируются выбранные товары...</h2>";
  mainSection.innerHTML += `<img id="loading" src="loading.gif" alt="">`;
  mainSection.innerHTML += `<h3 id="progress"> </h3>`;
  let progress = document.getElementById('progress');

  let index = 0;
  let success = true;
  let interval = setInterval(()=>{
    if (success) {
      success = false;
            BX.ajax({
                url: 'import_products.php',
                data: {
                  "status" : 'load_products',
                  "items": JSON.stringify(selected.slice(index, index+5)),
                  "xml_url" : "<?=$_POST['xml_url']?>"
                },
                method: 'POST',
                dataType: 'json',
                onsuccess: function(data){

          index += 5;

          success = true;
          if (index >= selected.length) {
            index = selected.length;
            clearInterval(interval);
            mainSection.innerHTML = "<h2>Импорт товаров завершен.</h2>";
			  mainSection.innerHTML += `Импортировано ${index} товаров`;
            document.querySelector("#ft-import-products-list").innerHTML = "";
          }
          console.log('Imported: ', index, ' from ', selected.length);
          progress.innerHTML = `Импортировано ${index} из ${selected.length} товаров`;
                }
            });
      } // if success
  }, 1000); // setInterval
}); // ('#ft-import-products').addEventListener

</script>

<?if ($activate_count > 0): // скрипт активации?>
<script>

  let activate = document.querySelectorAll('.activate-item');
  let activateListVisible = false;

  document.querySelector('#ft-import-activate-view').addEventListener('click', ()=>{
    document.querySelector('#ft-import-activate-list').style.display='block';
  });

  document.querySelector('#ft-import-activate-view').addEventListener('click', (e)=>{
  let el = document.querySelector('#ft-import-activate-list');
  if (!activateListVisible) {
    el.style.display = "block";
    e.target.innerHTML = "Скрыть список";
  } 
  else {
    el.style.display = "none";
    e.target.innerHTML = "Показать список";
  }
  activateListVisible = !activateListVisible;
})

  const activateCheckedCalc = ()=> {
    checkedCalc('.activate-item:checked', '#activate-selection', '#ft-import-activate-selected');
  }

  activate.forEach((i)=>i.addEventListener('change', activateCheckedCalc));

  document.querySelector('#ft-import-activate-total').addEventListener('click', ()=>
  {
    activate.forEach((i)=> {if (i.getAttribute('disabled') === null) i.checked=true});
    activateCheckedCalc();
  })
  document.querySelector('#ft-import-activate-clear').addEventListener('click', ()=>{
    activate.forEach((i)=>i.checked=false);
    activateCheckedCalc();
  });
  document.querySelector('#ft-import-activate-selected').addEventListener('click', ()=>{
    document.querySelector('#ft-import-activate-list').style.display = "none";
    activateProcess('.activate-item:checked', '#ft-activate', 'Активация выбранных товаров...', 'Активация завершена.', 'Y');
  });
  
</script>
<?endif; // конец скрипта активации?>

<?if ($deactivate_count > 0): // скрипт деактивации:?>
<script>

  let deactivate = document.querySelectorAll('.deactivate-item');
  let deactivateListVisible = false;

  document.querySelector('#ft-import-deactivate-view').addEventListener('click', ()=>{
    document.querySelector('#ft-import-deactivate-list').style.display='block';
  });

  document.querySelector('#ft-import-deactivate-view').addEventListener('click', (e)=>{
  let el = document.querySelector('#ft-import-deactivate-list');
  if (!deactivateListVisible) {
    el.style.display = "block";
    e.target.innerHTML = "Скрыть список";
  } 
  else {
    el.style.display = "none";
    e.target.innerHTML = "Показать список";
  }
  deactivateListVisible = !deactivateListVisible;
})


  const deactivateCheckedCalc = ()=> {
    checkedCalc('.deactivate-item:checked', '#deactivate-selection', '#ft-import-deactivate-selected');
  }

  deactivate.forEach((i)=>i.addEventListener('change', deactivateCheckedCalc));

  document.querySelector('#ft-import-deactivate-total').addEventListener('click', ()=>
  {
    deactivate.forEach((i)=> {if (i.getAttribute('disabled') === null) i.checked=true});
    deactivateCheckedCalc();
  })
  document.querySelector('#ft-import-deactivate-clear').addEventListener('click', ()=>{
    deactivate.forEach((i)=>i.checked=false);
    deactivateCheckedCalc();
  })

  document.querySelector('#ft-import-deactivate-selected').addEventListener('click', ()=>{
    document.querySelector('#ft-import-deactivate-list').style.display = "none";
    activateProcess('.deactivate-item:checked', '#ft-deactivate', 'Деактивация выбранных товаров...', 'Деактивация завершена.', 'N');
    });
</script>
<?endif; // скрипт деактивации?>
<?endif; // конец обработки нажатия кнопки импорт?>


<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>