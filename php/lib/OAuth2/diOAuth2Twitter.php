<?php
class diOAuth2Twitter extends diOAuth2
{
    const loginUrlBase = 'https://api.twitter.com/oauth/authorize';
    const authUrlBase = 'https://api.twitter.com/oauth/access_token';
    const profileUrlBase = 'https://api.twitter.com/1.1/users/show.json';
    const requestTokenUrlBase = 'https://api.twitter.com/oauth/request_token';

    const signatureMethod = 'HMAC-SHA1';

    protected $vendorId = diOAuth2Vendors::twitter;

    protected $nonce;
    protected $timestamp;

    /** @var TwitterAuth */
    protected $t;

    public function __construct()
    {
        parent::__construct();

        $this->nonce = get_unique_id();
        $this->timestamp = time();

        $this->nonce = md5('zhopa');
        $this->timestamp = strtotime('12:00');
    }

    protected function buildForSignature($ar)
    {
        $ar2 = [];

        foreach ($ar as $k => $v) {
            if ($k == 'oauth_callback') {
                $v = urlencode($v);
            }

            //$ar2[] = urlencode("{$k}={$v}");
            $ar2[] = "{$k}={$v}";
        }

        return urlencode(join('&', $ar2));
    }

    protected function getLoginUrlParams()
    {
        $prefix = 'GET&' . urlencode(static::requestTokenUrlBase) . '&';

        $ar = [
            'oauth_callback' => $this->getBackUrl(),
            'oauth_consumer_key' => static::appId,
            'oauth_nonce' => $this->nonce,
            'oauth_signature_method' => static::signatureMethod,
            'oauth_timestamp' => $this->timestamp,
            'oauth_version' => '1.0',
        ];

        $key = static::secret . '&';
        $hashReference = $prefix . $this->buildForSignature($ar);
        $signature = base64_encode(hash_hmac('sha1', $hashReference, $key, true));

        $ar['oauth_signature'] = $signature;
        ksort($ar);

        $response = static::makeHttpRequest(static::requestTokenUrlBase, $ar);
        parse_str($response, $result);

        if (!$response || !$result) {
            throw \diCore\Base\Exception\HttpException::notFound(
                'Twitter OAuth2 out of order'
            );
        }

        if (isset($result['oauth_token'])) {
            diSession::set('twitterToken', $result['oauth_token']);
            diSession::set('twitterTokenSecret', $result['oauth_token_secret']);
        } else {
            $result = json_decode($response, true);
            throw new \Exception(
                $result['errors'][0]['code'] . ': ' . $result['errors'][0]['message']
            );
        }

        return [
            'oauth_token' => $result['oauth_token'],
        ];
    }

    protected function isReturn()
    {
        return diRequest::get('oauth_token') && diRequest::get('oauth_verifier');
    }

    protected function getAuthUrlParams()
    {
        $token = diRequest::get('oauth_token');
        $verifier = diRequest::get('oauth_verifier');
        $tokenSecret = diSession::getAndKill('twitterTokenSecret');

        $prefix = 'GET&' . urlencode(static::authUrlBase) . '&';

        $ar = [
            'oauth_consumer_key' => static::appId,
            'oauth_nonce' => $this->nonce,
            'oauth_signature_method' => static::signatureMethod,
            'oauth_token' => $token,
            'oauth_timestamp' => $this->timestamp,
            'oauth_verifier' => $verifier,
            'oauth_version' => '1.0',
        ];

        $key = static::secret . '&' . $tokenSecret;
        $hashReference = $prefix . $this->buildForSignature($ar);
        $signature = base64_encode(hash_hmac('sha1', $hashReference, $key, true));

        $ar['oauth_signature'] = $signature;
        ksort($ar);

        return $ar;
    }

    protected function downloadData()
    {
        parent::downloadData();

        $response = static::makeHttpRequest(
            static::authUrlBase,
            $this->getAuthUrlParams()
        );
        parse_str($response, $tokenInfo);

        if (count($tokenInfo)) {
            if (isset($tokenInfo['oauth_token'])) {
                $prefix = 'GET&' . urlencode(static::profileUrlBase) . '&';

                $ar = [
                    'oauth_consumer_key' => static::appId,
                    'oauth_nonce' => $this->nonce,
                    'oauth_signature_method' => static::signatureMethod,
                    'oauth_timestamp' => $this->timestamp,
                    'oauth_token' => $tokenInfo['oauth_token'],
                    'oauth_version' => '1.0',
                    'screen_name' => $tokenInfo['screen_name'],
                ];

                $key = static::secret . '&' . $tokenInfo['oauth_token_secret'];
                $hashReference = $prefix . $this->buildForSignature($ar);
                $signature = base64_encode(
                    hash_hmac('sha1', $hashReference, $key, true)
                );

                $ar['oauth_signature'] = $signature;
                ksort($ar);

                $this->setProfileRawData(
                    json_decode(
                        static::makeHttpRequest(static::profileUrlBase, $ar),
                        true
                    )
                );
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
