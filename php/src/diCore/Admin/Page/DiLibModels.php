<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 02.07.2015
 * Time: 14:27
 */

namespace diCore\Admin\Page;

use diCore\Admin\Base;
use diCore\Database\Connection;
use diCore\Tool\Code\ModelsManager;

class DiLibModels extends \diCore\Admin\BasePage
{
	/** @var ModelsManager */
	private $Manager;

	private $pseudoTable = 'di_lib_models';

	protected function initTable()
	{
		$this->setTable($this->pseudoTable);

		$this->Manager = new ModelsManager();
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
        $tables = [];

        /**
         * @var string $name
         * @var Connection $conn
         */
        foreach (Connection::getAll() as $name => $conn) {
            foreach ($conn->getTableNames() as $table) {
                $n = $name . '::' . $table;

                $tables[] = $n;
            }
        }

		$this->getForm()
			->setData('namespace', \diLib::getFirstNamespace())
			->setSelectFromArray2Input('table', $tables)
			->setSelectFromArray2Input('namespace', array_merge([''], \diLib::getAllNamespaces()));

		/** @var \diSelect $sel */
		$sel = $this->getForm()->getInput('table');
		$tablesInfoAr = [];

		foreach ($sel->getItemsAr() as $a) {
			list($connName, $table) = explode('::', $a['value']);
			$name = ModelsManager::getModelNameByTable($table);

			$tablesInfoAr[$a['value']] = [
				'model' => \diModel::existsFor($name),
				'collection' => \diCollection::existsFor($name),
			];
		}

		$this->setAfterFormTemplate([
		    'tables_info' => $tablesInfoAr,
        ]);

		$this->getTpl()
			->assign([
				'ACTION' => Base::getPageUri($this->pseudoTable, 'submit'),
			], 'ADMIN_FORM_');
	}

	public function submitForm()
	{
		$this->getManager()->createModel(
			explode('::', $this->getSubmit()->getData('table')),
			$this->getSubmit()->getData('needed'),
			$this->getSubmit()->getData('classname'),
			$this->getSubmit()->getData('collection_needed'),
			$this->getSubmit()->getData('collection_classname'),
			$this->getSubmit()->getData('namespace')
		);
	}

	protected function afterSubmitForm()
	{
		$this->redirectTo(Base::getPageUri($this->pseudoTable, 'form'));
	}

	public function getFormFields()
	{
		return [
			'table' => [
				'type' => 'string',
				'title' => 'Таблица',
				'default' => '',
			],

			'namespace' => [
				'type' => 'string',
				'title' => 'Местоположение',
				'default' => '',
			],

			'needed' => [
				'type' => 'checkbox',
				'title' => 'Создать модель',
				'default' => 1,
			],

			'classname' => [
				'type' => 'string',
				'title' => 'Имя класса модели (необязательно)',
				'default' => '',
			],

			'collection_needed' => [
				'type' => 'checkbox',
				'title' => 'Создать коллекцию',
				'default' => 1,
			],

			'collection_classname' => [
				'type' => 'string',
				'title' => 'Имя класса коллекции (необязательно)',
				'default' => '',
			],
		];
	}

	public function getLocalFields()
	{
		return [];
	}

	public function getModuleCaption()
	{
		return 'Модели и коллекции';
	}
}