<?php
/**
 * Created by diModelsManager
 * Date: 25.08.2016
 * Time: 21:47
 */

/**
 * Class diGeoIpCacheCollection
 * Methods list for IDE
 *
 * @method diGeoIpCacheCollection filterByIp($value, $operator = null)
 * @method diGeoIpCacheCollection filterByCountryCode($value, $operator = null)
 * @method diGeoIpCacheCollection filterByCountryName($value, $operator = null)
 * @method diGeoIpCacheCollection filterByRegionCode($value, $operator = null)
 * @method diGeoIpCacheCollection filterByRegionName($value, $operator = null)
 * @method diGeoIpCacheCollection filterByCity($value, $operator = null)
 * @method diGeoIpCacheCollection filterByZipCode($value, $operator = null)
 * @method diGeoIpCacheCollection filterByLatitude($value, $operator = null)
 * @method diGeoIpCacheCollection filterByLongitude($value, $operator = null)
 * @method diGeoIpCacheCollection filterByCreatedAt($value, $operator = null)
 * @method diGeoIpCacheCollection filterByUpdatedAt($value, $operator = null)
 *
 * @method diGeoIpCacheCollection orderByIp($direction = null)
 * @method diGeoIpCacheCollection orderByCountryCode($direction = null)
 * @method diGeoIpCacheCollection orderByCountryName($direction = null)
 * @method diGeoIpCacheCollection orderByRegionCode($direction = null)
 * @method diGeoIpCacheCollection orderByRegionName($direction = null)
 * @method diGeoIpCacheCollection orderByCity($direction = null)
 * @method diGeoIpCacheCollection orderByZipCode($direction = null)
 * @method diGeoIpCacheCollection orderByLatitude($direction = null)
 * @method diGeoIpCacheCollection orderByLongitude($direction = null)
 * @method diGeoIpCacheCollection orderByCreatedAt($direction = null)
 * @method diGeoIpCacheCollection orderByUpdatedAt($direction = null)
 *
 * @method diGeoIpCacheCollection selectIp()
 * @method diGeoIpCacheCollection selectCountryCode()
 * @method diGeoIpCacheCollection selectCountryName()
 * @method diGeoIpCacheCollection selectRegionCode()
 * @method diGeoIpCacheCollection selectRegionName()
 * @method diGeoIpCacheCollection selectCity()
 * @method diGeoIpCacheCollection selectZipCode()
 * @method diGeoIpCacheCollection selectLatitude()
 * @method diGeoIpCacheCollection selectLongitude()
 * @method diGeoIpCacheCollection selectCreatedAt()
 * @method diGeoIpCacheCollection selectUpdatedAt()
 */
class diGeoIpCacheCollection extends diCollection
{
	const type = diTypes::geo_ip_cache;
	protected $table = "geo_ip_cache";
	protected $modelType = "geo_ip_cache";

	// todo: remove this
	public static function addToCache($ipAr)
	{
		//\diCollectionCache::addManual(self::type, 'ip', $ipAr);
	}
}