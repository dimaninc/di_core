<?php
/**
 * Created by diModelsManager
 * Date: 29.07.2015
 * Time: 11:52
 */

namespace diCore\Entity\Slug;

use diCore\Traits\Model\TargetInside;

/**
 * Class diSlugModel
 * Methods list for IDE
 *
 * @method string	getFullSlug
 * @method integer	getLevelNum
 *
 * @method bool hasFullSlug
 * @method bool hasLevelNum
 *
 * @method $this setFullSlug($value)
 * @method $this setLevelNum($value)
 */
class Model extends \diModel
{
    use TargetInside;

    const type = \diTypes::slug;
    const slug_field_name = self::SLUG_FIELD_NAME;
    protected $table = 'slugs';

    public function getHref()
    {
        return '/' . $this->getFullSlug() . '/';
    }

    public function validate()
    {
        if (!$this->getTargetType() || !$this->getTargetId()) {
            $this->addValidationError('Target required', 'target_id');
        }

        if (!$this->getSlug()) {
            $this->addValidationError('Slug required', 'slug');
        }

        if (!$this->exists('level_num')) {
            $this->addValidationError('Level num required', 'level_num');
        }

        return parent::validate();
    }
}