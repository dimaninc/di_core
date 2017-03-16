<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 16.10.15
 * Time: 18:02
 */
class diDynamicPicCollection extends diCollection
{
	protected $table = "dipics";
	protected $modelType = "dynamic_pic";

	/**
	 * @param $table
	 * @param $id
	 * @param $field
	 * @return diDynamicPicCollection
	 * @throws Exception
	 */
	public static function createByTarget($table, $id, $field = null)
	{
		$table = diDB::_in($table);
		$id = (int)$id;

		$query = array(
			"_table='$table'",
			"_id='$id'",
		);

		if ($field !== null)
		{
			$field = diDB::_in($field);

			$query[] = "_field='$field'";
		}

		return static::create("dynamic_pic", "WHERE " . join(" AND ", $query));
	}
}