<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 08.06.2015
 * Time: 18:34
 */

namespace diCore\Admin\Page;

class Login extends \diCore\Admin\BasePage
{
    protected function initTable()
    {
        $this->setTable('#');
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
        $this->getAdmin()->setHeadPrinter(function(\diCore\Admin\Base $A) {
            return $A->getTwig()->parse('admin/_index/head_of_login', []);
        });

        $this->getTpl()
            ->define('`login/form', [
                'index',
                'page',
            ]);
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
        return [
            'en' => 'Sign in',
            'ru' => 'Авторизация',
        ];
    }
}