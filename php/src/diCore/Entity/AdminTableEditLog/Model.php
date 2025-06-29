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

    /** @var \diModel */
    protected $target;

    const MAX_UNCUT_LENGTH = 0;

    private $dataParsed = false;

    private $useAllFields = false;

    // skip these fields in every table
    protected static $globalSkipFields = ['created_at', 'updated_at'];

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

    // if true, then skip
    public static function isGlobalFieldSkipped(\diModel $model, $field)
    {
        return in_array($field, static::$globalSkipFields);
    }

    public static function createForModel(
        \diModel $m,
        $adminId = 0,
        $useAllFields = true
    ) {
        $log = static::create(static::type);

        $log->setUseAllFields($useAllFields)
            ->setTargetTable($m->getTable())
            ->setTargetId($m->getId())
            ->setAdminId($adminId)
            ->setBothData($m);

        return $log;
    }

    public static function createForCollection(
        \diCollection|array $col,
        $adminId = 0,
        $useAllFields = true,
        $save = true
    ) {
        $logs = [];

        foreach ($col as $m) {
            $log = static::createForModel($m, $adminId, $useAllFields);

            if ($save) {
                $log->save();
            }

            $logs[] = $log;
        }

        return $logs;
    }

    public static function adminTabTitle($lang)
    {
        return static::$lang[$lang]['admin_tab_title'];
    }

    public function getTargetAdminHref()
    {
        return "/_admin/{$this->getTargetTable()}/form/{$this->getTargetId()}/";
    }

    public function setUseAllFields($state)
    {
        $this->useAllFields = $state;

        return $this;
    }

    public static function htmlEntitiesToUtf8($str)
    {
        /* old approach
        $enc = 'HTML-ENTITIES';
        $origEnc = 'UTF-8';

        return mb_convert_encoding($str, $enc, $origEnc);
        */

        return mb_encode_numericentity(
            htmlspecialchars_decode(
                htmlentities($str, ENT_NOQUOTES, 'UTF-8', false),
                ENT_NOQUOTES
            ),
            [0x80, 0x10ffff, 0, ~0],
            'UTF-8'
        );
    }

    public function parseData($maxUncutLength = null)
    {
        if ($this->dataParsed) {
            return $this;
        }

        if ($maxUncutLength === null) {
            $maxUncutLength = static::MAX_UNCUT_LENGTH;
        }

        $this->setOldValues(unserialize($this->getOldData()) ?: [])->setNewValues(
            unserialize($this->getNewData()) ?: []
        );

        $newData = $this->getNewValues();
        $diffs = [];

        foreach ($this->getOldValues() as $field => $oldValue) {
            $oldValue = $this->normalizeValue($oldValue ?? '');
            $newValue = $this->normalizeValue($newData[$field] ?? '');

            if (!is_scalar($newValue) || !is_scalar($oldValue)) {
                continue;
            }

            $origEnc = 'UTF-8';
            $enc = 'HTML-ENTITIES';
            $oldValue = static::htmlEntitiesToUtf8($oldValue);
            $newValue = static::htmlEntitiesToUtf8($newValue);

            $diff = new \FineDiff($oldValue, $newValue, \FineDiff::$wordGranularity);
            $diffs[$field] = html_entity_decode(
                mb_convert_encoding($diff->renderDiffToHTML(), $origEnc, $enc)
            );

            if ($maxUncutLength && mb_strlen($diffs[$field]) > $maxUncutLength) {
                preg_match_all(
                    '#<\s*?(ins|del)\b[^>]*>(.*?)</(ins|del)\b[^>]*>#s',
                    $diffs[$field],
                    $matches
                );

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

        $skipFields = $globalSkipFields[$this->getTargetTable()] ?? [];

        if ($skipFields) {
            $ar = ArrayHelper::filterByKey($ar ?: [], [], $skipFields);

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

        if ($field === null) {
            return $a;
        }

        return $this->normalizeValue($a[$field] ?? null);
    }

    public function getNewValues($field = null)
    {
        $a = $this->getRelated('new');

        if ($field === null) {
            return $a;
        }

        return $this->normalizeValue($a[$field] ?? null);
    }

    protected function normalizeValue($v)
    {
        if (is_array($v) || is_object($v)) {
            return json_encode($v);
        }

        return $v;
    }

    public function getDataDiff($field = null)
    {
        $a = $this->getRelated('diff');

        if ($field === null) {
            return $a;
        }

        return $a[$field] ?? null;
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

        if (static::isGlobalFieldSkipped($model, $field)) {
            return true;
        }

        if ($this->useAllFields) {
            return false;
        }

        $formFields = $this->getRelated('formFields') ?: [];

        if (
            isset($formFields[$field]) &&
            (!isset($formFields[$field]['flags']) ||
                !in_array('virtual', $formFields[$field]['flags']))
        ) {
            return false;
        }

        return true;
    }

    public function setFormFields(array $fields)
    {
        return $this->setRelated('formFields', $fields);
    }

    public function setBothData(\diModel $model, \diModel|null $oldModel = null)
    {
        if ($oldModel) {
            $model = clone $model;
            $model->setOrigData($oldModel->get());
        }

        $fields = $model->changedFields(['id']);
        $old = $new = [];

        $hasRealChanges = function ($field) use ($model, $old, $new) {
            if (
                $model::isJsonField($field) &&
                static::normalizeJson($old[$field]) ===
                    static::normalizeJson($new[$field])
            ) {
                return false;
            }

            if (
                !empty($old[$field]) &&
                !empty($new[$field]) &&
                is_string($old[$field]) &&
                is_string($new[$field]) &&
                trim($old[$field]) === trim($new[$field])
            ) {
                return false;
            }

            return true;
        };

        foreach ($fields as $field) {
            if ($this->isFieldSkipped($model, $field)) {
                continue;
            }

            $old[$field] = $model->getOrigData($field);
            $new[$field] = $model->get($field);

            if (!$hasRealChanges($field)) {
                unset($old[$field]);
                unset($new[$field]);
            }
        }

        $old = $model->processFieldsOnSave($old);
        $new = $model->processFieldsOnSave($new);

        if ($old && $new) {
            $this->setOldData(serialize($old))->setNewData(serialize($new));
        }

        return $this;
    }

    protected static function normalizeJson($json)
    {
        $decoded = $json && is_string($json) ? json_decode("$json", true) : $json;

        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return json_encode(
            $decoded,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }

    public function validate()
    {
        if (!$this->hasTargetTable()) {
            $this->addValidationError('Table required', 'target_table');
        }

        if (!$this->hasTargetId()) {
            $this->addValidationError('Id required', 'target_id');
        }

        if (!$this->hasAdminId()) {
            $this->addValidationError('Admin required', 'admin_id');
        }

        if (!$this->hasOldData()) {
            $this->addValidationError('Old data required', 'old_data');
        }

        if (!$this->hasNewData()) {
            $this->addValidationError('New data required', 'new_data');
        }

        return parent::validate();
    }

    public function getAppearanceFeedForAdmin()
    {
        return [$this->getTargetTable(), $this->getTargetId()];
    }

    public function getTarget()
    {
        if (!$this->target) {
            $this->target = \diModel::createForTable(
                $this->getTargetTable(),
                $this->getTargetId(),
                'id'
            );
        }

        return $this->target;
    }
}
