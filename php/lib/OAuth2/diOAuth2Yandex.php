<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 31.12.15
 * Time: 10:49
 */
class diOAuth2Yandex extends diOAuth2
{
    const loginUrlBase = 'https://oauth.yandex.ru/authorize';
    const authUrlBase = 'https://oauth.yandex.ru/token';
    const profileUrlBase = 'https://login.yandex.ru/info';

    protected $vendorId = diOAuth2Vendors::yandex;

    protected function getLoginUrlParams()
    {
        return extend(parent::getLoginUrlParams(), [
            'display' => 'popup',
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
                    'format' => 'json',
                    'oauth_token' => $tokenInfo['access_token'],
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
