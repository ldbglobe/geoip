<?php
namespace ldbglobe\geoip;

class geoip {

	static $_storage_path = null;
	static $_sources = array();

	private $_ip = null;
	private $_location = false;

	static public function SetStoragePath($path)
	{
		self::$_storage_path = realpath($path);
	}

	static public function AddSource($source)
	{
		self::$_sources[] = $source;
	}
	static public function AddDefaultSources()
	{
		self::AddSource(new source\geoplugin());
		self::AddSource(new source\ipapicom());
		self::AddSource(new source\ipapico());
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
		if(count(self::$_sources)==0)
			self::AddDefaultSources();

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

				foreach(self::$_sources as $source)
				{
					if($this->api_throttle_check($source))
					{
						$success = $source->resolve_location($this->_ip,$this);
						if($success)
							break;
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

	public function setGps($lat,$lon) { $this->_location['lat'] = $lat; $this->_location['lon'] = $lon; }
	public function setCountryCode($v) { $this->_location['country_code'] = $v; }

	private function cache_file()
	{
		$source_hash = '';
		foreach(self::$_sources as $source)
			$source_hash .= $source->source_hash();

		// handle both IP v4 & v6
		return self::$_storage_path.DIRECTORY_SEPARATOR.'location'.DIRECTORY_SEPARATOR.sha1($source_hash).DIRECTORY_SEPARATOR.str_replace(array('.',':'),DIRECTORY_SEPARATOR,$this->_ip).'.json';
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

	private function api_throttle_check($code,$max_hit=0,$per_periode='month')
	{
		if(is_object($code))
		{
			$limitation = $code->getLimitation();
			$max_hit = $limitation->max_hit;
			$per_periode = $limitation->per_periode;
			$code = $code->source_hash();
		}

		if($per_periode=='year')
			$log_file = self::$_storage_path.DIRECTORY_SEPARATOR.'api_throttle'.DIRECTORY_SEPARATOR.$code.DIRECTORY_SEPARATOR.date('Y').'log';
		else if($per_periode=='month')
			$log_file = self::$_storage_path.DIRECTORY_SEPARATOR.'api_throttle'.DIRECTORY_SEPARATOR.$code.DIRECTORY_SEPARATOR.date('Ym').'log';
		else if($per_periode=='day')
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
							//echo $full;
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