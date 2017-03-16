<?php
/**
 * Created by diModelsManager
 * Date: 02.06.2016
 * Time: 17:58
 */

/**
 * Class diAdminTableEditLogModel
 * Methods list for IDE
 *
 * @method string	getTargetTable
 * @method string	getTargetId
 * @method integer	getAdminId
 * @method string	getOldData
 * @method string	getNewData
 * @method string	getCreatedAt
 *
 * @method bool hasTargetTable
 * @method bool hasTargetId
 * @method bool hasAdminId
 * @method bool hasOldData
 * @method bool hasNewData
 * @method bool hasCreatedAt
 *
 * @method diAdminTableEditLogModel setTargetTable($value)
 * @method diAdminTableEditLogModel setTargetId($value)
 * @method diAdminTableEditLogModel setAdminId($value)
 * @method diAdminTableEditLogModel setOldData($value)
 * @method diAdminTableEditLogModel setNewData($value)
 * @method diAdminTableEditLogModel setCreatedAt($value)
 */
class diAdminTableEditLogModel extends diModel
{
	const ADMIN_TAB_NAME = 'admin_edit_log';
	const ADMIN_TAB_TITLE = 'История изменений';

	const type = diTypes::admin_table_edit_log;
	protected $table = "admin_table_edit_log";

	private $dataParsed = false;

	protected static $skipFields = [
		//'table' => ['field1', 'field2'],
	];

	public function getTargetAdminHref()
	{
		return '/_admin/' . $this->getTargetTable() . '/form/' . $this->getTargetId() . '/';
	}

	public function parseData()
	{
		if ($this->dataParsed)
		{
			return $this;
		}

		$this
			->setOldValues(unserialize($this->getOldData()))
			->setNewValues(unserialize($this->getNewData()));

		$newData = $this->getNewValues();
		$diffs = [];

		foreach ($this->getOldValues() as $field => $oldValue)
		{
			$newValue = $newData[$field];

			$diff = new FineDiff($oldValue, $newValue, FineDiff::$characterGranularity);
			$diffs[$field] = $diff->renderDiffToHTML();;
		}

		$this->setDataDiff($diffs);

		$this->dataParsed = true;

		return $this;
	}

	protected function filterValuesAr($ar, $globalSkipFields = [])
	{
		$globalSkipFields = $globalSkipFields ?: static::$skipFields;

		$skipFields = isset($globalSkipFields[$this->getTargetTable()])
			? $globalSkipFields[$this->getTargetTable()]
			: [];

		if ($skipFields)
		{
			$ar = \diCore\Helper\ArrayHelper::filterByKey($ar, [], $skipFields);

			// skipping all fields
			if (in_array('*', $skipFields))
			{
				$ar = [];
			}
		}

		return $ar;
	}

	protected function setOldValues($ar)
	{
		$ar = $this->filterValuesAr($ar);

		return $this->setRelated('old', $ar);
	}

	protected function setNewValues($ar)
	{
		$ar = $this->filterValuesAr($ar);

		return $this->setRelated('new', $ar);
	}

	protected function setDataDiff($ar)
	{
		$ar = $this->filterValuesAr($ar);

		return $this->setRelated('diff', $ar);
	}

	public function getOldValues($field = null)
	{
		$a = $this->getRelated('old');

		return $field === null
			? $a
			: (isset($a[$field]) ? $a[$field] : null);
	}

	public function getNewValues($field = null)
	{
		$a = $this->getRelated('new');

		return $field === null
			? $a
			: (isset($a[$field]) ? $a[$field] : null);
	}

	public function getDataDiff($field = null)
	{
		$a = $this->getRelated('diff');

		return $field === null
			? $a
			: (isset($a[$field]) ? $a[$field] : null);
	}

	protected function isFieldSkipped($field)
	{
		$skipFields = isset(static::$skipFields[$this->getTargetTable()])
			? static::$skipFields[$this->getTargetTable()]
			: [];

		if (in_array($field, $skipFields) || in_array('*', $skipFields))
		{
			return true;
		}

		$formFields = $this->getRelated('formFields') ?: [];

		if (
			isset($formFields[$field]) &&
		    (!isset($formFields[$field]['flags']) || !in_array('virtual', $formFields[$field]['flags']))
		   )
		{
			return false;
		}

		return true;
	}

	public function setBothData(diModel $model, diModel $oldModel = null)
	{
		if ($oldModel)
		{
			$model = clone $model;
			$model->setOrigData($oldModel->get());
		}

		$fields = $model->changedFields(['id']);
		$old = $new = [];

		foreach ($fields as $field)
		{
			if ($this->isFieldSkipped($field))
			{
				continue;
			}

			$old[$field] = $model->getOrigData($field);
			$new[$field] = $model->get($field);
		}

		if ($old && $new)
		{
			$this
				->setOldData(serialize($old))
				->setNewData(serialize($new));
		}

		return $this;
	}

	public function validate()
	{
		if (!$this->hasTargetTable())
		{
			$this->addValidationError("Table required");
		}

		if (!$this->hasTargetId())
		{
			$this->addValidationError("Id required");
		}

		if (!$this->hasAdminId())
		{
			$this->addValidationError("Admin required");
		}

		if (!$this->hasOldData())
		{
			$this->addValidationError("Old data required");
		}

		if (!$this->hasNewData())
		{
			$this->addValidationError("New data required");
		}

		return parent::validate();
	}
}