<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 02.07.2015
 * Time: 17:51
 */

class diSlug
{
	public static function prepare($source, $delimiter = "-")
	{
		$source = trim($source, ' \"\'');
		$source = str_replace(array(" ", "/", "\\", "_", "-"), $delimiter, $source);
		$source = preg_replace("/{$delimiter}{2,}/", $delimiter, $source);
		$source = preg_replace("/\&\#?[a-z0-9]+\;/", "", $source);
		$source = transliterate_rus_to_eng($source);
		$source = preg_replace("/[^a-zA-Z0-9{$delimiter}]/", "", $source);

		return $source;
	}

	public static function unique($slug, $table, $id, $options = [])
	{
		$options = extend([
			"idFieldName" => "id",
			"slugFieldName" => "slug",
			"delimiter" => "-",
			"queryConditions" => [],
			"uniqueMaker" => function($origSlug, $delimiter, $index) {
				return $origSlug . $delimiter . $index;
			},
		], $options);

		$i = 1;

		if ($slug)
		{
			$origSlug = $slug;
		}
		else
		{
			$origSlug = self::prepare(diTypes::getNameByTable($table), $options["delimiter"]);
			$slug = $origSlug . $options["delimiter"] . strval($i++);
		}

		$queryAr = array_merge(["`{$options["slugFieldName"]}` = '{$slug}'"], $options["queryConditions"]);

		while (true)
		{
			$model = diCollection::createForTable($table, "WHERE " . join(" AND ", $queryAr))->getFirstItem();

			if (!$model->exists() || $id == $model->get($options["idFieldName"]))
			{
				break;
			}

			$slug = $options["uniqueMaker"]($origSlug, $options["delimiter"], $i++);

			$queryAr = array_merge(["`{$options["slugFieldName"]}` = '{$slug}'"], $options["queryConditions"]);
		}

		return $slug;
	}

	public static function generate($source, $table, $id = null,
	                                $idFieldName = "id", $slugFieldName = "slug",
	                                $delimiter = "-", $extraOptions = [])
	{
		if (is_object($table) && $table instanceof diModel)
		{
			$delimiter = $id ?: $delimiter;
			$id = $table->getId();
			$idFieldName = $table->getIdFieldName();
			$slugFieldName = $table->getSlugFieldName();

			$table = $table->getTable();
		}

		return self::unique(self::prepare($source, $delimiter), $table, $id, extend([
			"idFieldName" => $idFieldName,
			"slugFieldName" => $slugFieldName,
			"delimiter" => $delimiter,
		], $extraOptions));
	}
}