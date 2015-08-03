<?php

error_reporting(E_ALL & ~E_WARNING);
ini_set('display_errors', 1);
mb_internal_encoding("UTF-8");
header("Content-Type: text/html; charset=utf-8");

define("domainsRU", "http://www.mac-cosmetics.ru"); 
define("domainsUSA", "http://www.maccosmetics.com");

 set_time_limit(600);

//При работе с БД в версии php ниже 5.4 приходит пустой html ¯\_(ツ)_/¯
include_once('config.php');
$DB;
getConnect($user, $pass, $host, $db); 

processingProducts(20);
echo "я всё";

//Подключение к БД
function getConnect($user, $pass, $host, $db){
    require_once "DbSimple/Connect.php";
	global $DB;
	$connectString = "mysqli://" . $user . ":" . $pass . "@" . $host . "/" . $db;	
	$DB = new DbSimple_Connect($connectString);
	require_once "DbSimple/Generic.php";
}

//Получение html страницы
function getHtml($url){
	//Инициализация curl
	$c_init = curl_init($url);
	//чтобы функция curl_exec() возвращала текст
	curl_setopt($c_init, CURLOPT_RETURNTRANSFER, '1');
	//Разрешаем редирект
	curl_setopt($c_init, CURLOPT_FOLLOWLOCATION, true);
	// Получение html
	$html = curl_exec($c_init);	
	return $html;
}

//Получение ссылки на продукт по id категории и id продукта
function getUrlByCatAndId($cat, $id){
	$url = domainsRU . "/product/shaded/" . $cat . "/" . $id . "/";
	return $url;
}

function saveImage($url, $dir, $filename){
	$path = $dir .  $filename . ".jpg";
	echo $path;
	imagejpeg(imagecreatefromjpeg($url), $path);
}

//__________________________________Американская версия сайта_______________________________________________

//Получение списка категорий с американского сайта
function getCategoriesUSA(){
	$html = getHtml(domainsUSA);
	$doc = new DomDocument;
	$doc->loadHTML($html);
	$xpath = new DOMXPath($doc);
	$menu = $xpath->query('//*[contains(@class,"site-navigation__submenu-col")]'); //Список div со структурой <h3>catName</h3><ul><li><a href="url">subCatName</a></li>...</ul>
	$divCount = $menu->length - 1; //Последний div с gifCards - не нужен
	for ($i=0; $i < $divCount; $i++) {
		$cat = $menu->item($i);
		$ulCount = $cat->getElementsByTagName('ul')->length;
		if ($ulCount > 0){	//Среди div с классом site-navigation__submenu-col есть ненужные, в них нет ul	
			for ($j=0; $j<$ulCount; $j++)	{
				if ($cat->getElementsByTagName('h3')->length > 0) //Не везде есть заголовок
					$catName = $cat->getElementsByTagName('h3')->item($j)->textContent; //Наименование типа категории (Lips/Eyes....)
				else
					$catName = "_";
				$subCats = $cat->getElementsByTagName('ul')->item($j)->getElementsByTagName('li'); //Список подкатегорий(Lipstick/Eyeshadow..)
				echo "<br>". $catName . "<br>";
				//Обходим список всех подкатегорий
				foreach ($subCats as $subCat) {
					$subCatName = $subCat->textContent;	//Наименование подкатегории
					$url = $subCat->getElementsByTagName("a")->item(0)->getAttribute("href");  //Ссылка на подкатегорию					
					preg_match("/\d{2,}/", $url, $matches);
					if (count($matches) > 0) 
						$id = $matches[0];			
					global $DB;
					$DB->query("INSERT INTO categories_USA VALUES (?,?,?,?,?);", $id, $subCatName, $url, $catName, "0");

					echo $subCatName . " |  " . $url .  "  | " . $id . "<br>";
				}
			}
		}
	}
	

}

function getAllProductsInCat($categoryUrl, $needImage){
	$html = getHtml(domainsUSA . $categoryUrl);
	$doc = new DomDocument;
	$doc->loadHTML($html);

	$xpath = new DOMXPath($doc);
	$products = $xpath->query('//*[contains(@class,"product__name-link")]');	
	if ($needImage){
		$image = $xpath->query('//*[contains(@class,"multi-use-tout__image")]')->item(0);
		if (!is_null($image)){
			$imageUrl = $image->getAttribute('src');
			$imageName = $xpath->query('//*[contains(@class,"multi-use-tout__title")]')->item(0)->textContent;
			saveImage(domainsUSA . $imageUrl, "H:\OpenServer\domains\localhost\Mac_cosmetics_ru\header_images\\", $imageName);
		}
	}
	global $DB;
	foreach ($products as $prod){
		$url = $prod->getAttribute('href');
		$matches = null;
		preg_match_all("/\d{2,}/", $url, $matches);		
		$cat = $matches[0][0];
		$id = $matches[0][1];	
		$DB->query("INSERT INTO product_links_USA VALUES (?,?,?,?);", $url, $cat, $id, '0');			
	}

}

//Обработка N категорий
function processingCategories($n){
	global $DB;
	$rows = $DB->select("SELECT category_url FROM categories_USA WHERE status='0' LIMIT ?d;" , $n ); //Получаем первые n строк с необработанныыми ссылками 
	foreach ($rows as $row){
		sleep(1);
		getAllProductsInCat($row['category_url'], false);
		$status = '1';
		$DB->query("UPDATE categories_USA SET status=? WHERE category_url = ?;", $status, $row['category_url']); //Меняем статус url'а в базе данных

	}
}

function processingProducts($n){
	global $DB;
	$rows = $DB->select("SELECT url FROM product_links_USA WHERE status='0' LIMIT ?d;" , $n ); //Получаем первые n строк с необработанныыми ссылками 
	foreach ($rows as $row){
		sleep(1);
		$url = domainsUSA . $row['url'];
		$html = getHtml($url);
		if (!is_null($html)){
			$status = '1';
			getSkusUSA($html);
		}
		else
			$status = '2';
		$DB->query("UPDATE product_links_USA SET status=? WHERE url = ?;", $status, $row['url']); //Меняем статус url'а в базе данных

	}
	$rows_rest = $DB->select("SELECT COUNT(status) AS rows_rest FROM product_links_USA WHERE status='0';"); 
	echo "осталось " . $rows_rest[0]['rows_rest'] . "<br>";
	
}


//Получение всех оттенков продукта и их описаний
function getSkusUSA($html)
{
	$str = substr($html, strpos($html, 'var page_data') + 16);
	$str = substr($str, 0, strpos($str, '</script>'));
	$data = json_decode($str, true);	
	$products = $data["catalog-spp"]["products"]["0"]["skus"];
	//var_dump($products);
	$skus = array();
	for ($i=0; $i < count($products); $i++)
	{
		$skus['LARGE_IMAGE'] = checkSubKeyExist('LARGE_IMAGE', $products[$i]);
		$skus['SKIN_TYPE'] = checkKeyExist('', $products[$i]);
		$skus['LIFE_OF_PRODUCT'] = checkKeyExist('LIFE_OF_PRODUCT', $products[$i]);
		$skus['MEDIUM_IMAGE'] = checkSubKeyExist('MEDIUM_IMAGE', $products[$i]);
		$skus['UOM'] = checkKeyExist('UOM', $products[$i]);
		$skus['UNIT_SIZE'] = checkKeyExist('UNIT_SIZE', $products[$i]);
		$skus['PRICE'] = checkKeyExist('PRICE', $products[$i]);
		$skus['SKIN_TYPE_TEXT'] = checkKeyExist('SKIN_TYPE_TEXT', $products[$i]);
		$skus['FLASH_DESC'] = checkKeyExist('FLASH_DESC', $products[$i]);
		$skus['SKIN_TONE_TEXT'] = checkKeyExist('SKIN_TONE_TEXT', $products[$i]);
		$skus['formattedUnitPrice'] = checkKeyExist('formattedUnitPrice', $products[$i]);
		$skus['PRODUCT_CODE'] = checkKeyExist('PRODUCT_CODE', $products[$i]);
		$skus['HEX_VALUE'] = checkKeyExist('HEX_VALUE', $products[$i]);
		$skus['isShoppable'] = checkKeyExist('isShoppable', $products[$i]);
		$skus['IMAGE_COLLECTION'] = checkKeyExist('IMAGE_COLLECTION', $products[$i]);
		$skus['IMAGE_VIDEO'] = checkKeyExist('IMAGE_VIDEO', $products[$i]);
		$skus['IMAGE_SMOOSH'] = checkKeyExist('IMAGE_SMOOSH', $products[$i]);
		$skus['UNDERTONE'] = checkKeyExist('UNDERTONE', $products[$i]);
		$skus['STRENGTH'] = checkKeyExist('STRENGTH', $products[$i]);
		$skus['isOrderable'] = checkKeyExist('isOrderable', $products[$i]);
		$skus['SKU_ID'] = checkKeyExist('SKU_ID', $products[$i]);
		$skus['FINISH_SIMPLE'] = checkKeyExist('FINISH_SIMPLE', $products[$i]);
		$skus['FINISH'] = checkKeyExist('FINISH', $products[$i]);
		$skus['PRODUCT_FORM'] = checkKeyExist('PRODUCT_FORM', $products[$i]);
		$skus['SKIN_TONE'] = checkKeyExist('SKIN_TONE', $products[$i]);
		$skus['formattedPrice'] = checkKeyExist('formattedPrice', $products[$i]);

		$skus['PARENT_CAT_ID'] = checkKeyExist('PARENT_CAT_ID', $products[$i]);
		preg_match("/\d{2,}/", $skus['PARENT_CAT_ID'], $matches);		
		$skus['PARENT_CAT_ID'] = $matches[0];

		$skus['PRODUCT_ID'] = checkKeyExist('PRODUCT_ID', $products[$i]);
		preg_match("/\d{2,}/", $skus['PRODUCT_ID'], $matches);		
		$skus['PRODUCT_ID'] = $matches[0];

		$skus['formattedTaxedPrice'] = checkKeyExist('formattedTaxedPrice', $products[$i]);
		$skus['PRODUCT_SIZE'] = checkKeyExist('PRODUCT_SIZE', $products[$i]);
		$skus['INTENSITY'] = checkKeyExist('INTENSITY', $products[$i]);
		$skus['PRODUCT_TYPE'] = checkKeyExist('PRODUCT_TYPE', $products[$i]);
		$skus['SHADE_DESCRIPTION'] = checkKeyExist('SHADE_DESCRIPTION', $products[$i]);
		$skus['COLORGROUPING'] = checkKeyExist('COLORGROUPING', $products[$i]);
		$skus['INVENTORY_STATUS'] = checkKeyExist('INVENTORY_STATUS', $products[$i]);
		$skus['SMALL_IMAGE'] = checkKeyExist('SMALL_IMAGE', $products[$i]);
		$skus['ATTRIBUTE_COLOR_FAMILY'] = checkKeyExist('ATTRIBUTE_COLOR_FAMILY', $products[$i]);
		$skus['SHADENAME'] = checkKeyExist('SHADENAME', $products[$i]);
		$skus['isComingSoon'] = checkKeyExist('isComingSoon', $products[$i]);
		$skus['COVERAGE'] = checkKeyExist('COVERAGE', $products[$i]);
		$skus['FIBRE'] = checkKeyExist('FIBRE', $products[$i]);
		$skus['HEX_VALUE_STRING'] = checkKeyExist('HEX_VALUE_STRING', $products[$i]);
		$skus['path'] = checkKeyExist('path', $products[$i]);
		$skus['SKU_BASE_ID'] = checkKeyExist('SKU_BASE_ID', $products[$i]);
		$skus['DISPLAY_ORDER'] = checkKeyExist('DISPLAY_ORDER', $products[$i]);
		$skus['REFILLABLE'] = checkKeyExist('REFILLABLE', $products[$i]);
		$skus['USE'] = checkKeyExist('USE', $products[$i]);
		$skus['AREA_OF_FACE'] = checkKeyExist('AREA_OF_FACE', $products[$i]);
		$skus['DISPLAY_STATUS'] = checkKeyExist('', $products[$i]);
		putSkus($skus);
	}

}

function putSkus($skus){
	global $DB;
	$DB->query("INSERT INTO skus_USA VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
											$skus['LARGE_IMAGE'] ,
											$skus['SKIN_TYPE'] ,
											$skus['LIFE_OF_PRODUCT'] ,
											$skus['MEDIUM_IMAGE'] ,
											$skus['UOM'] ,
											$skus['UNIT_SIZE'] ,
											$skus['PRICE'] ,
											$skus['SKIN_TYPE_TEXT'] ,
											$skus['FLASH_DESC'] ,
											$skus['SKIN_TONE_TEXT'] ,
											$skus['formattedUnitPrice'] ,
											$skus['PRODUCT_CODE'] ,
											$skus['HEX_VALUE'] ,
											$skus['isShoppable'] ,
											$skus['IMAGE_COLLECTION'] ,
											$skus['IMAGE_VIDEO'] ,
											$skus['IMAGE_SMOOSH'] ,
											$skus['UNDERTONE'] ,
											$skus['STRENGTH'] ,
											$skus['isOrderable'] ,
											$skus['SKU_ID'] ,
											$skus['FINISH_SIMPLE'] ,
											$skus['FINISH'] ,
											$skus['PRODUCT_FORM'] ,
											$skus['SKIN_TONE'] ,
											$skus['formattedPrice'] ,
											$skus['PARENT_CAT_ID'] ,
											$skus['PRODUCT_ID'] ,
											$skus['formattedTaxedPrice'] ,
											$skus['PRODUCT_SIZE'] ,
											$skus['INTENSITY'] ,
											$skus['PRODUCT_TYPE'] ,
											$skus['SHADE_DESCRIPTION'] ,
											$skus['COLORGROUPING'] ,
											$skus['INVENTORY_STATUS'] ,
											$skus['SMALL_IMAGE'] ,
											$skus['ATTRIBUTE_COLOR_FAMILY'] ,
											$skus['SHADENAME'] ,
											$skus['isComingSoon'] ,
											$skus['COVERAGE'] ,
											$skus['FIBRE'] ,
											$skus['HEX_VALUE_STRING'] ,
											$skus['path'] ,
											$skus['SKU_BASE_ID'] ,
											$skus['DISPLAY_ORDER'] ,
											$skus['REFILLABLE'] ,
											$skus['USE'] ,
											$skus['AREA_OF_FACE'] ,
											$skus['DISPLAY_STATUS'],
											'0');
}

function checkKeyExist($key, $array){
	$result = array_key_exists($key, $array) ? $array[$key] : '-1';
	return $result;
}

function checkSubKeyExist($key, $array){	
    if (array_key_exists($key, $array) )			   		
   		$result = array_key_exists(0, $array[$key]) ? $array[$key][0] : '-1';
    else
   		$result = '-1';
	return $result;
}



//__________________________________Русская версия сайта____________________________________________________

//Получение информации об одном продукте по id его категории и его id  
function getProductInfoRU($cat, $id){
	$url = getUrlByCatAndId($cat, $id);
	$html = getHtml($url);
	$descr = getDescription($html);
	$name = getName($html);
	//getSwatches($html);
	echo $name;
}


//Краткое описание продукта
function getDescription($html)
{
	$id = 'descr-full';
	$doc = new DomDocument;
	$doc->loadHTML($html);
	$descr = $doc->getElementById($id)->textContent;
	return $descr;
}

//Наименование продукта
function getName($html)
{
	$dom = new DomDocument;
	$dom->loadHTML($html);
	$h1 = $dom->getElementsByTagName('h1')->item(0);
	$img = $h1->childNodes->item(0);
	$name = $img->getAttribute('alt');
	return $name;	
}

//Получение информации обо всех оттенках продукта
function getSwatches($html)
{
	$str = substr($html, strpos($html, 'var page_data') + 16);
	$str = substr($str, 0, strpos($str, '</script>'));
	$data = json_decode($str, true);	
	$shades = array();
	$products = $data["catalog"]["spp"]["product"]["skus"];
	for ($i=0; $i < count($products); $i++)
	{			
		   $shades[$i]['finish']= array_key_exists('finish', $products[$i]) ? $products[$i]['finish'] : '_';
		   if (array_key_exists('color', $products[$i]))			   		
		   		$shades[$i]['color'] = array_key_exists(0, $products[$i]['color']) ? $products[$i]['color'][0] : '_';
		   else
		   		$shades[$i]['color'] = '_';
		   $shades[$i]['shoppable']= array_key_exists('shoppable', $products[$i]) ? $products[$i]['shoppable'] : '-1';
		   $shades[$i]['inventory_status']= array_key_exists('inventory_status', $products[$i]) ? $products[$i]['inventory_status'] : '-1'; 
		   $shades[$i]['shade_name']= array_key_exists('shade_name', $products[$i]) ? $products[$i]['shade_name'] : '_'; 
		   $shades[$i]['finish_description']= array_key_exists('finish_description', $products[$i]) ? $products[$i]['finish_description'] : '_'; 
		   $shades[$i]['product_code']= array_key_exists('product_code', $products[$i]) ? $products[$i]['product_code'] : '_'; 
		   $shades[$i]['product_size']= array_key_exists('product_size', $products[$i]) ? $products[$i]['product_size'] : '_'; 
		   $shades[$i]['shade_description']= array_key_exists('shade_description', $products[$i]) ? $products[$i]['shade_description'] : '_'; 
		   $shades[$i]['inventory_status_message']= array_key_exists('inventory_status_message', $products[$i]) ? $products[$i]['inventory_status_message'] : '_';
		   $shades[$i]['sku_base_id']= array_key_exists('sku_base_id', $products[$i]) ? $products[$i]['sku_base_id'] : '-1';	 
		   $shades[$i]['formatted_price']= array_key_exists('formatted_price', $products[$i]) ? $products[$i]['formatted_price'] : '_';	 
		   $shades[$i]['display_order']= array_key_exists('display_order', $products[$i]) ? $products[$i]['display_order'] : '-1';
		   $shades[$i]['displayable']= array_key_exists('displayable', $products[$i]) ? $products[$i]['displayable'] : '-1';
		   $shades[$i]['sku_id']= array_key_exists('sku_id', $products[$i]) ? $products[$i]['sku_id'] : '_';
		   $shades[$i]['path']= array_key_exists('path', $products[$i]) ? $products[$i]['path'] : '_';	
		   $shades[$i]['sku_image']= array_key_exists('sku_image', $products[$i]) ? $products[$i]['sku_image'] : '_'; 
		   $shades[$i]['smoosh_thumb']= array_key_exists('smoosh_thumb', $products[$i]) ? $products[$i]['smoosh_thumb'] : '_';	
		   $shades[$i]['smoosh']= array_key_exists('smoosh', $products[$i]) ? $products[$i]['smoosh'] : '_';
		   $shades[$i]['pro_product']= array_key_exists('pro_product', $products[$i]) ? $products[$i]['pro_product'] : '-1';	
		   $shades[$i]['image_medium_rollover']= array_key_exists('image_medium_rollover', $products[$i]) ? $products[$i]['image_medium_rollover'] : '_';
		   $shades[$i]['price']= array_key_exists('price', $products[$i]) ? $products[$i]['price'] : '-1'; 
		   $shades[$i]['limited_life']= array_key_exists('limited_life', $products[$i]) ? $products[$i]['limited_life'] : '-1';
           $shades[$i]['product_id'] = preg_match("", $shades[$i]['path'], $matches);
           //putShades($shades[$i]);	
	}
}


//Добавление строки с информацией об оттенке продукта в БД shades
function putShades($shades){	
	global $DB;
	$DB->query("INSERT INTO shades VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?);", 
																   $shades['product_id'],
																   $shades['finish'], 
																   $shades['color'], 
																   $shades['shoppable'], 
																   $shades['inventory_status'], 
																   $shades['shade_name'], 
																   $shades['finish_description'], 
																   $shades['product_code'], 
																   $shades['product_size'], 
																   $shades['shade_description'], 
																   $shades['inventory_status_message'],
																   $shades['sku_base_id'],	 
																   $shades['formatted_price'],	 
																   $shades['display_order'],
																   $shades['displayable'],
																   $shades['sku_id'],
																   $shades['path'],	
																   $shades['sku_image'], 
																   $shades['smoosh_thumb'],	
																   $shades['smoosh'],
																   $shades['pro_product'],	
																   $shades['image_medium_rollover'],
																   $shades['price'], 
																   $shades['limited_life']
																   ); 
}







?>