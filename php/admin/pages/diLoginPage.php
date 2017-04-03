<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 08.06.2015
 * Time: 18:34
 */

class diLoginPage extends diAdminBasePage
{
	protected function initTable()
	{
		$this->setTable("login");
	}

	public function renderList()
	{
	}

	protected function beforeRenderForm()
	{
		// this prevents errors due to fake table

		return true;
	}

	protected function afterRenderForm()
	{
		// this prevents errors due to fake table
	}

	public function renderForm()
	{
		$this->getAdmin()->setHeadPrinter(function(\diAdmin $A) {
			return $A->getTwig()->parse('admin/_index/head_of_login', []);
		});

		$this->getTpl()
			->define("`login/form", [
				"index",
				"page",
			]);
	}

	public function submitForm()
	{
	}

	public function getFormFields()
	{
		return [];
	}

	public function getLocalFields()
	{
		return [];
	}

	public function getModuleCaption()
	{
		return "Авторизация";
	}
}