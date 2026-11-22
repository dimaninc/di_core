<?php
class diOAuth2Google extends diOAuth2
{
    const loginUrlBase = 'https://accounts.google.com/o/oauth2/auth';
    const authUrlBase = 'https://accounts.google.com/o/oauth2/token';
    const profileUrlBase = 'https://www.googleapis.com/oauth2/v1/userinfo';

    protected $vendorId = diOAuth2Vendors::google;

    protected function getLoginUrlParams()
    {
        return extend(parent::getLoginUrlParams(), [
            'scope' =>
                'https://www.googleapis.com/auth/userinfo.email https://www.googleapis.com/auth/userinfo.profile',
        ]);
    }

    protected function getAuthUrlParams()
    {
        return extend(parent::getAuthUrlParams(), [
            'grant_type' => 'authorization_code',
        ]);
    }

    protected function downloadData()
    {
        parent::downloadData();

        $tokenInfo = static::decodeAnswer(
            static::makeHttpRequest(
                static::authUrlBase,
                $this->getAuthUrlParams(),
                static::REQUEST_POST
            )
        );

        if ($tokenInfo) {
            if (isset($tokenInfo['access_token'])) {
                $params = [
                    'access_token' => $tokenInfo['access_token'],
                ];

                $profile = static::decodeAnswer(
                    static::makeHttpRequest(static::profileUrlBase, $params)
                );

                if ($profile) {
                    $this->setProfileRawData($profile);
                } else {
                    $this->setProfileError('Error retrieving data');
                }
            } else {
                $this->setProfileError(static::vendorErrorText($tokenInfo));
            }
        } else {
            $this->setProfileError('Error during first request');
        }

        return $this;
    }
}
