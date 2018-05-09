<?php
namespace ldbglobe\geoip\source;

class geoplugin {

	public function __construct()
	{
	}

	public function getLimitation()
	{
		return (object)array('max_hit'=>1000,'per_periode'=>'day');
	}

	public function source_hash()
	{
		return 'geoplugin';
	}

	public function resolve_location($ip,$geoip)
	{
		$data = json_decode(file_get_contents('http://www.geoplugin.net/json.gp?ip='.$ip));
		/*
		"geoplugin_request": "x.x.x.x",
		"geoplugin_status": 206,
		"geoplugin_credit": "Some of the returned data includes GeoLite data created by MaxMind, available from <a href='http://www.maxmind.com'>http://www.maxmind.com</a>.",
		"geoplugin_city": "",
		"geoplugin_region": "",
		"geoplugin_areaCode": "0",
		"geoplugin_dmaCode": "0",
		"geoplugin_countryCode": "FR",
		"geoplugin_countryName": "France",
		"geoplugin_continentCode": "EU",
		"geoplugin_latitude": "48.8582",
		"geoplugin_longitude": "2.3387",
		"geoplugin_regionCode": "",
		"geoplugin_regionName": "",
		"geoplugin_currencyCode": "EUR",
		"geoplugin_currencySymbol": "&#8364;",
		"geoplugin_currencySymbol_UTF8": "€",
		"geoplugin_currencyConverter": 0.8438
		*/
		if(is_object($data) && isset($data->geoplugin_city) && $data->geoplugin_city)
		{
			$geoip->setGps($data->geoplugin_latitude, $data->geoplugin_longitude);
			$geoip->setCountryCode($data->geoplugin_countryCode);

			return true;
		}
		return false;
	}
}
