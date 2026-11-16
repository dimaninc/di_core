<?php

namespace diCore\Payment;

/**
 * Подпись адреса возврата плательщика секретом шлюза. Адрес несёт номер
 * черновика, а возврат ведёт на страницу его цели: без подписи перебор
 * номеров выдавал бы адреса чужих покупок.
 */
class ReturnSignature
{
    const string PARAM = 'sig';

    /** Пустой секрет – пустая подпись: такой адрес не пройдёт verify() */
    public static function sign(
        string $secret,
        string $system,
        string $subAction,
        int $draftId
    ): string {
        if ($secret === '') {
            return '';
        }

        // Префикс отделяет эти подписи от других, сделанных тем же секретом
        return hash_hmac('sha256', "return|$system|$subAction|$draftId", $secret);
    }

    public static function verify(
        string $secret,
        string $system,
        string $subAction,
        int $draftId,
        $received
    ): bool {
        $expected = static::sign($secret, $system, $subAction, $draftId);

        return $expected !== '' &&
            is_string($received) &&
            hash_equals($expected, $received);
    }
}
