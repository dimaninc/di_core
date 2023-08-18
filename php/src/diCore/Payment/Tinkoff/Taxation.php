<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 20.05.2019
 * Time: 18:33
 */

namespace diCore\Payment\Tinkoff;

use diCore\Tool\SimpleContainer;

class Taxation extends SimpleContainer
{
    const osn = 1;
    const usn_income = 2;
    const usn_income_outcome = 3;
    const envd = 4;
    const esn = 5;
    const patent = 6;

    public static $names = [
        self::osn => 'osn',
        self::usn_income => 'usn_income',
        self::usn_income_outcome => 'usn_income_outcome',
        self::envd => 'envd',
        self::esn => 'esn',
        self::patent => 'patent',
    ];

    public static $titles = [
        self::osn => 'Общая СН',
        self::usn_income => 'Упрощенная СН (доходы)',
        self::usn_income_outcome => 'Упрощенная СН (доходы минус расходы)',
        self::envd => 'Единый налог на вмененный доход',
        self::esn => 'Единый сельскохозяйственный налог',
        self::patent => 'Патентная СН',
    ];
}
