<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 09.07.2017
 * Time: 16:47
 */

namespace diCore\Payment\Yandex;

use diCore\Payment\VendorContainer;

/**
 * Способы ЮKassa. Один справочник на оба протокола: `$codes` – `paymentType`
 * старого HTTP-протокола, `$apiTypes` – `payment_method_data.type` API v3.
 */
class Vendor extends VendorContainer
{
    const YANDEX_MONEY = 1;
    const WEBMONEY = 2;
    const CARD = 3;
    const MOBILE = 4;
    const QIWI_WALLET = 5;
    const SBERPAY = 6;
    const ALPHA_CLICK = 7;
    const TERMINAL = 8;
    const MPOS = 9;
    const MASTER_PASS = 10;
    const PROM_SVYAZ_BANK = 11;
    // Бывший «КупиВКредит»: ни одного платежа им не было, id отдан T-Pay
    const T_PAY = 12;
    const QPPI = 13;
    const GOOGLE_PAY = 14;
    const APPLE_PAY = 15;
    const SBP = 16;
    const MIR_PAY = 17;

    /** @deprecated старое имя, тот же способ: SberPay */
    const SBERBANK_ONLINE = self::SBERPAY;

    /**
     * Старые имена того же способа: проект со старой формой иначе молча
     * создаст черновик без способа. Имени `tinkoff` (КупиВКредит) здесь нет:
     * это другой, закрытый продукт, а не T-Pay.
     */
    private const LEGACY_NAMES = [
        'sberbank-online' => self::SBERPAY,
    ];

    public static function id($name)
    {
        return parent::id($name) ??
            (is_string($name) ? self::LEGACY_NAMES[$name] ?? null : null);
    }

    public static $titles = [
        self::YANDEX_MONEY => 'ЮMoney', // Яндекс.Деньги
        self::WEBMONEY => 'WebMoney',
        self::CARD => 'Банковская карта',
        self::MOBILE => 'Баланс телефона',
        self::QIWI_WALLET => 'Qiwi',
        self::SBERPAY => 'SberPay',
        self::ALPHA_CLICK => 'Альфа-клик',
        self::TERMINAL => 'Терминалы/кассы',
        self::MPOS => 'Моб.терминал MPos',
        self::MASTER_PASS => 'MasterPass',
        self::PROM_SVYAZ_BANK => 'ПромСвязьБанк',
        self::T_PAY => 'T-Pay',
        self::QPPI => 'Куппи.ру',
        self::GOOGLE_PAY => 'Google Pay',
        self::APPLE_PAY => 'Apple Pay',
        self::SBP => 'СБП',
        self::MIR_PAY => 'МИР Pay',
    ];

    public static $names = [
        self::YANDEX_MONEY => 'yandex-money',
        self::WEBMONEY => 'webmoney',
        self::CARD => 'card',
        self::MOBILE => 'mobile',
        self::QIWI_WALLET => 'qiwi',
        // Те же имена, что у Т-Банка и Робокассы: по имени выбираются иконка и
        // текст способа, а это один и тот же способ у разных шлюзов
        self::SBERPAY => 'sberpay',
        self::ALPHA_CLICK => 'alpha-click',
        self::TERMINAL => 'terminal',
        self::MPOS => 'mpos',
        self::MASTER_PASS => 'master-pass',
        self::PROM_SVYAZ_BANK => 'prom-svyaz-bank',
        self::T_PAY => 't-pay',
        self::QPPI => 'qppi',
        self::GOOGLE_PAY => 'google-pay',
        self::APPLE_PAY => 'apple-pay',
        self::SBP => 'sbp',
        self::MIR_PAY => 'mir-pay',
    ];

    /** `paymentType` старого HTTP-протокола */
    public static $codes = [
        self::YANDEX_MONEY => 'PC',
        self::WEBMONEY => 'WM',
        self::CARD => 'AC',
        self::MOBILE => 'MC',
        self::QIWI_WALLET => 'QW',
        self::SBERPAY => 'SB',
        self::ALPHA_CLICK => 'AB',
        self::TERMINAL => 'GP',
        self::MPOS => 'MP',
        self::MASTER_PASS => 'MA',
        self::PROM_SVYAZ_BANK => 'PB',
        // T_PAY нет в старом протоколе. Не ключ с null: VendorContainer::id(null)
        // строгим array_search нашёл бы его, и платёж без paymentType стал бы T-Pay
        self::QPPI => 'QP',
        self::GOOGLE_PAY => 'AC',
        self::APPLE_PAY => 'AC',
        self::SBP => 'CP',
        self::MIR_PAY => 'AC',
    ];

    /**
     * `payment_method_data.type` API v3. Способа нет в списке – API его не
     * принимает. Mir Pay, Google Pay и Apple Pay в API – это `bank_card`:
     * кошелёк предлагает сама страница ЮKassa на подходящем устройстве.
     */
    public static $apiTypes = [
        self::YANDEX_MONEY => 'yoo_money',
        self::CARD => 'bank_card',
        self::SBERPAY => 'sberbank',
        self::T_PAY => 'tinkoff_bank',
        self::GOOGLE_PAY => 'bank_card',
        self::APPLE_PAY => 'bank_card',
        self::SBP => 'sbp',
        self::MIR_PAY => 'bank_card',
    ];

    /** `card.source` оплаченного `bank_card` → способ */
    public static $cardSources = [
        'mir_pay' => self::MIR_PAY,
        'google_pay' => self::GOOGLE_PAY,
        'apple_pay' => self::APPLE_PAY,
    ];

    public static $minLimits = [
        self::YANDEX_MONEY => null,
        self::WEBMONEY => null,
        self::CARD => null,
        self::MOBILE => 10,
        self::QIWI_WALLET => null,
        self::SBERPAY => 10,
        self::ALPHA_CLICK => null,
        self::TERMINAL => null,
        self::MPOS => null,
        self::MASTER_PASS => null,
        self::PROM_SVYAZ_BANK => null,
        self::T_PAY => null,
        self::QPPI => 100,
        self::GOOGLE_PAY => null,
        self::APPLE_PAY => null,
        self::SBP => null,
        self::MIR_PAY => null,
    ];

    public static function apiType($id): ?string
    {
        return static::$apiTypes[$id] ?? null;
    }

    public static function isApiSupported($id): bool
    {
        return static::apiType($id) !== null;
    }

    /**
     * Способ, которым платёж реально оплачен, по `payment_method` из ответа
     * API; null – не распознан. Нужен, потому что `bank_card` покрывает и
     * карту, и кошельки.
     */
    public static function fromPaymentMethod($paymentMethod): ?int
    {
        if (!is_array($paymentMethod)) {
            return null;
        }

        $type = $paymentMethod['type'] ?? null;

        if ($type === 'bank_card') {
            $source = $paymentMethod['card']['source'] ?? null;

            return is_string($source)
                ? static::$cardSources[$source] ?? self::CARD
                : self::CARD;
        }

        // bank_card стоит у нескольких способов, остальные типы однозначны
        $id = array_search($type, static::$apiTypes, true);

        return $id === false ? null : (int) $id;
    }
}
