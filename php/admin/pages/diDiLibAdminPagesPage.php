<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 23.01.2016
 * Time: 18:27
 */

class diDiLibAdminPagesPage extends diAdminBasePage
{
	/** @var diAdminPagesManager */
	private $Manager;

	private $pseudoTable = "di_lib_admin_pages";

	protected function initTable()
	{
		$this->setTable($this->pseudoTable);

		$this->Manager = new diAdminPagesManager();
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
		$this->getTpl()
			->assign([
				"ACTION" => \diCore\Admin\Base::getPageUri($this->pseudoTable, "submit"),
			], "ADMIN_FORM_");

		$this->getForm()
			->setData('namespace', \diLib::getFirstNamespace())
			->setSelectFromDbInput("table", $this->getDb()->q("SHOW TABLE STATUS"), "%Name%", "%Name%")
			->setSelectFromArray2Input('namespace', array_merge([''], \diLib::getAllNamespaces()));
	}

	public function submitForm()
	{
		$this->getManager()->createPage(
			$this->getSubmit()->getData("table"),
			$this->getSubmit()->getData("caption"),
			$this->getSubmit()->getData("classname"),
			$this->getSubmit()->getData("namespace")
		);
	}

	protected function afterSubmitForm()
	{
		$this->redirectTo(\diCore\Admin\Base::getPageUri($this->pseudoTable, "form"));
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

			"caption" => [
				"type" => "string",
				"title" => "Название модуля (необязательно)",
				"default" => "",
			],

			"classname" => [
				"type" => "string",
				"title" => "Имя класса (необязательно)",
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
		return "Админ.страницы";
	}
}