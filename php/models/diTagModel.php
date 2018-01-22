<?php
/**
 * Created by diModelsManager
 * Date: 02.07.2015
 * Time: 15:40
 */
/**
 * Class diTagModel
 * Methods list for IDE
 *
 * @method string	getSlugSource
 * @method string	getTitle
 * @method string	getContent
 * @method integer	getWeight
 * @method string	getPic
 * @method string	getHtmlTitle
 * @method string	getHtmlKeywords
 * @method string	getHtmlDescription
 * @method integer	getVisible
 * @method string	getDate
 *
 * @method bool hasSlugSource
 * @method bool hasTitle
 * @method bool hasContent
 * @method bool hasWeight
 * @method bool hasPic
 * @method bool hasHtmlTitle
 * @method bool hasHtmlKeywords
 * @method bool hasHtmlDescription
 * @method bool hasVisible
 * @method bool hasDate
 *
 * @method diTagModel setSlugSource($value)
 * @method diTagModel setTitle($value)
 * @method diTagModel setContent($value)
 * @method diTagModel setWeight($value)
 * @method diTagModel setPic($value)
 * @method diTagModel setHtmlTitle($value)
 * @method diTagModel setHtmlKeywords($value)
 * @method diTagModel setHtmlDescription($value)
 * @method diTagModel setVisible($value)
 * @method diTagModel setDate($value)
 */
class diTagModel extends \diModel
{
	const type = \diTypes::tag;
	const slug_field_name = self::SLUG_FIELD_NAME;
	protected $table = "tags";

	public function prepareForSave()
	{
		if (!$this->hasSlug())
		{
			$this->generateSlug();
		}

		return $this;
	}

	public function validate()
	{
		if (!$this->getTitle())
		{
			$this->addValidationError("Title required");
		}

		if (!$this->getSlug())
		{
			$this->addValidationError("Slug required");
		}

		return parent::validate();
	}
}