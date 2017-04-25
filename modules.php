<?php
class location
{
	public $uuid;
	public $country;
	public $country_uuid;
	public $region;
	public $region_uuid;
	public $subarea;
	public $city;
	public $cityarea;
	public $street;
	public $building;
	public $comment;
	public $coordinates;
	public $kind;
	public $precision;
	public $locaton_name;
	public $number_of_incidents;
	
	public function __construct()
	{
	}
}

function parseFeatureMember($featureMember, $uuid, $source_address, $locaton_name, $number_of_incidents, $output_file)
{
	global $region_replace_array;
	global $country_replace_array;
	//global $handle_good, $handle_bad;
	$city_obj = "";
	$dep_loc = "";
	$i_cityarea = "";
	$i_street = "";
	$i_building = "";
	$i_region_uuid = "";
	
//для читабельности и возможности нормально построить вариант с отсутствующей веткой SubAdministrativeArea введем переменную $city_obj и будем там держать ссылку на эту часть дерева
	if ($featureMember->GeoObject->metaDataProperty->GeocoderMetaData->AddressDetails->Country->AdministrativeArea->SubAdministrativeArea !== null)
	{
		$city_obj = $featureMember->GeoObject->metaDataProperty->GeocoderMetaData->AddressDetails->Country->AdministrativeArea->SubAdministrativeArea->Locality;
	}
	else 
	{
		$city_obj = $featureMember->GeoObject->metaDataProperty->GeocoderMetaData->AddressDetails->Country->AdministrativeArea->Locality;
	}
	
	if ($city_obj->DependentLocality !== null)
	{
		if ($city_obj->DependentLocality->DependentLocality !== null)
		{
			$dep_loc = $city_obj->DependentLocality->DependentLocality;
		}
		else
		{
			$dep_loc = $city_obj->DependentLocality;
		}
	
		$i_cityarea = $dep_loc->DependentLocalityName;
		if ($dep_loc->Thoroughfare !== null)
		{
			$i_street = $dep_loc->Thoroughfare->ThoroughfareName;
			$i_building = $dep_loc->Thoroughfare->Premise->PremiseNumber;
		}
		else
		{
			$i_street = "";
			$i_building = $dep_loc->Premise->PremiseNumber;
		}
	}
	else //$city_obj->DependentLocality == null
	{
		//$i_cityarea = "";
		if ($city_obj->Thoroughfare !== null)
		{
			$i_street = $city_obj->Thoroughfare->ThoroughfareName;
			$i_building = $city_obj->Thoroughfare->Premise->PremiseNumber;
		}
		else
		{
			$i_street = null;
			$i_building = $city_obj->Premise->PremiseNumber;
		}
	}
	
	if ($featureMember->GeoObject->metaDataProperty->GeocoderMetaData->AddressDetails->Country->CountryName == "Россия")
	{
		$i_region_uuid= $region_replace_array[$featureMember->GeoObject->metaDataProperty->GeocoderMetaData->AddressDetails->Country->AdministrativeArea->AdministrativeAreaName];
		if ($i_region_uuid == "")
		{
			$i_region_uuid = "*** " . $featureMember->GeoObject->metaDataProperty->GeocoderMetaData->AddressDetails->Country->AdministrativeArea->AdministrativeAreaName . " ***";
		}
	}
	else
	{
		$i_region_uuid= $region_replace_array[$featureMember->GeoObject->metaDataProperty->GeocoderMetaData->AddressDetails->Country->CountryName];
	}
	
	$temp_loc = new location ();
	$temp_loc->uuid         = $uuid;
	$temp_loc->country      = $featureMember->GeoObject->metaDataProperty->GeocoderMetaData->AddressDetails->Country->CountryName;
	$temp_loc->country_uuid = $country_replace_array[$featureMember->GeoObject->metaDataProperty->GeocoderMetaData->AddressDetails->Country->CountryName];
	$temp_loc->region       = $featureMember->GeoObject->metaDataProperty->GeocoderMetaData->AddressDetails->Country->AdministrativeArea->AdministrativeAreaName;
	$temp_loc->region_uuid  = $i_region_uuid;
	$temp_loc->subarea      = $featureMember->GeoObject->metaDataProperty->GeocoderMetaData->AddressDetails->Country->AdministrativeArea->SubAdministrativeArea->SubAdministrativeAreaName;
	$temp_loc->city         = $city_obj->LocalityName;
	$temp_loc->cityarea     = $i_cityarea;
	$temp_loc->street       = $i_street;
	$temp_loc->building     = str_replace('"','', $i_building);
	$temp_loc->comment      = str_replace('"','', $source_address);
	$temp_loc->coordinates  = $featureMember->GeoObject->Point->pos;
	$temp_loc->kind			= $featureMember->GeoObject->metaDataProperty->GeocoderMetaData->kind;
	$temp_loc->precision   	= $featureMember->GeoObject->metaDataProperty->GeocoderMetaData->precision;
	$temp_loc->locaton_name	= $locaton_name;
	$temp_loc->number_of_incidents	= $number_of_incidents;
	
	//print_r ($temp_loc);
	return $temp_loc;
}

function SaveToFileForPDMLoad($locations)
{
	//на вход получаем массив объектов класса location. Открываем для записи файл, пишем в него заголовок для pdm_load, а потом нужные поля в нужном формате.
	$handle = fopen(FOR_LOAD,"wb");
	$tail = '"Распознавание адресов", 1424270704' ; //inactive, last_mod_by, last_mod_dt
	fwrite ($handle, "Table ca_location\n");
	//fwrite ($handle, "id, country, state, city, address_1, address_2, address_3, address_4, mail_address_1, inactive, last_update_user, last_update_date\n");
	fwrite ($handle, "id, country, state, city, address_1, address_2, address_3, address_4, mail_address_1, last_update_user, last_update_date\n");
	
	//	***********************УДАЛЯТЬ КАВЫЧКИ ИЗ АДРЕСОВ!!!!!!!!!!!!!********************
	
	
	foreach ($locations as $i)
	{
		if (!empty($i->street) && !empty($i->building))
		{
			$address1 = "$i->street, $i->building";
		}
		elseif (!empty($i->street) && empty($i->building))
		{
			$address1 = "$i->street";
		}
		elseif (!empty($i->cityarea) && !empty($i->building))
		{
			$address1 = "$i->cityarea, $i->building";
		}
		elseif (!empty($i->cityarea) && empty($i->building))
		{
			$address1 = "$i->cityarea";
		}
		elseif (!empty($i->city) && empty($i->cityarea) && empty($i->street) && !empty($i->building))
		{
			$address1 = "$i->city, $i->building";
		}
		else
		{
			$address1 = "$i->city $i->cityarea $i->street $i->building";
		}
								//id, 			country, 			state, 		city, 		address1, 		address2, 			address3, 	address4, mail_address1, inactive, last_mod_by, last_mod_dt
				
		fwrite ($handle,  "{ $i->uuid, $i->country_uuid, $i->region_uuid, \"$i->city\", \"$address1\", \"$i->subarea\", \"$i->cityarea\", \"$i->coordinates\", \"$i->comment\", $tail }\n");
		//fwrite ($handle,  "{ $tail \n");
		//echo '{$i->uuid, $i->country_uuid, $i->region_uuid, "$i->city", "$address1", "$i->subarea", "$i->cityarea", "$i->coordinates", "$i->comment", $tail }';
	}
	fclose($handle);
}
function SaveToCSV($locations, $filename)
{
$handle = fopen($filename,"wb");
	foreach ($locations as $i)
	{
		foreach ($i as $key=>$value)
		{
			fwrite($handle, $value.";");
		}
		fwrite ($handle, "\n");
	}
	fclose($handle);
}
?>