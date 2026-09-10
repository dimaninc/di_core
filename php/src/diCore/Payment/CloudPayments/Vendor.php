<?php
/**
 * @link https://developers.cloudpayments.ru/
 */

namespace diCore\Payment\CloudPayments;

use diCore\Payment\VendorContainer;

class Vendor extends VendorContainer
{
    /**
     * The single vendor this gateway is connected for. Named `foreign-card`
     * rather than `card` on purpose: `card` is already taken by three other
     * systems, and the vendor NAME is what the pay route, the icon lookup and
     * the mobile app's picture filename are all keyed on.
     */
    const FOREIGN_CARD = 10;

    public static $titles = [
        self::FOREIGN_CARD => 'Иностранная карта',
    ];

    public static $names = [
        self::FOREIGN_CARD => 'foreign-card',
    ];
}
