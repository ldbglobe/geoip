<?php
namespace ldbglobe\geoip;

class geoip {

	static $_storage_path = null;

	private $_ip = null;
	private $_location = false;

	static public function SetStoragePath($path)
	{
		self::$_storage_path = realpath($path);
	}

	public function __construct($ip=null)
	{
		$this->_ip = $ip!==null ? $ip : (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null);

		if(!file_exists(self::$_storage_path))
			throw new \Exception("ldbglobe\\geoip : Storage path is not set", 1);
		if(!is_dir(self::$_storage_path))
			throw new \Exception("ldbglobe\\geoip : Storage path is not a directory \"".self::$_storage_path."\"", 1);
	}

	public function ip_validation()
	{
		return $this->_ip != null;
	}

	public function resolve_location($ip=null)
	{
		$this->_ip = $ip!==null ? $ip : $this->_ip;

		if($this->ip_validation())
		{
			if($this->cache_up_to_date())
			{
				$this->cache_read();
			}

			// if data available or last update is too recent
			if($this->_location!==false || $this->cache_up_to_date(true))
			{
				// nothing to do
			}
			else
			{
				$success = false;
				$this->_location = array();

				// doing some calls then update cache before returning result

				// ----------------------------------------------------------------------------------
				// ----------------------------------------------------------------------------------
				// massive call but lack of accuracy
				if(!$success && $this->api_throttle_check('geoplugin.net',10000,'day'))
				{
					$data = json_decode(file_get_contents('http://www.geoplugin.net/json.gp?ip='.$this->_ip));
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
						$success = true;
						$this->setGps($data->geoplugin_latitude, $data->geoplugin_longitude);
						$this->setCountryCode($data->geoplugin_countryCode);
					}
				}

				// ----------------------------------------------------------------------------------
				// ----------------------------------------------------------------------------------
				// massive call but lack of accuracy
				if(!$success && $this->api_throttle_check('freegeoip.net',10000,'hour'))
				{
					$data = json_decode(file_get_contents('http://www.freegeoip.net/json/'.$this->_ip));
					/*
					"ip": "x.x.x.x",
					"country_code": "FR",
					"country_name": "France",
					"region_code": "",
					"region_name": "",
					"city": "",
					"zip_code": "",
					"time_zone": "Europe/Paris",
					"latitude": 48.8582,
					"longitude": 2.3387,
					"metro_code": 0
					*/
					if(is_object($data) && isset($data->city) && $data->city)
					{
						$success = true;
						$this->setGps($data->latitude, $data->longitude);
						$this->setCountryCode($data->country_code);
					}
				}

				// ----------------------------------------------------------------------------------
				// ----------------------------------------------------------------------------------
				if(!$success && $this->api_throttle_check('ip-api.com',100,'minute'))
				{
					$data = json_decode(file_get_contents('http://ip-api.com/json/'.$this->_ip));
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
						$success = true;
						$this->setGps($data->lat, $data->lon);
						$this->setCountryCode($data->countryCode);
					}
				}

				// ----------------------------------------------------------------------------------
				// ----------------------------------------------------------------------------------
				if(!$success && $this->api_throttle_check('ipapi.co',1000,'day'))
				{
					$data = json_decode(file_get_contents('https://ipapi.co/'.$this->_ip.'/json/'));
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
						$success = true;
						$this->setGps($data->latitude, $data->longitude);
						$this->setCountryCode($data->country);
					}
				}

				if(!$success)
					$this->_location = false;

				$this->cache_write();
			}
		}
		else
		{
			throw new \Exception("ldbglobe\\geoip : IP is not valid ".$this->_ip, 1);
		}

		return json_decode(json_encode(array(
			'ip'=>$this->_ip,
			'location'=>$this->_location,
		)));
	}

	private function setGps($lat,$lon) { $this->_location['lat'] = $lat; $this->_location['lon'] = $lon; }
	private function setCountryCode($v) { $this->_location['country_code'] = $v; }

	private function cache_file()
	{
		// handle both IP v4 & v6
		return self::$_storage_path.DIRECTORY_SEPARATOR.'location'.DIRECTORY_SEPARATOR.str_replace(array('.',':'),DIRECTORY_SEPARATOR,$this->_ip).'.json';
	}

	private function cache_up_to_date($short=false)
	{
		return file_exists($this->cache_file()) && filemtime($this->cache_file()) > time() - ($short ? 3600*6 : 3600*24*30); // one month retention in long mode
	}

	private function cache_read()
	{
		if($this->cache_up_to_date())
		{
			$this->_location = json_decode(file_get_contents($this->cache_file()),1);
		}
		else
		{
			return null;
		}
	}

	private function cache_write()
	{
		if(!file_exists(dirname($this->cache_file())))
			mkdir(dirname($this->cache_file()),0777,true);

		file_put_contents($this->cache_file(),json_encode($this->_location));
	}

	private function api_throttle_check($code,$max_hit,$per_periode)
	{
		if($per_periode=='month')
			$log_file = self::$_storage_path.DIRECTORY_SEPARATOR.'api_throttle'.DIRECTORY_SEPARATOR.$code.DIRECTORY_SEPARATOR.date('Ym').'log';
		if($per_periode=='day')
			$log_file = self::$_storage_path.DIRECTORY_SEPARATOR.'api_throttle'.DIRECTORY_SEPARATOR.$code.DIRECTORY_SEPARATOR.date('Ymd').'log';
		else if($per_periode=='hour')
			$log_file = self::$_storage_path.DIRECTORY_SEPARATOR.'api_throttle'.DIRECTORY_SEPARATOR.$code.DIRECTORY_SEPARATOR.date('YmdH').'log';
		else if($per_periode=='minute')
			$log_file = self::$_storage_path.DIRECTORY_SEPARATOR.'api_throttle'.DIRECTORY_SEPARATOR.$code.DIRECTORY_SEPARATOR.date('YmdHm').'log';

		// cleanup of all old file
		$this->api_throttle_purge();

		if(file_exists($log_file))
			$c = file_get_contents($log_file);
		else
			$c = 0;

		if($c < $max_hit)
		{
			if(!file_exists(dirname($log_file)))
				mkdir(dirname($log_file),0777,true);
			file_put_contents($log_file,$c+1);
			return true;
		}
		return false;
	}

	private function api_throttle_purge($src=null)
	{
		$src = $src ? $src : self::$_storage_path.DIRECTORY_SEPARATOR.'api_throttle';
		if(file_exists($src))
		{
			$dir = opendir($src);
			$allclear = true;

			while(false !== ( $file = readdir($dir)) )
			{
				if (( $file != '.' ) && ( $file != '..' ))
				{
					$full = $src . '/' . $file;
					if ( is_dir($full) )
					{
						$allclear = $allclear && $this->api_throttle_purge($full);
					}
					else
					{
						if(filemtime($full) < time()-3600*24*60)
						{
							//unlink($full);
							echo $full;
							$allclear = false;
						}
						else
							$allclear = false;
					}
				}
			}
			closedir($dir);
			if($allclear)
				rmdir($src);

			return $allclear;
		}
		return false;
	}
}

/*
//http://ip-api.com/json
	// Geoloc Api Session
	if(!Session::Get('geolocate'))
	{
	    try {
	        $ctx = stream_context_create(array('http'=>
	                array(
	                    'timeout' => 5,
	                )
	            ));
	        $info_visitor = unserialize(file_get_contents('http://www.geoplugin.net/php.gp?ip='.$_SERVER['REMOTE_ADDR'], false, $ctx));
	        $info_visitor['geoplugin_credit'] = null;
	        $info_visitor = json_encode($info_visitor);
	        Session::Set('geolocate', $info_visitor);
	    } catch (Exception $e) {
	        $info_visitor = json_encode(array('status'=>'error'));
	    }
	}
	else
	{
	    $info_visitor = Session::Get('geolocate');
	}
*/