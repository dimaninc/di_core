<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 20.05.2019
 * Time: 18:33
 */

namespace diCore\Payment\Tinkoff;

use diCore\Tool\SimpleContainer;

class PaymentObject extends SimpleContainer
{
    const commodity = 1;
    const excise = 2;
    const job = 3;
    const service = 4;
    const gambling_bet = 5;
    const gambling_prize = 6;
    const lottery = 7;
    const lottery_prize = 8;
    const intellectual_activity = 9;
    const payment = 10;
    const agent_commission = 11;
    const composite = 12;
    const another = 13;

    public static $names = [
        self::commodity => 'commodity',
        self::excise => 'excise',
        self::job => 'job',
        self::service => 'service',
        self::gambling_bet => 'gambling_bet',
        self::gambling_prize => 'gambling_prize',
        self::lottery => 'lottery',
        self::lottery_prize => 'lottery_prize',
        self::intellectual_activity => 'intellectual_activity',
        self::payment => 'payment',
        self::agent_commission => 'agent_commission',
        self::composite => 'composite',
        self::another => 'another',
    ];

    public static $titles = [
        self::commodity => 'Товар',
        self::excise => 'Подакцизный товар',
        self::job => 'Работа',
        self::service => 'Услуга',
        self::gambling_bet => 'Ставка азартной игры',
        self::gambling_prize => 'Выигрыш азартной игры',
        self::lottery => 'Лотерейный билет',
        self::lottery_prize => 'Выигрыш лотереи',
        self::intellectual_activity =>
            'Предоставление результатов интеллектуальной деятельности',
        self::payment => 'Платеж',
        self::agent_commission => 'Агентское вознаграждение',
        self::composite => 'Составной предмет расчета',
        self::another => 'Иной предмет расчета',
    ];
}
