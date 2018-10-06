<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 06.10.2018
 * Time: 12:48
 */

namespace diCore\Entity\Geo;

class ExtremeIpLookupLocation extends GeoIpLocation
{
    protected function fetchData()
    {
        $this->data = (array)json_decode(file_get_contents('http://extreme-ip-lookup.com/json/' . $this->getIp()));

        $this->data['countryName'] = $this->data['country'];

        return $this;
    }
}