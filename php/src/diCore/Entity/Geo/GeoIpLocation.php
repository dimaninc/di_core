<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 24.08.2016
 * Time: 16:47
 */

namespace diCore\Entity\Geo;

/**
 * Methods list for IDE
 *
 * @method string	getCountryCode
 * @method string	getCountryName
 * @method string	getRegionCode
 * @method string	getRegionName
 * @method string	getCity
 * @method integer	getZipCode
 * @method double	getLatitude
 * @method double	getLongitude
 * @method integer	getMetroCode
 *
 * @method bool 	hasCountryCode
 * @method bool 	hasCountryName
 * @method bool 	hasRegionCode
 * @method bool 	hasRegionName
 * @method bool 	hasCity
 * @method bool 	hasZipCode
 * @method bool 	hasLatitude
 * @method bool 	hasLongitude
 * @method bool 	hasMetroCode
 *
 * @method GeoIpLocation setCountryCode($value)
 * @method GeoIpLocation setCountryName($value)
 * @method GeoIpLocation setRegionCode($value)
 * @method GeoIpLocation setRegionName($value)
 * @method GeoIpLocation setCity($value)
 * @method GeoIpLocation setZipCode($value)
 * @method GeoIpLocation setLatitude($value)
 * @method GeoIpLocation setLongitude($value)
 * @method GeoIpLocation setMetroCode($value)
 */
class GeoIpLocation
{
	protected $ip;
	protected $data = [];

	protected static $providers = [
		FreeGeoIpLocation::class,
	];

	public function __construct($ip = null)
	{
		$this->ip = $ip;

		if (isInteger($this->ip))
		{
			$this->ip = bin2ip($this->ip);
		}

		$this->readData();
	}

	/**
	 * @param int|string|null $ip
	 * @return GeoIpLocation
	 * @throws \Exception
	 */
	public static function create($ip = null)
	{
		foreach (self::$providers as $provider)
		{
			if (class_exists($provider))
			{
				return new $provider($ip);
			}
		}

		throw new \Exception('No providers found');
	}

	protected function useCache()
	{
		return true;
	}

	protected function readFromCache()
	{
		if ($this->useCache())
		{
			/** @var \diGeoIpCacheModel $cache */
			$cache = \diCollectionCache::getModel(\diTypes::geo_ip_cache, $this->getBinIp());

			if (!$cache->exists())
			{
				$cache = \diModel::create(\diTypes::geo_ip_cache, $this->getBinIp());
			}

			if ($cache->exists())
			{
				$this
					->setCountryCode($cache->getCountryCode())
					->setCountryName($cache->getCountryName())
					->setRegionCode($cache->getRegionCode())
					->setRegionName($cache->getRegionName())
					->setCity($cache->getCity())
					->setZipCode($cache->getZipCode())
					->setLatitude($cache->getLatitude())
					->setLongitude($cache->getLongitude());
			}
		}

		return $this;
	}

	protected function writeToCache()
	{
		if ($this->useCache() && $this->exists())
		{
			/** @var \diGeoIpCacheModel $cache */
			$cache = \diModel::create(\diTypes::geo_ip_cache);

			try
			{
				$cache
					->setIp($this->getBinIp())
					->setCountryCode($this->getCountryCode())
					->setCountryName($this->getCountryName())
					->setRegionCode($this->getRegionCode())
					->setRegionName($this->getRegionName())
					->setCity($this->getCity())
					->setZipCode($this->getZipCode())
					->setLatitude($this->getLatitude())
					->setLongitude($this->getLongitude())
					->save();
			}
			catch (\Exception $e)
			{
				throw $e;
			}
		}

		return $this;
	}

	protected function readData()
	{
		$this->readFromCache();

		if (!$this->exists() && $this->getBinIp())
		{
			$this
				->fetchData()
				->writeToCache();
		}

		return $this;
	}

	/**
	 * @link http://stackoverflow.com/questions/409999/getting-the-location-from-an-ip-address
	 * Needs to be overridden
	 * @return $this
	 */
	protected function fetchData()
	{
		return $this;
	}

	public function __call($method, $arguments)
	{
		$fullMethod = underscore($method);
		$value = isset($arguments[0]) ? $arguments[0] : null;

		$x = strpos($fullMethod, "_");
		$method = substr($fullMethod, 0, $x);
		$field = substr($fullMethod, $x + 1);

		switch ($method)
		{
			case "get":
				return $this->get($field);

			case "has":
				return $this->has($field);

			case "exists":
				return $this->exists($field);

			case "set":
				return $this->set($field, $value);
		}

		throw new \Exception(
			sprintf("Invalid method %s::%s(%s)", get_class($this), $method, print_r($arguments, 1))
		);
	}

	/**
	 * @param string|null $field
	 * @return string|int|null|object
	 */
	public function get($field = null)
	{
		if (is_null($field))
		{
			return $this->data;
		}

		if (!$this->exists($field))
		{
			return null;
		}

		return $this->data[$field];
	}

	/**
	 * @param null|string $field
	 * @return bool
	 */
	public function exists($field = null)
	{
		return is_null($field)
			? !!$this->data
			: isset($this->data[$field]);
	}

	public function has($field)
	{
		return !empty($this->data[$field]);
	}

	public function set($field, $value = null)
	{
		if (is_null($value))
		{
			$this->data = extend($this->data, $field);
		}
		else
		{
			$this->data[$field] = $value;
		}

		return $this;
	}

	public function getIp()
	{
		return $this->ip ?: get_user_ip();
	}

	public function getBinIp()
	{
		return ip2bin($this->getIp());
	}
}