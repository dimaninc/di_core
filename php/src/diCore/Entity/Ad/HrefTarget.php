<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 24.12.2017
 * Time: 11:26
 */

namespace diCore\Entity\Ad;

use diCore\Tool\SimpleContainer;

class HrefTarget extends SimpleContainer
{
	const self = 0;
	const blank = 1;
	const parent = 2;
	const top = 3;

	public static $titles = [
		self::self => 'В том же окне',
		self::blank => 'В новом окне',
		self::parent => 'В родительском фрейме',
		self::top => 'В основном окне',
	];

	public static $names = [
		self::self => 'self',
		self::blank => 'blank',
		self::parent => 'parent',
		self::top => 'top',
	];

	public static function htmlAttribute($id)
	{
		return '_' . static::name($id);
	}
}