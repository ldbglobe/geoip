<?php
namespace ldbglobe\geoip\source;

class ipapicom {

	public function __construct()
	{
	}

	public function getLimitation()
	{
		return (object)array('max_hit'=>100,'per_periode'=>'minute');
	}

	public function source_hash()
	{
		return 'ipapicom';
	}

	public function resolve_location($ip,$geoip)
	{
		$data = json_decode(file_get_contents('http://ip-api.com/json/'.$ip));
		/*
		[as] => AS3215 Orange
		[city] => Andard
		[country] => France
		[countryCode] => FR
		[isp] => Orange
		[lat] => 47.4566
		[lon] => -0.3975
		[org] => Orange
		[query] => 2.9.22.228
		[region] => PDL
		[regionName] => Pays de la Loire
		[status] => success
		[timezone] => Europe/Paris
		[zip] => 49800
		*/
		if(is_object($data) && isset($data->city) && $data->city)
		{
			$geoip->setGps($data->lat, $data->lon);
			$geoip->setCountryCode($data->countryCode);
			return true;
		}
		return false;
	}
}
