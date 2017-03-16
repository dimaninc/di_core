<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 31.12.15
 * Time: 10:49
 */
class diOAuth2Yandex extends diOAuth2
{
	const loginUrlBase = "https://oauth.yandex.ru/authorize";
	const authUrlBase = "https://oauth.yandex.ru/token";
	const profileUrlBase = "https://login.yandex.ru/info";

	protected $vendorId = diOAuth2Vendors::yandex;

	protected function getLoginUrlParams()
	{
		return extend(parent::getLoginUrlParams(), array(
			"display" => "popup",
		));
	}

	protected function getAuthUrlParams()
	{
		return extend(parent::getAuthUrlParams(), array(
			'grant_type' => 'authorization_code',
		));
	}

	protected function downloadData()
	{
		parent::downloadData();

		$tokenInfo = json_decode(static::makeHttpRequest(static::authUrlBase, $this->getAuthUrlParams(), static::REQUEST_POST), true);

		if (count($tokenInfo))
		{
			if (isset($tokenInfo["access_token"]))
			{
				$params = array(
					'format' => 'json',
					'oauth_token' => $tokenInfo['access_token'],
				);

				$this->setProfileRawData(json_decode(static::makeHttpRequest(static::profileUrlBase, $params), true));
			}
			else
			{
				$this->setProfileError($tokenInfo["error"] . ": " . $tokenInfo["error_description"]);
			}
		}
		else
		{
			$this->setProfileError("Error during first request");
		}

		return $this;
	}
}