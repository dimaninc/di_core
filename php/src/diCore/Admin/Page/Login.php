<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 08.06.2015
 * Time: 18:34
 */

namespace diCore\Admin\Page;

use diCore\Admin\Config;
use diCore\Admin\Data\LoginLayout;

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

    protected function getIndexTemplateName()
    {
        if (Config::isCustomLoginLayout()) {
            $layout = LoginLayout::name(Config::getLoginLayout());

            return "admin/login/$layout/index";
        }

        return 'admin/login/index';
    }

    public function renderForm()
    {
        $errors = [];

        if (\diRequest::post(\diAdminUser::POST_LOGIN_FIELD)) {
            $errors['password'] = [
                'en' => 'Login/password not match',
                'ru' => 'Логин/пароль введены неверно',
            ];
        }

        $this->getTwig()
            ->assign([
                'login_errors' => $errors,
                'login_credentials' => [
                    'login' => \diRequest::post(\diAdminUser::POST_LOGIN_FIELD),
                ],
                'login_layout' => Config::getLoginLayout(),
            ])
            ->setTemplateForIndex($this->getIndexTemplateName());
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
