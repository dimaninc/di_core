<?php

class diMigrationController extends diBaseAdminController
{
	/** @var diMigrationsManager */
	private $Manager;

	public function __construct($params = [])
	{
		parent::__construct($params);

		$this->Manager = diMigrationsManager::create();
	}

	public function upAction()
	{
		$this->Manager->run($this->param(0), true);

		$this->redirect();
	}

	public function downAction()
	{
		$this->Manager->run($this->param(0), false);

		$this->redirect();
	}
}