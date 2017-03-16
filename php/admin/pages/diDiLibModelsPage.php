<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 02.07.2015
 * Time: 14:27
 */

class diDiLibModelsPage extends diAdminBasePage
{
	/** @var diModelsManager */
	private $Manager;

	private $pseudoTable = "di_lib_models";

	protected function initTable()
	{
		$this->setTable($this->pseudoTable);

		$this->Manager = new diModelsManager();
	}

	public function getManager()
	{
		return $this->Manager;
	}

	public function renderList()
	{
	}

	public function renderForm()
	{
		$this->getForm()
			->setData('namespace', diLib::getFirstNamespace())
			->setSelectFromDbInput("table", $this->getDb()->q("SHOW TABLE STATUS"), "%Name%", "%Name%")
			->setSelectFromArray2Input('namespace', array_merge([''], diLib::getAllNamespaces()));

		/** @var diSelect $sel */
		$sel = $this->getForm()->getInput("table");
		$tablesInfoAr = [];

		foreach ($sel->getItemsAr() as $a)
		{
			$table = $a["value"];
			$name = diModelsManager::getModelNameByTable($table);

			$tablesInfoAr[$table] = [
				"model" => diModel::existsFor($name),
				"collection" => diCollection::existsFor($name),
			];
		}

		$this->getTpl()
			->define("`di_lib_models/form", [
				"after_form",
			])
			->assign([
				"TABLES_INFO_AR" => json_encode($tablesInfoAr),
			])
			->assign([
				"ACTION" => diAdminBase::getPageUri($this->pseudoTable, "submit"),
			], "ADMIN_FORM_");
	}

	public function submitForm()
	{
		$this->getManager()->createModel(
			$this->getSubmit()->getData("table"),
			$this->getSubmit()->getData("needed"),
			$this->getSubmit()->getData("classname"),
			$this->getSubmit()->getData("collection_needed"),
			$this->getSubmit()->getData("collection_classname"),
			$this->getSubmit()->getData("namespace")
		);
	}

	protected function afterSubmitForm()
	{
		$this->redirectTo(diAdminBase::getPageUri($this->pseudoTable, "form"));
	}

	public function getFormFields()
	{
		return [
			"table" => [
				"type" => "string",
				"title" => "Таблица",
				"default" => "",
			],

			"namespace" => [
				"type" => "string",
				"title" => "Местоположение",
				"default" => "",
			],

			"needed" => [
				"type" => "checkbox",
				"title" => "Создать модель",
				"default" => 1,
			],

			"classname" => [
				"type" => "string",
				"title" => "Имя класса модели (необязательно)",
				"default" => "",
			],

			"collection_needed" => [
				"type" => "checkbox",
				"title" => "Создать коллекцию",
				"default" => 1,
			],

			"collection_classname" => [
				"type" => "string",
				"title" => "Имя класса коллекции (необязательно)",
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
		return "Модели и коллекции";
	}
}