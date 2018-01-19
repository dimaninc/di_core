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
	protected $idFieldName = '_id';

	/**
	 * @var \MongoDB\Collection
	 */
	protected $collectionResource;

	protected static $fieldTypes = [];

	protected function getFieldTypes()
	{
		return static::$fieldTypes;
	}

	protected function getFieldType($field)
	{
		$ar = $this->getFieldTypes();

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

			switch ($this->getFieldType($field))
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
					$value = new \MongoDB\BSON\UTCDateTime((new \DateTime($value))->getTimestamp() * 1000);
					break;
			}
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
		return new \MongoDB\BSON\ObjectId($id);
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
			if ($value instanceof \MongoDB\BSON\ObjectId)
			{
				$value = (string)$value;
			}
			elseif ($value instanceof \MongoDB\BSON\UTCDateTime)
			{
				$value = $value->toDateTime()->format(\diDateTime::FORMAT_SQL_DATE_TIME);
			}
		}

		return $ar;
	}
}