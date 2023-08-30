<?php
/**
 * Created by \diModelsManager
 * Date: 03.03.2017
 * Time: 19:08
 */

namespace diCore\Entity\Comment;

use diCore\Traits\Collection\AutoTimestamps;
use diCore\Traits\Collection\TargetInside;

/**
 * Class Collection
 * Methods list for IDE
 *
 * @method $this filterById($value, $operator = null)
 * @method $this filterByUserType($value, $operator = null)
 * @method $this filterByUserId($value, $operator = null)
 * @method $this filterByOwnerId($value, $operator = null)
 * @method $this filterByParent($value, $operator = null)
 * @method $this filterByContent($value, $operator = null)
 * @method $this filterByDate($value, $operator = null)
 * @method $this filterByIp($value, $operator = null)
 * @method $this filterByOrderNum($value, $operator = null)
 * @method $this filterByLevelNum($value, $operator = null)
 * @method $this filterByVisible($value, $operator = null)
 * @method $this filterByModerated($value, $operator = null)
 * @method $this filterByKarma($value, $operator = null)
 * @method $this filterByEvilScore($value, $operator = null)
 *
 * @method $this orderById($direction = null)
 * @method $this orderByUserType($direction = null)
 * @method $this orderByUserId($direction = null)
 * @method $this orderByOwnerId($direction = null)
 * @method $this orderByParent($direction = null)
 * @method $this orderByContent($direction = null)
 * @method $this orderByDate($direction = null)
 * @method $this orderByIp($direction = null)
 * @method $this orderByOrderNum($direction = null)
 * @method $this orderByLevelNum($direction = null)
 * @method $this orderByVisible($direction = null)
 * @method $this orderByModerated($direction = null)
 * @method $this orderByKarma($direction = null)
 * @method $this orderByEvilScore($direction = null)
 *
 * @method $this selectId()
 * @method $this selectUserType()
 * @method $this selectUserId()
 * @method $this selectOwnerId()
 * @method $this selectParent()
 * @method $this selectContent()
 * @method $this selectDate()
 * @method $this selectIp()
 * @method $this selectOrderNum()
 * @method $this selectLevelNum()
 * @method $this selectVisible()
 * @method $this selectModerated()
 * @method $this selectKarma()
 * @method $this selectEvilScore()
 */
class Collection extends \diCollection
{
    use AutoTimestamps;
    use TargetInside;

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
     * @return self
     * @throws \Exception
     */
    public static function createForTarget($targetType, $targetId = null)
    {
        if ($targetType instanceof \diModel && $targetId === null) {
            $targetId = $targetType->getId();
            $targetType = $targetType->modelType();
        }

        /** @var self $col */
        $col = static::create(static::type);
        $col->setTargetType($targetType)
            ->setTargetId($targetId)
            ->filterByTargetType($targetType)
            ->filterByTargetId($targetId)
            ->orderByOrderNum();

        return $col;
    }

    protected function getBaseCacheSubFolder($cacheKind = self::CACHE_ALL)
    {
        switch ($cacheKind) {
            case self::CACHE_BY_TARGET:
                return parent::getBaseCacheSubFolder($cacheKind) .
                    '/' .
                    \diTypes::getName($this->getTargetType());

            default:
                return parent::getBaseCacheSubFolder($cacheKind);
        }
    }

    protected function getCacheFilename($cacheKind = self::CACHE_ALL)
    {
        switch ($cacheKind) {
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
