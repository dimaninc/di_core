<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 06.10.2018
 * Time: 13:27
 */

namespace diCore\Entity\Geo;

use diCore\Tool\Logger;
use MaxMind\Db\Reader;

class GeoLiteLocation extends GeoIpLocation
{
    /** @var Reader */
    protected $Reader;

    protected $language = 'en';

    /**
     * @return Reader
     */
    protected function getReader()
    {
        if (!$this->Reader)
        {
            $this->Reader = new Reader(\diCore\Helper\StringHelper::slash(dirname(\diPaths::fileSystem())) .
                'data/geo/GeoLite2-City.mmdb');
        }

        return $this->Reader;
    }

    protected function closeReader()
    {
        if ($this->Reader)
        {
            $this->Reader->close();
            $this->Reader = null;
        }

        return $this;
    }

    protected function fetchData()
    {
        $d = $this->getReader()->get($this->getIp());

        if ($d)
        {
            $this->data = [
                'city' => $d['city']['names'][$this->language],
                'country_code' => $d['country']['iso_code'],
                'country_name' => $d['country']['names'][$this->language],
                'region_code' => $d['subdivisions'][0]['iso_code'],
                'region_name' => $d['subdivisions'][0]['names'][$this->language],
                'zip_code' => $d['postal']['code'],
                'latitude' => $d['location']['latitude'],
                'longitude' => $d['location']['longitude'],
                'metro_code' => null,
            ];
        }
        else
        {
            Logger::getInstance()->log('Geo data for IP not found: ' . $this->getIp());

            $this->data = [];
        }

        return $this;
    }
}