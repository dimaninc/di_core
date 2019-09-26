<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 18.09.2016
 * Time: 17:32
 */

namespace diCore\Helper;

class Phone
{
	const INPUT_PATTERN = '(00|\+)?\d{1,3}[\s\t\x20()\-]*\d{2,3}[\s\t\x20()\-]*\d{3,3}[\s\t\x20\-]*\d{2,3}[\s\t\x20\-]*\d{2,3}';
	const INPUT_PATTERN_INT_AND_LOCAL = '(00|\+)?\d{1,3}\s*(\d{2,3}\s*\d{3,3})?\s*\d{2,3}\s*\d{2,3}';
	const INPUT_EXAMPLE = '+7 123 4567890';

	public static function isValid($phone)
	{
		return preg_match('/^\d{10,15}$/', $phone);
	}

	public static function isRussian($phone)
    {
        $cleanPhone = Phone::clean($phone);

        return $cleanPhone && substr($cleanPhone, 0, 1) === '7' && strlen($cleanPhone) == 11;
    }

	public static function clean($phone, $prefix = '')
	{
		$phone = preg_replace('/[^\d]+/', '', $phone);

		if (substr($phone, 0, 2) == '00')
		{
			$phone = substr($phone, 2);
		}

		if (strlen($phone) == 10 && static::isValid($phone))
		{
			$phone = '7' . $phone; // default Russian code
		}

		if (strlen($phone) == 11 && static::isValid($phone) && substr($phone, 0, 1) == '8')
		{
			$phone = '7' . substr($phone, 1); // 8 -> default Russian code
		}

		return $phone
            ? $prefix . $phone
            : '';
	}
}