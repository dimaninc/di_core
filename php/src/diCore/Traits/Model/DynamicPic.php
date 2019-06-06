<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 06.06.2019
 * Time: 15:19
 */

namespace diCore\Traits\Model;

use diCore\Entity\DynamicPic\Collection;
use diCore\Entity\DynamicPic\Model;

/**
 * Trait DynamicPic
 * @package diCore\Traits\Model
 *
 * @method integer getId
 * @method integer getTable
 */
trait DynamicPic
{
    /**
     * @var array Collections by table/field
     */
    protected static $colByField = [];
    /**
     * @var Collection Collections by table
     */
    protected static $colForTable = [];

    protected $cacheByField = false;
    protected $cacheForTable = false;

    private function checkCacheByField($field)
    {
        if ($this->cacheByField && !isset(self::$colByField[$this->getTable()][$field])) {
            self::$colByField[$this->getTable()][$field] = Collection::createByTargetTable(
                $this->getTable(), $field
            );
        }

        return $this;
    }

    private function checkCacheForTable()
    {
        if ($this->cacheForTable && !isset(self::$colForTable[$this->getTable()])) {
            self::$colForTable[$this->getTable()] = Collection::createByTargetTable($this->getTable());
        }

        return $this;
    }

    public function getDynamicPicsByField($field)
    {
        $this
            ->checkCacheByField($field)
            ->checkCacheForTable();

        if (!empty(self::$colByField[$this->getTable()][$field])) {
            $col = self::$colByField[$this->getTable()][$field];
        } elseif (!empty(self::$colForTable[$this->getTable()])) {
            $col = self::$colForTable[$this->getTable()];
        } else {
            $col = Collection::createByTarget($this->getTable(), $this->getId(), $field);
        }

        $ar = [];

        /** @var Model $pic */
        foreach ($col as $pic) {
            if ($pic->getTargetId() == $this->getId()) {
                $ar[$pic->getId()] = $pic;
            }
        }

        return $ar;
    }
}