<?php
namespace ldbglobe\geoip\source;

class ipinfodb {

	public function __construct($access_key)
	{
		$this->access_key = $access_key;
	}

	public function getLimitation()
	{
		return (object)array('max_hit'=>1000,'per_periode'=>'hour');
	}

	public function source_hash()
	{
		return 'ipinfodb';
	}

	public function resolve_location($ip,$geoip)
	{
		$data = json_decode(file_get_contents('http://api.ipinfodb.com/v3/ip-city/?key='.$this->access_key.'&ip='.$ip.'&format=json'));
		/*
		[statusCode] => OK
		[statusMessage] => 
		[ipAddress] => 77.136.85.134
		[countryCode] => FR
		[countryName] => France
		[regionName] => Provence-Alpes-Cote d'Azur
		[cityName] => Nice
		[zipCode] => 06833
		[latitude] => 43.7031
		[longitude] => 7.26608
		[timeZone] => +02:00
		*/
		if(is_object($data) && isset($data->cityName) && $data->cityName)
		{
			$geoip->setGps($data->latitude, $data->longitude);
			$geoip->setCountryCode($data->countryCode);
			return true;
		}
		return false;
	}
}
