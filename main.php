<?php
include 'region_replace.php';
include 'modules.php';
// phpinfo() ;
set_time_limit (3000);
define('SERVER_MODE','test');
//define('SERVER_MODE','prod');
define('MAX_JSON_RESULTS','5');

/*define('INPUT_FILE','test5.txt');
define('BAD_OUTPUT_FILE','test5_out_bad.csv');
define('GOOD_OUTPUT_FILE','test5_out_good.csv');
define('FOR_LOAD','parced_test5.txt');*/

/*define('INPUT_FILE','all_active_2015-03-12.txt');
define('BAD_OUTPUT_FILE','all_active_2015-03-12_out_bad.csv');
define('GOOD_OUTPUT_FILE','all_active_2015-03-12_out_good.csv');
define('FOR_LOAD','parced_all_1320.txt');*/

define('INPUT_FILE','locations_20150608.csv');
/*Формат файла: <id>;<адрес>;<название> */
define('BAD_OUTPUT_FILE','locations_20150608_bad.csv');
define('GOOD_OUTPUT_FILE','locations_20150608_good.csv');
define('FOR_LOAD','parced_locations_20150608.txt');


echo 'Режим работы (тестовый/продуктивный):'  . SERVER_MODE.'</br>';
$aContext = array(
    'http' => array(
        'proxy' => 'tcp://proxy:81',
        'request_fulluri' => true,
    ),
);
$cxContext = stream_context_create($aContext);


//$handle_in = fopen("test1.txt","r");
$handle_good = fopen("test_good1.txt","w");
$handle_bad = fopen("test_bad1.txt","w");

// отличный пример http://php.net/manual/ru/function.fgetcsv.php
$row = 1;
if (($handle_in = fopen(INPUT_FILE, "r")) !== FALSE) {
    while (($data = fgetcsv($handle_in, 1000, ";")) !== FALSE) 
	{
        $num = count($data);
        //echo "<p> $num полей в строке $row: <br /></p>\n";
        $row++;
        //echo "Код {$data[0]}, адрес {$data[1]}</br>";
		$params = array('geocode' => $data[1], 'format'  => 'json', 'results' => MAX_JSON_RESULTS);
        //echo 'http://geocode-maps.yandex.ru/1.x/?' . http_build_query($params, '', '&')."</br>";
		$response = json_decode(file_get_contents('http://geocode-maps.yandex.ru/1.x/?' . http_build_query($params, '', '&'), False, $cxContext));
		//print_r($response);
		$var_count = $response->response->GeoObjectCollection->metaDataProperty->GeocoderResponseMetaData->found;
		//echo "Найдено $var_count вариантов</br>";
		$exact_count=0; //количество вариантов с точностью exact/house для данного запроса
		$exact_var_num=-1; // индекс последнего найденного варианта с точностью exact/house. Если он один - то он и есть нужный. Если больше одного - то всё равно нам не нужен.
		for ($i=0; $i<min(MAX_JSON_RESULTS, $var_count); $i++)
		{
			//echo "Вариант №$i<br>";
			//echo "Координаты: ".$response->response->GeoObjectCollection->featureMember[$i]->GeoObject->Point->pos. "<br />\n";
			//echo "точность: ".$response->response->GeoObjectCollection->featureMember[$i]->GeoObject->metaDataProperty->GeocoderMetaData->precision. " / ";
			//echo "тип: ".$response->response->GeoObjectCollection->featureMember[$i]->GeoObject->metaDataProperty->GeocoderMetaData->kind. "<br />\n";
			/*foreach ($response->response->GeoObjectCollection->featureMember[$i] as $key ) {
				echo "Ключ: $key; Значение: value<br />\n";
			}
			*/
			
			if ($response->response->GeoObjectCollection->featureMember[$i]->GeoObject->metaDataProperty->GeocoderMetaData->precision == "exact" 
				&& $response->response->GeoObjectCollection->featureMember[$i]->GeoObject->metaDataProperty->GeocoderMetaData->kind == "house"	)
			{
				$exact_count++;
				$exact_var_num=$i;
			}
		}
		if ($exact_count==1  && $var_count < 10 && !strpos($data[1],"тел.") && !strpos($data[1],"Тел.") && $var_count < 10)
		{
			$good[] = parseFeatureMember($response->response->GeoObjectCollection->featureMember[$exact_var_num],$data[0],$data[1],$data[2],$data[3],$handle_good);
		}
		else //if ($exact_count==1)
		{
			for ($i=0; $i<min(MAX_JSON_RESULTS, $var_count); $i++)
			{	
			//	echo "Код $data[0], адрес $data[1] нераспознан</br>";
				$bad[] = parseFeatureMember($response->response->GeoObjectCollection->featureMember[$i],$data[0],$data[1],$data[2],$data[3],$handle_bad);
			}
		}
		if ($row % 100 == 0)
		{
			echo "Обработано $row строк";
		}
	}
    fclose($handle_in);
	/*
		echo "<table border=1>";
	echo "<tr>
	<th>uuid</th>
	<th>state*</th>
	<th>region*</th>
	<th>subarea</th>
	<th>city</th>
	<th>cityarea</th>
	<th>Улица+дом (address2)</th>
	<th>Исходный адрес (address3)</th>
	<th>Координаты</th>
	<th>kind / precision</th>
	<th>Название расположения</th>
	<th>Кол-во заявок</th>
	</tr>";
	foreach ($good as $g_i)
	{
		echo "<tr>
		<td>$g_i->uuid</td>
		<td>$g_i->country $g_i->country_uuid</td>
		<td>$g_i->region $g_i->region_uuid</td>
		<td>$g_i->subarea</td>
		<td>$g_i->city</td>
		<td>$g_i->cityarea</td>
		<td>$g_i->street $g_i->building</td>
		<td>$g_i->comment</td>
		<td>$g_i->coordinates</td>
		<td>$g_i->kind / $g_i->precision</td>
		<td>$g_i->locaton_name</td>
		<td>$g_i->number_of_incidents</td>
		</tr>";
	}
	
	echo "</table>";
	
	echo "<table border=1>";
	echo "<tr>
	<th>uuid</th>
	<th>state*</th>
	<th>region*</th>
	<th>subarea</th>
	<th>city</th>
	<th>cityarea</th>
	<th>Улица+дом (address2)</th>
	<th>Исходный адрес (address3)</th>
	<th>Координаты</th>
	<th>kind / precision</th>
	<th>Название расположения</th>
	<th>Кол-во заявок</th>
	</tr>";
	foreach ($bad as $b_i)
	{
		echo "<tr>
		<td>$b_i->uuid</td>
		<td>$b_i->country $b_i->country_uuid</td>
		<td>$b_i->region $b_i->region_uuid</td>
		<td>$b_i->subarea</td>
		<td>$b_i->city</td>
		<td>$b_i->cityarea</td>
		<td>$b_i->street $b_i->building</td>
		<td>$b_i->comment</td>
		<td>$b_i->coordinates</td>
		<td>$b_i->kind / $b_i->precision</td>
		<td>$b_i->locaton_name</td>
		<td>$b_i->number_of_incidents</td>
		</tr>";
	}
	
	echo "</table>";
	
		*/
	//SaveToFileForPDMLoad($good);
	//SaveToCSV($bad,BAD_OUTPUT_FILE);
	//SaveToCSV($good,GOOD_OUTPUT_FILE);
	//ниже просто отладочный вывод
	//print_r($bad);
	Echo "</br>";
	//echo $good[0]->comment."</br>";
	echo "Уверенно распознанных: ".count($good)."</br>";
}

fclose($handle_good);
fclose($handle_bad);
?>