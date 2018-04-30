<?php
/**
 * Created by diModelsManager
 * Date: 01.10.2015
 * Time: 00:20
 */

namespace diCore\Entity\Feedback;

/**
 * Class Model
 * Methods list for IDE
 *
 * @method integer	getUserId
 * @method string	getName
 * @method string	getEmail
 * @method string	getPhone
 * @method string	getContent
 * @method integer	getIp
 * @method string	getDate
 *
 * @method bool hasUserId
 * @method bool hasName
 * @method bool hasEmail
 * @method bool hasPhone
 * @method bool hasContent
 * @method bool hasIp
 * @method bool hasDate
 *
 * @method Model setUserId($value)
 * @method Model setName($value)
 * @method Model setEmail($value)
 * @method Model setPhone($value)
 * @method Model setContent($value)
 * @method Model setIp($value)
 * @method Model setDate($value)
 */
class Model extends \diModel
{
	const type = \diTypes::feedback;
	const table = 'feedback';
	protected $table = 'feedback';

	public function validate()
	{
		if (!$this->hasContent())
		{
			$this->addValidationError('Content required');
		}

		return parent::validate();
	}
}