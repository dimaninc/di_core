<?php
abstract class diOAuth2Facebook extends diOAuth2
{
	const loginUrlBase = "https://www.facebook.com/dialog/oauth";
	const authUrlBase = "https://graph.facebook.com/oauth/access_token";
	const profileUrlBase = "https://graph.facebook.com/me";

	protected $vendorId = diOAuth2Vendors::facebook;

	protected function getScopeArray()
    {
        return [
            'email',
            'public_profile',
        ];
    }

	protected function getLoginUrlParams()
	{
		return extend(parent::getLoginUrlParams(), [
			'scope' => join(',', $this->getScopeArray()),
		]);
	}

	protected function downloadData()
	{
		parent::downloadData();

		$tokenInfo = json_decode($response = static::makeHttpRequest(static::authUrlBase, $this->getAuthUrlParams()));

		if (!empty($tokenInfo->access_token))
		{
			$token = $tokenInfo->access_token;

			if ($token)
			{
				$params = [
					'access_token' => $token,
					'fields' => 'first_name,last_name,middle_name,name,link,gender,timezone,locale,verified,picture,age_range,birthday,cover,email,id,hometown,languages,name_format,updated_time,website',
				];

				$this->setProfileRawData(json_decode(static::makeHttpRequest(static::profileUrlBase, $params), true));
			}
			else
			{
				var_debug('Facebook error');
				var_debug($tokenInfo);

				$errorInfo = json_decode(current(array_keys((array)$tokenInfo)));

				$this->setProfileError($errorInfo->error->message);
			}
		}
		else
		{
			$this->setProfileError("Error during first request");

			var_debug('Facebook error');
			var_debug($response);
		}

		return $this;
	}
}