<?php

error_reporting(E_ALL & ~E_WARNING);
ini_set('display_errors', 1);
//Открытие файла .csv
$handle = fopen("Report.csv", "a"); 
//Запись строки в файл
fputcsv($handle, ["1","2","3"], ";");
//Закрытие файла .csv
fclose($handle);



$html = get_html("http://www.mac-cosmetics.ru/product/shaded/168/28049/Products/RiRi-Hearts-MAC-Lipstick/index.tmpl");
$descr = get_description($html);
echo get_name($html);
echo $descr;

//Получение html страницы
function get_html($url){
	//Инициализация curl
	$c_init=curl_init($url);
	//чтобы функция curl_exec() возвращала текст
	curl_setopt($c_init, CURLOPT_RETURNTRANSFER, '1');
	// Получение html
	$html = curl_exec($c_init);	
	return $html;
}

function get_description($html)
{
	$id = 'descr-full';
	$doc = new DomDocument;
	$doc->loadHTML($html);
	$descr = $doc->getElementById($id)->textContent;
	return $descr;
}

function get_name($html)
{
	$dom = new DomDocument;
	$dom->loadHTML($html);
	$h1 = $dom->getElementsByTagName('h1')->item(0);
	$img = $h1->childNodes->item(0);
	$name = $img->getAttribute('alt');	
	return $name;	
}



?>