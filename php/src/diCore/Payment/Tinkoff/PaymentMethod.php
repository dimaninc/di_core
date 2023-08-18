<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 20.05.2019
 * Time: 18:33
 */

namespace diCore\Payment\Tinkoff;

use diCore\Tool\SimpleContainer;

class PaymentMethod extends SimpleContainer
{
    const full_prepayment = 1;
    const prepayment = 2;
    const advance = 3;
    const full_payment = 4;
    const partial_payment = 5;
    const credit = 6;
    const credit_payment = 7;

    public static $names = [
        self::full_prepayment => 'full_prepayment',
        self::prepayment => 'prepayment',
        self::advance => 'advance',
        self::full_payment => 'full_payment',
        self::partial_payment => 'partial_payment',
        self::credit => 'credit',
        self::credit_payment => 'credit_payment',
    ];

    public static $titles = [
        self::full_prepayment => 'Предоплата 100%',
        self::prepayment => 'Предоплата',
        self::advance => 'Аванc',
        self::full_payment => 'Полный расчет',
        self::partial_payment => 'Частичный расчет и кредит',
        self::credit => 'Передача в кредит',
        self::credit_payment => 'Оплата кредита',
    ];
}
