<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 06.10.2018
 * Time: 13:27
 */

namespace diCore\Entity\Geo;

use diCore\Helper\StringHelper;
use diCore\Tool\Logger;
use MaxMind\Db\Reader;

class GeoLiteLocation extends GeoIpLocation
{
    /** @var Reader */
    protected $Reader;

    protected $language = 'en';

    protected function getReader()
    {
        if (!$this->Reader) {
            $this->Reader = new Reader(
                StringHelper::slash(dirname(\diPaths::fileSystem())) .
                    'data/geo/GeoLite2-City.mmdb'
            );
        }

        return $this->Reader;
    }

    protected function closeReader()
    {
        if ($this->Reader) {
            $this->Reader->close();
            $this->Reader = null;
        }

        return $this;
    }

    protected function fetchData()
    {
        try {
            $d = $this->getReader()->get($this->getIp());

            if ($d) {
                $this->data = [
                    'city' => $d['city']['names'][$this->language] ?? null,
                    'country_code' => $d['country']['iso_code'] ?? null,
                    'country_name' =>
                        $d['country']['names'][$this->language] ?? null,
                    'region_code' => $d['subdivisions'][0]['iso_code'] ?? null,
                    'region_name' =>
                        $d['subdivisions'][0]['names'][$this->language] ?? null,
                    'zip_code' => $d['postal']['code'] ?? null,
                    'latitude' => $d['location']['latitude'] ?? null,
                    'longitude' => $d['location']['longitude'] ?? null,
                    'metro_code' => null,
                ];
            } else {
                if (static::shouldLogAboutIpNotFound($this->getIp())) {
                    Logger::getInstance()->log(
                        'Geo data for IP not found: ' . $this->getIp()
                    );
                }

                $this->data = [];
            }
        } catch (\Exception $e) {
            Logger::getInstance()->log('Geo data error: ' . $e->getMessage());

            $this->data = [];
        }

        return $this;
    }
}
