<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 24.08.2016
 * Time: 16:47
 */

namespace diCore\Entity\Geo;

class FreeGeoIpLocation extends GeoIpLocation
{
	protected function fetchData()
	{
		// todo: find another free ip provider
		//$this->data = (array)json_decode(file_get_contents('http://freegeoip.net/json/' . $this->getIp()));

		return $this;
	}
}