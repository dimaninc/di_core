<?php
class diOAuth2Google extends diOAuth2
{
	const loginUrlBase = "https://accounts.google.com/o/oauth2/auth";
	const authUrlBase = "https://accounts.google.com/o/oauth2/token";
	const profileUrlBase = "https://www.googleapis.com/oauth2/v1/userinfo";

	protected $vendorId = diOAuth2Vendors::google;

	protected function getLoginUrlParams()
	{
		return extend(parent::getLoginUrlParams(), array(
			'scope' => 'https://www.googleapis.com/auth/userinfo.email https://www.googleapis.com/auth/userinfo.profile',
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
					'access_token' => $tokenInfo['access_token'],
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