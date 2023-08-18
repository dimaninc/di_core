<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 23.01.2016
 * Time: 18:27
 */

namespace diCore\Admin\Page;

use diCore\Admin\Base;
use diCore\Database\Connection;
use diCore\Tool\Code\AdminPagesManager;

class DiLibAdminPages extends \diCore\Admin\BasePage
{
    /** @var AdminPagesManager */
    private $Manager;

    private $pseudoTable = 'di_lib_admin_pages';

    protected function initTable()
    {
        $this->setTable($this->pseudoTable);

        $this->Manager = new AdminPagesManager();
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
        $this->getTpl()->assign(
            [
                'ACTION' => Base::getPageUri($this->pseudoTable, 'submit'),
            ],
            'ADMIN_FORM_'
        );

        $this->getForm()
            ->setData('namespace', \diLib::getFirstNamespace())
            ->setSelectFromArray2Input('table', $tables)
            ->setSelectFromArray2Input(
                'namespace',
                array_merge([''], \diLib::getAllNamespaces())
            );
    }

    public function submitForm()
    {
        $this->getManager()->createPage(
            explode('::', $this->getSubmit()->getData('table')),
            $this->getSubmit()->getData('caption'),
            $this->getSubmit()->getData('classname'),
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

            'caption' => [
                'type' => 'string',
                'title' => 'Название модуля (необязательно)',
                'default' => '',
            ],

            'classname' => [
                'type' => 'string',
                'title' => 'Имя класса (необязательно)',
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
        return 'Админ.страницы';
    }
}
