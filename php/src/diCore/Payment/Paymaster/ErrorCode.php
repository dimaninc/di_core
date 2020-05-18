<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 17.05.2020
 * Time: 11:43
 */

namespace diCore\Payment\Paymaster;

use diCore\Tool\SimpleContainer;

class ErrorCode extends SimpleContainer
{
    const unknown = -1;
    const net = -2;
    const no_access = -6;
    const invalid_signature = -7;
    const payment_not_found = -13;
    const dupe = -14;
    const invalid_amount = -18;

    public static $titles = [
        self::unknown => 'Неизвестная ошибка. Сбой в системе PayMaster. Если ошибка повторяется, обратитесь в техподдержку.',
        self::net => 'Сетевая ошибка. Сбой в системе PayMaster. Если ошибка повторяется, обратитесь в техподдержку.',
        self::no_access => 'Нет доступа. Неверно указан логин, или у данного логина нет прав на запрошенную информацию.',
        self::invalid_signature => 'Неверная подпись запроса. Неверно сформирован хеш запроса.',
        self::payment_not_found => 'Платеж не найден по номеру счета',
        self::dupe => 'Повторный запрос с тем же nonce',
        self::invalid_amount => 'Неверное значение суммы (в случае возвратов имеется в виду значение amount)',
    ];
}