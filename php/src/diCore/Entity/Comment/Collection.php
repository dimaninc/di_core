<?php
/**
 * Created by \diModelsManager
 * Date: 03.03.2017
 * Time: 19:08
 */

namespace diCore\Entity\Comment;

/**
 * Class Collection
 * Methods list for IDE
 *
 * @method Collection filterById($value, $operator = null)
 * @method Collection filterByUserType($value, $operator = null)
 * @method Collection filterByUserId($value, $operator = null)
 * @method Collection filterByOwnerId($value, $operator = null)
 * @method Collection filterByParent($value, $operator = null)
 * @method Collection filterByTargetType($value, $operator = null)
 * @method Collection filterByTargetId($value, $operator = null)
 * @method Collection filterByContent($value, $operator = null)
 * @method Collection filterByDate($value, $operator = null)
 * @method Collection filterByIp($value, $operator = null)
 * @method Collection filterByOrderNum($value, $operator = null)
 * @method Collection filterByLevelNum($value, $operator = null)
 * @method Collection filterByVisible($value, $operator = null)
 * @method Collection filterByModerated($value, $operator = null)
 * @method Collection filterByKarma($value, $operator = null)
 * @method Collection filterByEvilScore($value, $operator = null)
 *
 * @method Collection orderById($direction = null)
 * @method Collection orderByUserType($direction = null)
 * @method Collection orderByUserId($direction = null)
 * @method Collection orderByOwnerId($direction = null)
 * @method Collection orderByParent($direction = null)
 * @method Collection orderByTargetType($direction = null)
 * @method Collection orderByTargetId($direction = null)
 * @method Collection orderByContent($direction = null)
 * @method Collection orderByDate($direction = null)
 * @method Collection orderByIp($direction = null)
 * @method Collection orderByOrderNum($direction = null)
 * @method Collection orderByLevelNum($direction = null)
 * @method Collection orderByVisible($direction = null)
 * @method Collection orderByModerated($direction = null)
 * @method Collection orderByKarma($direction = null)
 * @method Collection orderByEvilScore($direction = null)
 *
 * @method Collection selectId()
 * @method Collection selectUserType()
 * @method Collection selectUserId()
 * @method Collection selectOwnerId()
 * @method Collection selectParent()
 * @method Collection selectTargetType()
 * @method Collection selectTargetId()
 * @method Collection selectContent()
 * @method Collection selectDate()
 * @method Collection selectIp()
 * @method Collection selectOrderNum()
 * @method Collection selectLevelNum()
 * @method Collection selectVisible()
 * @method Collection selectModerated()
 * @method Collection selectKarma()
 * @method Collection selectEvilScore()
 */
class Collection extends \diCollection
{
	const type = \diTypes::comment;
	protected $table = 'comments';
	protected $modelType = 'comment';

	protected $targetType;
	protected $targetId;

	const CACHE_RECENT = 1;
	const CACHE_BY_TARGET = 2;

	protected static $cacheNames = [
		self::CACHE_RECENT => 'recent',
		self::CACHE_BY_TARGET => 'by_target',
	];

	/**
	 * @param int|\diModel $targetType
	 * @param int|null $targetId
	 * @return Collection
	 * @throws \Exception
	 */
	public static function createForTarget($targetType, $targetId = null)
	{
		if ($targetType instanceof \diModel && $targetId === null)
		{
			$targetId = $targetType->getId();
			$targetType = $targetType->modelType();
		}

		/** @var Collection $col */
		$col = static::create(static::type);
		$col
			->setTargetType($targetType)
			->setTargetId($targetId)
			->filterByTargetType($targetType)
			->filterByTargetId($targetId)
			->orderByOrderNum();

		return $col;
	}

	protected function getBaseCacheSubFolder($cacheKind = self::CACHE_ALL)
	{
		switch ($cacheKind)
		{
			case self::CACHE_BY_TARGET:
				return parent::getBaseCacheSubFolder($cacheKind) . '/' . \diTypes::getName($this->getTargetType());

			default:
				return parent::getBaseCacheSubFolder($cacheKind);
		}
	}

	protected function getCacheFilename($cacheKind = self::CACHE_ALL)
	{
		switch ($cacheKind)
		{
			case self::CACHE_BY_TARGET:
				return $this->getTargetId() . static::CACHE_FILE_EXTENSION;

			default:
				return parent::getCacheFilename($cacheKind);
		}
	}

	/**
	 * @return int
	 */
	public function getTargetType()
	{
		return $this->targetType;
	}

	/**
	 * @param int $targetType
	 * @return $this
	 */
	public function setTargetType($targetType)
	{
		$this->targetType = $targetType;

		return $this;
	}

	/**
	 * @return int
	 */
	public function getTargetId()
	{
		return $this->targetId;
	}

	/**
	 * @param int $targetId
	 * @return $this
	 */
	public function setTargetId($targetId)
	{
		$this->targetId = $targetId;

		return $this;
	}
}