<?php

/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 03.01.16
 * Time: 18:14
 */
class diAuthErrors extends diSimpleContainer
{
	const OK = 0;
	const EMAIL_NOT_FOUND = 1;
	const EMPTY_EMAIL = 2;
	const NOT_ACTIVATED = 3;
	const WRONG_PASSWORD = 4;

	public static $names = array(
		self::EMAIL_NOT_FOUND => "email_not_found",
		self::EMPTY_EMAIL => "empty_email",

		self::NOT_ACTIVATED => "not_activated",
		self::WRONG_PASSWORD => "wrong_password",
	);
}