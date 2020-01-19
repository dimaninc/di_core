<?php
/**
 * Created by diModelsManager
 * Date: 29.07.2015
 * Time: 11:52
 */
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
 * @method diSlugModel setFullSlug($value)
 * @method diSlugModel setLevelNum($value)
 */
class diSlugModel extends \diModel
{
    use \diCore\Traits\Model\TargetInside;

	const type = \diTypes::slug;
	const slug_field_name = self::SLUG_FIELD_NAME;
	protected $table = "slugs";

	public function validate()
	{
		if (!$this->getTargetType() || !$this->getTargetId())
		{
			$this->addValidationError("Target required");
		}

		if (!$this->getSlug())
		{
			$this->addValidationError("Slug required");
		}

		if (!$this->exists("level_num"))
		{
			$this->addValidationError("Level Num required");
		}

		return parent::validate();
	}

	public function getTargetModel()
	{
		return diModel::create(diTypes::getName($this->getTargetType()), $this->getTargetId(), "id");
	}

	/**
	 * @param diModel|int $targetType
	 * @param int|null $targetId
	 * @return diModel
	 * @throws Exception
	 */
	public static function createByTarget($targetType, $targetId = null)
	{
		if ($targetType instanceof diModel && $targetId === null)
		{
			$targetId = $targetType->getId();
			$targetType = diTypes::getId($targetType->getTable());
		}

        /** @var \diSlugCollection $col */
        $col = \diCollection::create(static::type);

        return $col
            ->filterByTargetType($targetType)
            ->filterByTargetId($targetId)
            ->getFirstItem();
	}
}