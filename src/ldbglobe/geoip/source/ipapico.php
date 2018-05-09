<?php
namespace ldbglobe\geoip\source;

class ipapico {

	public function __construct()
	{
	}

	public function getLimitation()
	{
		return (object)array('max_hit'=>1000,'per_periode'=>'day');
	}

	public function source_hash()
	{
		return 'ipapico';
	}

	public function resolve_location($ip,$geoip)
	{
		$data = json_decode(file_get_contents('https://ipapi.co/'.$ip.'/json/'));
		/*
		"ip": "x.x.x.x",
		"city": "Nantes",
		"region": "Pays de la Loire",
		"region_code": "PDL",
		"country": "FR",
		"country_name": "France",
		"postal": "44000",
		"latitude": 47.2165,
		"longitude": -1.5524,
		"timezone": "Europe/Paris",
		"asn": "AS3215",
		"org": "Orange"
		*/
		if(is_object($data) && isset($data->city) && $data->city)
		{
			$geoip->setGps($data->latitude, $data->longitude);
			$geoip->setCountryCode($data->country);
			return true;
		}
		return false;
	}
}
