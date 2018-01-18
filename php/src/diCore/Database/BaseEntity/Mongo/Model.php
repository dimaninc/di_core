<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 18.01.2018
 * Time: 23:07
 */

namespace diCore\Database\BaseEntity\Mongo;

class Model extends \diModel
{
	protected $idFieldName = '_id';

	/**
	 * @var \MongoDB\Collection
	 */
	protected $collectionResource;

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

		// set type of each field here

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
			$id = $insertResult->getInsertedId();

			if ($id)
			{
				$this->setId($id);
			}
		}

		$this
			->setOrigData();

		return $this;
	}
}