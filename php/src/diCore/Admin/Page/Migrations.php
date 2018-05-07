<?php

namespace diCore\Admin\Page;

use diCore\Database\Tool\Migration;
use diCore\Tool\CollectionCache;
use diCore\Entity\DiMigrationsLog\Model;

/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 14.06.2015
 * Time: 0:16
 */
class Migrations extends \diCore\Admin\BasePage
{
	protected $options = [
		"filters" => [
			"defaultSorter" => [
				"sortBy" => "date",
				"dir" => "DESC",
			],
		],
	];

	/** @var \diMigrationsManager */
	private $Manager;

	private $pseudoTable = "migrations";

	protected function initTable()
	{
		$this->Manager = \diMigrationsManager::create();

		$this->setTable($this->pseudoTable);

		switch ($this->getMethod())
		{
			case "log":
				$this->setTable(\diMigrationsManager::logTable);
				break;
		}
	}

	public function getManager()
	{
		return $this->Manager;
	}

	protected function beforeRenderLog()
	{
		parent::beforeRenderList();

		return true;
	}

	protected function afterRenderLog()
	{
		parent::afterRenderList();
	}

	public function getMethodCaption($action)
	{
		if ($action == "log")
		{
			return "Журнал";
		}

		return parent::getMethodCaption($action);
	}

	public function renderLog()
	{
		$this->getList()->addColumns([
			"id" => "ID",
			"idx" => [
				"headAttrs" => [
					"width" => "10%",
				],
				"noHref" => true,
			],
			"name" => [
				"headAttrs" => [
					"width" => "50%",
				],
				"noHref" => true,
			],
			"admin_id" => [
				"title" => "Применил админ",
				"value" => function(Model $l) {
					/** @var \diCore\Entity\Admin\Model $admin */
					$admin = CollectionCache::getModel(\diTypes::admin, $l->getAdminId());

					return $admin->exists() ? $admin->getLogin() : "&ndash;";
				},
				"headAttrs" => [
					"width" => "30%",
				],
				"noHref" => true,
			],
			"direction" => [
				"title" => "",
				"value" => function (Model $l)
				{
					return $l->getDirection() == Migration::UP ? "+" : "-";
				},
				"noHref" => true,
			],
			"date" => [
				"title" => "Когда",
				"value" => function (Model $l)
				{
					return \diDateTime::simpleFormat($l->getDate());
				},
				"attrs" => [],
				"headAttrs" => [
					"width" => "10%",
				],
				"bodyAttrs" => [
					"class" => "dt",
				],
				"noHref" => true,
			],
			//"#del" => "",
		]);
	}

	protected function cacheDataForList()
	{
		parent::cacheDataForList();

		CollectionCache::addManual(\diTypes::admin, 'id', $this->getListCollection()->map('admin_id'));

		return $this;
	}

	public function renderList()
	{
		$that = $this;

		$this->getList()->addColumns([
			"id" => "ID",
			"description" => [
				"title" => "Описание",
				"headAttrs" => [
					"width" => "60%",
				],
				"noHref" => true,
			],
			"subFolder" => [
				"title" => "Папка",
				"headAttrs" => [
					"width" => "10%",
				],
				"bodyAttrs" => [
					"class" => "lite",
				],
				"noHref" => true,
			],
			"date_created" => [
				"title" => "Дата создания",
				"value" => function(\diModel $m) {
					if ($m->has("folder"))
					{
						return "";
					}

					$ar = str_split($m->getId(), 2);

					return $ar[3] . "." . $ar[2] . "." . $ar[0] . $ar[1] . " " . $ar[4] . ":" . $ar[5];
				},
				"attrs" => [],
				"headAttrs" => [
					"width" => "10%",
				],
				"bodyAttrs" => [
					"class" => "dt",
				],
				"noHref" => true,
			],
			"date_modified" => [
				"title" => "Дата модификации",
				"value" => function(\diModel $m) {
					if ($m->has("folder"))
					{
						return "";
					}

					return \diDateTime::simpleFormat($m->get("date"));
				},
				"attrs" => [],
				"headAttrs" => [
					"width" => "10%",
				],
				"bodyAttrs" => [
					"class" => "dt",
				],
				"noHref" => true,
			],
			"date_applied" => [
				"title" => "Дата наката",
				"value" => function(\diModel $m) {
					if ($m->has("folder"))
					{
						return "";
					}

					$date = $this->getManager()->whenExecuted($m->getId());

					return $date ? \diDateTime::simpleFormat($date) : "&ndash;";
				},
				"attrs" => [],
				"headAttrs" => [
					"width" => "10%",
				],
				"bodyAttrs" => [
					"class" => "dt",
				],
				"noHref" => true,
			],
			"#play" => [
				"active" => function(\diModel $m) {
					if ($m->has("folder"))
					{
						return false;
					}

					return !$this->getManager()->wasExecuted($m->getId());
				},
				"href" => \diLib::getAdminWorkerPath("migration", "up", "%id%") . "?back=" . urlencode($_SERVER["REQUEST_URI"]),
				"onclick" => "return confirm('Накатить миграцию?');",
			],
			"#rollback" => [
				"active" => function(\diModel $m) {
					if ($m->has("folder"))
					{
						return false;
					}

					return $this->getManager()->wasExecuted($m->getId());
				},
				"href" => \diLib::getAdminWorkerPath("migration", "down", "%id%") . "?back=" . urlencode($_SERVER["REQUEST_URI"]),
				"onclick" => "return confirm('Откатить миграцию?');",
			],
		]);

		/** @var \diMigrationsManager $c */
		$c = get_class($this->getManager());

		foreach ($c::$foldersIdsAr as $folderId)
		{
			// printing folder
			$folder = $this->getManager()->getFolderById($folderId);

			$r = (object)[
				"id" => "-",
				"description" => $folder,
				"folder" => true,
				"subFolder" => "",
			];

			$this->getList()->addRow($r);
			//

			$migrations = \diMigrationsManager::getMigrationsInFolder($folder);
			usort($migrations, function($a, $b) use ($that) {
				return \diMigrationsManager::getIdxByFileName($a) < \diMigrationsManager::getIdxByFileName($b) ? 1 : -1;
			});

			foreach ($migrations as $i => $fn)
			{
				$idx = \diMigrationsManager::getIdxByFileName($fn);

				/** @var Migration $className */
				$className = \diMigrationsManager::getClassNameByIdx($idx);
				include($fn);

				$subFolder = basename(dirname($fn));
				if ($subFolder == "migrations")
				{
					$subFolder = "";
				}

				$r = [
					"id" => $idx,
					"description" => \diDB::_out($className::$name),
					"date" => filemtime($fn),
					"subFolder" => $subFolder,
				];

				$this->getList()->addRow($r);
			}
		}
	}

	public function printList()
	{
		if ($this->getMethod() != "list")
		{
			parent::printList();
		}
	}

	public function renderForm()
	{
		$this->getTpl()
			->define("`migrations/form", [
				"after_form",
			])
			->assign([
				"ACTION" => \diCore\Admin\Base::getPageUri($this->pseudoTable, "submit"),
			], "ADMIN_FORM_");

		$rawFolders = \diMigrationsManager::getSubFolders(\diMigrationsManager::FOLDER_LOCAL);
		$folders = [
			"" => "/",
		];

		$start = $this->getManager()->getLocalFolder() . "/";

		foreach ($rawFolders as $f)
		{
			$f = mb_substr($f, mb_strlen($start));

			$folders[$f] = $f;
		}

		$folders["*"] = "Создать папку";

		$this->getForm()
			->setInputSuffix("folder", \diCore\Admin\Form::INPUT_SUFFIX_NEW_FIELD)
			->setSelectFromArrayInput("folder", $folders);
	}

	public function submitForm()
	{
		$this->getSubmit()
			->processData("idx", function ($idx)
			{
				return preg_replace('/[^a-z0-9_]/i', "", $idx);
			})
			->processData("folder", function ($folder)
			{
				if ($folder == "*")
				{
					$folder = \diRequest::post("folder" . \diCore\Admin\Form::NEW_FIELD_SUFFIX, "");
				}

				return preg_replace('/[^a-z0-9_]/i', "", $folder);
			});

		$this->getManager()->createMigration(
			$this->getSubmit()->getData('idx'),
			$this->getSubmit()->getData('name'),
			$this->getSubmit()->getData('folder')
		);
	}

	protected function afterSubmitForm()
	{
		$this->redirectTo(\diCore\Admin\Base::getPageUri($this->pseudoTable, "list"));
	}

	public function getFormFields()
	{
		return [
			"idx" => [
				"type" => "string",
				"title" => "Идентификатор",
				"default" => date("YmdHis"),
			],

			"name" => [
				"type" => "string",
				"title" => "Название",
				"default" => "",
			],

			"folder" => [
				"type" => "string",
				"title" => "Папка",
				"default" => "",
			],
		];
	}

	public function getLocalFields()
	{
		return [];
	}

	public function getModuleCaption()
	{
		return "Миграции";
	}
}