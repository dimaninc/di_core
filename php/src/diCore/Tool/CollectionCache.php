<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 26.10.2016
 * Time: 13:42
 */

namespace diCore\Tool;

class CollectionCache
{
	protected static $data = [];

	public static function addForCollection($modelType, \diCollection $col, callable $callback, $options = [])
	{
		$options = extend([
			"field" => "id",
			"queryFields" => null,
		], is_array($options) ? $options : [
			"field" => $options,
		]);

		$values = [];

		/** @var \diModel $model */
		foreach ($col as $model)
		{
			$values[] = $callback($model);
		}

		self::add([
			\diCollection::create($modelType, "WHERE {$options["field"]}" . \diDB::in(array_unique($values)), $options["queryFields"]),
		]);
	}

	public static function add($collections)
	{
		if (!is_array($collections))
		{
			$collections = [$collections];
		}

		/** @var \diCollection $col */
		foreach ($collections as $col)
		{
			$modelType = \diTypes::getId($col->getModelType());

			self::$data[$modelType] = $col;
		}
	}

	public static function addManual($dataType, $field, $values)
	{
		$col = \diCollection::create($dataType);
		$col->filterBy($field, $values);

		self::add($col);
	}

	public static function remove($modelTypes = null)
	{
		if ($modelTypes === null)
		{
			self::$data = [];
		}
		else
		{
			if (!is_array($modelTypes))
			{
				$modelTypes = [$modelTypes];
			}

			foreach ($modelTypes as $modelType)
			{
				$modelType = \diTypes::getId($modelType);

				if (isset(self::$data[$modelType]))
				{
					unset(self::$data[$modelType]);
				}
			}
		}
	}

	/**
	 * @param int|string $modelType
	 * @return \diCollection
	 */
	public static function get($modelType, $force = false)
	{
		$modelType = \diTypes::getId($modelType);

		return isset(self::$data[$modelType])
			? self::$data[$modelType]
			: ($force
                ? \diCollection::create($modelType)
                : null
            );
	}

	/**
	 * @param int|string $modelType
	 * @return boolean
	 */
	public static function exists($modelType)
	{
		$modelType = \diTypes::getId($modelType);

		return isset(self::$data[$modelType]);
	}

	/**
	 * @param int|string $modelType
	 * @param int        $modelId
	 * @return \diModel
	 * @throws \Exception
	 */
	public static function getModel($modelType, $modelId, $force = false)
	{
		$col = self::get($modelType);

		return $col[$modelId] ?: \diModel::create($modelType, $force ? $modelId : null, 'id');
	}
}