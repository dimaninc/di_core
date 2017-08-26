<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 26.08.2017
 * Time: 17:13
 */

namespace diCore\Entity\MailIncut;


use diCore\Tool\SimpleContainer;

class Type extends SimpleContainer
{
	const text = 1;
	const binary_attachment = 2;

	public static $titles = [
		self::text => 'Text',
		self::binary_attachment => 'Binary attachment',
	];

	public static $names = [
		self::text => 'text',
		self::binary_attachment => 'binary_attachment',
	];
}