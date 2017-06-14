<?php

use diCore\Tool\CollectionCache;
use diCore\Entity\DynamicPic\Collection as dpCol;

/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 04.06.2015
 * Time: 14:33
 */

class diAdminTasksPage extends diAdminBasePage
{
	protected $options = [
		"filters" => [
			"defaultSorter" => [
				"sortBy" => "priority",
				"dir" => "DESC",
			],
			"sortByAr" => [
				"priority" => "По приоритету",
				"due_date" => "По сроку сдачи",
				"date" => "По дате добавления",
				"id" => "По ID",
			],
		],
	];

	/** @var dpCol */
	protected $picsBefore;
	/** @var dpCol */
	protected $picsAfter;

	protected function initTable()
	{
		$this->setTable("admin_tasks");
	}

	protected function setupFilters()
	{
		$this->getFilters()
			->addFilter([
				"field" => "id",
				"type" => "string",
				"title" => "ID задачи",
				"where_tpl" => "diaf_several_ints",
			])
			->addFilter([
				"field" => "title",
				"type" => "string",
				"title" => "Название",
				"where_tpl" => "diaf_substr",
			])
			->addFilter([
				"field" => "admin_id",
				"type" => "int",
				"title" => "Исполнитель",
				"where_tpl" => "diaf_minus_one",
			])
			->addFilter([
				"field" => "priority",
				"type" => "int",
				"title" => "Приоритет",
			])
			->addFilter([
				"field" => "status",
				"type" => "string",
				"title" => "Статус",
				"where_tpl" => "diaf_several_ints",
				"default_value" => join(",", \diAdminTaskModel::statusesActual()),
				'strict' => true,
			])
			->buildQuery()
			->setSelectFromCollectionInput('admin_id',
				\diCollection::create(\diTypes::admin)->filterBy('active', 1)->orderBy('login'),
				function(\diAdminModel $admin) {
					return [
						'value' => $admin->getId(),
						'text' => $admin->getLogin(),
					];
				},
				[
					0 => "Все исполнители",
					-1 => "Не присвоен",
				]
			)
			->setSelectFromArrayInput("status", \diAdminTaskModel::statusStr(), [
				//0 => "Все",
				join(",", \diAdminTaskModel::statusesActual()) => "[ Текущие задачи ]",
			])
			->setSelectFromArrayInput("priority", \diAdminTaskModel::$priorities, [
				0 => "Все",
			]);
	}

	/*
	protected function getDefaultListRows($options = [])
	{
		$options = $this->extendListQueryOptions($options);

		$orderBy = $options["sortBy"] ? " ORDER BY t.{$options["sortBy"]} t.{$options["dir"]}" : "";

		$col = diCollection::create(diTypes::admin_task, $this->getDb()->rs(
			$this->getTable() . ' t INNER JOIN admin_task_participants p INNER JOIN admins a '.
			'ON t.id = p.task_id AND a.id = p.admin_id',
			$options["query"] . $orderBy . $options["limit"],
			't.*,' . join(',', diAdminModel::getFieldsWithTablePrefix('a.', diAdminModel::COMPLEX_QUERY_PREFIX))
		));

		return $col;
	}
	*/

	protected function cacheDataForList()
	{
		parent::cacheDataForList();

		CollectionCache::addManual(diTypes::admin, 'id', $this->getListCollection()->map('admin_id'));

		return $this;
	}

	public function renderList()
	{
		$this->getList()->addColumns([
			"id" => "ID",
			"admin_id" => [
				"value" => function(diAdminTaskModel $model) {
					/** @var diAdminModel $admin */
					$admin = CollectionCache::getModel(diTypes::admin, $model->getAdminId());

					return $admin->exists() ? $admin->getLogin() : "&ndash;";
				},
				"headAttrs" => [
					"width" => "10%",
				],
			],
			"priority" => [
				"title" => "Приоритет",
				"value" => function(diAdminTaskModel $model) {
					$icon = "<span class=\"admin-task-priority p{$model->getPriority()}\"></span>";

					return $icon . $model->getPriorityStr();
				},
				"headAttrs" => [
					"width" => "10%",
				],
				"bodyAttrs" => [
					"class" => "lite",
				],
			],
			"status" => [
				"title" => "Статус",
				"value" => function(diAdminTaskModel $model) {
					return $model->getStatusStr();
				},
				"headAttrs" => [
					"width" => "10%",
				],
				"bodyAttrs" => [
					"class" => "lite",
				],
			],
			"attaches" => [
				"title" => "*",
				"value" => function(diAdminTaskModel $model) {
					$pics = $this->getAttachedPicsCollection($model->getId());

					return count($pics) ?: "";
				},
				"bodyAttrs" => [
					"class" => "lite",
				],
			],
			"title" => [
				"title" => "Задача",
				"value" => function(diAdminTaskModel $model) {
					return $model->getTitle() . "<div class='lite'>" . str_cut_end($model->getContent(), 150) . "</div>";
				},
				"headAttrs" => [
					"width" => "50%",
				],
			],
			"date" => [
				"title" => "Добавлено",
				"value" => function(diAdminTaskModel $model, $field) {
					return diDateTime::format("d.m.Y H:i", $model->get($field));
				},
				"headAttrs" => [
					"width" => "10%",
				],
				"bodyAttrs" => [
					"class" => "dt",
				],
			],
			"due_date" => [
				"title" => "Срок сдачи",
				"value" => function(diAdminTaskModel $model, $field) {
					return diDateTime::format("d.m.Y H:i", $model->get($field));
				},
				"headAttrs" => [
					"width" => "10%",
				],
				"bodyAttrs" => [
					"class" => "dt",
				],
			],
			"#edit" => "",
			"#del" => "",
		]);

		if (!$this->getAdmin()->isAdminSuper())
		{
			$this->getList()->removeColumn([
				'#del',
			]);
		}
	}

	public function renderForm()
	{
		$this->getForm()
			->setSelectFromCollectionInput('admin_id',
				diCollection::create(diTypes::admin)->filterBy('active', 1)->orderBy('login'),
				function(diAdminModel $admin) {
					return [
						'value' => $admin->getId(),
						'text' => $admin->getLogin(),
					];
				},
				['' => "Не выбран"]
			)
			->setSelectFromArrayInput("status", \diAdminTaskModel::statusStr())
			->setSelectFromArrayInput("priority", \diAdminTaskModel::priorityStr());

		if (!$this->getId())
		{
			$this->getForm()
				->setHiddenInput("log")
				->setHiddenInput("id");
		}
		else
		{
			$this->getForm()
				->setTemplateForInput("log", "`_snippets/actions_log", "block");
		}
	}

	public function submitForm()
	{
		if ($this->getId())
		{
			if ($this->getSubmit()->wasFieldChanged("status"))
			{
				diActionsLog::act(diTypes::admin_task, $this->getId(), diActionsLog::aStatusChanged,
					$this->getSubmit()->getCurRec("status") . "," . $this->getSubmit()->getData("status"));
			}

			if ($this->getSubmit()->wasFieldChanged("priority"))
			{
				diActionsLog::act(diTypes::admin_task, $this->getId(), diActionsLog::aPriorityChanged,
					$this->getSubmit()->getCurRec("priority") . "," . $this->getSubmit()->getData("priority"));
			}

			if ($this->getSubmit()->wasFieldChanged("admin_id"))
			{
				diActionsLog::act(diTypes::admin_task, $this->getId(), diActionsLog::aOwned,
					$this->getSubmit()->getCurRec("admin_id") . "," . $this->getSubmit()->getData("admin_id"));
			}

			if ($this->getSubmit()->wasFieldChanged(["title", "content", "date", "due_date"]))
			{
				diActionsLog::act(diTypes::admin_task, $this->getId(), diActionsLog::aEdited);
			}
		}
	}

	/**
	 * @return dpCol
	 * @throws Exception
	 */
	public function getAttachedPicsCollection($id = null)
	{
		return dpCol::createByTarget($this->getTable(), $id ?: $this->getId(), "pics")->load();
	}

	protected function beforeSubmitForm()
	{
		$result = parent::beforeSubmitForm();

		$this->picsBefore = $this->getAttachedPicsCollection();

		return $result;
	}

	protected function afterSubmitForm()
	{
		parent::afterSubmitForm();

		if ($this->isNew())
		{
			diActionsLog::act(diTypes::admin_task, $this->getId(), diActionsLog::aAdded);
		}
		else
		{
			$this->picsAfter = $this->getAttachedPicsCollection();

			/** @var diDynamicPicModel $pic */
			foreach ($this->getDeletedPics() as $pic)
			{
				diActionsLog::act(diTypes::admin_task, $this->getId(), diActionsLog::aUploadDeleted, [
					"info" => $pic->getOrigFn(),
				]);
			}

			/** @var diDynamicPicModel $pic */
			foreach ($this->getNewPics() as $pic)
			{
				diActionsLog::act(diTypes::admin_task, $this->getId(), diActionsLog::aUploaded, [
					"info" => $pic->getOrigFn(),
				]);
			}
		}
	}

	protected function getNewPics()
	{
		return $this->getPicsDiff($this->picsAfter, $this->picsBefore);
	}

	protected function getDeletedPics()
	{
		return $this->getPicsDiff($this->picsBefore, $this->picsAfter);
	}

	/**
	 * @param $subjectCollection diCollection
	 * @param $objectCollection  diCollection
	 *
	 * @return array
	 */
	protected function getPicsDiff($subjectCollection, $objectCollection)
	{
		$ar = [];

		/** @var diDynamicPicModel $subjectModel */
		foreach ($subjectCollection as $subjectModel)
		{
			$found = false;

			/** @var diDynamicPicModel $objectModel */
			foreach ($objectCollection as $objectModel)
			{
				if ($objectModel->getId() == $subjectModel->getId())
				{
					$found = true;

					break;
				}
			}

			if (!$found)
			{
				$ar[] = $subjectModel;
			}
		}

		return $ar;
	}

	public function getFormFields()
	{
		return [
			"admin_id" => [
				"type" => "int",
				"title" => "Исполнитель",
				"default" => 0,
			],

			"priority" => [
				"type" => "int",
				"title" => "Приоритет",
				"default" => 0,
			],

			"status" => [
				"type" => "int",
				"title" => "Статус",
				"default" => 0,
			],

			"id" => [
				"type" => "int",
				"title" => "ID",
				"default" => "",
				"flags" => ["static"],
			],

			"title" => [
				"type" => "string",
				"title" => "Название",
				"default" => "",
			],

			"content" => [
				"type" => "text",
				"title" => "Описание",
				"default" => "",
			],

			"pics" => [
				"type" => "dynamic_files",
				"title" => "Подгруженные файлы",
				"default" => "",
			],

			"due_date" => [
				"type" => "datetime_str",
				"title" => "Дата выполнения",
				"default" => date("Y-m-d H:i", strtotime("+2 weeks")),
			],

			"date" => [
				"type" => "datetime_str",
				"title" => "Дата добавления",
				"default" => date("Y-m-d H:i"),
				"flags" => ["static"],
			],

			"log" => [
				"type" => "string",
				"title" => "Журнал изменений",
				"default" => "",
				"flags" => ["virtual", "static"],
			],
		];
	}

	public function getLocalFields()
	{
		return [];
	}

	public function getModuleCaption()
	{
		return "Задачи";
	}
}