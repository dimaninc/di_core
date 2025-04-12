<?php

namespace diCore\Tool\Captcha;

use diCore\Helper\ArrayHelper;

class YandexCaptcha
{
    const CLIENT_KEY = null;
    const SERVER_KEY = null;

    public static function getToken()
    {
        return \diRequest::postExt('smart-token');
    }

    public static function check($token = null)
    {
        $ch = curl_init('https://smartcaptcha.yandexcloud.net/validate');
        $args = [
            'secret' => static::SERVER_KEY,
            'token' => $token ?? static::getToken(),
            'ip' => \diRequest::getRemoteIp(),
        ];
        curl_setopt($ch, CURLOPT_TIMEOUT, 1);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($args));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $serverOutput = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            // echo "Allow access due to an error: code=$httpCode; message=$serverOutput\n";
            return false;
        }

        $resp = json_decode($serverOutput);

        return ArrayHelper::get($resp, 'status') === 'ok';
    }
}
