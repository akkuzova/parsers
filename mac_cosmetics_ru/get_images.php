<?php

error_reporting(E_ALL & ~E_WARNING);
ini_set('display_errors', 1);
mb_internal_encoding("UTF-8");
header("Content-Type: text/html; charset=utf-8");

define("domainsRU", "http://www.mac-cosmetics.ru"); 
define("domainsUSA", "http://www.maccosmetics.com");
define("baseDir", "H:\OpenServer\domains\localhost\Mac_cosmetics_ru\\");
 set_time_limit(10000);

include_once('config.php');
$DB;
getConnect($user, $pass, $host, $db); 

processing();

//Подключение к БД
function getConnect($user, $pass, $host, $db){
    require_once "DbSimple/Connect.php";
	global $DB;
	$connectString = "mysqli://" . $user . ":" . $pass . "@" . $host . "/" . $db;	
	$DB = new DbSimple_Connect($connectString);
	require_once "DbSimple/Generic.php";
}

function saveImage($url, $dir, $filename){
	$path = $dir .  $filename . ".jpg";	
	if (!file_exists($path)) //Если изображения с таким именем еще нет,
	{
		$image = imagecreatefromjpeg($url);  //Получаем изображение по url	
		if ($image)
			imagejpeg($image, $path); //Если изображение получено, сохраняем его
		else
			error("   Не удалось получить изображение : " . $url);
	}	
	else
		error("   Изображение уже существует : " . $path);	
	
}

function processing(){
	global $DB;
	$log = fopen("image_grub_log.txt", "a");
	$rows_rest = $DB->select("SELECT COUNT(status) AS rows_rest FROM skus_USA WHERE status='0';"); 
	$rest = $rows_rest[0]['rows_rest'];
	$k = 0;
	while ($rest > 0){
		getImages(10);
		$k++;
		if ($k % 2 == 0)
			sleep(2);
		if ($k % 10 == 0)
		{
			sleep(10);
			$rows_rest = $DB->select("SELECT COUNT(status) AS rows_rest FROM skus_USA WHERE status='0';"); 
			$rest = $rows_rest[0]['rows_rest'];			
			fwrite($log, date("d.m.y H:i:s") .  " осталось " . $rest . "\n\r");			
		}
	}
	fclose($log);
}


function getImages($n){
	global $DB;	
	$status = 0;
	$error = false;

	$skus = $DB->select("SELECT * FROM skus_USA WHERE status='0' LIMIT ?d;" , $n ); //Выбираем n оттенков из БД, которые еще не сохраняли
	
	foreach ($skus as $sku) {		
		$cat_id_USA = $sku['PARENT_CAT_ID'];   //Категория товара на американском сайте		
		//Соответствующая категория на русском сайте
		$rows = $DB->select("SELECT category_id_ru FROM categories_ru WHERE category_id_usa=?d;" , $cat_id_USA);
		if (empty($rows))
			$cat_id = $cat_id_USA;	
		else
			$cat_id = $rows[0]['category_id_ru']; 		
			
		$product_code = $sku['PRODUCT_CODE']; //Код продукта
		$rows = $DB->select("SELECT category_type FROM categories_USA WHERE category_id=?d;" , $cat_id_USA); 
		$type = $rows[0]['category_type'];     //Тип товара, нужен для именования директорий верхнего уровня (Lips, Eyes...)
		$dir = baseDir . "images\\" . $type . "\\" . $cat_id . "\\" . $sku['PRODUCT_ID'] . "\\" ; //Директория для хранения изображений данного продукта	
		//Если создание директории прошло успешно
		if (makeDir($dir)){
			//Сохраняем все нужные изображения в созданную директорию
			saveImage(domainsUSA . $sku["LARGE_IMAGE"], $dir , $product_code . "_LARGE_IMAGE");
			saveImage(domainsUSA . $sku["MEDIUM_IMAGE"], $dir , $product_code . "_MEDIUM_IMAGE");
			saveImage(domainsUSA . $sku["IMAGE_COLLECTION"], $dir , $product_code . "_IMAGE_COLLECTION");
			saveImage(domainsUSA . $sku["IMAGE_SMOOSH"], $dir , $product_code . "_IMAGE_SMOOSH");
			saveImage(domainsUSA . $sku["SMALL_IMAGE"], $dir , $product_code . "_SMALL_IMAGE");
			$status = 1;
		}
		else 
			$status = 2;

		$DB->query("UPDATE skus_USA SET status=? WHERE product_code = ?;", $status, $product_code);
	}
}

function error($message){
	$log = fopen("image_error_log.txt", "a");
	fwrite($log, date("d.m.y H:i:s") . $message . "\n\r");
	fclose($log);
}

function makeDir($dir){
$result = true;
//Если директория еще не создана		
	if (!is_dir($dir))
		//Если не удалось создать директорию
		if (!mkdir($dir, 0777, true)){
			//Пишет сообщение в файл
			error("   Не удалось создать директорию: " . $dir);
			$result = false;
		}
return $result;
}

?>