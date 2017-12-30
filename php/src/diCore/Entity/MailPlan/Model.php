<?php
/**
 * Created by \diModelsManager
 * Date: 11.12.2017
 * Time: 15:59
 */

namespace diCore\Entity\MailPlan;

/**
 * Class Model
 * Methods list for IDE
 *
 * @method integer	getTargetType
 * @method integer	getTargetId
 * @method integer	getMode
 * @method string	getConditions
 * @method string	getCreatedAt
 * @method string	getStartedAt
 * @method string	getProcessedAt
 *
 * @method bool hasTargetType
 * @method bool hasTargetId
 * @method bool hasMode
 * @method bool hasConditions
 * @method bool hasCreatedAt
 * @method bool hasStartedAt
 * @method bool hasProcessedAt
 *
 * @method Model setTargetType($value)
 * @method Model setTargetId($value)
 * @method Model setMode($value)
 * @method Model setConditions($value)
 * @method Model setCreatedAt($value)
 * @method Model setStartedAt($value)
 * @method Model setProcessedAt($value)
 */
abstract class Model extends \diModel
{
	const type = \diTypes::mail_plan;
	protected $table = 'mail_plans';
	protected $sentMailsCount = 0;

	protected $customDateFields = [
		'started_at',
		'processed_at',
	];

	public function getModeName()
	{
		return Mode::name($this->getMode());
	}

	public function getModeTitle()
	{
		return Mode::title($this->getMode());
	}

	public function getCustomTemplateVars()
	{
		return extend(parent::getCustomTemplateVars(), [
			'mode_name' => $this->getModeName(),
			'mode_title' => $this->getModeTitle(),
		]);
	}

	protected function beforeProcess()
	{
		$this
			->setStartedAt(\diDateTime::format(\diDateTime::FORMAT_SQL_DATE_TIME))
			->save();

		return $this;
	}

	protected function afterProcess()
	{
		$this
			->setProcessedAt(\diDateTime::format(\diDateTime::FORMAT_SQL_DATE_TIME))
			->save();

		return $this;
	}

	/**
	 * @return $this
	 */
	abstract protected function mainProcess();

	public function process()
	{
		return $this
			->beforeProcess()
			->mainProcess()
			->afterProcess();
	}

	public function getSentMailsCount()
	{
		return $this->sentMailsCount;
	}
}