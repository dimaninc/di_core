<?php
/**
 * Created by diModelsManager
 * Date: 25.08.2016
 * Time: 21:47
 */

/**
 * Class diGeoIpCacheModel
 * Methods list for IDE
 *
 * @method integer	getIp
 * @method string	getCountryCode
 * @method string	getCountryName
 * @method string	getRegionCode
 * @method string	getRegionName
 * @method string	getCity
 * @method string	getZipCode
 * @method double	getLatitude
 * @method double	getLongitude
 * @method string	getCreatedAt
 * @method string	getUpdatedAt
 *
 * @method bool hasIp
 * @method bool hasCountryCode
 * @method bool hasCountryName
 * @method bool hasRegionCode
 * @method bool hasRegionName
 * @method bool hasCity
 * @method bool hasZipCode
 * @method bool hasLatitude
 * @method bool hasLongitude
 * @method bool hasCreatedAt
 * @method bool hasUpdatedAt
 *
 * @method diGeoIpCacheModel setIp($value)
 * @method diGeoIpCacheModel setCountryCode($value)
 * @method diGeoIpCacheModel setCountryName($value)
 * @method diGeoIpCacheModel setRegionCode($value)
 * @method diGeoIpCacheModel setRegionName($value)
 * @method diGeoIpCacheModel setCity($value)
 * @method diGeoIpCacheModel setZipCode($value)
 * @method diGeoIpCacheModel setLatitude($value)
 * @method diGeoIpCacheModel setLongitude($value)
 * @method diGeoIpCacheModel setCreatedAt($value)
 * @method diGeoIpCacheModel setUpdatedAt($value)
 */
class diGeoIpCacheModel extends \diModel
{
	const type = \diTypes::geo_ip_cache;
	const id_field_name = 'ip';
	const slug_field_name = 'ip';
	protected $table = "geo_ip_cache";
	protected $idAutoIncremented = false;

	public function prepareForSave()
	{
		if (!isInteger($this->getIp()))
		{
			$this
				->setIp(ip2bin($this->getIp()));
		}

		return $this;
	}

	public function validate()
	{
		parent::validate();

		if (!$this->hasIp())
		{
			$this->addValidationError("IP required");
		}

		return $this;
	}
}