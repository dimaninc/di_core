<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 31.12.15
 * Time: 10:49
 */
class diOAuth2Instagram extends diOAuth2
{
    const loginUrlBase = 'https://api.instagram.com/oauth/authorize/';
    const authUrlBase = 'https://api.instagram.com/oauth/access_token';
    const profileUrlBase = 'https://login.yandex.ru/info';

    protected $vendorId = diOAuth2Vendors::instagram;

    protected function getLoginUrlParams()
    {
        return extend(parent::getLoginUrlParams(), [
            'scope' => 'basic',
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

        $tokenInfo = json_decode(
            static::makeHttpRequest(
                static::authUrlBase,
                $this->getAuthUrlParams(),
                static::REQUEST_POST
            ),
            true
        );

        if (count($tokenInfo)) {
            if (isset($tokenInfo['access_token'])) {
                $this->setProfileRawData($tokenInfo['user']);
            } else {
                $this->setProfileError(
                    $tokenInfo['error_type'] .
                        ' (' .
                        $tokenInfo['code'] .
                        '): ' .
                        $tokenInfo['error_message']
                );
            }
        } else {
            $this->setProfileError('Error during first request');
        }

        return $this;
    }
}
