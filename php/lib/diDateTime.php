<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 03.07.2015
 * Time: 13:06
 */

use diCore\Helper\ArrayHelper;
use diCore\Helper\StringHelper;
use MongoDB\BSON\UTCDatetime;

class diDateTime
{
    const SECS_PER_DAY = 86400;

    const MONDAY = 1;
    const TUESDAY = 2;
    const WEDNESDAY = 3;
    const THURSDAY = 4;
    const FRIDAY = 5;
    const SATURDAY = 6;
    const SUNDAY = 7;

    const FORMAT_SQL_DATE = 'Y-m-d';
    const FORMAT_SQL_TIME = 'H:i:s';
    const FORMAT_SQL_DATE_TIME = self::FORMAT_SQL_DATE . ' ' . self::FORMAT_SQL_TIME;
    const FORMAT_FILENAME_DATE_TIME = 'Y-m-d-H-i-s';
    const FORMAT_SIMPLE_DATE_TIME = 'd.m.Y H:i';
    const FORMAT_SIMPLE_DATE = 'd.m.Y';
    const FORMAT_SIMPLE_TIME = 'H:i';
    const FORMAT_ATOM = 'Y-m-d\TH:i:sP';

    public static $daysInMonth = [
        false => [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31],
        true => [31, 29, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31],
    ];

    public static $weekDaysShort = [
        self::MONDAY => 'Пн',
        self::TUESDAY => 'Вт',
        self::WEDNESDAY => 'Ср',
        self::THURSDAY => 'Чт',
        self::FRIDAY => 'Пт',
        self::SATURDAY => 'Сб',
        self::SUNDAY => 'Вс',
    ];

    public static $engWeekDaysShort = [
        self::MONDAY => 'Mo',
        self::TUESDAY => 'Tu',
        self::WEDNESDAY => 'We',
        self::THURSDAY => 'Th',
        self::FRIDAY => 'Fr',
        self::SATURDAY => 'Sa',
        self::SUNDAY => 'Su',
    ];

    public static $weekDays = [
        self::MONDAY => 'Понедельник',
        self::TUESDAY => 'Вторник',
        self::WEDNESDAY => 'Среда',
        self::THURSDAY => 'Четверг',
        self::FRIDAY => 'Пятница',
        self::SATURDAY => 'Суббота',
        self::SUNDAY => 'Воскресенье',
    ];

    public static $engWeekDays = [
        self::MONDAY => 'Monday',
        self::TUESDAY => 'Tuesday',
        self::WEDNESDAY => 'Wednesday',
        self::THURSDAY => 'Thursday',
        self::FRIDAY => 'Friday',
        self::SATURDAY => 'Saturday',
        self::SUNDAY => 'Sunday',
    ];

    public static $months = [
        1 => 'Январь',
        2 => 'Февраль',
        3 => 'Март',
        4 => 'Апрель',
        5 => 'Май',
        6 => 'Июнь',
        7 => 'Июль',
        8 => 'Август',
        9 => 'Сентябрь',
        10 => 'Октябрь',
        11 => 'Ноябрь',
        12 => 'Декабрь',
    ];

    public static $monthsGenitive = [
        1 => 'Января',
        2 => 'Февраля',
        3 => 'Марта',
        4 => 'Апреля',
        5 => 'Мая',
        6 => 'Июня',
        7 => 'Июля',
        8 => 'Августа',
        9 => 'Сентября',
        10 => 'Октября',
        11 => 'Ноября',
        12 => 'Декабря',
    ];

    public static $engMonths = [
        1 => 'January',
        2 => 'February',
        3 => 'March',
        4 => 'April',
        5 => 'May',
        6 => 'June',
        7 => 'July',
        8 => 'August',
        9 => 'September',
        10 => 'October',
        11 => 'November',
        12 => 'December',
    ];

    public static function sqlFormat($dt = null)
    {
        return self::format(self::FORMAT_SQL_DATE_TIME, $dt);
    }

    public static function sqlDateFormat($dt = null)
    {
        return self::format(self::FORMAT_SQL_DATE, $dt);
    }

    public static function sqlTimeFormat($dt = null)
    {
        return self::format(self::FORMAT_SQL_TIME, $dt);
    }

    public static function filenameFormat($dt = null)
    {
        return self::format(self::FORMAT_FILENAME_DATE_TIME, $dt);
    }

    public static function isoFormat($dt = null)
    {
        $datetime = new \DateTime(static::sqlFormat($dt));

        return $datetime->format(self::FORMAT_ATOM);
    }

    public static function mongoFormat($dt = null)
    {
        $dt = self::sqlFormat($dt);
        return new UTCDatetime((new \DateTime($dt))->getTimestamp() * 1000);
    }

    public static function durationInIso8601($seconds)
    {
        $days = floor($seconds / 86400);
        $seconds = $seconds % 86400;

        $hours = floor($seconds / 3600);
        $seconds = $seconds % 3600;

        $minutes = floor($seconds / 60);
        $seconds = $seconds % 60;

        return sprintf('P%dDT%dH%dM%dS', $days, $hours, $minutes, $seconds);
    }

    public static function simpleFormat($dt = null)
    {
        return self::format(self::FORMAT_SIMPLE_DATE_TIME, $dt);
    }

    public static function simpleDateFormat($dt = null)
    {
        return self::format(self::FORMAT_SIMPLE_DATE, $dt);
    }

    public static function simpleTimeFormat($dt = null)
    {
        return self::format(self::FORMAT_SIMPLE_TIME, $dt);
    }

    public static function format($format, $dt = null)
    {
        $dt = self::timestamp($dt ?: time());
        $a = getdate($dt);

        $ar = [
            '%мес%' => mb_substr(self::$months[$a['mon']], 0, 3),
            '%месяц%' => self::$months[$a['mon']],
            '%месяца%' => self::$monthsGenitive[$a['mon']],
            '%деньнедели%' => self::$weekDays[$a['wday'] ?: 7],
            '%день_недели%' => self::$weekDays[$a['wday'] ?: 7],
            '%дн%' => self::$weekDaysShort[$a['wday'] ?: 7],
        ];

        $format = date($format, $dt);
        $format = str_replace(array_keys($ar), array_values($ar), $format);

        return $format;
    }

    public static function timestamp($dt = null)
    {
        if ($dt === null) {
            return time();
        } elseif (isInteger($dt)) {
            return $dt;
        } elseif ($dt instanceof UTCDatetime) {
            return $dt->toDateTime()->getTimestamp();
        }

        return strtotime($dt);
    }

    /**
     * @param integer $m                Month
     * @param integer|boolean $leapYear Year or leap year flag
     * @return integer|null
     */
    public static function getDaysInMonth($m, $leapYear)
    {
        if (!is_bool($leapYear)) {
            $leapYear = self::isLeapYear($leapYear);
        }

        return isset(self::$daysInMonth[$leapYear][$m - 1])
            ? self::$daysInMonth[$leapYear][$m - 1]
            : null;
    }

    public static function isLeapYear($year)
    {
        return ($year % 4 == 0 && $year % 100 != 0) || $year % 400 == 0;
    }

    public static function weekDay($dt = null)
    {
        $a = getdate(self::timestamp($dt));

        return $a['wday'] ?: self::SUNDAY;
    }

    public static function yearDay($dt = null)
    {
        if (!$dt) {
            return 0;
        }

        $dt = self::timestamp($dt);

        return floor(
            ($dt - mktime(0, 0, 0, 1, 1, date('Y', $dt))) / self::SECS_PER_DAY
        );
    }

    public static function bigYearDay($dt = null)
    {
        $yd = self::yearDay($dt);
        $yd = str_repeat('0', 3 - mb_strlen($yd)) . $yd;

        return $dt ? date('Y', self::timestamp($dt)) . $yd : 0;
    }

    public static function passedBy($timestamp, $now = null, $vocabulary = null)
    {
        $vocabulary = extend(
            [
                'long_term_limit' => '+1 year',
                'long_term_date_format' => 'd.m.Y H:i',
                'just_now' => 'только что',
                'second1' => 'секунду',
                'second2' => 'секунды',
                'second3' => 'секунд',
                'minute1' => 'минуту',
                'minute2' => 'минуты',
                'minute3' => 'минут',
                'hour1' => 'час',
                'hour2' => 'часа',
                'hour3' => 'часов',
                'day1' => 'день',
                'day2' => 'дня',
                'day3' => 'дней',
                'month1' => 'месяц',
                'month2' => 'месяца',
                'month3' => 'месяцев',
                'year1' => 'год',
                'year2' => 'года',
                'year3' => 'лет',
                'ago' => 'назад',
            ],
            (array) $vocabulary
        );

        $timestamp = self::timestamp($timestamp);
        $now = self::timestamp($now);
        $seconds = $now - $timestamp;

        $getCase = function ($amount, $term) use ($vocabulary) {
            return StringHelper::digitCase(
                abs($amount),
                $vocabulary[$term . '1'],
                $vocabulary[$term . '2'],
                $vocabulary[$term . '3']
            );
        };

        if (!$seconds) {
            return $vocabulary['just_now'];
        } elseif (strtotime($vocabulary['long_term_limit'], $timestamp) < $now) {
            // more than a month ago
            return self::format($vocabulary['long_term_date_format'], $timestamp);
        } else {
            // secs
            if ($seconds < 60) {
                $s = $getCase($seconds, 'second');
            } else {
                $seconds = round($seconds / 60);

                //mins
                if ($seconds < 60) {
                    $s = $getCase($seconds, 'minute');
                } else {
                    $seconds = round($seconds / 60);

                    // hours
                    if ($seconds < 24) {
                        $s = $getCase($seconds, 'hour');
                    } else {
                        $seconds = round($seconds / 24);

                        // days
                        if ($seconds < 30) {
                            $s = $getCase($seconds, 'day');
                        } else {
                            $seconds = round($seconds / 30);

                            if ($seconds < 12) {
                                // months
                                $s = $getCase($seconds, 'month');
                            } else {
                                $seconds = round($seconds / 12);

                                $s = $getCase($seconds, 'year');
                            }
                        }
                    }
                }
            }

            return $s . ($vocabulary['ago'] ? ' ' . $vocabulary['ago'] : '');
        }
    }

    public static function engPassedBy($timestamp, $now = null, $vocabulary = null)
    {
        return self::passedBy(
            $timestamp,
            $now,
            extend(
                [
                    'long_term_date_format' => 'm/d/Y H:i',
                    'just_now' => 'just now',
                    'second1' => 'second',
                    'second2' => 'seconds',
                    'second3' => null,
                    'minute1' => 'minute',
                    'minute2' => 'minutes',
                    'minute3' => null,
                    'hour1' => 'hour',
                    'hour2' => 'hours',
                    'hour3' => null,
                    'day1' => 'day',
                    'day2' => 'days',
                    'day3' => null,
                    'month1' => 'month',
                    'month2' => 'months',
                    'month3' => null,
                    'year1' => 'year',
                    'year2' => 'years',
                    'year3' => null,
                    'ago' => 'ago',
                ],
                (array) $vocabulary
            )
        );
    }

    public static function minSecToSeconds($time)
    {
        preg_match('/^((\d*):)?(\d*)([,.](\d+))?$/', $time, $regs);
        $min = 2;
        $sec = 3;
        $msec = 5;

        $m = floatval('0.' . ArrayHelper::get($regs, $msec, 0));

        return ArrayHelper::get($regs, $min, 0) * 60 +
            ArrayHelper::get($regs, $sec, 0) +
            ($m ?: 0);
    }
}
