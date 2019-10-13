<?php
/**
 * Created by diModelsManager
 * Date: 02.07.2015
 * Time: 15:40
 */

namespace diCore\Entity\Tag;

/**
 * Class Model
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
 * @method Model setSlugSource($value)
 * @method Model setTitle($value)
 * @method Model setContent($value)
 * @method Model setWeight($value)
 * @method Model setPic($value)
 * @method Model setHtmlTitle($value)
 * @method Model setHtmlKeywords($value)
 * @method Model setHtmlDescription($value)
 * @method Model setVisible($value)
 * @method Model setDate($value)
 */
class Model extends \diModel
{
	const type = \diTypes::tag;
	const slug_field_name = self::SLUG_FIELD_NAME;
	protected $table = 'tags';

	public function prepareForSave()
	{
		if (!$this->hasSlug()) {
			$this->generateSlug();
		}

		return $this;
	}

	public function validate()
	{
		if (!$this->getTitle()) {
			$this->addValidationError('Title required');
		}

		if (!$this->getSlug()) {
			$this->addValidationError('Slug required');
		}

		return parent::validate();
	}
}