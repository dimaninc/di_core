<?php
abstract class diOAuth2Facebook extends diOAuth2
{
	const loginUrlBase = "https://www.facebook.com/dialog/oauth";
	const authUrlBase = "https://graph.facebook.com/oauth/access_token";
	const profileUrlBase = "https://graph.facebook.com/me";

	protected $vendorId = diOAuth2Vendors::facebook;

	protected function getLoginUrlParams()
	{
		return extend(parent::getLoginUrlParams(), array(
			'scope' => 'email,public_profile',
		));
	}

	protected function downloadData()
	{
		parent::downloadData();

		parse_str(static::makeHttpRequest(static::authUrlBase, $this->getAuthUrlParams()), $tokenInfo);

		if (count($tokenInfo))
		{
			if (isset($tokenInfo["access_token"]))
			{
				$params = array(
					'access_token' => $tokenInfo['access_token'],
					'fields' => 'first_name,last_name,middle_name,name,link,gender,timezone,locale,verified,picture,age_range,birthday,cover,email,id,hometown,languages,name_format,updated_time,website',
				);

				$this->setProfileRawData(json_decode(static::makeHttpRequest(static::profileUrlBase, $params), true));
			}
			else
			{
				$errorInfo = json_decode(current(array_keys($tokenInfo)));

				$this->setProfileError($errorInfo->error->message);
			}
		}
		else
		{
			$this->setProfileError("Error during first request");
		}

		return $this;
	}
}