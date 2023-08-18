<?php
/**
 * Created by \diModelsManager
 * Date: 10.04.2017
 * Time: 18:54
 */

namespace diCore\Entity\DynamicPic;

/**
 * Class Model
 * Methods list for IDE
 *
 * @method string	getTitle
 * @method string	getContent
 * @method string	getOrigFn
 * @method string	getPic
 * @method integer	getPicT
 * @method integer	getPicW
 * @method integer	getPicH
 * @method string	getPicTn
 * @method integer	getPicTnT
 * @method integer	getPicTnW
 * @method integer	getPicTnH
 * @method integer	getPicTn2T
 * @method integer	getPicTn2W
 * @method integer	getPicTn2H
 * @method string	getDate
 * @method integer	getByDefault
 * @method integer	getVisible
 * @method integer	getOrderNum
 *
 * @method bool hasTitle
 * @method bool hasContent
 * @method bool hasOrigFn
 * @method bool hasPic
 * @method bool hasPicT
 * @method bool hasPicW
 * @method bool hasPicH
 * @method bool hasPicTn
 * @method bool hasPicTnT
 * @method bool hasPicTnW
 * @method bool hasPicTnH
 * @method bool hasPicTn2T
 * @method bool hasPicTn2W
 * @method bool hasPicTn2H
 * @method bool hasDate
 * @method bool hasByDefault
 * @method bool hasVisible
 * @method bool hasOrderNum
 *
 * @method $this setTitle($value)
 * @method $this setContent($value)
 * @method $this setOrigFn($value)
 * @method $this setPic($value)
 * @method $this setPicT($value)
 * @method $this setPicW($value)
 * @method $this setPicH($value)
 * @method $this setPicTn($value)
 * @method $this setPicTnT($value)
 * @method $this setPicTnW($value)
 * @method $this setPicTnH($value)
 * @method $this setPicTn2T($value)
 * @method $this setPicTn2W($value)
 * @method $this setPicTn2H($value)
 * @method $this setDate($value)
 * @method $this setByDefault($value)
 * @method $this setVisible($value)
 * @method $this setOrderNum($value)
 */
class Model extends \diModel
{
    const type = \diTypes::dynamic_pic;
    protected $table = 'dipics';

    /**
     * @return string
     */
    public function getTargetTable()
    {
        return $this->get('_table');
    }

    /**
     * @return string
     */
    public function getTargetField()
    {
        return $this->get('_field');
    }

    /**
     * @return integer
     */
    public function getTargetId()
    {
        return $this->get('_id');
    }

    /**
     * @return bool
     */
    public function hasTargetTable()
    {
        return $this->has('_table');
    }

    /**
     * @return bool
     */
    public function hasTargetField()
    {
        return $this->has('_field');
    }

    /**
     * @return bool
     */
    public function hasTargetId()
    {
        return $this->has('_id');
    }

    /**
     * @param string $value
     * @return $this
     */
    public function setTargetTable($value)
    {
        return $this->set('_table', $value);
    }

    /**
     * @param string $value
     * @return $this
     */
    public function setTargetField($value)
    {
        return $this->set('_field', $value);
    }

    /**
     * @param integer $value
     * @return $this
     */
    public function setTargetId($value)
    {
        return $this->set('_id', $value);
    }

    public function getPicsFolder()
    {
        return get_pics_folder(
            $this->getRelated('table') ?:
            $this->getTargetTable() ?:
            $this->getTable()
        );
    }

    public static function getDynamicPicVars(
        \diModel $model,
        $relatedTable = null
    ) {
        $ar = [];

        $pic = $model->getRelated('pic') ?: $model->get('pic');

        if ($pic) {
            $folder = get_pics_folder($relatedTable ?: $model->getTable());

            $ar = [
                'pic' => $folder . $pic,
                'pic_tn' => $folder . get_tn_folder() . $pic,
                'pic_tn2' => $folder . get_tn_folder(2) . $pic,
                'pic_tn3' => $folder . get_tn_folder(3) . $pic,
                'pic_big' => $folder . get_big_folder() . $pic,
                'pic_orig' => $folder . get_orig_folder() . $pic,
            ];
        }

        return $ar;
    }

    public function getTemplateVars()
    {
        /*
		if (!$this->getRelated("table"))
		{
			throw new Exception("Related table not set");
		}
		*/

        $ar = array_merge(
            parent::getTemplateVars(),
            static::getDynamicPicVars(
                $this,
                $this->getRelated('table') ?: $this->getTargetTable()
            )
        );

        return $ar;
    }
}
