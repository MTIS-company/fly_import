<?
  require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
  $APPLICATION->SetTitle("Импорт flytechnology");
  $cmain = new CMain;
  $cmain->SetAdditionalCSS($cmain->GetCurPage().'styles.css');
  $file = new CFile;
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

<?if (!array_key_exists('status', $_POST)): // первичная загрузка страницы?> 
  <form action="" name="ft-import-form" method="POST">
    <span>URL каталога:</span>
    <input type="text" value=<?=XML_URL?> name="xml_url" action="">
    <input type="hidden" name="status" value="xml_loaded">
    <button id="import-section" type="submit">Импортировать разделы</button>
  </form>
  <div id="show-wait-sections"></div>
  <script>
    document.querySelector('#import-section').addEventListener('click', ()=>{
      BX.showWait('show-wait-sections');
      document.querySelector('#show-wait-sections').innerHTML = 'Импортируются разделы каталога...';
    });
  </script>
<?endif;?>

<?if (array_key_exists('status', $_POST) && $_POST['status'] == 'xml_loaded'): // обработка нажатия кнопки импорт
$read_xml = simplexml_load_file($_POST['xml_url'], 'SimpleXMLElement');
if (!$read_xml):?>
	<h2>Ошибка чтения файла <?=$_POST['xml_url']?> </h2>
	<? 
	exit();
endif;

$category_img = []; // масс. картинок категорий товаров
$xml = simplexml_load_file('folders_img.xml', 'SimpleXMLElement'); // получаем картинки категорий товаров из folders_img.xml

if ($xml) {
  $category_img_xml = $xml->xpath("//pictures/picture"); 
  foreach($category_img_xml as $val) $category_img [(string)$val->namecategory] = (string)$val->img;
} 
if (!$xml):?>
  <h2>Ошибка чтения списка изображений разделов</h2>
<?endif;
unset($xml);

$categories = $read_xml->xpath("//shop/categories/category"); // категории из внешнего каталога
  $cat_assoc = []; // для замены name на id (нормализация таблицы)
  foreach ($categories as &$item) {
    $item->idcategory = trim((string)$item->idcategory);
    $item->namecategory = trim((string)$item->namecategory);
    $item->parentcategory = trim((string)$item->parentcategory);
    $item->picture = (string)$category_img[(string)$item->namecategory];
    $cat_assoc[(string)$item->namecategory] = (int)$item->idcategory;
  }

  function get_folders() {// возв. категории внешнего каталога, существующие в navi
    $list = CIBlockSection::GetList([], ["IBLOCK_ID"=>IBLOCK], false, ["ID", "XML_ID"]); // все категории navi
    $ft_list = []; 
    while($el = $list->GetNext()) if (strpos($el['XML_ID'], SECTION_PREFIX) !== false) $ft_list[] = ['XML_ID'=>parse_int($el['XML_ID']), 'ID'=>$el['ID']];
    return $ft_list;
  };

  $ft_list = get_folders();

  // импортируем категории в таблицы бд:
  $new_sections = []; // новые категории
  foreach ($categories as $i) {
    // проверяем наличие категории в базе navi:
    $exists = false;
    foreach ($ft_list as $val ){
      if ($val['XML_ID'] == (string)$i->idcategory) $exists = true;
    };
    if ($exists) continue;

    $arFields = [
      "IBLOCK_ID" => IBLOCK,
      "IBLOCK_SECTION_ID"=> SECTION,
      "NAME" => $i->namecategory,
      "XML_ID" => SECTION_PREFIX.(string)$i->idcategory, // входящий id вида: "flytechnology{xml_id}"
      "PICTURE" => $file->MakeFileArray((string)$i->picture),
      "CODE" => SECTION_PREFIX.$i->idcategory, // добавляем входящий id к символьному коду раздела (во избежание дублирования при последующем импорте)
    ];
    $ID = $bs->Add($arFields); // создаем новую запись
    $APPLICATION->GetException();
    // если новый раздел был создан - добавляем запись в масив $new_sections:
    if ($ID) $new_sections [] = ['id'=>$ID, 'xml_id'=>(int)$i->idcategory, 'old_parent'=>(int)$i->idparentcategory, 'section_name'=>(string)$i->namecategory];
  }

  if (count($new_sections) > 0) { // обработка созданных категорий
    $ft_list = get_folders();
    $parent_change = []; // массив для замены родительских id новыми значениями:
    foreach ($ft_list as $el) {
      $parent_change[$el['XML_ID']] = (int)$el['ID'];
    }

    // заменяем входящие id родительских разделов реальными id:
    foreach ($new_sections as &$value) {
      $new_section_id = $parent_change[$value['old_parent']];
      $bs->Update($value['id'], ["IBLOCK_SECTION_ID"=>$new_section_id]);
      $nav = $bs->GetNavChain(false, $new_section_id);
      $path = '';
      foreach ($nav->arResult as $val) $path .= $val['NAME'].'/';
      $path .= $value['section_name'];
      $value['path'] = $path;
    }

    function sections_sort ($a, $b) {
      if ($a == $b) return 0;
      return ($a['parent'] < $b['parent']) ? -1 : 1;
    };
    usort ($new_sections, 'sections_sort');
  }
  // -------------------------------- конец обработки списка категорий ----------------------
  ?> 

<ul id="ft-sectionsinfo">
  <li class="bold">Всего разделов в каталоге Flytechnology: <?=count($categories)?></li>
  <li class="bold">Импортировано разделов из каталога Flytechnology: <?=count($new_sections)?></li>
</ul>
<?if (count($new_sections) > 0):?>
  <button id="ft-sections-list-show" data-show="hidden">Показать список</button>
  <ul id="ft-sections-list" style="display:none">
    <?
      foreach($new_sections as $val):?>
      <li>
        <a href="/bitrix/admin/iblock_section_edit.php?IBLOCK_ID=<?=IBLOCK?>&type=aspro_next_catalog&lang=ru&ID=<?=$val['id']?>" target="blanc" title="Просмотр категории товаров">
          <span><?=$val['path']?></span>
          <img class="ft-product-view" src="view.png">
        </a>
      </li>
      <?endforeach;?>
  </ul>
<?endif;?>

<? // --------------------------- обработка списка товаров -------------------------

$existing_items_request = $b_el->GetList( // создаем список товаров, уже существующих в базе
 [(int)"XML_ID"=>"ASC"],
 ["IBLOCK_ID"=>IBLOCK, "CODE"=>ITEM_PREFIX."%"],
 false,
 false,
 ["ID", "IBLOCK_ID", "XML_ID", "NAME", "IBLOCK_SECTION_ID", "ACTIVE"]
);
$existing_items_list = []; // масс. xml_id товаров, существующих в базе
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

// все товары flytechnology:
$items_xml = simplexml_load_file($_POST['xml_url'], 'SimpleXMLElement', LIBXML_NOCDATA)->xpath("//shop/itemlist/item[idproduct>=0]");

$items_xml_id = []; // для поиска товаров, не существующих в xml но существующих в базе (для деактивации)
foreach($items_xml as $val) $items_xml_id[] = (int)$val->idproduct;
$deactivate = array_diff($existing_items_list, $items_xml_id); // товары, отсутствующие в xml
// актуализируем массивы активации/деактивации:
$activate_list = [];
$deactivate_list = [];
foreach($navi_items_list as $val) { // перебор всех товаров navi
  if (array_search($val['xml_id'], $deactivate) != false && strtoupper($val['active']) == 'Y') $deactivate_list[] = $val;
  if ($val['active'] == 'N' && array_search($val['xml_id'], $items_xml_id) != false) $activate_list[] = $val;
}

unset ($items_xml_id);
unset($navi_items_list);

$activate_count = count($activate_list);
$deactivate_count = count($deactivate_list);

$items = []; // список товаров из каталога xml для вывода на экран
$new_products = 0;
foreach ($items_xml as $val) {
	$exists = array_search( (int)$val->idproduct, $existing_items_list)===false ? 0 : 1;
	if (!$exists) $new_products ++;
	if ($val) $items[] = [
    'nameproduct'=>(string)$val->nameproduct,
    'idproduct'=>(int)$val->idproduct,
    'exists'=>$exists,
  ];
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
			<li>
				<label>
				<input type="checkbox" class="activate-item" data-id="<?=$val['id']?>">
				<?=$val['name']?>
				<a href="/bitrix/admin/iblock_element_edit.php?IBLOCK_ID=<?=IBLOCK?>&type=aspro_next_catalog&lang=ru&ID=<?=$val['id']?>&find_section_section=<?=$val['parent']?>&WF=Y" target="blanc">
					<img class="ft-product-view" src="view.png" title="Просмотр товара">
				</a>
				</label>
			</li>
    <?endforeach;?>
  </ul>
<?endif; // активация.?>


<hr>
<ul id="ft-products-import">
  <li class="bold">Товары в каталоге flytechnology: <?=count($items)?></li>
  <li class="bold">Товары в каталоге NAVI: <?=count($existing_items_list);?></li>
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
        <?if ($disabled):// ссылка на товар в базе navi?>
          <!-- <a href="/bitrix/admin/iblock_element_edit.php?IBLOCK_ID=<?=IBLOCK?>&type=aspro_next_catalog&lang=ru&ID=12781&WF=Y" target="blanc">
            <img class="ft-product-view" src="view.png" title="Просмотр товара">
          </a>-->
          <span class="ft-import-product-exists"> есть в каталоге NAVI </span> 
        <?endif;?>
      </label>
    </li> 
  <?endforeach;?>
</ul>

<script>
let sectionsShow = document.querySelector('#ft-sections-list-show');
sectionsShow.addEventListener('click', (e)=>{
  sectionsShow.dataset.show = sectionsShow.dataset.show == 'hidden' ? "visible" : "hidden";
  sectionsShow.innerHTML = sectionsShow.dataset.show == 'hidden' ? "Показать список" : "Скрыть список";
  document.querySelector('#ft-sections-list').style.display = sectionsShow.dataset.show == 'hidden' ? "none" : "block";
})

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
    let activateSection = document.querySelector(messageSelector);
    activateSection.innerHTML = `<h2>${title}</h2>`;
    BX.showWait(activateSection);
    activateSection.innerHTML += `<h3 id="activate-progress"> </h3>`;
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
                      BX.closeWait(activateSection);
                      activateSection.innerHTML = `<h2>${finishTitle}</h2>`;
                      activateSection.innerHTML += `Обработано: ${index} товаров`;
                      console.log("Response: ", data);
                    } else
                    {
                      console.log('Processed: ', index, ' of ', selected.length);
                      progress.innerHTML = `Обработано ${index} из ${selected.length} товаров`;
                    }
                  }
              });
        } // if success
    }, 500); // setInterval
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
  BX.showWait('ft-products-import');
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
              BX.closeWait('ft-products-import');
              mainSection.innerHTML += `Импортировано ${index} товаров`;
              document.querySelector("#ft-import-products-list").innerHTML = "";
              console.log(data)
            }
            console.log('Imported: ', index, ' from ', selected.length);
            progress.innerHTML = `Импортировано ${index} из ${selected.length} товаров`;
          }
      });
      } // if success
  }, 500); // setInterval
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