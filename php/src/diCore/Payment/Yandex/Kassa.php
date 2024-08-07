<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 06.12.2016
 * Time: 19:41
 */

namespace diCore\Payment\Yandex;

use diCore\Base\CMS;
use diCore\Entity\PaymentDraft\Model as PaymentDraft;
use diCore\Helper\ArrayHelper;
use diCore\Payment\Payment;
use diCore\Tool\Logger;

class Kassa
{
    const shopId = null;
    const shopPassword = null;
    const showCaseId = null;

    const securityType = 'MD5'; //'PKCS7'
    const testMode = false;

    const productionUrl = 'https://yoomoney.ru/eshop.xml';
    const testUrl = 'https://demomoney.yandex.ru/eshop.xml';

    protected static $actions = ['check', 'aviso'];

    protected $action;
    protected $options = [
        'init' => null,
        'onAviso' => null,
    ];

    public function __construct($action, $options = [])
    {
        $this->action = $action;
        $this->options = extend($this->options, $options);

        if ($this->action) {
            $this->log('Yandex request: ' . $this->action);
        }
    }

    /**
     * @return Kassa
     */
    public static function create($action = null, $options = [])
    {
        $className = \diLib::getChildClass(self::class, 'Settings');

        $pp = new $className($action, $options);

        return $pp;
    }

    public static function getShopId()
    {
        return static::shopId;
    }

    public static function getShopPassword()
    {
        return static::shopPassword;
    }

    public static function getShowCaseId()
    {
        return static::showCaseId;
    }

    public static function isTestMode()
    {
        return static::testMode;
    }

    public static function getSecurityType()
    {
        return static::securityType;
    }

    public static function actionExists($action)
    {
        return in_array($action, static::$actions);
    }

    public static function getUrl()
    {
        return static::isTestMode() ? static::testUrl : static::productionUrl;
    }

    /**
     * @return string
     */
    public function getAction()
    {
        return $this->action;
    }

    public function process()
    {
        if (!$this->actionExists($this->getAction())) {
            $this->log('Unknown action: ' . $this->getAction());

            return [
                'ok' => false,
                'message' => 'Unknown action: ' . $this->getAction(),
            ];
        }

        $this->processRequest();

        $this->log(
            'Request processed, now trying to pass action: ' . $this->getAction()
        );

        switch ($this->getAction()) {
            case 'check':
                $response = $this->checkResponse();
                break;

            case 'aviso':
                if (is_callable($this->options['onAviso'])) {
                    $this->options['onAviso']($this);
                }

                $response = $this->avisoResponse();
                break;

            default:
                $response = null;
                break;
        }

        return $this->sendResponse($response);
    }

    protected function processRequest()
    {
        $this->log('Start ' . $this->getAction());
        $this->log('Security type ' . static::getSecurityType());

        switch (static::getSecurityType()) {
            case 'MD5':
                $this->log('Request: ' . print_r($_POST, true));

                // If the MD5 checking fails, respond with '1' error code
                if (!$this->checkMD5()) {
                    $this->log('MD5 not passed');

                    $response = $this->buildResponse(
                        $this->getAction(),
                        \diRequest::post('invoiceId'),
                        1
                    );

                    return $this->sendResponse($response, true);
                }

                break;

            case 'PKCS7':
                // Checking for a certificate sign. If the checking fails, respond with '200' error code.
                if (($request = $this->verifySign()) == null) {
                    $response = $this->buildResponse($this->getAction(), null, 200);
                    return $this->sendResponse($response, true);
                }

                $this->log('Request: ' . print_r($_POST, true));

                break;
        }

        if (is_callable($this->options['init'])) {
            $this->options['init']($this);
        }

        return $this;
    }

    public function checkResponse()
    {
        static::log('Check action!');

        return $this->buildResponse('check', \diRequest::post('invoiceId'), 0);
    }

    public function avisoResponse()
    {
        static::log('Aviso action!');

        return $this->buildResponse('aviso', \diRequest::post('invoiceId'), 0);
    }

    public static function formatDate(\DateTime $date)
    {
        $performedDatetime =
            $date->format('Y-m-d') .
            'T' .
            $date->format('H:i:s') .
            '.000' .
            $date->format('P');

        return $performedDatetime;
    }

    public static function formatDateForMWS(\DateTime $date)
    {
        $performedDatetime =
            $date->format('Y-m-d') . 'T' . $date->format('H:i:s') . '.000Z';

        return $performedDatetime;
    }

    public static function log($message)
    {
        Logger::getInstance()->log($message, 'Yandex.Kassa', '-payment');
    }

    public function sendErrorResponse($message, $forcePrintAndExit = false)
    {
        $this->log('Sending error response: ' . $message);

        $response = $this->buildResponse($this->getAction(), null, 100, $message);

        return $this->sendResponse($response, $forcePrintAndExit);
    }

    public function sendResponse($responseBody, $forcePrintAndExit = false)
    {
        $this->log('Response: ' . $responseBody);

        header('HTTP/1.0 200');
        header('Content-Type: application/xml');

        if ($forcePrintAndExit) {
            die($responseBody);
        }

        return $responseBody;
    }

    /**
     * Checking the MD5 sign.
     * @return bool true if MD5 hash is correct
     */
    private function checkMD5()
    {
        $ar = [
            \diRequest::post('action'),
            \diRequest::post('orderSumAmount'),
            \diRequest::post('orderSumCurrencyPaycash'),
            \diRequest::post('orderSumBankPaycash'),
            \diRequest::post('shopId'),
            \diRequest::post('invoiceId'),
            \diRequest::post('customerNumber'),
            static::getShopPassword(),
        ];

        $str = join(';', $ar);

        $this->log('String to md5: ' . $str);
        $md5 = strtoupper(md5($str));

        if ($md5 != strtoupper(\diRequest::post('md5'))) {
            $this->log(
                'Waited for md5: ' .
                    $md5 .
                    ', received md5: ' .
                    \diRequest::post('md5')
            );

            return false;
        }

        $this->log('md5: OK');

        return true;
    }

    /**
     * Checking for sign when XML/PKCS#7 scheme is used.
     * @return array if request is successful, returns key-value array of request params, null otherwise.
     */
    private function verifySign()
    {
        $descriptorspec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $certificate = 'yamoney.pem';
        $process = proc_open(
            'openssl smime -verify -inform PEM -nointern -certfile ' .
                $certificate .
                ' -CAfile ' .
                $certificate,
            $descriptorspec,
            $pipes
        );

        if (is_resource($process)) {
            // Getting data from request body.
            $data = file_get_contents('php://input');
            fwrite($pipes[0], $data);
            fclose($pipes[0]);
            $content = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            $resCode = proc_close($process);

            if ($resCode != 0) {
                return null;
            } else {
                $this->log('Raw xml: ' . $content);
                $xml = simplexml_load_string($content);
                $array = json_decode(json_encode($xml), true);
                return $array['@attributes'];
            }
        }

        return null;
    }

    /**
     * Building XML response.
     * @param  string $functionName  'checkOrder' or 'paymentAviso' string
     * @param  string $invoiceId     transaction number
     * @param  string $resultCode    result code
     * @param  string $message       error message. May be null.
     * @return string                prepared XML response
     */
    private function buildResponse(
        $functionName,
        $invoiceId,
        $resultCode,
        $message = null
    ) {
        switch ($functionName) {
            case 'check':
                $methodName = 'checkOrder';
                break;

            case 'aviso':
                $methodName = 'paymentAviso';
                break;

            default:
                $methodName = $functionName;
                break;
        }

        try {
            $performedDatetime = self::formatDate(new \DateTime());

            $attrs = [
                'performedDatetime' => $performedDatetime,
                'code' => $resultCode,
                'message' => $message,
                'invoiceId' => $invoiceId,
                'shopId' => static::getShopId(),
            ];

            $response =
                '<?xml version="1.0" encoding="UTF-8"?><' .
                $methodName .
                'Response ' .
                ArrayHelper::toAttributesString($attrs, true) .
                '/>';

            return $response;
        } catch (\Exception $e) {
            $this->log($e);
        }

        return null;
    }

    public static function formatPrice($price)
    {
        return sprintf('%.2f', $price);
    }

    public static function getForm(PaymentDraft $draft, $opts = [])
    {
        $action = static::getUrl();
        $shopId = static::getShopId();
        $showCaseId = static::getShowCaseId();

        $opts = extend(
            [
                'amount' => $draft->getAmount(),
                'userId' => $draft->getUserId(),
                'draftId' => $draft->getId(),
                'customerEmail' => '',
                'customerPhone' => '',
                'autoSubmit' => false,
                'buttonCaption' => 'Заплатить',
                'paymentSystem' => '',
                'additionalParams' => [],
            ],
            $opts
        );

        $params = extend(
            [
                'shopId' => $shopId,
                'scid' => $showCaseId,
                'sum' => self::formatPrice($opts['amount']),
                'customerNumber' => $opts['userId'],
                'orderNumber' => $opts['draftId'],
                'paymentType' => $opts['paymentSystem'],
                'cps_phone' => $opts['customerPhone'] ?: null,
                'cps_email' => $opts['customerEmail'],
            ],
            $opts['additionalParams']
        );

        $form = Payment::formHtml($action, $params, $opts);

        static::log("Yandex.Kassa form:\n" . $form);

        return $form;
    }

    public function getSuccessUrl(PaymentDraft $draft)
    {
        return \diPaths::defaultHttp() .
            CMS::makeUrl([CMS::ct('payment_callback'), 'thanks']);
    }

    public function getFailUrl(PaymentDraft $draft)
    {
        return \diPaths::defaultHttp() .
            CMS::makeUrl(
                [CMS::ct('payment_callback'), 'failed'],
                [
                    'draftNumber' => $draft->getId(),
                ]
            );
    }
}
