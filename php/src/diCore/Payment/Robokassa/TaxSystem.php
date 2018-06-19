<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 19.06.2018
 * Time: 19:08
 */

namespace diCore\Payment\Robokassa;

use diCore\Tool\SimpleContainer;

class TaxSystem extends SimpleContainer
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
        self::usn_income => 'Упрощённая СН (доходы)',
        self::usn_income_outcome => 'Упрощённая СН (доходы минус расходы)',
        self::envd => 'Единый налог на вменённый доход',
        self::esn => 'Единый сельскохозяйственный налог',
        self::patent => 'Патентная СН',
    ];
}