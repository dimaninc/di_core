<?php
/**
 * Created by diModelsManager
 * Date: 01.10.2015
 * Time: 00:20
 */
/**
 * Class diFeedbackModel
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
 * @method diFeedbackModel setUserId($value)
 * @method diFeedbackModel setName($value)
 * @method diFeedbackModel setEmail($value)
 * @method diFeedbackModel setPhone($value)
 * @method diFeedbackModel setContent($value)
 * @method diFeedbackModel setIp($value)
 * @method diFeedbackModel setDate($value)
 */
class diFeedbackModel extends diModel
{
	const type = diTypes::feedback;
	protected $table = "feedback";

	public function validate()
	{
		if (!$this->hasContent())
		{
			$this->addValidationError("Content required");
		}

		return parent::validate();
	}
}