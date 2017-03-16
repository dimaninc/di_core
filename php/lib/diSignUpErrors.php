<?php

/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 05.01.16
 * Time: 14:40
 */
class diSignUpErrors extends diSimpleContainer
{
	const NAME_REQUIRED = 1;

	const EMAIL_REQUIRED = 11;
	const EMAIL_NOT_VALID = 12;
	const EMAIL_IN_USE = 13;

	const PASSWORD_REQUIRED = 21;
	const PASSWORD_MIN_LENGTH = 22;

	const PHONE_REQUIRED = 31;
	const PHONE_NOT_VALID = 32;
	const PHONE_IN_USE = 33;

	public static $names = [
		self::NAME_REQUIRED => "name_required",

		self::EMAIL_REQUIRED => "email_required",
		self::EMAIL_NOT_VALID => "email_not_valid",
		self::EMAIL_IN_USE => "email_in_use",

		self::PASSWORD_REQUIRED => "password_required",
		self::PASSWORD_MIN_LENGTH => "password_min_length",

		self::PHONE_REQUIRED => "phone_required",
		self::PHONE_NOT_VALID => "phone_not_valid",
		self::PHONE_IN_USE => "phone_in_use",
	];
}