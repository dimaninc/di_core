<?php
/**
 * Created by diModelsManager
 * Date: 02.06.2016
 * Time: 17:58
 */

namespace diCore\Entity\AdminTableEditLog;

use diCore\Database\FieldType;
use diCore\Helper\ArrayHelper;

/**
 * Class Model
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
 * @method $this setTargetTable($value)
 * @method $this setTargetId($value)
 * @method $this setAdminId($value)
 * @method $this setOldData($value)
 * @method $this setNewData($value)
 * @method $this setCreatedAt($value)
 */
class Model extends \diModel
{
	const ADMIN_TAB_NAME = 'admin_edit_log';
	protected static $lang = [
	    'ru' => [
	        'admin_tab_title' => 'История изменений',
        ],
        'en' => [
            'admin_tab_title' => 'Changes log',
        ],
    ];

	const type = \diTypes::admin_table_edit_log;
    const table = 'admin_table_edit_log';
	protected $table = 'admin_table_edit_log';

	const MAX_UNCUT_LENGTH = 0;

	private $dataParsed = false;

	private $useAllFields = false;

	protected static $skipFields = [
		//'table' => ['field1', 'field2'],
	];

    protected static $fieldTypes = [
        'target_table' => FieldType::string,
        'target_id' => FieldType::string,
        'admin_id' => FieldType::int,
        'old_data' => FieldType::string,
        'new_data' => FieldType::string,
        'created_at' => FieldType::timestamp,
    ];

    // if true, then skip
    public static function isModelFieldSkipped(\diModel $model, $field)
    {
        return false;
    }

	public static function createForModel(\diModel $m, $adminId = 0, $useAllFields = true)
    {
        $log = static::create(static::type);

        $log
            ->setUseAllFields($useAllFields)
            ->setTargetTable($m->getTable())
            ->setTargetId($m->getId())
            ->setAdminId($adminId)
            ->setBothData($m);

        return $log;
    }

	public static function adminTabTitle($lang)
    {
        return static::$lang[$lang]['admin_tab_title'];
    }

	public function getTargetAdminHref()
	{
		return '/_admin/' . $this->getTargetTable() . '/form/' . $this->getTargetId() . '/';
	}

	public function setUseAllFields($state)
    {
        $this->useAllFields = $state;

        return $this;
    }

	public function parseData($maxUncutLength = null)
	{
		if ($this->dataParsed) {
			return $this;
		}

		if ($maxUncutLength === null) {
            $maxUncutLength = static::MAX_UNCUT_LENGTH;
        }

		$this
			->setOldValues(unserialize($this->getOldData()))
			->setNewValues(unserialize($this->getNewData()));

		$newData = $this->getNewValues();
		$diffs = [];

		foreach ($this->getOldValues() as $field => $oldValue) {
			$newValue = $newData[$field];

			$origEnc = 'UTF-8';
			$enc = 'HTML-ENTITIES';
			$oldValue = mb_convert_encoding($oldValue, $enc, $origEnc);
			$newValue = mb_convert_encoding($newValue, $enc, $origEnc);

			$diff = new \FineDiff($oldValue, $newValue, \FineDiff::$wordGranularity);
			$diffs[$field] = html_entity_decode(mb_convert_encoding($diff->renderDiffToHTML(), $origEnc, $enc));

			if ($maxUncutLength && mb_strlen($diffs[$field]) > $maxUncutLength) {
			    preg_match_all('#<\s*?(ins|del)\b[^>]*>(.*?)</(ins|del)\b[^>]*>#s', $diffs[$field], $matches);

                $diffs[$field] = join("\n", $matches[0]);
            }
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

		if ($skipFields) {
			$ar = ArrayHelper::filterByKey($ar, [], $skipFields);

			// skipping all fields
			if (in_array('*', $skipFields)) {
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

	protected function isFieldSkipped(\diModel $model, $field)
	{
		$skipFields = static::$skipFields[$this->getTargetTable()] ?? [];

		if (in_array($field, $skipFields) || in_array('*', $skipFields)) {
			return true;
		}

		if (static::isModelFieldSkipped($model, $field)) {
		    return true;
        }

		if ($this->useAllFields) {
		    return false;
        }

		$formFields = $this->getRelated('formFields') ?: [];

		if (
			isset($formFields[$field]) &&
		    (
		        !isset($formFields[$field]['flags']) ||
                !in_array('virtual', $formFields[$field]['flags'])
            )
        ) {
			return false;
		}

		return true;
	}

	public function setBothData(\diModel $model, \diModel $oldModel = null)
	{
		if ($oldModel) {
			$model = clone $model;
			$model->setOrigData($oldModel->get());
		}

		$fields = $model->changedFields(['id']);
		$old = $new = [];

        foreach ($fields as $field) {
			if ($this->isFieldSkipped($model, $field)) {
				continue;
			}

            $old[$field] = $model->getOrigData($field);
			$new[$field] = $model->get($field);
		}

		$old = $model->processFieldsOnSave($old);
        $new = $model->processFieldsOnSave($new);

		if ($old && $new) {
			$this
				->setOldData(serialize($old))
				->setNewData(serialize($new));
		}

		return $this;
	}

	public function validate()
	{
		if (!$this->hasTargetTable()) {
			$this->addValidationError('Table required');
		}

		if (!$this->hasTargetId()) {
			$this->addValidationError('Id required');
		}

		if (!$this->hasAdminId()) {
			$this->addValidationError('Admin required');
		}

		if (!$this->hasOldData()) {
			$this->addValidationError('Old data required');
		}

		if (!$this->hasNewData()) {
			$this->addValidationError('New data required');
		}

		return parent::validate();
	}
}