<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 18.02.2022
 * Time: 11:35
 */

namespace diCore\Traits\Collection;

use diCore\Entity\Content\Model;

/**
 * Trait Hierarchy
 * @package diCore\Traits\Collection
 *
 * @method $this filterByParent($value, $operator = null)
 * @method $this filterByLevelNum($value, $operator = null)
 * @method $this filterByOrderNum($value, $operator = null)
 *
 * @method $this orderByParent($direction = null)
 * @method $this orderByLevelNum($direction = null)
 * @method $this orderByOrderNum($direction = null)
 *
 * @method $this selectParent()
 * @method $this selectLevelNum()
 * @method $this selectOrderNum()
 */
trait Hierarchy
{
    /**
     * @return $this
     * @throws \Exception
     */
    public static function asHierarchyCol()
    {
        $col = static::create()->orderByOrderNum();

        $col->map(function (Model $m) use ($col) {
            if ($m->getParent() > 0) {
                $col->getById($m->getParent())->addChildren($m);
            }
        });

        return $col->filtered(function (\diModel $m) {
            return $m->getParent() <= 0;
        });
    }

    public static function asHierarchyPublicDataArray(\diCollection $col = null)
    {
        if (!$col) {
            $col = static::asHierarchyCol();
        }

        return $col->map(function (\diModel $m) {
            $children = static::asHierarchyPublicDataArray($m->getChildren());

            return extend(
                $m->getPublicData(),
                $children
                    ? [
                        '_children' => $children,
                    ]
                    : []
            );
        });
    }
}
