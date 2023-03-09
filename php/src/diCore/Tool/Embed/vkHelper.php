<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 01.06.2016
 * Time: 18:23
 */

namespace diCore\Tool\Embed;

class vkHelper extends Helper
{
	public static $queryParamsToRemove = [
		'api_url',
		'api_id',
		'api_settings',
		'viewer_id',
		'viewer_type',
		'sid',
		'user_id',
		'group_id',
		'is_app_user',
		'secret',
		'access_token',
		'auth_key',
		'language',
		'parent_language',
		'ad_info',
		'is_secure',
        'is_favorite',
        'stats_hash',
		'ads_app_id',
		'referrer',
		'lc_name',
        'platform',
        'is_widescreen',
        'whitelist_scopes',
        'group_whitelist_scopes',
        'timestamp',
        'sign',
        'sign_keys',
        'hash',
		'api_script',
        'access_token_settings',
	];

	public static $identifyParams = [];

	public static function is()
	{
		return \diRequest::get('lc_name') !== null;
	}
}