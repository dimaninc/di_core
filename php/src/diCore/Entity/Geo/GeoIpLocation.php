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
 * @method $this setCountryCode($value)
 * @method $this setCountryName($value)
 * @method $this setRegionCode($value)
 * @method $this setRegionName($value)
 * @method $this setCity($value)
 * @method $this setZipCode($value)
 * @method $this setLatitude($value)
 * @method $this setLongitude($value)
 * @method $this setMetroCode($value)
 */
class GeoIpLocation
{
    /** @var string */
    protected $ip;
    protected $data = [];

    protected static $providers = [
        //FreeGeoIpLocation::class,
        //ExtremeIpLookupLocation::class,
        GeoLiteLocation::class,
    ];

    public static $localIps = ['127.0.0.1'];

    public static $crimeaRegions = [
        'Crimea',
        'Republic of Crimea',
        'Autonomous Republic of Crimea',
        'Krym',
        'Gorod Sevastopol',
        'Sevastopol',
        "Sevastopol'",
        'Sebastopol City',
    ];

    public static $novorossiaRegions = [
        'Donetsk',
        'Donetsk Oblast',
        'Kherson',
        'Kherson Oblast',
        'Luhansk',
        'Zaporizhzhia',
        'Zaporizhzhya Oblast',
    ];

    public function __construct($ip = null)
    {
        $this->ip = $ip;

        if (isInteger($this->ip)) {
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
        foreach (self::$providers as $provider) {
            if (class_exists($provider)) {
                return new $provider($ip);
            }
        }

        throw new \Exception('No providers found');
    }

    protected function useCache()
    {
        return false;
    }

    protected function readData()
    {
        if (!$this->exists() && $this->getBinIp()) {
            $this->fetchData()->fixData();
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

    protected function fixData()
    {
        if ($this->isCrimea() || $this->isNovorossia()) {
            $this->setCountryCode('RU')->setCountryName('Russia');
        }

        return $this;
    }

    public function isCrimea()
    {
        return in_array($this->getRegionName(), GeoIpLocation::$crimeaRegions);
    }

    public function isNovorossia()
    {
        return in_array($this->getRegionName(), GeoIpLocation::$novorossiaRegions);
    }

    public function __call($method, $arguments)
    {
        $fullMethod = underscore($method);
        $value = isset($arguments[0]) ? $arguments[0] : null;

        $x = strpos($fullMethod, '_');
        $method = substr($fullMethod, 0, $x);
        $field = substr($fullMethod, $x + 1);

        switch ($method) {
            case 'get':
                return $this->get($field);

            case 'has':
                return $this->has($field);

            case 'exists':
                return $this->exists($field);

            case 'set':
                return $this->set($field, $value);
        }

        throw new \Exception(
            sprintf(
                'Invalid method %s::%s(%s)',
                get_class($this),
                $method,
                print_r($arguments, 1)
            )
        );
    }

    /**
     * @param string|null $field
     * @return string|int|null|array
     */
    public function get($field = null)
    {
        if (is_null($field)) {
            return $this->data;
        }

        if (!$this->exists($field)) {
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
        return is_null($field) ? !!$this->data : isset($this->data[$field]);
    }

    public function has($field)
    {
        return !empty($this->data[$field]);
    }

    public function set($field, $value = null)
    {
        if (is_null($value)) {
            $this->data = extend($this->data, $field);
        } else {
            $this->data[$field] = $value;
        }

        return $this;
    }

    public function getIp()
    {
        return $this->ip ?: \diRequest::getRemoteIp();
    }

    public function getBinIp()
    {
        return ip2bin($this->getIp());
    }

    public static function isLocalIp($ip)
    {
        return in_array($ip, static::$localIps);
    }

    public static function shouldLogAboutIpNotFound($ip)
    {
        return !static::isLocalIp($ip);
    }
}
