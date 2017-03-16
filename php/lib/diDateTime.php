<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 03.07.2015
 * Time: 13:06
 */

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

	const FORMAT_SQL_DATE_TIME = 'Y-m-d H:i:s';

	public static $daysInMonth = [
		false => [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31],
		true => [31, 29, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31],
	];

	public static $weekDaysShort = [
		self::MONDAY => "Пн",
		self::TUESDAY => "Вт",
		self::WEDNESDAY => "Ср",
		self::THURSDAY => "Чт",
		self::FRIDAY => "Пт",
		self::SATURDAY => "Сб",
		self::SUNDAY => "Вс",
	];

	public static $engWeekDaysShort = [
		self::MONDAY => "Mo",
		self::TUESDAY => "Tu",
		self::WEDNESDAY => "We",
		self::THURSDAY => "Th",
		self::FRIDAY => "Fr",
		self::SATURDAY => "Sa",
		self::SUNDAY => "Su",
	];

	public static $weekDays = [
		self::MONDAY => "Понедельник",
		self::TUESDAY => "Вторник",
		self::WEDNESDAY => "Среда",
		self::THURSDAY => "Четверг",
		self::FRIDAY => "Пятница",
		self::SATURDAY => "Суббота",
		self::SUNDAY => "Воскресенье",
	];

	public static $engWeekDays = [
		self::MONDAY => "Monday",
		self::TUESDAY => "Tuesday",
		self::WEDNESDAY => "Wednesday",
		self::THURSDAY => "Thursday",
		self::FRIDAY => "Friday",
		self::SATURDAY => "Saturday",
		self::SUNDAY => "Sunday",
	];

	public static $months = [
		1 => "Январь",
		2 => "Февраль",
		3 => "Март",
		4 => "Апрель",
		5 => "Май",
		6 => "Июнь",
		7 => "Июль",
		8 => "Август",
		9 => "Сентябрь",
		10 => "Октябрь",
		11 => "Ноябрь",
		12 => "Декабрь",
	];

	public static $monthsGenitive = [
		1 => "Января",
		2 => "Февраля",
		3 => "Марта",
		4 => "Апреля",
		5 => "Мая",
		6 => "Июня",
		7 => "Июля",
		8 => "Августа",
		9 => "Сентября",
		10 => "Октября",
		11 => "Ноября",
		12 => "Декабря",
	];

	public static $engMonths = [
		1 => "January",
		2 => "February",
		3 => "March",
		4 => "April",
		5 => "May",
		6 => "June",
		7 => "July",
		8 => "August",
		9 => "September",
		10 => "October",
		11 => "November",
		12 => "December",
	];

	public static function format($format, $dt = null)
	{
		$dt = self::timestamp($dt ?: time());
		$a = getdate($dt);

		$ar = array(
			"%мес%" => mb_substr(self::$months[$a["mon"]], 0, 3),
			"%месяц%" => self::$months[$a["mon"]],
			"%месяца%" => self::$monthsGenitive[$a["mon"]],
			"%деньнедели%" => self::$weekDays[$a["wday"] ?: 7],
			"%день_недели%" => self::$weekDays[$a["wday"] ?: 7],
			"%дн%" => self::$weekDaysShort[$a["wday"] ?: 7],
		);

		$format = date($format, $dt);
		$format = str_replace(array_keys($ar), array_values($ar), $format);

		return $format;
	}

	public static function timestamp($dt = null)
	{
		if ($dt === null)
		{
			return time();
		}

		return isInteger($dt) ? $dt : strtotime($dt);
	}

	/**
	 * @param integer $m                Month
	 * @param integer|boolean $leapYear Year or leap year flag
	 * @return integer|null
	 */
	public static function getDaysInMonth($m, $leapYear)
	{
		if (!is_bool($leapYear))
		{
			$leapYear = self::isLeapYear($leapYear);
		}

		return isset(self::$daysInMonth[$leapYear][$m - 1])
			? self::$daysInMonth[$leapYear][$m - 1]
			: null;
	}

	public static function isLeapYear($year)
	{
		return $year % 4 == 0 && $year % 100 != 0 || $year % 400 == 0;
	}

	public static function weekDay($dt)
	{
		$a = getdate(self::timestamp($dt));

		return $a["wday"] ?: 7;
	}

	public static function yearDay($dt)
	{
		if (!$dt)
		{
			return 0;
		}

		$dt = self::timestamp($dt);

		return floor(($dt - mktime(0, 0, 0, 1, 1, date("Y", $dt))) / self::SECS_PER_DAY);
	}

	public static function bigYearDay($dt)
	{
		$yd = self::yearDay($dt);
		$yd = str_repeat("0", 3 - mb_strlen($yd)).$yd;

		return $dt ? date("Y", self::timestamp($dt)).$yd : 0;
	}

	public static function passedBy($timestamp, $now = null)
	{
		if ($now)
		{
			$now = self::timestamp($now);
		}
		else
		{
			$now = time();
		}

		$timestamp = self::timestamp($timestamp);

		$t_diff = $now - $timestamp;

		if (!$t_diff)
		{
			return "только что";
		}
		elseif (strtotime("+1 month", $timestamp) < $now) // more than a month ago
		{
			return date("d.m.Y H:i", $timestamp);
		}
		else
		{
			if ($t_diff < 60) // secs
			{
				$s = digit_case(abs($t_diff), "секунду", "секунды", "секунд");
			}
			else
			{
				$t_diff = round($t_diff / 60);

				if ($t_diff < 60) //mins
				{
					$s = digit_case(abs($t_diff), "минуту", "минуты", "минут");
				}
				else
				{
					$t_diff = round($t_diff / 60);

					if ($t_diff < 24) // hours
					{
						$s = digit_case($t_diff, "час", "часа", "часов");
					}
					else
					{
						$t_diff = round($t_diff / 24);

						if ($t_diff < 30) // days
						{
							$s = digit_case($t_diff, "день", "дня", "дней");
						}
						else
						{
							$t_diff = round($t_diff / 30);

							if ($t_diff < 12) // months
							{
								$s = digit_case($t_diff, "месяц", "месяца", "месяцев");
							}
							else
							{
								$t_diff = round($t_diff / 12);

								$s = digit_case($t_diff, "год", "года", "лет");
							}
						}
					}
				}
			}

			return $s . " назад";
		}
	}
}