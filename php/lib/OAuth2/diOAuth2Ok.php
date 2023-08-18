<?php
abstract class diOAuth2Ok extends diOAuth2
{
    const loginUrlBase = 'https://www.ok.ru/oauth/authorize';
    const authUrlBase = 'https://api.ok.ru/oauth/token.do';
    const profileUrlBase = 'https://api.ok.ru/fb.do';

    protected $vendorId = diOAuth2Vendors::ok;

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
                self::REQUEST_POST
            ),
            true
        );

        if (isset($tokenInfo['error'])) {
            $this->setProfileError(
                $tokenInfo['error'] . ': ' . $tokenInfo['error_description']
            );
        } elseif (
            is_array($tokenInfo) &&
            count($tokenInfo) &&
            isset($tokenInfo['access_token'])
        ) {
            $sign = md5(
                'application_key=' .
                    static::publicKey .
                    'format=jsonmethod=users.getCurrentUser' .
                    md5("{$tokenInfo['access_token']}" . static::secret)
            );

            $params = [
                'method' => 'users.getCurrentUser',
                'access_token' => $tokenInfo['access_token'],
                'application_key' => static::publicKey,
                'format' => 'json',
                'sig' => $sign,
            ];

            $this->setProfileRawData(
                json_decode(
                    static::makeHttpRequest(
                        static::makeUrl(static::profileUrlBase, $params)
                    ),
                    true
                )
            );
        } else {
            $this->setProfileError('Error during first request');
        }

        return $this;
    }
}
