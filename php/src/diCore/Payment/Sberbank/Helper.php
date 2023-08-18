<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 21.05.2019
 * Time: 12:04
 */

namespace diCore\Payment\Sberbank;

use diCore\Helper\ArrayHelper;
use diCore\Payment\BaseHelper;
use diCore\Payment\System;
use Voronkovich\SberbankAcquiring\Client;

// use https://github.com/voronkovich/sberbank-acquiring-client
class Helper extends BaseHelper
{
    const system = System::sberbank;

    /** @var Client */
    protected $client;

    protected function getClient()
    {
        if (!$this->client) {
            $this->client = new Client([
                'userName' => static::getLogin(),
                'password' => static::getPassword(),
            ]);
        }

        return $this->client;
    }

    /**
     * @param \diCore\Entity\PaymentDraft\Model $draft
     * @param array $opts
     * @return string
     */
    public function getFormUri(
        \diCore\Entity\PaymentDraft\Model $draft,
        $opts = []
    ) {
        $opts = extend(
            [
                'amount' => $draft->getAmount(),
                'userId' => $draft->getUserId(),
                'draftId' => $draft->getId(),
                'description' => '',
                'customerEmail' => '',
                'customerPhone' => '',
                'paymentVendor' => '',
                'additionalParams' => [],
            ],
            $opts
        );

        $response = $this->getClient()->registerOrder(
            $opts['draftId'],
            $opts['amount'],
            $draft->getTargetModel()->getHref()
        );

        static::log("registerOrder:\n" . print_r($opts, true));
        static::log("Response:\n" . print_r($response, true));

        /*
        if ($this->getClient()->getError()) {
            throw new \Exception('Sberbank init error: ' . $this->getApi()->getError());
        }
        */

        return ArrayHelper::get($response, 'formUrl');
    }
}
