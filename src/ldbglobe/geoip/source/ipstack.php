<?php
namespace ldbglobe\geoip\source;

class ipstack {

	public function __construct($access_key)
	{
		$this->access_key = $access_key;
	}

	public function getLimitation()
	{
		return (object)array('max_hit'=>10000,'per_periode'=>'month');
	}

	public function source_hash()
	{
		return 'ipstack';
	}

	public function resolve_location($ip,$geoip)
	{
		$data = json_decode(file_get_contents('http://api.ipstack.com/'.$ip.'?access_key='.$this->access_key.'&output=json'));
		/*
	    [ip] => 77.136.85.134
	    [type] => ipv4
	    [continent_code] => EU
	    [continent_name] => Europe
	    [country_code] => FR
	    [country_name] => France
	    [region_code] => IDF
	    [region_name] => Île-de-France
	    [city] => Bondy
	    [zip] => 93140
	    [latitude] => 48.9018
	    [longitude] => 2.4893
	    [location] => stdClass Object
	        (
	            [geoname_id] => 3031815
	            [capital] => Paris
	            [languages] => Array
	                (
	                    [0] => stdClass Object
	                        (
	                            [code] => fr
	                            [name] => French
	                            [native] => Français
	                        )

	                )

	            [country_flag] => http://assets.ipstack.com/flags/fr.svg
	            [country_flag_emoji] => 🇫🇷
	            [country_flag_emoji_unicode] => U+1F1EB U+1F1F7
	            [calling_code] => 33
	            [is_eu] => 1
	        )
		*/
		if(is_object($data) && isset($data->city) && $data->city)
		{
			$geoip->setGps($data->latitude, $data->longitude);
			$geoip->setCountryCode($data->country_code);
			return true;
		}
		return false;
	}
}
