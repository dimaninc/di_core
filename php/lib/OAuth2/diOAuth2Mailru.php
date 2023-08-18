<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 31.12.15
 * Time: 10:49
 */
class diOAuth2Mailru extends diOAuth2
{
    const loginUrlBase = 'https://connect.mail.ru/oauth/authorize';
    const authUrlBase = 'https://connect.mail.ru/oauth/token';
    const profileUrlBase = 'https://www.appsmail.ru/platform/api';

    protected $vendorId = diOAuth2Vendors::mailru;

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
                $sign = md5(
                    'app_id=' .
                        static::appId .
                        "method=users.getInfosecure=1session_key={$tokenInfo['access_token']}" .
                        static::secret
                );

                $params = [
                    'method' => 'users.getInfo',
                    'secure' => '1',
                    'app_id' => static::appId,
                    'session_key' => $tokenInfo['access_token'],
                    'sig' => $sign,
                ];

                $data = json_decode(
                    static::makeHttpRequest(static::profileUrlBase, $params),
                    true
                );

                if (isset($data[0]['uid'])) {
                    $this->setProfileRawData($data[0]);
                }
            } else {
                $this->setProfileError(
                    $tokenInfo['error'] . ': ' . $tokenInfo['error_description']
                );
            }
        } else {
            $this->setProfileError('Error during first request');
        }

        return $this;
    }
}
