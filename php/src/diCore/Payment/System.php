<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 08.07.2017
 * Time: 16:58
 */

namespace diCore\Payment;

use diCore\Payment\Mixplat\MobileVendors;
use diCore\Payment\Paymaster\Vendor as PaymasterVendor;
use diCore\Payment\Robokassa\Vendor as RobokassaVendor;
use diCore\Payment\Tinkoff\Vendor as TinkoffVendor;
use diCore\Payment\Yandex\Vendor as YandexKassaVendor;
use diCore\Tool\SimpleContainer;

class System extends SimpleContainer
{
    const webmoney = 1;
    const robokassa = 2;
    const yandex_kassa = 3;
    const mixplat = 4;
    const paypal = 5;
    const sms_online = 6;
    const tinkoff = 7;
    const sberbank = 8;
    const paymaster = 9;
    const crypto_cloud = 10;
    const alter = 99;

    public static $titles = [
        self::webmoney => 'Webmoney',
        self::robokassa => 'Робокасса',
        self::yandex_kassa => 'Yookassa',
        self::mixplat => 'Mixplat',
        self::paypal => 'Paypal',
        self::sms_online => 'SMS online',
        self::tinkoff => 'Тинькофф',
        self::sberbank => 'Сбербанк',
        self::paymaster => 'Paymaster',
        self::crypto_cloud => 'CryptoCloud',
        self::alter => 'Alt',
    ];

    public static $names = [
        self::webmoney => 'webmoney',
        self::robokassa => 'robokassa',
        self::yandex_kassa => 'yandex_kassa',
        self::mixplat => 'mixplat',
        self::paypal => 'paypal',
        self::sms_online => 'sms_online',
        self::tinkoff => 'tinkoff',
        self::sberbank => 'sberbank',
        self::paymaster => 'paymaster',
        self::crypto_cloud => 'crypto_cloud',
        self::alter => 'alt',
    ];

    public static function getSystemClass($systemId, $vendorId = null)
    {
        switch ($systemId) {
            case self::robokassa:
                return RobokassaVendor::class;

            case self::yandex_kassa:
                return YandexKassaVendor::class;

            case self::tinkoff:
                return TinkoffVendor::class;

            case self::mixplat:
                return $vendorId ? MobileVendors::class : self::class;

            case self::webmoney:
            case self::paypal:
            case self::crypto_cloud:
            case self::alter:
                return self::class;

            case self::paymaster:
                return PaymasterVendor::class;

            default:
                throw new \Exception(
                    'System ' . self::name($systemId) . ' not supported yet'
                );
        }
    }

    public static function vendorName($systemId, $vendorId)
    {
        /** @var SimpleContainer $class */
        $class = self::getSystemClass($systemId, $vendorId);

        return $class::name($vendorId ?: $systemId);
    }

    public static function vendorTitle($systemId, $vendorId)
    {
        /** @var SimpleContainer $class */
        $class = self::getSystemClass($systemId, $vendorId);

        return $class::title($vendorId ?: $systemId);
    }

    public static function vendorIdByName($systemId, $vendorName)
    {
        /** @var SimpleContainer $class */
        $class = self::getSystemClass($systemId, $vendorName);

        return $class::id($vendorName);
    }
}
