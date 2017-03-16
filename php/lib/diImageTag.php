<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 01.09.2015
 * Time: 18:41
 */

class diImageTag
{
	public static function get($table, $pic, $w, $h)
	{
		$folder = get_pics_folder($table);
		$fn = "/" . $folder . $pic;

		if (diSwiffy::is($pic))
		{
			return diSwiffy::getHtml($fn, $w, $h);
		}
		else
		{
			return "<img src=\"{$fn}\" width=\"{$w}\" height=\"{$h}\">";
		}
	}

	public static function getForModel(diModel $model, $field = "pic")
	{
		return static::get($model->getTable(), $model->get($field), $model->get($field."_w"), $model->get($field."_h"));
	}
}