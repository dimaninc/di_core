<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 05.12.2016
 * Time: 10:24
 */

namespace diCore\Payment\Paypal;

use diCore\Entity\PaymentDraft\Model as PaymentDraft;
use diCore\Payment\Payment;
use diCore\Tool\Logger;

class Helper
{
    const testMode = false;

    const email = '#paypal-account-email';
    const merchantAccountId = '#id';
    const notifyUrl = null;
    const transport = self::TRANSPORT_CURL;

    const productionUrl = 'https://www.paypal.com/cgi-bin/websc';
    const testUrl = 'https://www.sandbox.paypal.com/cgi-bin/websc';

    const TRANSPORT_CURL = 1;
    const TRANSPORT_SOCKETS = 2;
    const TRANSPORT_GUZZLE = 3;
    const TRANSPORT_REQUESTS = 4;

    const TRANSACTION_TYPE_SINGLE_PAY = 1;
    const TRANSACTION_TYPE_SUBSCRIPTION = 2;

    protected $options = [
        'onSuccessPayment' => null,
    ];

    protected $data = [];

    public function __construct($options = [])
    {
        $this->options = extend($this->options, $options);
    }

    /**
     * @return Helper
     */
    public static function create($options = [])
    {
        $className = \diLib::getChildClass(self::class, 'Settings');

        $pp = new $className($options);

        return $pp;
    }

    public static function getUrl()
    {
        return static::testMode ? static::testUrl : static::productionUrl;
    }

    public static function getForm(PaymentDraft $draft, $opts = [])
    {
        $action = static::getUrl();
        $business = static::email;

        $opts = extend(
            [
                'charset' => 'utf-8',
                'amount' => $draft->getAmount(),
                'currency' => 'RUB',
                'userId' => $draft->getUserId(),
                'draftId' => $draft->getId(),
                'orderTitle' => '',
                'autoSubmit' => false,
                'buttonCaption' => 'Заплатить',
                'paymentSystem' => '',
                'customData' => [],
                'additionalParams' => [],
            ],
            $opts
        );

        $params = extend(
            [
                'cmd' => '_xclick',
                'charset' => $opts['charset'],
                'amount' => $opts['amount'],
                'currency_code' => $opts['currency'],
                'business' => $business,
                'item_name' => $opts['orderTitle'],
                'item_number' => $opts['draftId'],
                //'button_subtype' => 'services',
                'no_note' => 1,
                'no_shipping' => 1,
                'notify_url' => static::notifyUrl,
                'custom' => $opts['customData']
                    ? json_encode($opts['customData'])
                    : null,
                //'bn' => 'PP-BuyNow',
            ],
            $opts['additionalParams']
        );

        $form = Payment::formHtml($action, $params, $opts);

        static::log("Paypal form:\n" . $form);

        return $form;
    }

    public function notification()
    {
        $this->data = $this->getPostFromRawData();
        $transactionType = $this->getPaymentType();

        $this->log('Notification, POST DATA: ' . print_r($this->data, true));
        $this->log('Notification, transaction type: ' . $transactionType);

        switch ($transactionType) {
            case Helper::TRANSACTION_TYPE_SINGLE_PAY:
                break;

            case Helper::TRANSACTION_TYPE_SUBSCRIPTION:
            default:
                throw new \Exception(
                    'Unsupported payment type: ' . $transactionType
                );
        }

        //$customData = json_decode($$this->data['custom'], true);
        //$userId = $customData['user_id'];

        $this->log('Sending request _notify-validate');

        $response = $this->sendRequest(
            [
                'cmd' => '_notify-validate',
            ],
            true
        );

        $this->log('Notification, response: ' . $response);

        $tokens = explode("\r\n\r\n", trim($response));
        $response = trim(end($tokens));

        if (strcmp($response, 'VERIFIED') == 0) {
            static::log('Verified IPN response: ' . $response);

            if (is_callable($this->options['onSuccessPayment'])) {
                $this->options['onSuccessPayment']($this);
            }
        } elseif (strcmp($response, 'INVALID') == 0) {
            static::log('Invalid IPN response: ' . $response);
        }
    }

    protected function sendRequest($query = [], $extendIncoming = false)
    {
        if ($extendIncoming) {
            $query = extend($_POST, $query);
        }

        /** @var Transport $transport */
        $transport = Transport::create();

        $this->log('sendRequest: transport=' . static::transport);

        switch (static::transport) {
            case self::TRANSPORT_CURL:
                return $transport::requestCUrl(static::getUrl(), $query);

            case self::TRANSPORT_SOCKETS:
                return $transport::requestSockets(static::getUrl(), $query);

            case self::TRANSPORT_GUZZLE:
                return $transport::requestGuzzle(static::getUrl(), $query);

            case self::TRANSPORT_REQUESTS:
                return $transport::requestRequests(static::getUrl(), $query);

            default:
                throw new \Exception('Unsupported transport: ' . static::transport);
        }
    }

    protected function getPaymentType($rawPostData = null)
    {
        $post = $this->getPostFromRawData($rawPostData);

        if (isset($post['subscr_id'])) {
            return Helper::TRANSACTION_TYPE_SUBSCRIPTION;
        } else {
            return Helper::TRANSACTION_TYPE_SINGLE_PAY;
        }
    }

    /**
     * @param $rawPostData
     * @return array
     */
    protected function getPostFromRawData($rawPostData = null)
    {
        $raw_post_array = explode(
            '&',
            $rawPostData ?: file_get_contents('php://input')
        );
        $myPost = [];

        foreach ($raw_post_array as $keyVal) {
            $keyVal = explode('=', $keyVal);

            if (count($keyVal) == 2) {
                $myPost[$keyVal[0]] = urldecode($keyVal[1]);
            }
        }

        return $myPost;
    }

    public function getData($key = null)
    {
        return $key === null ? $this->data : $this->data[$key] ?? null;
    }

    public function getItemNumber()
    {
        return $this->getData('item_number') ?: $this->getData('item_number1');
    }

    public function getTransactionId()
    {
        return $this->getData('txn_id');
    }

    public function getTransactionAmount()
    {
        return $this->getData('mc_gross');
    }

    public function getTransactionCurrency()
    {
        return $this->getData('mc_currency');
    }

    public function getPayerEmail()
    {
        return $this->getData('payer_email');
    }

    public static function log($message)
    {
        Logger::getInstance()->log($message, 'PayPal', '-payment');
    }
}
