<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 08.06.2017
 * Time: 17:08
 */

namespace diCore\Admin;

use diCore\Admin\Data\Skin;
use diCore\Data\Config;
use diCore\Entity\AdminTableEditLog\Model as TableEditLog;
use diCore\Helper\ArrayHelper;
use diCore\Helper\StringHelper;
use diCore\Helper\FileSystemHelper;
use diCore\Tool\Font\Helper;

class Form
{
	/** @var \diDB */
	private $db;

	/** @var \diCore\Admin\BasePage */
	private $AdminPage;

	const wysiwygCK = 1;
	const wysiwygTinyMCE = 2;
	const wysiwygNone = 3;

	const NEW_FIELD_SUFFIX = '__new__';

	public static $wysiwygAliases = [
		self::wysiwygCK => 'ck',
		self::wysiwygTinyMCE => 'tinymce',
		self::wysiwygNone => null,
	];

	public static $lngStrings = [
		'en' => [
			'notes_caption' => [
				false => 'Note',
				true => 'Notes',
			],

			'view_help' => 'View help',
			'save' => 'Save',
			'clone' => 'Save as a new record',
			'cancel' => 'Cancel',
			'quick_save' => 'Apply',
			'dispatch' => 'Save and dispatch',
			'dispatch_test' => 'Save and test dispatch',
			'edit' => 'Edit record',
			'calendar' => 'Calendar',
			'submit_and_add' => 'Save and add new item',
			'submit_and_next' => 'Save and go to next item',
			'submit_and_send' => 'Save and send via email',
			'delete' => 'Delete',
			'delete_pic_confirmation' => 'Delete the pic? Are you sure?',
			'delete_file_confirmation' => 'Delete the file? Are you sure?',
			'rotate_pic_confirmation' => 'Rotate the pic? Are you sure?',
			'rotate_pic.ccw' => 'Rotate pic 90° CCW',
			'rotate_pic.cw' => 'Rotate pic 90° CW',
			'watermark_pic_confirmation' => 'Watermark the pic? Are you sure?',
			'watermark_pic' => 'Watermark pic',

            'placeholder.date.day' => 'DD',
            'placeholder.date.month' => 'MM',
            'placeholder.date.year' => 'YY',
            'placeholder.time.hour' => 'HH',
            'placeholder.time.minute' => 'MM',

			'yes' => 'Yes',
			'no' => 'No',

			'confirm' => 'Confirm',
			'confirm_dispatch' => 'Dispatch this record to the subscribers? Are you sure?',
			'confirm_send' => 'Send the reply to email? Are you sure?',

			'or_enter' => 'or enter',
			'add_item' => 'Add',
			'link' => 'Link',
            'not_selected' => 'Not selected',

			'tab_general' => 'General',

            'choose_file' => 'Choose file...',

            'tag.enter_new' => 'Add new items, comma separated',
            'tag.toggle_on' => 'All on',
            'tag.toggle_off' => 'All off',
		],

		'ru' => [
			'notes_caption' => [
				false => 'Примечание',
				true => 'Примечания',
			],

			'view_help' => 'Помощь',
			'save' => 'Сохранить',
			'clone' => 'Сохранить как новую запись',
			'cancel' => 'Отмена',
			'quick_save' => 'Применить',
			'dispatch' => 'Сохранить и произвести рассылку',
			'dispatch_test' => 'Сохранить и произвести тестовую рассылку',
			'edit' => 'Редактировать',
			'calendar' => 'Календарь',
			'submit_and_add' => 'Сохранить и добавить новый товар',
			'submit_and_next' => 'Сохранить и перейти к следующей записи',
			'submit_and_send' => 'Сохранить и отправить письмо',
			'delete' => 'Удалить',
			'delete_pic_confirmation' => 'Удалить картинку? Вы уверены?',
			'delete_file_confirmation' => 'Удалить файл? Вы уверены?',
			'rotate_pic_confirmation' => 'Повернуть картинку? Вы уверены?',
			'rotate_pic.ccw' => 'Повернуть на 90° против часовой стрелки',
			'rotate_pic.cw' => 'Повернуть на 90° по часовой стрелке',
			'watermark_pic_confirmation' => 'Наложить водяной знак?',
			'watermark_pic' => 'Наложить водяной знак',

            'placeholder.date.day' => 'ДД',
            'placeholder.date.month' => 'ММ',
            'placeholder.date.year' => 'ГГГГ',
            'placeholder.time.hour' => 'ЧЧ',
            'placeholder.time.minute' => 'ММ',

			'yes' => 'Да',
			'no' => 'Нет',

			'confirm' => 'Подтвердите',
			'confirm_dispatch' => 'Пустить этот материал в рассылку подписчикам? Вы уверены?',
			'confirm_send' => 'Отправить ответ на почту? Вы уверены?',

			'or_enter' => 'или введите',
			'add_item' => 'Добавить',
			'link' => 'Ссылка',
            'not_selected' => 'Не выбрано',

            'tab_general' => 'Основное',

            'choose_file' => 'Выбрать файл...',

            'tag.enter_new' => 'Добавить новые, через запятую',
            'tag.toggle_on' => 'Выделить все',
            'tag.toggle_off' => 'Снять все',
		],
	];

	protected static $numericTypes = ['int', 'integer', 'float', 'double'];
	protected static $stringTypes = ['str', 'string', 'email', 'tel', 'url', 'varchar'];
    protected static $textTypes = ['text', 'wysiwyg'];

	private $wysiwygVendor = self::wysiwygTinyMCE;

	public $table;
	public $inputs = [];
	public $force_inputs_fields = []; // local fields having inputs
	protected $inputAttributes = [];
	public $uploaded_images = [];
	public $uploaded_images_w = [];
	public $uploaded_files = [];
	public $data = [];

	private $inputPrefixes = [];
	private $inputSuffixes = [];

	const INPUT_SUFFIX_NEW_FIELD = 1;

	/** @var \diModel */
	private $model;
	public $id;
	public $rec = null;
	public $static_mode = true;
	protected static $language = 'ru';
	public $show_help = false;
	public $module_id;	 // module_id of current table

	private $formFields = [];
	private $allFields = [];

	private $manualFieldFlags = [];

	private $pics_table = 'dipics';

	protected $submitButtonsOptions = [
		'show' => [],
		'show_additional' => [],
		'hide' => [],
	];

	public function __construct($table, $id = 0, $module_id = 0)
	{
		global $lite, $db;

		$this->db = $db;

		if (gettype($table) == 'object') {
			$this->AdminPage = $table;
			$this->table = $this->AdminPage->getTable();
			$this->id = $this->AdminPage->getId();
			self::$language = $this->AdminPage->getAdmin()->getLanguage();
		} else {
			$this->table = $table;
			$this->id = $id;
			$this->module_id = $module_id;
		}

		$this->lite = !empty($lite) ? $lite : 0;

		if (true || \diRequest::get('edit', 0) || !$id)
		{
			$this->static_mode = false;
		}

		if (!$this->AdminPage)
		{
			if (!isset($GLOBALS[$this->table . "_all_fields"]))
			{
				throw new \Exception("$" . $this->table . "_all_fields, etc. variables not defined");
			}

			$this->allFields = $GLOBALS[$this->table . "_all_fields"];
			$this->formFields = $GLOBALS[$this->table . "_form_fields"];
		}
    }

	public function afterInit($options = [])
    {
        $options = extend([
            'static_mode' => false,
            'read_data' => true,
        ], $options);

        $this
            ->setAutoInputAttributes()
            ->setStaticMode($options['static_mode']);

        if ($options['read_data']) {
            $this->read_data();
        }

        return $this;
    }

	private function setAutoInputAttributes()
	{
		foreach ($this->getAllFields() as $field => $v) {
			if ($this->getFieldProperty($field, "required")) {
				$this->setInputAttribute($field, ["required" => "required"]);
			}
		}

		return $this;
	}

	private function getAllFields()
	{
		$ar = $this->AdminPage
			? $this->AdminPage->getAllFields()
			: $this->allFields;

		$ar = $this->mergeManualFieldFlags($ar);

		return $ar;
	}

	private function getFormFields()
	{
		$ar = $this->AdminPage
			? $this->AdminPage->getFormFieldsFiltered()
			: $this->formFields;

		$ar = $this->mergeManualFieldFlags($ar);

		return $ar;
	}

	public function getFieldType($field)
	{
		return $this->getFieldProperty($field, 'type');
	}

	public function getFieldProperty($field = null, $property = null)
	{
		$a = $this->getAllFields();

		if ($field !== null && $property !== null && isset($a[$field][$property]))
		{
			return $a[$field][$property];
		}
		elseif ($field && $property === null && isset($a[$field]))
		{
			return $a[$field];
		}

		return null;
	}

	public static function L($token, $language = null)
	{
		$language = $language ?: self::$language;

		return isset(self::$lngStrings[$language][$token])
			? self::$lngStrings[$language][$token]
			: $token;
	}

	public function setStaticMode($state)
	{
		$this->static_mode = !!$state;

		return $this;
	}

	public function getCurRec()
	{
		return $this->rec;
	}

	/** @deprecated */
	function is_flag($fieldOrFlagsAr, $flag)
	{
		return $this->isFlag($fieldOrFlagsAr, $flag);
	}

	public function isFlag($fieldOrFlagsAr, $flag)
	{
		if (is_string($fieldOrFlagsAr) && $flags = $this->getFieldProperty($fieldOrFlagsAr, "flags"))
		{
		}
		elseif (is_array($fieldOrFlagsAr) && isset($fieldOrFlagsAr["flags"]))
		{
			$flags = $fieldOrFlagsAr["flags"];
		}
		else
		{
			$flags = [];
		}

		if (!is_array($flags))
		{
			$flags = [$flags];
		}

		return $flags && in_array($flag, $flags);
	}

	public function setWysiwygVendor($vendor)
	{
		$this->wysiwygVendor = $vendor;
	}

	public function getWysiwygVendor($mode = "int")
	{
		if ($this->AdminPage)
		{
			$this->wysiwygVendor = $this->AdminPage->getAdmin()->getWysiwygVendor();
		}

		return $mode == "string" ? self::getWysiwygAlias($this->wysiwygVendor) : $this->wysiwygVendor;
	}

	public static function getWysiwygAlias($id)
	{
		return self::$wysiwygAliases[$id];
	}

	/** @deprecated */
	public function is_static($field)
	{
		return $this->isStatic($field);
	}

	public function isStatic($field)
	{
		return $this->static_mode || $this->isFlag($field, "static");
	}

	/** @deprecated */
	public static function is_button_shown($id, $show_ar = [], $hide_ar = [])
	{
		return static::isButtonShown($id, $show_ar, $hide_ar);
	}

	public static function isButtonShown($id, $show_ar = [], $hide_ar = [])
	{
		return (!$show_ar || in_array($id, $show_ar)) && !in_array($id, $hide_ar);
	}

	public function processData($field, $callback)
	{
		$this->setData($field, $callback($this->getData($field), $field));

		return $this;
	}

	public function hasField($field)
	{
		return !!$this->getFieldProperty($field);
	}

	public function setData($field, $value = null)
	{
		if (is_array($field))
		{
			$this->data = extend($this->data, $field);

			if ($value === true)
			{
				$this->getModel()
					->set($field);
			}
		}
		else
		{
			$this->data[$field] = $value;
		}

		return $this;
	}

	public function getData($field = null)
	{
		return $field === null
			? $this->data
			: isset($this->data[$field]) ? $this->data[$field] : null;
	}

	public function getDb()
	{
		return $this->db;
	}

	public function getId()
	{
		return $this->id;
	}

	public function getTable()
	{
		return $this->table;
	}

	public function getX()
	{
		return $this->AdminPage;
	}

	public function getTpl()
	{
		return $this->getX()->getTpl();
	}

	public function getModel()
	{
	    if (!$this->model || !$this->model->exists()) {
	        $this->model = \diModel::createForTableNoStrict($this->getTable(), $this->getId(), 'id');
        }

		return $this->model;
	}

	private function processDefaultValue($field)
	{
		if (strtoupper($this->getData($field)) == "NOW()")
		{
			switch ($this->getFieldProperty($field, "type"))
			{
				case "date_str":
					$this->setData($field, \diDateTime::format("Y-m-d"));
					break;

				case "time_str":
					$this->setData($field, \diDateTime::format("H:i:s"));
					break;

				case "datetime_str":
					$this->setData($field, \diDateTime::format("Y-m-d H:i:s"));
					break;
			}
		}

		return $this;
	}

	protected function getFieldDefaultValue($field)
	{
		return $this->getFieldProperty($field, 'default');
	}

	function read_data()
	{
		if ($this->getId()) {
			if ($this->getModel()->exists()) {
				$this->rec = (object)$this->getModel()->get();
			} else {
				$this->rec = $this->getDb()->r($this->getTable(), $this->getId());
			}

			if ($this->rec) {
				foreach ($this->getAllFields() as $k => $v) {
					if (isset($this->rec->$k)) {
						$this->data[$k] = $this->rec->$k;
					} elseif ($this->isFlag($v, 'virtual')) {
						$this->data[$k] = $this->getFieldDefaultValue($k);

						$this->processDefaultValue($k);
					} else {
						$this->data[$k] = '';
					}
				}
			} else {
				throw new \Exception("There's no such record ($this->table#'$this->id')");
			}
		} else {
			foreach ($this->getAllFields() as $k => $v) {
				$this->data[$k] = \diRequest::get($k, $this->getFieldDefaultValue($k));

				$this->processDefaultValue($k);
			}
		}

		$this->getModel()
            ->set(extend($this->rec, $this->data));

		if ($this->id) {
			$this->getModel()
                ->setId($this->id);
		}

		return $this;
	}

	/**
	 * @param array $options
	 * @return $this
	 */
	public function setSubmitButtonsOptions($options)
	{
		$this->submitButtonsOptions = $options;

		return $this;
	}

	public function get_submit_buttons($buttons_ar = [], $prefix_div = '', $suffix_div = '')
	{
		return $this->getSubmitButtons($buttons_ar, $prefix_div, $suffix_div);
	}

    protected function getButtonIcon($name)
    {
        switch (Config::getAdminSkin()) {
            case Skin::entrine:
                $icons = [
                    'save' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path d="M16 10.975v13.025l-6-5.269-6 5.269v-24h6.816c-.553.576-1.004 1.251-1.316 2h-3.5v17.582l4-3.512 4 3.512v-8.763c.805.19 1.379.203 2 .156zm-.5-10.975c-2.486 0-4.5 2.015-4.5 4.5s2.014 4.5 4.5 4.5c2.484 0 4.5-2.015 4.5-4.5s-2.016-4.5-4.5-4.5zm-.469 6.484l-1.688-1.637.695-.697.992.94 2.115-2.169.697.696-2.811 2.867z"/></svg>',
                    'quick_save' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path d="M13.938 2c2.232 0 4.055 1.816 4.062 4.042v13.54l-4-3.512-4 3.512v-12.993c0-2.464-.28-3.333-.858-4.589h4.796zm0-2h-9.938c2.834 1.042 4 3.042 4 6.589v17.411l6-5.269 6 5.269v-17.958c-.011-3.341-2.723-6.042-6.062-6.042z"/></svg>',
                    'cancel' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path d="M16 10.975v13.025l-6-5.269-6 5.269v-24h6.816c-.553.576-1.004 1.251-1.316 2h-3.5v17.582l4-3.512 4 3.512v-8.763c.805.19 1.379.203 2 .156zm4-6.475c0 2.485-2.017 4.5-4.5 4.5s-4.5-2.015-4.5-4.5 2.017-4.5 4.5-4.5 4.5 2.015 4.5 4.5zm-3.086-2.122l-1.414 1.414-1.414-1.414-.707.708 1.414 1.414-1.414 1.414.707.708 1.414-1.415 1.414 1.414.708-.708-1.414-1.413 1.414-1.414-.708-.708z"/></svg>',
                ];

                return isset($icons[$name]) ? $icons[$name] : '';
        }

        return '';
    }

	public function getSubmitButtons($buttons = [], $prefix = "", $suffix = "")
	{
		$buttons = extend([
			"show" => [],
			"show_additional" => [],
			"hide" => [],
		], $buttons);

		foreach (["show", "show_additional", "hide"] as $purpose)
		{
			if (isset($buttons[$purpose]) && !is_array($buttons[$purpose]))
			{
				$buttons[$purpose] = [$buttons[$purpose]];
			}

			if (isset($this->submitButtonsOptions[$purpose]))
			{
				if (!is_array($this->submitButtonsOptions[$purpose]))
				{
					$this->submitButtonsOptions[$purpose] = [$this->submitButtonsOptions[$purpose]];
				}

				$buttons[$purpose] = array_merge($this->submitButtonsOptions[$purpose], $buttons[$purpose]);
			}
		}

		if (empty($buttons["show"]))
		{
			$buttons["show"] = ["save", "cancel"];

			// showing Apply button only for existing records
			if ($this->getId()) {
			    $buttons['show'][] = "quick_save";
            }
		}

		$show_ar = isset($buttons["show_additional"])
            ? array_merge($buttons["show"], $buttons["show_additional"])
            : $buttons["show"];
		$hide_ar = isset($buttons["hide"]) ? $buttons["hide"] : [];

		$help_link = $this->show_help
			? "<a href=\"help_files/toc.php?location=/" . self::$language . "/$this->table/\" rel=\"width:910,height:500,ajax:false,scrollbar:true,showControls:false\" id=\"adminHelp_toc\" class=\"mb\">{$this->L("view_help")}</a>"
			: "";

		$auto_save_timeout = \diConfiguration::safeGet("auto_save_timeout", 0);
		$js = <<<EOF
<script type="text/javascript">
var admin_form_{$this->table}_{$this->id}, admin_form;

$(function() {
	admin_form = admin_form_{$this->table}_{$this->id} = new diAdminForm('$this->table', '$this->id', '$auto_save_timeout');

	$('iframe[name="save_frame_{$this->table}_{$this->id}"]').load(function() {
		admin_form_{$this->table}_{$this->id}.loaded();
	});
});
</script>
EOF;

		if ($this->static_mode)
		{
			$edit_btn = $this->isButtonShown("edit", $show_ar, $hide_ar) ? "<button type=\"button\" onclick=\"admin_form_{$this->table}_{$this->id}.switch_to_edit_mode();\">{$this->L("edit")}</button>" : "";
			$cancel_btn = $this->isButtonShown("cancel", $show_ar, $hide_ar) ? "<button type=\"button\" id=\"btn-cancel\" onclick=\"admin_form_{$this->table}_{$this->id}.cancel_click();\">{$this->L("cancel")}</button>" : "";

			return <<<EOF
<div class="submit-block">

	$help_link

	$edit_btn

	$cancel_btn

</div>

$js
EOF;
		}
		else
		{
			$save_btn = $this->isButtonShown("save", $show_ar, $hide_ar)
                ? "<button type=\"submit\" id=\"btn-save\" onclick=\"admin_form_{$this->table}_{$this->id}.set_able_to_leave_page(true);\">{$this->getButtonIcon('save')}{$this->L("save")}</button>"
                : "";
			$clone_btn = $this->isButtonShown("clone", $show_ar, $hide_ar)
                ? "<button type=\"button\" id=\"btn-clone\" onclick=\"admin_form_{$this->table}_{$this->id}.set_able_to_leave_page(true);\">{$this->L("clone")}</button>"
                : "";
			$quick_save_btn = $this->isButtonShown("quick_save", $show_ar, $hide_ar)
                ? "<button type=\"button\" id=\"btn-quick-save\" onclick=\"admin_form_{$this->table}_{$this->id}.quick_save();\">{$this->getButtonIcon('quick_save')}{$this->L("quick_save")}</button>"
                : "";
			$dispatch_btn = $this->isButtonShown("dispatch", $show_ar, $hide_ar)
                ? "<button type=\"submit\" name=\"dispatch\" id=\"btn-dispatch\" value='1' onclick=\"admin_form_{$this->table}_{$this->id}.set_able_to_leave_page(true); return confirm('{$this->L("confirm_dispatch")}');\">{$this->L("dispatch")}</button>"
                : "";
			$dispatch_test_btn = $this->isButtonShown("dispatch", $show_ar, $hide_ar)
                ? "<button type=\"submit\" name=\"dispatch_test\" value='1' id=\"btn-dispatch-test\" onclick=\"admin_form_{$this->table}_{$this->id}.set_able_to_leave_page(true);\">{$this->L("dispatch_test")}</button>"
                : "";
			$submit_and_add_btn = $this->isButtonShown("submit_and_add", $show_ar, $hide_ar)
                ? "<button type=\"submit\" name=\"submit_and_add\" id=\"btn-submit_and_add\" value=1 onclick=\"admin_form_{$this->table}_{$this->id}.set_able_to_leave_page(true);\">{$this->L("submit_and_add")}</button>"
                : "";
			$submit_and_next_btn = $this->isButtonShown("submit_and_next", $show_ar, $hide_ar)
                ? "<button type=\"submit\" name=\"submit_and_next\" id=\"btn-submit_and_next\" value=1 onclick=\"admin_form_{$this->table}_{$this->id}.set_able_to_leave_page(true);\">{$this->L("submit_and_next")}</button>"
                : "";
			$submit_and_send_btn = $this->isButtonShown("submit_and_send", $show_ar, $hide_ar)
                ? "<button type=\"submit\" name=\"submit_and_send\" id=\"btn-submit_and_send\" value=1 onclick=\"admin_form_{$this->table}_{$this->id}.set_able_to_leave_page(true); return confirm('{$this->L("confirm_send")}');\">{$this->L("submit_and_send")}</button>"
                : "";
			$cancel_btn = $this->isButtonShown("cancel", $show_ar, $hide_ar)
                ? "<button type=\"button\" id=\"btn-cancel\" onclick=\"admin_form_{$this->table}_{$this->id}.cancel(".($this->lite ? "'&lite={$this->lite}'" : "").");\">{$this->getButtonIcon('cancel')}{$this->L("cancel")}</button>"
                : "";

			$submit_status_line = $this->isButtonShown("quick_save", $show_ar, $hide_ar)
                ? "<div id=\"submit_status_line_{$this->table}_{$this->id}\" class=\"submit-status-line\"></div>"
                : "";

			return <<<EOF
<div class="submit-block">

	$prefix

	$help_link

	$save_btn

	$clone_btn

	$submit_and_add_btn

	$submit_and_next_btn

	$submit_and_send_btn

	$cancel_btn

	$quick_save_btn

	$dispatch_btn

	$dispatch_test_btn

	$submit_status_line

	$suffix

</div>

$js

<iframe name="save_frame_{$this->table}_{$this->id}" class="save_frame"></iframe>

<input type="hidden" name="redirect_after_submit" value="1" />
EOF;
		}
	}

	public function get_html()
	{
		if ($this->AdminPage)
		{
			$formTabs = $this->AdminPage->getFormTabs();

			if ($this->AdminPage->useEditLog() && $this->getId())
			{
				$formTabs[TableEditLog::ADMIN_TAB_NAME] = TableEditLog::adminTabTitle($this->getX()->getLanguage());
			}
		}
		else
		{
			$formTabs = isset($GLOBALS['tables_tabs_ar'][$this->table])
                ? $GLOBALS['tables_tabs_ar'][$this->table]
                : [];
        }

        $tabsExist = !!$formTabs;

		if ($tabsExist)
		{
			if (!isset($formTabs['general']))
			{
				$formTabs = array_merge([
					'general' => $this->L('tab_general'),
				], $formTabs);
			}
		}

		$tabs = [];
		$notesStarsCounter = '';

		foreach ($this->getAllFields() as $field => $v)
		{
			$html = '';
			unset($input);

			if (empty($v['tab']) || empty($formTabs[$v['tab']]))
			{
				$v['tab'] = 'general';
			}

			if (!isset($tabs[$v['tab']]))
			{
				$tabs[$v['tab']] = '';
			}

			if ($v['type'] == 'separator')
			{
				$html .= $this->getSeparatorRow();
				$tabs[$v['tab']] .= $html;

				continue;
			}

			if ($v['type'] == 'password' && ($this->static_mode || $this->isFlag($v, 'static')))
			{
				$v['flags'][] = 'hidden';
			}

			if (
			    in_array($field, array_keys($this->getFormFields())) ||
                in_array($field, array_keys($this->force_inputs_fields))
               )
			{
				if (!$this->hasInputAttribute($field, 'size') && in_array($v['type'], self::$numericTypes))
				{
					$this->setInputAttribute($field, ['size' => 15]);
				}
				elseif (in_array($v['type'], self::$stringTypes))
				{
					if (!$this->hasInputAttribute($field, 'style'))
					{
						$this->setInputAttribute($field, ['style' => 'width: 100%;']);
					}

					if ($v['type'] == 'email')
					{
						$this->setInputAttribute($field, ['type' => 'email']);
					}

					if ($v['type'] == 'tel')
					{
						$this->setInputAttribute($field, ['type' => 'tel']);
					}

					if ($v['type'] == 'url')
					{
						$this->setInputAttribute($field, ['type' => 'url']);
					}
				}
				elseif ($v['type'] == 'password')
				{
					$this->setInputAttribute($field, [
						'value' => '',
						'type' => 'password',
						'onkeyup' => "admin_form_{$this->table}_{$this->id}.check_password('$field');",
						'size' => 32,
                        'autocomplete' => 'new-password',
					]);
				}

				if ($this->isFlag($v, 'static') || $this->static_mode)
				{
					if (isset($this->inputs[$field])) // already set, we'll leave it alone
					{
						$s = $this->inputs[$field];
					}
					else
					{
						$s = false;

						switch ($v['type'])
						{
							case "date":
								$s = $this->data[$field] ? date("d.m.Y", $this->data[$field]) : "---";
								break;

							case "time":
								$s = $this->data[$field] ? date("H:i", $this->data[$field]) : "---";
								break;

							case "datetime":
								$s = $this->data[$field] ? date("d.m.Y H:i", $this->data[$field]) : "---";
								break;

							case "date_str":
								$s = $this->data[$field] && $this->data[$field] != "0000-00-00 00:00:00" ? date("d.m.Y", strtotime($this->data[$field])) : "---";
								break;

							case "time_str":
								$s = $this->data[$field] && $this->data[$field] != "0000-00-00 00:00:00" ? date("H:i", strtotime($this->data[$field])) : "---";
								break;

							case "datetime_str":
								$s = $this->data[$field] && $this->data[$field] != "0000-00-00 00:00:00" ? date("d.m.Y H:i", strtotime($this->data[$field])) : "---";
								break;

							case "checkbox":
								$s = $this->L($this->data[$field] ? "yes" : "no");
								break;

							case 'checkboxes':
								$this->setCheckboxesListInput($field);
								break;

							case "color":
								$this->setColorInput($field);
								break;

							case "font":
								$this->setFontInput($field);
								break;

							case "href":
								$this->setHrefInput($field);
								break;

							case "ip":
								$this->setIpInput($field);
								break;

							case "pic":
								$this->setPicInput($field);
								break;

							case "file":
								$this->setFileInput($field);
								break;

							case "dynamic_pics":
								$this->set_dynamic_pics_input($field);
								break;

							case "dynamic_files":
								$this->set_dynamic_files_input($field);
								break;

							case "dynamic":
								$this->set_dynamic_input($field);
								break;

							case "text":
							case "blob":
								$this->setTextareaInput($field);
								break;

							case "wysiwyg":
								$this->setWysiwygInput($field);
								break;

							case "int":
							case "integer":
							case "float":
							case "double":
								$s = $this->data[$field];
								break;

							case "tags":
								$this->setTagsInput($field);
								break;

							default:
								$s = StringHelper::out($this->data[$field]);
								break;
						}
					}

					if ($s === false)
					{
						$s = isset($this->inputs[$field]) ? $this->inputs[$field] : $this->getData($field);
					}

					$this->setInputAttribute($field, [
						'type' => 'hidden',
						'value' => StringHelper::out($this->data[$field]),
						'id' => $field,
						'name' => $field,
					]);

					$this->inputs[$field] = "<div class=\"static\">$s</div><input " . $this->getInputAttributesString($field) . ">";
				}

				if (isset($this->inputs[$field]))
				{
					$input = $this->inputs[$field];
				}
				else
				{
					switch ($v["type"])
					{
						case "date":
						case "date_str":
							$this->set_datetime_input($field, true, false);
							break;

						case "time":
						case "time_str":
							$this->set_datetime_input($field, false, true);
							break;

						case "datetime":
						case "datetime_str":
							$this->set_datetime_input($field, true, true);
							break;

						case "text":
						case "blob":
							$this->setTextareaInput($field);
							break;

						case "wysiwyg":
							$this->setWysiwygInput($field);
							break;

						case "checkbox":
							$this->set_checkbox_input($field);
							break;

						case 'checkboxes':
							$this->setCheckboxesListInput($field);
							break;

						case "color":
							$this->setColorInput($field);
							break;

						case "font":
							$this->setFontInput($field);
							break;

						case "href":
							$this->setHrefInput($field);
							break;

						case "pic":
							$this->setPicInput($field);
							break;

						case "file":
							$this->setFileInput($field);
							break;

						case "dynamic_pics":
							$this->set_dynamic_pics_input($field);
							break;

						case "dynamic_files":
							$this->set_dynamic_files_input($field);
							break;

						case "dynamic":
							$this->set_dynamic_input($field);
							break;

						case "tags":
							$this->setTagsInput($field);
							break;

						case "ip":
							$this->setIpInput($field);
							break;

						default:
							$input = $this->getSimpleInput($field);
							break;
					}

					if (!isset($input))
					{
						$input = $this->getInput($field);
					}
				}

				$hidden =
					$this->isFlag($field, 'hidden') ||
					($this->isFlag($field, 'initially_hidden') && !$this->getId());

				if ($hidden)
				{
					$html .= "\n<input type=\"hidden\" id=\"$field\" name=\"$field\" value=\"" .
						StringHelper::out($this->data[$field]) . "\">\n";
				}
				else
				{
					if (!empty($v["notes"]))
					{
						$notesStarsCounter .= "*";
						$notesStar = $notesStarsCounter;
					}
					else
					{
						$notesStar = "";
					}

					switch ($v["type"])
					{
						case "password":
							$input1 = $this->getSimpleInput("password");
							$input2 = $this->getSimpleInput("password", ["name" => "password2"]);

							$t2 = $v["title"];
							if ($t2)
							{
								$t2 = mb_strtolower(mb_substr($t2, 0, 1)) . mb_substr($t2, 1);
							}

							$html .= $this->getRow($field, "{$v["title"]}{$notesStar}:", "$input1 &nbsp;<span id=\"{$field}_console\" class=\"error\"></span>");
							$html .= $this->getRow($field . "2", "{$this->L("confirm")} {$t2}{$notesStar}:", $input2);

							break;

						case "dynamic_pics":
						case "dynamic_files":
						case "pic":
						case "file":
							if (!empty($this->uploaded_images[$field]))
								$tag =	$this->uploaded_images[$field];
							elseif (!empty($this->uploaded_files[$field]))
								$tag =	$this->uploaded_files[$field];
							else
								$tag = "";

							if ($this->static_mode)
								$input = "";

							$input = $this->wrapInput($field, $input);

							//(isset($this->uploaded_images_w[$k]) && $this->uploaded_images_w[$k] > 200 || ( &&
							$html .= $this->getRow($field, "{$v["title"]}{$notesStar}:",
								$tag && ($this->static_mode || $this->data[$field]) ? "$tag<div>$input</div>" : $input);

							break;

						default:
							$input = $this->wrapInput($field, $input);

							$title = isset($v["title"])
								? "{$v["title"]}{$notesStar}:"
								: '';
							$html .= $this->getRow($field, $title, $input);

							break;
					}

					if (!empty($v["notes"]))
					{
						if (!is_array($v["notes"]))
						{
							$v["notes"] = array($v["notes"]);
						}

						$_notes = "";

						foreach ($v["notes"] as $_note)
						{
							$_notes .= "<div><i>{$notesStar} {$_note}</i></div>";
						}

						$caption = $this->L("notes_caption");
						$caption = $caption[(count($v["notes"]) > 1)];

						$html .= $this->getRow($field, "{$caption}:", $_notes, 'data-purpose="notes"');
					}
				}
			}

			$tabs[$v["tab"]] .= $html;
		}

		// tabs
		if ($tabsExist)
		{
			$tab_head_ar = [];
			$tab_head_separator = "";

			foreach ($formTabs as $field => $v)
			{
				if (!empty($tabs[$field]))
				{
				    // multilingual support
				    if (is_array($v)) {
				        $v = $v[$this->getX()->getLanguage()];
                    }

					$tab_head_ar[] = "<li data-tab='{$field}'><a data-tab=\"{$field}\" href=\"{$_SERVER["REQUEST_URI"]}#$field\"><div>$v</div></a></li>";
				}
			}

			$result = "<div class=\"diadminform_tabs\"><ul>" . join($tab_head_separator, $tab_head_ar) . "</ul></div>\n\n";

			$result .= "<div data-purpose=\"tab-pages\">\n";

			foreach ($formTabs as $field => $v)
			{
				$result .= "<div data-tab=\"{$field}\">" .
					(isset($tabs[$field]) ? $tabs[$field] : "") .
					"</div>\n\n";
			}

			$result .= "</div>\n";
		}
		else
		{
			$result = isset($tabs["general"]) ? $tabs["general"] : "";
		}
		//

		return $result;
	}

	public function getInput($field)
	{
		return isset($this->inputs[$field]) ? $this->inputs[$field] : null;
	}

	public function getSimpleInput($field, $attributes = [])
	{
		$attributes = extend([
			"type" => "text",
			"name" => $field,
			"value" => StringHelper::out($this->getData($field)),
		], $this->getInputAttributes($field), $attributes);

		return "<input " . $this->getInputAttributesString($field, $attributes) . ">";
	}

	protected function getTextareaInput($field, $attributes = [])
	{
		$attributes = extend([
			'name' => $field,
			'cols' => $this->getFieldOption($field, 'cols') ?: 80,
			'rows' => $this->getFieldOption($field, 'rows') ?: 10,
		], $this->getInputAttributes($field), $attributes);

		return "<textarea " . $this->getInputAttributesString($field, $attributes) . ">" .
		StringHelper::out($this->getData($field)) . "</textarea>";
	}

	protected function getRow($field, $title, $value, $rowAttrs = '')
	{
		if (is_array($rowAttrs))
		{
			$rowAttrs = ArrayHelper::toAttributesString($rowAttrs);
		}

		if ($this->getFieldProperty($field, 'drag_and_drop_uploading'))
		{
			$rowAttrs .= ' data-drag-and-drop-uploading="true"';
		}

		if ($this->isStatic($field))
        {
            $rowAttrs .= ' data-static="true"';
        }

		return <<<EOF
<div id="tr_{$field}" class="diadminform-row"{$rowAttrs} data-field="$field" data-type="{$this->getFieldType($field)}">
	<label class="title" for="$field">$title</label>
	<div class="value">$value</div>
</div>
EOF;
	}

	protected function getSeparatorRow()
	{
		return '<div class="diadminform-separator"></div>';
	}

	function get_dynamic_row($id, $field, $value, $prefix = "", $suffix = "")
	{
		return "<div id=\"{$field}_div[{$id}]\" class=\"dynamic-row\">
			$prefix
			<input type=\"text\" id=\"{$field}[{$id}]\" name=\"{$field}[{$id}]\" value=\"{$value}\" />
			$suffix
			[<a href=\"#\" onclick=\"return diref_{$this->table}.remove('{$field}',{$id});\">&ndash;</a>]
			</div>";
	}

	/** @deprecated */
	public function get_field_options($field)
	{
		return $this->getFieldOption($field);
	}

	public function getFieldOption($field, $option = null)
	{
		$o = (array)$this->getFieldProperty($field, "options");

		if (is_null($option))
		{
			return $o;
		}
		else
		{
			return isset($o[$option]) ? $o[$option] : null;
		}
	}

	function create()
	{
		echo $this->get_html();

		return $this;
	}

	/** @deprecated */
	public function set_input($field, $input, $static_input = "")
	{
		return $this->setInput($field, $input, $static_input);
	}

	public function setInput($field, $input, $static_input = "")
	{
		if ($input instanceof \diModel)
		{
			$input = $input->appearanceForAdmin();
		}

		$this->inputs[$field] = $this->static_mode && $static_input
			? $static_input
			: $input;

		$this->force_inputs_fields[$field] = true;

		return $this;
	}

	public function setSimpleInput($field)
	{
		$this->setInput($field, $this->getSimpleInput($field));

		return $this;
	}

	public function setTwigInput($field, $templateName = null, $data = [])
	{
		if (is_array($templateName) && !$data)
		{
			$data = $templateName;
			$templateName = $field;
		}

		if ($templateName === null) {
            $templateName = $field;
        }

		if (!StringHelper::contains($templateName, '/'))
		{
			$templateName = 'admin/' . $this->getTable() . '/' . $templateName;
		}

		$this
			->setInput($field, $this->getX()->getTwig()->parse($templateName, extend([
				'id' => $this->getId(),
				'table' => $this->getTable(),
				'type' => \diTypes::getId($this->getTable()),
				'field' => $field,
				'value' => $this->getData($field),
			], $data)));

		return $this;
	}

	/** @deprecated  */
	public function setTemplateForInput($field, $templatePath, $templateName)
	{
		$this->getTpl()
			->define($templatePath, [
				"_input_block" => $templateName,
			])
			->assign([
				'id' => $this->getId(),
				'table' => $this->getTable(),
				'type' => \diTypes::getId($this->getTable()),
				'field' => $field,
				'value' => $this->getData($field),
			], "I_");

		$this->setInput($field, $this->getTpl()->parse("_input_block"));

		return $this;
	}

	public function setParentInput($field = "parent")
	{
		$h = new \diHierarchyTable($this->getTable());

		$parentsAr = array();
		foreach ($h->getParentsArByParentId($this->getData("parent")) as $parent_r)
		{
			$parentsAr[] = strip_tags($parent_r->title);
		}

		if ($parentsAr)
		{
			$this
				->setStaticInput($field)
				->setInput($field, join(" / ", $parentsAr));
		}
		else
		{
			$this->setHiddenInput($field);
		}

		return $this;
	}

	/** @deprecated */
	function set_input_param($field, $param = [])
	{
		return $this->setInputAttribute($field, $param);
	}

	/** @deprecated */
	public function setInputParam($field, $params = [])
	{
		return $this->setInputAttribute($field, $params);
	}

	public function setInputAttribute($field, $params = [])
	{
		if (!is_array($field))
		{
			$field = [$field];
		}

		foreach ($field as $f)
		{
			if (!isset($this->inputAttributes[$f]))
			{
				$this->inputAttributes[$f] = [];
			}

			$this->inputAttributes[$f] = extend($this->inputAttributes[$f], $params);
		}

		return $this;
	}

	private function processAffix($field, $affix)
	{
		switch ($affix)
		{
			case self::INPUT_SUFFIX_NEW_FIELD:
				$affix = ' или введите: <input type="text" name="' . $field . self::NEW_FIELD_SUFFIX . '" value="" style="width: 300px;">';
				break;
		}

		return $affix;
	}

	private function wrapInput($field, $input)
	{
		$prefix = $this->getInputPrefix($field);
		$suffix = $this->getInputSuffix($field);

		return ($prefix ? "<span class=\"input-prefix\">$prefix</span>" : "") . $input . ($suffix ? "<span class=\"input-suffix\">$suffix</span>" : "");
	}

	public function setInputPrefix($field, $prefix)
	{
		$this->inputPrefixes[$field] = $this->processAffix($field, $prefix);

		return $this;
	}

	public function getInputPrefix($field)
	{
		return isset($this->inputPrefixes[$field]) ? $this->inputPrefixes[$field] : null;
	}

	public function setInputSuffix($field, $suffix)
	{
		$this->inputSuffixes[$field] = $this->processAffix($field, $suffix);

		return $this;
	}

	public function getInputSuffix($field)
	{
		return isset($this->inputSuffixes[$field]) ? $this->inputSuffixes[$field] : null;
	}

	public function setHrefInput($field)
	{
		if (!$this->getId())
		{
			$this
				->setHiddenInput($field);
		}
		else
		{
			$this
                ->setStaticInput($field)
				->setInput($field, "<a href='{$this->getModel()->getHref()}' target='_blank'>{$this->getModel()->getFullHref()}</a>");
		}

		return $this;
	}

	function set_checkbox_input($field)
	{
		if ($this->static_mode || $this->isFlag($field, "static"))
		{
			$this->inputs[$field] = (int)$this->getData($field) ? $this->L("yes") : $this->L("no");
		}
		else
		{
			$checked = (int)$this->getData($field) ? " checked=\"checked\"" : "";
			$this->inputs[$field] = "<input type='checkbox' name='$field'" . $checked . $this->getInputAttributesString($field) . ">";
		}

		$this->force_inputs_fields[$field] = true;

		return $this;
	}

	/** @deprecated  */
	function _set_typed_input($field, $include_ar = [], $exclude_ar = [])
	{
		return $this->setSelectFromOwnValues($field, $include_ar, $exclude_ar);
	}

	function setSelectFromOwnValues($field, $include = [], $exclude = [])
	{
		$sel = new \diSelect($field, $this->getData($field));
		$sel
			->setAttr($this->getInputAttributes($field))
			->addItemArray2($include);

		$rs = $this->getDb()->rs($this->table, "ORDER BY $field ASC", "DISTINCT $field");
		while ($r = $this->getDb()->fetch($rs))
		{
			if (!in_array($r->$field, $exclude))
			{
				$sel->addItem($r->$field, $r->$field);
			}
		}

		$this->inputs[$field] = count($sel->getItemsAr())
			? $sel . ' ' . $this->L('or_enter') . ': '
			: '';

		$width = $this->inputs[$field]
			? '50%'
			: '100%';

		$this->inputs[$field] .= sprintf(
			'<input type="text" name="%s" value="" style="width: %s;">',
			$field . self::NEW_FIELD_SUFFIX,
			$width
		);

		$this->force_inputs_fields[$field] = true;

		return $this;
	}

	function set_grouped_typed_inputs($field_ar)
	{
		global $db;

		$values = [];

		foreach ($field_ar as $field)
		{
			$rs = $db->rs($this->table, "ORDER BY $field ASC", "DISTINCT $field");
			while ($r = $db->fetch($rs))
			{
				$values[] = $r->$field;
			}
		}

		$values = array_unique($values);
		sort($values, SORT_STRING);

		foreach ($field_ar as $field)
		{
			$sel = new \diSelect($field, StringHelper::out($this->getData($field)));

			foreach ($values as $v)
			{
				$sel->addItem(StringHelper::out($v), StringHelper::out($v));
			}

			$sel->setAttr($this->getInputAttributes($field));

			$this->inputs[$field] = $sel->getHTML();
			$this->inputs[$field] .= ' ' . $this->L('or_enter') . ': <input type="text" name="' .
				$field . self::NEW_FIELD_SUFFIX . '" value="" style="width: 300px;">';

			$this->force_inputs_fields[$field] = true;
		}

		return $this;
	}

	/** @deprecated */
	function set_select_from_array_input($field, $ar, $prefix_ar = [], $suffix_ar = [])
	{
		return $this->setSelectFromArrayInput($field, $ar, $prefix_ar, $suffix_ar);
	}

	public function setSelectFromArrayInput($field, $ar, $prefix_ar = [], $suffix_ar = [])
	{
		if ($this->static_mode || $this->isFlag($field, "static"))
		{
			if (isset($ar[$this->getData($field)]))
			{
				$this->inputs[$field] = $ar[$this->getData($field)];
			}
			elseif (isset($prefix_ar[$this->getData($field)]))
			{
				$this->inputs[$field] = $prefix_ar[$this->getData($field)];
			}
			elseif (isset($suffix_ar[$this->getData($field)]))
			{
				$this->inputs[$field] = $suffix_ar[$this->getData($field)];
			}

			if ($this->inputs[$field])
			{
				$this->inputs[$field] = StringHelper::out($this->inputs[$field]);
			}
		}
		else
		{
			$sel = \diSelect::fastCreate($field, $this->getData($field), $ar, $prefix_ar, $suffix_ar);
			$sel->setAttr($this->getInputAttributes($field));

			$this->inputs[$field] = $sel;
		}

		$this->force_inputs_fields[$field] = true;

		return $this;
	}

	/** @deprecated */
	function set_select_from_array2_input($field, $ar)
	{
		return $this->setSelectFromArray2Input($field, $ar);
	}

	public function setSelectFromArray2Input($field, $ar)
	{
		if ($this->static_mode || $this->isFlag($field, "static"))
		{
			$this->inputs[$field] = StringHelper::out($this->getData($field));
		}
		else
		{
			$sel = new \diSelect($field, $this->getData($field));

			$sel
				->setAttr($this->getInputAttributes($field))
				->addItemArray2($ar);

			$this->inputs[$field] = $sel;
		}

		$this->force_inputs_fields[$field] = true;

		return $this;
	}

	/** @deprecated */
	public function set_select_from_db_input($field, $db_rs, $template_text = "%title%", $template_value = "%id%", $prefix_ar = array(), $suffix_ar = array())
	{
		return $this->setSelectFromDbInput($field, $db_rs, $template_text, $template_value, $prefix_ar, $suffix_ar);
	}

	public function setSelectFromDbInput($field, $db_rs, $template_text = "%title%", $template_value = "%id%", $prefix_ar = [], $suffix_ar = [])
	{
		if (is_array($template_text))
		{
			$prefix_ar = $template_text;
			$template_text = "%title%";
			$template_value = "%id%";
		}

		$sel = new \diSelect($field, $this->getData($field));

		$sel->setAttr($this->getInputAttributes($field));

		if ($prefix_ar)
		{
			$sel->addItemArray($prefix_ar);
		}

		while ($db_rs && $db_r = $this->getDb()->fetch($db_rs))
		{
			$ar1 = [];
			$ar2 = [];

			foreach ($db_r as $k => $v)
			{
				$ar1[] = "%$k%";
				$ar2[] = $v;

				if ($k == "level_num")
				{
					$ar1[] = "%[left-padding]%";
					$ar2[] = str_repeat("&nbsp;", $db_r->$k * 4);
				}
			}

			$text = str_replace($ar1, $ar2, $template_text);
			$value = str_replace($ar1, $ar2, $template_value);

			$sel->addItem($value, $text);
		}

		if ($suffix_ar)
		{
			$sel->addItemArray($suffix_ar);
		}

		$this->inputs[$field] = $this->isStatic($field)
			? $sel->getTextByValue($this->getData($field))
			: $sel;

		$this->force_inputs_fields[$field] = true;

		return $this;
	}

    /**
     * @param string $field
     * @param \diCollection|array $collection
     * @param array|callable $format
     * @param array $prefixAr
     * @param array $suffixAr
     * @return $this
     */
	public function setSelectFromCollectionInput($field, $collection, $format = null, $prefixAr = [], $suffixAr = [])
	{
		if ($format === null || is_array($format))
		{
			if (is_array($format))
			{
				$suffixAr = $prefixAr;
				$prefixAr = $format;
			}

			$format = null;
		}

		$sel = new \diSelect($field, $this->getData($field));
		$sel->setAttr($this->getInputAttributes($field));

		if ($prefixAr)
		{
			$sel->addItemArray($prefixAr);
		}

		$sel->addItemsCollection($collection, $format);

		if ($suffixAr)
		{
			$sel->addItemArray($suffixAr);
		}

		$this->inputs[$field] = $this->isStatic($field)
			? $sel->getTextByValue($this->getData($field))
			: $sel;

		$this->force_inputs_fields[$field] = true;

		return $this;
	}

	/** @deprecated */
	public function set_wysiwyg_input($field)
	{
		return $this->setWysiwygInput($field);
	}

	public function setWysiwygInput($field)
	{
		if ($this->static_mode || $this->isFlag($field, "static"))
		{
			$this->inputs[$field] = "<div class='static-text'>{$this->getData($field)}</div>";
		}
		else
		{
			$attrs = $this->getInputAttributesString($field, [
				'name' => $field,
				'cols' => 80,
				'rows' => 10,
			]);

			$this->inputs[$field] = "<div class='wysiwyg'><textarea {$attrs}>{$this->getData($field)}</textarea></div>";

			if ($this->getWysiwygVendor() == self::wysiwygCK)
			{
				$this->inputs[$field] .= "<script type='text/javascript'>var editor_$field = CKEDITOR.replace('$field'); CKFinder.SetupCKEditor(editor_$field, {BasePath: '/_admin/ckfinder/', RememberLastFolder : false});</script>";
			}
		}

		$this->force_inputs_fields[$field] = true;

		return $this;
	}

	/**
	 * @deprecated
	 * @param $field
	 * @return Form
	 */
	public function set_textarea_input($field)
	{
		return $this->setTextareaInput($field);
	}

	public function setTextareaInput($field)
	{
		if ($this->static_mode || $this->isFlag($field, "static"))
		{
			$this->inputs[$field] = "<div class=\"static-text\">" . nl2br($this->getData($field)) . "</div>";
		}
		else
		{
			$this->inputs[$field] = "<div class='textarea'>" . $this->getTextareaInput($field) . "</div>";
		}

		$this->force_inputs_fields[$field] = true;

		return $this;
	}

	private function getInputAttributesString($field, $forceAttributes = [])
	{
		$ar = $this->getInputAttributes($field, $forceAttributes);

		return $ar ? ArrayHelper::toAttributesString($ar, true, ArrayHelper::ESCAPE_HTML) : "";
	}

	private function getInputAttributes($field, $forceAttributes = [])
	{
		return extend(
			$this->getFieldProperty($field, "attrs") ?: [],
			isset($this->inputAttributes[$field]) ? $this->inputAttributes[$field] : [],
			$forceAttributes
		);
	}

	private function getInputAttribute($field, $attribute)
	{
		$ar = $this->getInputAttributes($field);

		return isset($ar[$attribute]) ? $ar[$attribute] : null;
	}

	private function hasInputAttribute($field, $attribute)
	{
		return !!$this->getInputAttribute($field, $attribute);
	}

	private function getDelLinkCode($field)
	{
		return ", <a href=\"" . \diLib::getAdminWorkerPath("files", "del", [$this->table, $this->id, $field]) . "\" " .
		"data-field=\"$field\" data-confirm=\"{$this->L("delete_pic_confirmation")}\" " .
		"class=\"del-file\">{$this->L("delete")}</a>";
	}

	private function getRotateBlockCode($field)
	{
		$ccw = '<a href="' . \diLib::getAdminWorkerPath('files', 'rotate', [$this->table, $this->id, $field, 'ccw']) . '"' .
			' data-field="' . $field . '" data-confirm="' . $this->L('rotate_pic_confirmation') . '"' .
			' class="rotate-pic" title="' . $this->L('rotate_pic.ccw') . '">↶</a>';

		$cw = '<a href="' . \diLib::getAdminWorkerPath('files', 'rotate', [$this->table, $this->id, $field, 'cw']) . '"' .
			' data-field="' . $field . '" data-confirm="' . $this->L('rotate_pic_confirmation') . '"' .
			' class="rotate-pic" title="' . $this->L('rotate_pic.cw') . '">↷</a>';

		return '<div class="rotate-block">' . $ccw . $cw . '</div>';
	}

	private function getWatermarkBlockCode($field)
	{
		$btn = '<a href="' . \diLib::getAdminWorkerPath('files', 'watermark', [$this->table, $this->id, $field]) . '"' .
			' data-field="' . $field . '" data-confirm="' . $this->L('watermark_pic_confirmation') . '"' .
			' class="watermark-pic" title="' . $this->L('watermark_pic') . '">⧉</a>';

		return '<div class="watermark-block">' . $btn . '</div>';
	}

	public function getPreviewHtmlForFile($field, $fullName, $options = [])
	{
		$options = extend([
			'hideIfNoFile' => false,
			'showDelLink' => true,
			'showRotateBlock' => false,
			'showWatermarkBlock' => false,
			'showPreviewWithLink' => false,
		], $options);

		$f = \diPaths::fileSystem($this->getModel(), false, $field) . $fullName;
		$ext = strtoupper(StringHelper::fileExtension($fullName));
		$imgTag = '';
		$previewInfoBlock = '';

		if (is_file($f))
		{
			$httpName = \diPaths::http($this->getModel(), false, $field) . '/' . StringHelper::unslash($fullName, false);

            if (!StringHelper::contains($httpName, '://') && \diLib::getSubFolder())
            {
                $httpName = '/' . $httpName;
            }

			$ff_w = $ff_h = null;
			$ff_s = filesize($f);
			$previewWithText = false;

			// swiffy
			if (\diSwiffy::is($f))
			{
				list($ff_w, $ff_h) = \diSwiffy::getDimensions($f);

				$imgTag = \diSwiffy::getHtml($httpName, $ff_w, $ff_h);
			}
			// video
			elseif (in_array($ext, ['MP4', 'M4V', 'OGV', 'WEBM', 'AVI']))
			{
				//$mime_type = self::get_mime_type_by_ext($ext);
				// type=\"$mime_type\"
				$imgTag = "<div><video preload=\"none\" controls width=400 height=225><source src=\"$httpName\" /></video></div>";
			}
			// audio
			elseif (in_array($ext, ["MP3", "OGG"]))
			{
				$mimeType = self::get_mime_type_by_ext($ext);

				$imgTag = "<div><audio preload=\"none\" controls=\"controls\" type=\"$mimeType\"><source src=\"$httpName\" type=\"$mimeType\" /></audio></div>";
			}
			// font
			elseif (in_array($ext, ["TTF", "EOT", "WOFF", "OTF"]))
			{
				$uid = get_unique_id(10);
				$className = "font-preview-" . $uid;
				$fontFamily = "font-" . $uid;

				/** @var \diFontModel $font */
				$font = \diModel::create(\diTypes::font);
				$font
					->setToken($fontFamily)
					->setRelated("folder", preg_replace("/^\/+/", "", StringHelper::slash(dirname($fullName))))
					->set("file_" . strtolower($ext), basename($fullName));

				$fontDefinition = Helper::getCssForFont($font);

				$letters = join("", range("a", "z"));
				$capitalLetters = mb_strtoupper($letters);
				$cyrLetters = "абвгдеёжзийклмнопрстуфхцчшщъыьэюя";
				$capitalCyrLetters = mb_strtoupper($cyrLetters);
				$digits = join("", range(0, 9)) . '!@#$%^&*()[]{}\\/"\'-=+`~';

				$imgTag = "<div class='{$className}'>{$digits}<br>{$capitalLetters}<br>{$letters}<br>{$capitalCyrLetters}<br>{$cyrLetters}</div>" .
					"<style type='text/css'>{$fontDefinition}\n.{$className} {font-family: {$fontFamily};}</style>";

				$previewWithText = true;
			}
			// picture
			else
			{
				list($ff_w, $ff_h, $ff_t) = getimagesize($f);

				if (\diImage::isFlashType($ff_t))
				{
					$imgTag = "<script type=\"text/javascript\">run_movie(\"$httpName\", \"$ff_w\", \"$ff_h\", \"opaque\");</script>";
				}
				elseif (\diImage::isImageType($ff_t) || $ext == 'SVG')
				{
					if ($options['showPreviewWithLink'])
					{
						$subFolder = Submit::getFolderByImageType($options['showPreviewWithLink']);
						$previewHttpName = StringHelper::slash(dirname($httpName)) . $subFolder . basename($httpName);
						$previewFullName = StringHelper::slash(dirname($f)) . $subFolder . basename($f);

						list($wTn, $hTn) = getimagesize($previewFullName);
						$sizeTn = filesize($previewFullName);

						$previewInfoBlock = "<div class='info'>Preview: " . join(", ", array_filter([
								$ext,
								$wTn && $hTn ? $wTn . "x" . $hTn : null,
								size_in_bytes($sizeTn),
								//diDateTime::format("d.m.Y H:i", filemtime($previewFullName))
							])) . '</div>';

						$imgTag = "<a href='$httpName' target='_blank'><img src=\"$previewHttpName\" width='$wTn' height='$hTn' alt=\"$field\"></a>";
					}
					else
					{
						$imgTag = "<img src=\"$httpName\" alt=\"$field\">"; // width=\"$ff_w\" height=\"$ff_h\"
					}
				}
			}

			$info = join(', ', array_filter([
				$ext,
				$ff_w && $ff_h ? $ff_w . 'x' . $ff_h : null,
				size_in_bytes($ff_s),
				\diDateTime::simpleFormat(filemtime($f))
			]));

			if ($imgTag)
			{
				$additionalClassName = $previewWithText ? 'text' : 'embed';

				if ($this->getFieldOption($field, 'noZoomFeature'))
				{
					$additionalClassName .= ' no-zoom-feature img-full-size';
				}

				$imgTag = "<div class=\"container {$additionalClassName}\">$imgTag</div>";
			}
		}
		else
		{
			$info = "No file ($f)";

			$httpName = '#no-file';
		}

		$delLink = $options['showDelLink']
			? $this->getDelLinkCode($field)
			: '';

		$rotateBlock = $options['showRotateBlock'] && isset($ff_t) && \diImage::isImageType($ff_t)
			? $this->getRotateBlockCode($field)
			: '';

		$watermarkBlock = $options['showWatermarkBlock'] && isset($ff_t) && \diImage::isImageType($ff_t)
			? $this->getWatermarkBlockCode($field)
			: '';

		$this->uploaded_images_w[$field] = isset($ff_w) ? $ff_w : 0;

		return $fullName && (is_file(\diPaths::fileSystem() . $fullName) || !$options['hideIfNoFile'])
			? '<div class="existing-pic-holder">' . $imgTag .
				'<a href="' . $httpName . '" class="link">' . basename($fullName) . '</a>' .
				$previewInfoBlock .
				'<div class="info">' . $info . $delLink . $rotateBlock . $watermarkBlock . '</div>' .
			'</div>'
			: '';
	}

	/** @deprecated */
	function set_pic_input($field, $path = false, $hide_if_no_file = false)
	{
		return $this->setPicInput($field, $path, $hide_if_no_file);
	}

	/**
	 * @param string|array $field
	 * @param bool|string $path
	 * @param bool $hideIfNoFile
	 *
	 * @return Form
	 */
	public function setPicInput($field, $path = false, $hideIfNoFile = false)
	{
		if ($path === false)
		{
			$path = StringHelper::slash($this->getModel()->getPicsFolder() ?: get_pics_folder($this->table), false);
		}

		$fields = is_array($field) ? $field : [$field];

		foreach ($fields as $field)
		{
			$v = $this->getData($field) ?: '';

			$this->uploaded_images[$field] = $v
				? $this->getPreviewHtmlForFile($field, $path . $v, [
					'hideIfNoFile' => $hideIfNoFile,
					'showDelLink' => !$this->isFlag($field, 'static') || $this->getFieldProperty($field, 'showDelLink'),
					'showRotateBlock' => $this->getFieldProperty($field, 'showRotateBlock'),
					'showWatermarkBlock' => $this->getFieldProperty($field, 'showWatermarkBlock'),
					'showPreviewWithLink' => $this->getFieldProperty($field, 'showPreview'),
				])
				: '';

			$name = $field;

			if ($this->hasInputAttribute($field, 'multiple'))
			{
				$name .= '[]';
			}

			$attributes = $this->getInputAttributesString($field, $this->hasInputAttribute($field, 'accept') ? [] : [
				'accept' => '.jpg,.jpeg,.gif,.png,.svg',
			]);

			$this->inputs[$field] = $this->isFlag($field, 'static')
				? "<input type=\"hidden\" name=\"$field\" value=\"$v\">"
				: "<div class=\"file-input-wrapper\" data-caption=\"{$this->L('choose_file')}\"><input type=\"file\" name=\"$name\" value=\"\" size=\"70\" {$attributes}></div>";

			$this->force_inputs_fields[$field] = true;
		}

		return $this;
	}

	static public function get_mime_type_by_ext($ext)
	{
		$ext = strtolower($ext);

		switch ($ext)
		{
			case "mp4":
			case "webm":
			default:
				return "video/$ext";

			case "ogv":
				return "video/ogg";

			case "m4v":
				return "video/x-m4v";

			case "mp3":
				return "audio/mpeg";

			case "ogg":
				return "audio/$ext";
		}
	}

	/** @deprecated */
	public function get_file_html_for_input($field, $fullName, $hide_if_no_file = false, $show_del_link = true)
	{
		return $this->getPreviewHtmlForFile($field, $fullName, [
			'hideIfNoFile' => $hide_if_no_file,
			'showDelLink' => $show_del_link,
		]);
	}

	/** @deprecated */
	public function set_file_input($field, $path = false, $hide_if_no_file = false, $show_del_link = true)
	{
		return $this->setFileInput($field, $path, $hide_if_no_file, $show_del_link);
	}

	/**
	 * @param string|array $field
	 * @param bool|string $path
	 * @param bool $hideIfNoFile
	 * @param bool $showDelLink
	 *
	 * @return Form
	 */
	public function setFileInput($field, $path = false, $hideIfNoFile = false, $showDelLink = true)
	{
		$pics_folder = get_pics_folder($this->table);
		//$files_folder = get_files_folder($this->table);

		if ($path === false && !empty($files_folder))
		{
			$path = StringHelper::slash($files_folder, false);
		}
		elseif ($path === false && !empty($pics_folder))
		{
			$path = StringHelper::slash($pics_folder, false);
		}

		$fields = is_array($field) ? $field : [$field];

		foreach ($fields as $field)
		{
			$v = $this->getData($field) ?: '';

			$this->uploaded_files[$field] = $v
				? $this->getPreviewHtmlForFile($field, $path . $v, [
					'hideIfNoFile' => $hideIfNoFile,
					'showDelLink' => $showDelLink,
				])
				: '';

			$name = $field;

			if ($this->hasInputAttribute($field, 'multiple'))
			{
				$name .= '[]';
			}

			$this->inputs[$field] = $this->isFlag($field, 'static')
				? '<input type="hidden" name="' . $field . '" value="' . $v . '">'
				: '<div class="file-input-wrapper" data-caption="' . $this->L('choose_file') .
                '"><input type="file" name="' . $name . '" value="" size="70"' .
                $this->getInputAttributesString($field) . '></div>';

			$this->force_inputs_fields[$field] = true;
		}

		return $this;
	}

	function set_cover_pic_input($field, $rs, $path, $cols = 3)
	{
		global $db;
		$path2 = '/' . get_pics_folder($this->table);

		$orig_r = false;

		$ar = array();
		while ($r = $db->fetch($rs))
		{
			$class = $r->id == $this->getData($field) ? " class=\"cover_pic_selected\"" : "";

			if ($class)
				$orig_r = $r;

			$ar[] = " <td><a href=\"javascript:set_cover_pic('$field', $r->id);\" id=\"a_{$field}_$r->id\"$class>".$this->get_pic_html_tag(3, $path.$r->pic, $r->pic_tn_w, $r->pic_tn_h)."</a></td>";
		}

		if (isset($this->rec->pic) && !empty($path2))
		{
			$class = !$this->getData($field) ? " class=\"cover_pic_selected\"" : "";
			$img0 = $this->rec->pic
				? $this->get_pic_html_tag(3, $path2.$this->rec->pic, $this->rec->pic_w, $this->rec->pic_h)
				: "<div class=\"cover-note\" style=\"width: ".(\diConfiguration::get($this->table."_tn_width") + 8)."px;\">Обложка будет<br>автоматически создана</div>".$this->get_pic_html_tag(3, "/i/z.gif", \diConfiguration::get($this->table."_tn_width"), \diConfiguration::get($this->table."_tn_height"));

			$ar[] = " <td><a href=\"javascript:set_cover_pic('$field', 0);\" id=\"a_{$field}_0\"$class>".$img0."</a></td>";
		}

		$html = "<input type=\"hidden\" id=\"$field\" name=\"$field\" value=\"{$this->getData($field)}\" />\n";
		if ($orig_r)
			$html .= "<div id=\"current_img_{$field}\" style=\"margin: 5px 0;\">".$this->get_pic_html_tag(3, $path.$orig_r->pic, $orig_r->pic_tn_w, $orig_r->pic_tn_h)."</div>\n";
		$html .= "<div id=\"current_a_{$field}\" style=\"margin: 5px 0;\">[ <a href=\"javascript:show_cover_pic_table('$field');\">Выбрать".($orig_r ? " другую" : "")."</a> ]</div>\n";
		$html .= "<table class=\"cover_pic_select\" id=\"table_{$field}\">\n";

		$rows_count = ceil(count($ar) / $cols);
		for ($i = 0; $i < $rows_count; $i++)
		{
			$html .= "<tr>".join("\n", array_slice($ar, $i * $cols, $cols))."</tr>\n";
		}

		$html .= "</table>\n";

		$this->inputs[$field] = $html;

		$this->force_inputs_fields[$field] = true;

		return $this;
	}

	function set_cover_video_input($field, $rs, $path = false, $cols = 3)
	{
		global $db;
		$path2 = "/".get_pics_folder($this->table);

		$orig_r = false;

		$albums_ar = array();

		$ar = array();
		while ($r = $db->fetch($rs))
		{
			$class = $r->id == $this->getData($field) ? " cover_pic_selected" : "";

			if ($class)
				$orig_r = $r;

			$embed = $this->get_video_html_tag($r, $path, 300);
			//$embed = "$embed";
			//$embed = "<img width={$this->last_video_w} height={$this->last_video_h} src=\"/i/z.gif\" style=\"background: #ff0;\">";

			$album_r = isset($albums_ar[$r->album_id]) ? $r->album_id : ($r->album_id ? $db->r("albums", $r->album_id) : false);
			$album_title = $album_r ? " ($album_r->title)" : "";

			$ar[] = " <td><a href=\"javascript:set_cover_pic('$field', $r->id);\" id=\"a_{$field}_$r->id\" class=\"$class video-tn\" style=\"width: {$this->last_video_w}px; height: {$this->last_video_h}px;\"><img class=\"video\" width={$this->last_video_w} height={$this->last_video_h} src=\"/i/z.gif\"></a>$embed".
				"<div style='text-align: center; margin-top: 5px;'>{$r->title}{$album_title}</div>".
				"</td>";
		}

		$html = "<input type=\"hidden\" id=\"$field\" name=\"$field\" value=\"{$this->getData($field)}\" />\n";
		if ($orig_r)
			$html .= "<div id=\"current_img_{$field}\" style=\"margin: 5px 0;\">".$this->get_video_html_tag($orig_r, $path, 300)."</div>\n";
		$html .= "<div id=\"current_a_{$field}\" style=\"margin: 5px 0;\">[ <a href=\"javascript:show_cover_pic_table('$field');\">Выбрать".($orig_r ? " другое видео" : "")."</a> ]</div>\n";
		$html .= "<table class=\"cover_pic_select cover_video_select\" id=\"table_{$field}\">\n";

		$rows_count = ceil(count($ar) / $cols);
		for ($i = 0; $i < $rows_count; $i++)
		{
			$html .= "<tr>".join("\n", array_slice($ar, $i * $cols, $cols))."</tr>\n";
		}

		$html .= "</table>\n";

		$this->inputs[$field] = $html;

		$this->force_inputs_fields[$field] = true;

		return $this;
	}

	function get_video_html_tag($video_r, $path = false, $w = 0, $h = 0)
	{
		global $videos_pics_folder;

		if ($path === false)
		{
			$path = $videos_pics_folder;
		}

		if (!empty($video_r->embed))
		{
			list($video_r->embed, $video_w, $video_h) = get_video_embed_and_dimensions($video_r, $w, $h);

			$this->last_video_w = $video_w;
			$this->last_video_h = $video_h;

			return $video_r->embed;
		}
		elseif (!empty($video_r->file))
		{
			/*
			$videos_folder = $path;
			$pics_folder = $GLOBALS["{$table}_pics_folder"];

			$pic = isset($video_r->flv_pic) ? $video_r->flv_pic : $video_r->pic;

			if (!isset($FLV_PLAYER_IDX)) $FLV_PLAYER_IDX = 0;

			$this->tpl->assign(array(
				"PLAYER_IDX" => ++$FLV_PLAYER_IDX,
				//"PLAYER_FLV" => "/video/$video_r->id.flv",
				"PLAYER_FLV" => "/".$videos_folder.$video_r->file,
				"PLAYER_FLV_W" => $video_r->width,
				"PLAYER_FLV_H" => $video_r->height,
				//"PLAYER_FLV_H" => $video_r->video_h + 45,
				"PLAYER_PREVIEW" => "/".$pics_folder.$pic,
			));

			$this->last_video_w = $video_w;
			$this->last_video_h = $video_h;

			return $this->tpl->parse($token_name, "flv_player");
			*/
			throw new \Exception("[this is not implemented yet. diadminform::get_video_html_tag()]");
		}
		else
		{
			throw new \Exception("[video#$video_r->id is empty]");
		}
	}

	function get_pic_html_tag($type, $path, $width, $height)
	{
		return $type == 4 || $type == 13
			? "<script type=\"text/javascript\">run_movie(\"$path\", \"$width\", \"$height\", \"opaque\");</script>"
			: "<img src=\"$path\" width=\"$width\" height=\"$height\" alt=\"\" />";
	}

	function get_dynamic_pic_row($id, $field, $pic_r)
	{
		global $tn_folder, $orig_folder;

		$img_tag = $pic_r
			? $this->getPreviewHtmlForFile($field, "/" . get_pics_folder($this->getTable()) . $pic_r->pic, [
				'hideIfNoFile' => true,
				'showDelLink' => false,
			])
			: "";
		//$orig_img_tag = $pic_r ? $this->get_pic_html_for_input($field, "/".get_pics_folder($this->getTable()).$orig_folder.$pic_r->pic, true, false) : "";
		$tn_img_tag = $pic_r && $pic_r->pic_tn
			? $this->getPreviewHtmlForFile($field, "/".get_pics_folder($this->getTable()).$tn_folder.$pic_r->pic_tn, [
				'hideIfNoFile' => true,
				'showDelLink' => false,
			])
			: "";

		//if ($this->table == "items" && $field == "pics")
		//	$img_tag = $orig_img_tag;

		$callback = $this->getFieldProperty($field, "form_fields_callback");

		$additional_html = $callback && is_callable($callback) ? $callback($id, $field, $pic_r, $this) : "";

		$order_num = $pic_r ? $pic_r->order_num : "";

		$by_default_checked = $pic_r && $pic_r->by_default ? " checked=\"checked\"" : "";
		$by_default_text = $by_default_checked ? " Заглавная" : "";

		$visible_checked = ($pic_r && $pic_r->visible) || !$pic_r ? " checked=\"checked\"" : "";
		$visible_text = $visible_checked ? " Отображается" : " Не отображается";

		$title = $pic_r ? StringHelper::out($pic_r->title) : "";
		$content = $pic_r ? StringHelper::out($pic_r->content) : "";

		return $this->is_flag($field, "static") || $this->static_mode

			? "<div id=\"{$field}_div[{$id}]\" class=\"dynamic-row\">".
			$img_tag.
			$tn_img_tag.
			//"#{$order_num}".
			"<div>{$additional_html} {$by_default_text}{$visible_text}</div>".
			//"<div>$title</div>".
			"<div>$content</div>".
			"</div>"

			: "<div id=\"{$field}_div[{$id}]\" class=\"dynamic-row\">".
			"<a href=\"#\" onclick=\"return dipics_{$this->table}.remove('{$field}',{$id});\" class=\"close\"></a>".
			$img_tag.
			$tn_img_tag.
			"<div>".
			"# <input type=\"text\" id=\"{$field}_order_num[{$id}]\" name=\"{$field}_order_num[{$id}]\" value=\"{$order_num}\" size=\"4\" /> ".
			"Загрузить: <div class=\"file-input-wrapper\" data-caption=\"{$this->L('choose_file')}\"><input type=\"file\" id=\"{$field}_pic[{$id}]\" name=\"{$field}_pic[{$id}]\" size=\"5\"></div> ".
			//"Название: <input type=\"text\" id=\"{$field}_title[{$id}]\" name=\"{$field}_title[{$id}]\" value=\"{$title}\" size=\"20\" />, ".
			$additional_html.
			"<input type=\"radio\" id=\"{$field}_by_default[{$id}]\" name=\"{$field}_by_default\" value=\"$id\"$by_default_checked style=\"border:0;\" /> <label for=\"{$field}_by_default[{$id}]\">Заглавная</label> ".
			"<input type=\"checkbox\" id=\"{$field}_visible[{$id}]\" name=\"{$field}_visible[{$id}]\" value=\"1\"$visible_checked /> <label for=\"{$field}_visible[{$id}]\">Отображать</label>".
			"</div>".
			"<div class=m>".
			"<textarea id=\"{$field}_content[{$id}]\" name=\"{$field}_content[{$id}]\" cols=\"100\" rows=\"4\" placeholder=\"Описание\">{$content}</textarea>".
			//"Превью (для FLASH): <input type=\"file\" id=\"{$field}_pic_tn[{$id}]\" name=\"{$field}_pic_tn[{$id}]\" size=\"10\" />".
			"</div>".
			"</div>";
	}

	function set_dynamic_pics_input($field)
	{
		$s = ""; //"<div style=\"margin: 9px 0 5px 0;\">[<a href=\"#\" onclick=\"return dipics_{$this->table}.add('$field');\">Добавить +</a>]:</div>\n";
		$last_ref_idx = 0;
		$btnAdd = "<div class='dynamic-add'><a href='#' onclick=\"return dipics_{$this->table}.add('$field');\" class=\"simple-button\">{$this->L('add_item')}</a></div>\n";

		$pic_rs = $this->getDb()->rs($this->pics_table, "WHERE _table='$this->table' and _field='$field' and _id='$this->id' ORDER BY order_num ASC");

		if ($this->getDb()->count($pic_rs))
		{
			$s .= $btnAdd;
		}

		while ($pic_r = $this->getDb()->fetch($pic_rs)) {
			$s .= $this->get_dynamic_pic_row($pic_r->id, $field, $pic_r);

			if ($pic_r->order_num > $last_ref_idx) {
				$last_ref_idx = $pic_r->order_num;
			}
		}

		//$this->uploaded_images[$field] = $s;

		$s .= "<div id=\"{$field}_anchor_div\"></div>";
		$s .= "<div id=\"js_{$field}_resource\" style=\"display:none;\">".$this->get_dynamic_pic_row("%NEWID%", $field, false)."</div>";
		$s .= "<script type=\"text/javascript\">\nif (typeof dipics_{$this->table} == 'undefined') var dipics_{$this->table} = new diDynamicRows();\ndipics_{$this->table}.init('$field', 'изображение', 1, $last_ref_idx);\n</script>\n";
		$s .= $btnAdd;

		$this->inputs[$field] = $s;

		$this->force_inputs_fields[$field] = true;

		return $this;
	}

	function get_dynamic_file_row($id, $field, $pic_r)
	{
		$img_tag = $pic_r
			? $this->get_file_html_for_input($field, "/".get_pics_folder($this->getTable()).$pic_r->pic, true, false)
			: "";

		$order_num = $pic_r ? $pic_r->order_num : "";

		$by_default_checked = $pic_r && $pic_r->by_default ? " checked=\"checked\"" : "";
		$by_default_text = $by_default_checked ? ", Заглавная" : "";

		$visible_checked = ($pic_r && $pic_r->visible) || !$pic_r ? " checked=\"checked\"" : "";
		$visible_text = $visible_checked ? ", Отображается" : "";

		$title = $pic_r ? StringHelper::out($pic_r->title) : "";
		$content = $pic_r ? StringHelper::out($pic_r->content) : "";

		$a = $this->getAllFields();

		return $this->is_flag($a[$field], "static") || $this->static_mode
			?"<div id=\"{$field}_div[{$id}]\" class=\"dynamic-row\">".
			$img_tag.
			//$tn_img_tag.
			//"#{$order_num}".
			//"{$title_text}{$by_default_text}{$visible_text}".
			//"<div>$title</div>".
			"<div>$content</div>".
			"</div>"
			:"<div id=\"{$field}_div[{$id}]\" class=\"dynamic-row\">".
			"<a href=\"#\" onclick=\"return dipics_{$this->table}.remove('{$field}',{$id});\" class=\"close\"></a>".
			$img_tag.
			//$tn_img_tag.
			"<div>".
			"# <input type=\"text\" id=\"{$field}_order_num[{$id}]\" name=\"{$field}_order_num[{$id}]\" value=\"{$order_num}\" size=\"4\" /> ".
			"Загрузить: <div class=\"file-input-wrapper\" data-caption=\"{$this->L('choose_file')}\"><input type=\"file\" id=\"{$field}_pic[{$id}]\" name=\"{$field}_pic[{$id}]\" size=\"10\"></div> ".
			//"Название: <input type=\"text\" id=\"{$field}_title[{$id}]\" name=\"{$field}_title[{$id}]\" value=\"{$title}\" size=\"20\" />, ".
			//"<input type=\"radio\" id=\"{$field}_by_default[{$id}]\" name=\"{$field}_by_default\" value=\"$id\"$by_default_checked style=\"border:0;\" /> <label for=\"{$field}_by_default[{$id}]\">Заглавная</label>, ".
			"<input type=\"checkbox\" id=\"{$field}_visible[{$id}]\" name=\"{$field}_visible[{$id}]\" value=\"1\"$visible_checked /> <label for=\"{$field}_visible[{$id}]\">Отображать</label>".
			"</div>".
			"<div class=m>".
			"<textarea id=\"{$field}_content[{$id}]\" name=\"{$field}_content[{$id}]\" cols=\"100\" rows=\"4\">{$content}</textarea>".
			//"Превью (для FLASH): <input type=\"file\" id=\"{$field}_pic_tn[{$id}]\" name=\"{$field}_pic_tn[{$id}]\" size=\"10\" />".
			"</div>".
			"</div>";
	}

	function set_dynamic_files_input($field)
	{
		global $db;

		$s = ""; //"<div style=\"margin: 9px 0 5px 0;\">[<a href=\"#\" onclick=\"return dipics_{$this->table}.add('$field');\">Добавить +</a>]:</div>\n";
		$last_ref_idx = 0;

		$pic_rs = $db->rs($this->pics_table, "WHERE _table='$this->table' and _field='$field' and _id='$this->id' ORDER BY order_num ASC");
		while ($pic_r = $db->fetch($pic_rs))
		{
			$s .= $this->get_dynamic_file_row($pic_r->id, $field, $pic_r);

			if ($pic_r->order_num > $last_ref_idx)
				$last_ref_idx = $pic_r->order_num;
		}

		$this->uploaded_images[$field] = $s;

		$s .= "<div id=\"{$field}_anchor_div\"></div>";
		$s .= "<div id=\"js_{$field}_resource\" style=\"display:none;\">".$this->get_dynamic_pic_row("%NEWID%", $field, false)."</div>";

		$s .= "<script type=\"text/javascript\">\nif (typeof dipics_{$this->table} == 'undefined') var dipics_{$this->table} = new diDynamicRows();\ndipics_{$this->table}.init('$field', 'файл', 1, $last_ref_idx);\n</script>\n";

		$s .= "<div style=\"margin: 9px 0 5px 0;\">[<a href=\"#\" onclick=\"return dipics_{$this->table}.add('$field');\">Добавить +</a>]</div>\n";

		$this->inputs[$field] = $s;

		$this->force_inputs_fields[$field] = true;

		return $this;
	}

	/** @deprecated */
	function set_cb_list_input($field, $feed, $columns = null, $ableToAddNew = null)
	{
		return $this->setCheckboxesListInput($field, $feed, $columns, $ableToAddNew);
	}

	public function setCheckboxesListInput($field, $feed = null, $columns = null, $ableToAddNew = null)
	{
		if (is_null($feed)) {
			$feed = $this->getFieldProperty($field, 'feed') ?: $this->getFieldOption($field, 'feed');
		}

		if (!$feed) {
			throw new \Exception('Checkboxes feed not defined');
		}

		if (is_null($columns)) {
			$columns = $this->getFieldOption($field, 'columns') ?: 2;
		}

		if (is_null($ableToAddNew)) {
			$ableToAddNew = $this->getFieldOption($field, 'ableToAddNew') ?: false;
		}

		// field name or function($feedModel, $targetTable, $targetField, $targetId)
        // todo: use ($Form, $field) here after feedModel
		$titleGetter = $this->getFieldOption($field, 'titleGetter') ?: 'title';
		$defaultTitleField = is_string($titleGetter)
            ? $titleGetter
            : 'title';

		if (\diDB::is_rs($feed)) {
			$tmpFeed = [];

			while ($r = $this->getDb()->fetch($feed)) {
				$tmpFeed[$r->id] = $r->title;
			}

			$feed = $tmpFeed;
			unset($tmpFeed);
		}

		$values = $this->getFieldProperty($field, 'values') ?: $this->getData($field);

		if (is_callable($values)) {
		    $values = $values($this->getTable(), $field, $this->getId());
        } elseif (!is_array($values))  {
			$values = explode(',', $values);
		}

        $checkedHelper = $this->getFieldOption($field, 'checkedHelper') ?:
            function($k, $v, Form $Form, $field) use($values) {
                return
                    (
                        is_string($this->getData($field)) &&
                        StringHelper::contains(',' . $this->getData($field) . ',', ',' . $k . ',')
                    ) ||
                    in_array($k, $values);
            };

		if ($this->isStatic($field)) {
			$ar = [];

			foreach ($values as $k) {
				$ar[] = isset($feed[$k]) ? $feed[$k] : "[tag#{$k}]";
			}

			$table = $ar ? join(', ', $ar) : '&mdash;';
		} else {
			$tags = [];

			foreach ($feed as $k => $v) {
                $v = is_array($v) || $v instanceof \diModel
                    ? $v
                    : [
                        $defaultTitleField => $v,
                    ];

				if (is_array($v)) {
					$v = extend([
						'enabled' => true,
					], $v);
				} elseif ($v instanceof \diModel) {
					if ($v->getRelated('enabled') === null) {
						$v->setRelated('enabled', true);
					}
				}

				$checked = $checkedHelper($k, $v, $this, $field);

                $disabled =
                    $this->static_mode ||
                    (is_array($v) && empty($v['enabled'])) ||
                    ($v instanceof \diModel && !$v->getRelated('enabled'));

				$attributes = [
					'type' => 'checkbox',
					'name' => $field . '[]',
					'value' => $k,
					'id' => $field . '[' . $k . ']',
				];

				if ($checked) {
					$attributes['checked'] = 'checked';
				}

				if ($disabled) {
					$attributes['disabled'] = 'true';
				}

				$title = is_callable($titleGetter)
                    ? $titleGetter($v, $this->getTable(), $field, $this->getId())
                    : $v[$titleGetter];

                // string or function($feedModel, $callbackParams)
				$outerPrefix = $this->getFieldOption($field, 'outerPrefix') ?: '';
				$outerSuffix = $this->getFieldOption($field, 'outerSuffix') ?: '';
                $innerPrefix = $this->getFieldOption($field, 'innerPrefix') ?: '';
                $innerSuffix = $this->getFieldOption($field, 'innerSuffix') ?: '';

                if (is_callable($outerPrefix)) {
                    $outerPrefix = $outerPrefix($v, $this, $field);
                }

                if (is_callable($outerSuffix)) {
                    $outerSuffix = $outerSuffix($v, $this, $field);
                }

                if (is_callable($innerPrefix)) {
                    $innerPrefix = $innerPrefix($v, $this, $field);
                }

                if (is_callable($innerSuffix)) {
                    $innerSuffix = $innerSuffix($v, $this, $field);
                }

				$tags[] = sprintf(
					'<input %s> %s<label for="%s">%s%s%s</label>%s',
					ArrayHelper::toAttributesString($attributes),
					$outerPrefix,
					$attributes['id'],
					$innerPrefix,
					$title,
                    $innerSuffix,
                    $outerSuffix
				);
			}

			$table = '';

			if ($tags) {
			    $toggle = sprintf(
			        '<div class="tags-toggle"><span data-purpose="toggle-on">%s</span><span data-purpose="toggle-off">%s</span></div>',
                    self::L('tag.toggle_on'),
                    self::L('tag.toggle_off')
                );

				$table = '<div class="tags-grid">' . $toggle . '<table><tr>';

				$per_column = ceil(count($tags) / $columns);

				for ($i = 0; $i < $columns; $i++) {
					$table .= '<td style="padding-right: 20px; vertical-align: top;">' .
						join('<br />', array_slice($tags, $per_column * $i, $per_column)) .
						'</td>';
				}

				$table .= '</tr></table></div>';
			}

			if ($ableToAddNew) {
				$table .=
					'<div class="new-tag">' .
					'<input type="text" name="' . $field . self::NEW_FIELD_SUFFIX . '" value="" placeholder="' .
                    $this->L('tag.enter_new') . '">' .
					'</div>';
			}
		}

		$this->inputs[$field] = $table;
		$this->force_inputs_fields[$field] = true;

		return $this;
	}

	public function setTagsInput($field, $columns = null, $ableToAddNew = null)
	{
		/** @var \diTags $class */
		$class = $this->getFieldOption($field, 'class') ?: \diTags::class;
		/** @var \diTags $instance */
		$instance = new $class;

		if ($feed = $this->getFieldProperty($field, 'feed') ?: $this->getFieldOption($field, 'feed'))
		{
			$instance
				->setFeed($feed);
		}

		$this
			->setData($field, $class::tagIdsAr(\diTypes::getId($this->getTable()), $this->getId()))
			->setCheckboxesListInput($field, $instance->getFeed(), $columns, $ableToAddNew);
	}

	public function get_datetime_input($table, $field, $value, $date = true, $time = false, $calendar_cfg = true)
	{
		if ($value && $value != "0000-00-00 00:00:00")
		{
			$str_field_type = substr($this->getFieldProperty($field, "type"), -4) == "_str" ?: -1;

			if ($str_field_type == -1)
			{
				$str_field_type = !is_numeric($value);
			}

			$v = getdate($str_field_type ? strtotime($value) : $value);

			$dy = $v["year"];
			$dm = lead0($v["mon"]);
			$dd = lead0($v["mday"]);
			$th = lead0($v["hours"]);
			$tm = lead0($v["minutes"]);
		}
		else
		{
			$dy = "";
			$dm = "";
			$dd = "";
			$th = "";
			$tm = "";
		}

		$ph = [
		    'dd' => $this->getFieldOption($field, 'use_placeholder') ? self::L('placeholder.date.day') : '',
            'dm' => $this->getFieldOption($field, 'use_placeholder') ? self::L('placeholder.date.month') : '',
            'dy' => $this->getFieldOption($field, 'use_placeholder') ? self::L('placeholder.date.year') : '',
            'th' => $this->getFieldOption($field, 'use_placeholder') ? self::L('placeholder.time.hour') : '',
            'tm' => $this->getFieldOption($field, 'use_placeholder') ? self::L('placeholder.time.minute') : '',
        ];

		$d = "<input type=\"text\" name=\"{$field}[dd]\" id=\"{$field}[dd]\" value=\"$dd\" size=\"2\" placeholder=\"{$ph['dd']}\">" .
            "<span class='date-sep'>.</span>" .
			"<input type=\"text\" name=\"{$field}[dm]\" id=\"{$field}[dm]\" value=\"$dm\" size=\"2\" placeholder=\"{$ph['dm']}\">" .
            "<span class='date-sep'>.</span>" .
			"<input type=\"text\" name=\"{$field}[dy]\" id=\"{$field}[dy]\" value=\"$dy\" size=\"4\" placeholder=\"{$ph['dy']}\">";

		$t = "<input type=\"text\" name=\"{$field}[th]\" id=\"{$field}[th]\" value=\"$th\" size=\"2\" placeholder=\"{$ph['th']}\">" .
            "<span class='time-sep'>:</span>" .
			"<input type=\"text\" name=\"{$field}[tm]\" id=\"{$field}[tm]\" value=\"$tm\" size=\"2\" placeholder=\"{$ph['tm']}\">";

		$input = "";
		if ($date) $input .= $d;
		if ($input) $input .= "&nbsp;";
		if ($time) $input .= $t;

		if ($date && $calendar_cfg)
		{
			//$uid = substr(get_unique_id(), 0, 8);
			$uid = "{$table}_{$field}";

			if ($calendar_cfg === true)
			{
				$calendar_cfg_js = "months_to_show: 1, date1: '$field', able_to_go_to_past: true";
			}
			else
			{
				$calendar_cfg_js = $calendar_cfg;
			}

			$input .= <<<EOF
 <button type="button" onclick="c_{$uid}.toggle();" class="calendar-toggle w_hover">{$this->L("calendar")}</button>

<script type="text/javascript">
var c_{$uid} = new diCalendar({
	instance_name: 'c_{$uid}',
	position_base: 'parent',
	language: '{$this->getX()->getLanguage()}',
	$calendar_cfg_js
});
</script>
EOF;
		}

		return $input;
	}

	function set_datetime_input($field, $date = true, $time = false, $calendar_cfg = true)
	{
		$this->inputs[$field] = $this->get_datetime_input($this->table, $field, $this->getData($field), $date, $time, $calendar_cfg);

		$this->force_inputs_fields[$field] = true;

		return $this;
	}

	function set_eng_datetime_input($field, $date = true, $time = false)
	{
		$v = getdate($this->getData($field));
		$dy = $v["year"];
		$dm = lead0($v["mon"]);
		$dd = lead0($v["mday"]);
		$th = lead0($v["hours"]);
		$tm = lead0($v["minutes"]);

		$d = "<input type=\"text\" name=\"{$field}[dm]\" value=\"$dm\" size=\"2\"> / ".
			"<input type=\"text\" name=\"{$field}[dd]\" value=\"$dd\" size=\"2\"> / ".
			"<input type=\"text\" name=\"{$field}[dy]\" value=\"$dy\" size=\"4\">";

		$t = "<input type=\"text\" name=\"{$field}[th]\" value=\"$th\" size=\"2\"> : ".
			"<input type=\"text\" name=\"{$field}[tm]\" value=\"$tm\" size=\"2\">";

		$this->inputs[$field] = "";
		if ($date) $this->inputs[$field] .= $d;
		if ($this->inputs[$field]) $this->inputs[$field] .= " ";
		if ($time) $this->inputs[$field] .= $t;

		$this->force_inputs_fields[$field] = true;

		return $this;
	}

	private function setManualFieldFlag($field, $flag)
	{
		if (!isset($this->manualFieldFlags[$field]))
		{
			$this->manualFieldFlags[$field] = [];
		}

		if (!in_array($flag, $this->manualFieldFlags[$field]))
		{
			$this->manualFieldFlags[$field][] = $flag;
		}

		return $this;
	}

	private function resetManualFieldFlag($field, $flag)
	{
		if (isset($this->manualFieldFlags[$field]))
		{
			if (($key = array_search($flag, $this->manualFieldFlags[$field])) !== false)
			{
				unset($this->manualFieldFlags[$field][$flag]);
			}
		}

		return $this;
	}

	private function mergeManualFieldFlags($fields)
	{
		foreach ($this->manualFieldFlags as $field => $flags)
		{
			if (isset($fields[$field]))
			{
				if (!isset($fields[$field]["flags"]))
				{
					$fields[$field]["flags"] = [];
				}

				$fields[$field]["flags"] = array_merge($fields[$field]["flags"], $flags);
			}
		}

		return $fields;
	}

	/** @deprecated */
	function set_hidden_input($field)
	{
		return $this->setHiddenInput($field);
	}

	public function setHiddenInput($fields)
	{
		if (!is_array($fields))
		{
			$fields = explode(",", $fields);
		}

		foreach ($fields as $field)
		{
			$this->setManualFieldFlag($field, "hidden");

			$this->force_inputs_fields[$field] = true;
		}

		return $this;
	}

	/** @deprecated */
	function set_static_input($field)
	{
		return $this->setStaticInput($field);
	}

	public function setStaticInput($fields)
	{
		if (!is_array($fields))
		{
			$fields = explode(',', $fields);
		}

		foreach ($fields as $field)
		{
			$this->setManualFieldFlag($field, 'static');

			$this->force_inputs_fields[$field] = true;
		}

		return $this;
	}

	function set_dynamic_input($field)
	{
		$dr = new \diDynamicRows($this->AdminPage, $field);
		$dr->static_mode = $this->static_mode || $this->isFlag($field, 'static');

		$this->inputs[$field] = $dr->get_html();
		$this->force_inputs_fields[$field] = true;

		return $this;
	}

	public function setColorInput($field)
	{
		if (preg_match("/^[a-f0-9]{6}$/i", $this->getData($field)))
		{
			$this->setData($field, "#" . $this->getData($field));
		}

		$view = "<div data-purpose=\"color-view\" data-field=\"$field\" style=\"background: {$this->getData($field)}\"></div>";

		if (!$this->static_mode)
		{
			$this->inputs[$field] = "<input type=\"hidden\" name=\"$field\" value=\"{$this->getData($field)}\" />" .
				$view .
				"<div data-purpose=\"color-picker\" data-field=\"$field\"></div>";
		}
		else
		{
			$this->inputs[$field] = $view . " " . $this->getData($field);
		}

		$this->force_inputs_fields[$field] = true;

		return $this;
	}

	public function setFontInput($field)
	{
		$prefixAr = \diWebFonts::$titlesExtended;
		$prefixAr = array_merge(array(0 => "Не выбран",), $prefixAr);

		$this->setSelectFromCollectionInput($field,
			\diCore\Data\Font\Cache::getInstance()->getFonts(),
			function(\diFontModel $f) {
				return [
					'value' => $f->getToken(),
					'text' => $f->getToken() . ' &ndash; ' . $f->getTitle() . '',
				];
			},
			$prefixAr
		);

		return $this;
	}

	public function setIpInput($field)
	{
		$ip = $this->getData($field);
		if (is_numeric($ip))
		{
			$ip = bin2ip($this->getData($field));
		}

		$this->setData($field, $ip);

		if (!$this->isStatic($field))
		{
			$this->setSimpleInput($field);
		}

		return $this;
	}

	function set_select_file_input($field, $path, $ext_ar = array(), $kill_ext = true)
	{
		if (!is_array($ext_ar))
		{
			$ext_ar = [$ext_ar];
		}

		foreach ($ext_ar as $k => $v)
		{
			if ($ext_ar[$k] && $ext_ar[$k]{0} != ".")
			{
				$ext_ar[$k] = "." . $ext_ar[$k];
			}
		}

		$sel = new \diSelect($field, $this->getData($field));
		$sel->addItem("", "Не выбрано");

		$ar = FileSystemHelper::folderContents("{$_SERVER["DOCUMENT_ROOT"]}/{$path}");
		foreach ($ar["f"] as $fn)
		{
			$ext = StringHelper::fileExtension($fn);
			if ($ext)
			{
				$ext = ".$ext";
			}

			if (in_array($ext, $ext_ar))
			{
				$short_fn = $kill_ext ? pathinfo($fn, PATHINFO_FILENAME) : $fn;

				$sel->addItem($short_fn, $short_fn);
			}
		}

		$this->inputs[$field] = $sel;

		$this->force_inputs_fields[$field] = true;

		return $this;
	}

	function set_video_pic_input($field, $base_name, $cols = 3)
	{
		global $video_thumbs_count;

		$orig_fn = false;

		$ar = array();
		for ($i = 1; $i <= $video_thumbs_count; $i++)
		{
			$fn = $base_name."-$i.jpg";

			if (!is_file($_SERVER["DOCUMENT_ROOT"].$fn))
				continue;

			$class = $i == $this->getData($field) ? " class=\"cover_pic_selected\"" : "";

			if ($class)
				$orig_fn = $fn;

			$ar[] = " <td><a href=\"javascript:set_cover_pic('$field', $i);\" id=\"a_{$field}_{$i}\"$class>".$this->get_pic_html_tag(3, $fn, 300, null)."</a></td>";
		}

		$html = "<input type=\"hidden\" id=\"$field\" name=\"$field\" value=\"{$this->getData($field)}\" />\n";
		if ($orig_fn)
			$html .= "<div id=\"current_img_{$field}\" style=\"margin: 5px 0;\">".$this->get_pic_html_tag(3, $orig_fn, 300, null)."</div>\n";
		$html .= "<div id=\"current_a_{$field}\" style=\"margin: 5px 0;\">[ <a href=\"javascript:show_cover_pic_table('$field');\">Выбрать".($orig_fn ? " другую" : "")."</a> ]</div>\n";
		$html .= "<table class=\"cover_pic_select\" id=\"table_{$field}\">\n";

		$rows_count = ceil(count($ar) / $cols);
		for ($i = 0; $i < $rows_count; $i++)
		{
			$html .= "<tr>".join("\n", array_slice($ar, $i * $cols, $cols))."</tr>\n";
		}

		$html .= "</table>\n";

		$this->inputs[$field] = $html;

		$this->force_inputs_fields[$field] = true;

		return $this;
	}
}