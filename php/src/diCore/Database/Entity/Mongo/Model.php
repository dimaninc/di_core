<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 18.01.2018
 * Time: 23:07
 */

namespace diCore\Database\Entity\Mongo;

use diCore\Database\FieldType;

class Model extends \diModel
{
	const id_field_name = '_id';

	/**
	 * @var \MongoDB\Collection
	 */
	protected $collectionResource;

	protected static $fieldTypes = [];

	public static function getFieldTypes()
	{
		return static::$fieldTypes;
	}

	public static function getFieldType($field)
	{
		$ar = static::getFieldTypes();

		return isset($ar[$field]) ? $ar[$field] : null;
	}

	/**
	 * @return \MongoDB\Collection
	 */
	protected function getCollectionResource()
	{
		if (!$this->collectionResource)
		{
			$this->collectionResource = $this->getDb()->getLink()->selectCollection($this->getTable());
		}

		return $this->collectionResource;
	}

	public function initFrom($r)
	{
		if ($r instanceof \MongoDB\Model\BSONDocument)
		{
			$ar = [];

			foreach ($r->getIterator() as $field => $value)
			{
				$ar[$field] = self::tuneFieldValueByTypeAfterDb($field, $value);
			}

			return parent::initFrom($ar);
		}

		return parent::initFrom($r);
	}

	public static function tuneFieldValueByTypeAfterDb($field, $value)
	{
		if ($value instanceof \MongoDB\BSON\ObjectID)
		{
			return (string)$value;
		}
		elseif ($value instanceof \MongoDB\BSON\UTCDatetime)
		{
			return \diDateTime::sqlFormat(((string)$value) / 1000);
		}

		return $value;
	}

	public static function tuneFieldValueByTypeBeforeDb($field, $value)
	{
		$type = static::getFieldType($field);

		if (is_array($value))
		{
			foreach ($value as $k => &$v)
			{
				$v = static::tuneFieldValueByTypeBeforeDb($field, $v);
			}

			return $value;
		}

		if ($field == static::id_field_name)
		{
			if (!$value instanceof \MongoDB\BSON\ObjectID)
			{
				return new \MongoDB\BSON\ObjectID($value);
			}
		}

		switch ($type)
		{
			case FieldType::int:
				$value = (int)$value;
				break;

			case FieldType::float:
				$value = (float)$value;
				break;

			case FieldType::double:
				$value = (double)$value;
				break;

			case FieldType::timestamp:
			case FieldType::datetime:
				if (!$value instanceof \MongoDB\BSON\UTCDatetime)
				{
					$value = new \MongoDB\BSON\UTCDatetime((new \DateTime($value))->getTimestamp() * 1000);
				}
				break;
		}

		return $value;
	}

	protected function getDataForDb()
	{
		$ar = parent::getDataForDb();

		foreach ($this->getFieldTypes() as $field => $type)
		{
			if ($type == FieldType::timestamp)
			{
				if (!isset($ar[$field]))
				{
					$ar[$field] = 'now';
				}
			}
		}

		foreach ($ar as $field => &$value)
		{
			if ($value === null)
			{
				continue;
			}

			$value = self::tuneFieldValueByTypeBeforeDb($field, $value);
		}

		return $ar;
	}

	protected function saveToDb()
	{
		$ar = $this->getDataForDb();

		if (!count($ar))
		{
			return $this;
		}

		if ($this->isInsertOrUpdateAllowed())
		{
			throw new \Exception('isInsertOrUpdateAllowed not implemented for Mongo yet');
		}
		elseif ($this->getId() && ($this->idAutoIncremented || (!$this->idAutoIncremented && $this->getOrigId())))
		{
			$this->getCollectionResource()->updateOne([
				$this->getIdFieldName() => $this->getId(),
			], $ar);
		}
		else
		{
			$insertResult = $this->getCollectionResource()->insertOne($ar); //['fsync' => true,]
			/** @var \MongoDB\BSON\ObjectId $id */
			$id = $insertResult->getInsertedId();

			if ($id)
			{
				$this->setId((string)$id);
			}
		}

		$this
			->setOrigData();

		return $this;
	}

	protected function processIdBeforeGetRecord($id, $field)
	{
		return new \MongoDB\BSON\ObjectID($id);
	}

	protected static function isProperId($id)
	{
		return strlen($id) == 24;
	}

	protected function getRecord($id, $fieldAlias = null)
	{
		if (!$this->getTable())
		{
			throw new \Exception('Collection not defined');
		}

		$a = $this->prepareIdAndFieldForGetRecord($id, $fieldAlias);

		$ar = $this->getCollectionResource()
			->findOne([
				$a['field'] => $a['id'],
			]);

		return $this->tuneDataAfterFetch($ar);
	}

	protected function tuneDataAfterFetch($ar)
	{
		foreach ($ar as $field => &$value)
		{
			if ($value instanceof \MongoDB\BSON\ObjectID)
			{
				$value = (string)$value;
			}
			elseif ($value instanceof \MongoDB\BSON\UTCDatetime)
			{
				$value = $value->toDateTime()->format(\diDateTime::FORMAT_SQL_DATE_TIME);
			}
		}

		return $ar;
	}
}