<?php

namespace diCore\Admin\Page;

use diCore\Entity\Admin\Model;

class Admins extends \diCore\Admin\BasePage
{
    protected $options = [
        'filters' => [
            'defaultSorter' => [
                'sortBy' => 'login',
                'dir' => 'ASC',
            ],
        ],
    ];

    protected function initTable()
    {
        $this->setTable('admins');
    }

    public function renderList()
    {
        $this->getList()->addColumns([
            'id' => 'ID',
            'login' => [
                'attrs' => [
                    'width' => '70%',
                ],
            ],
            'level' => [
                'value' => function (Model $model) {
                    return $model->getLevelTitle();
                },
                'attrs' => [
                    'width' => '30%',
                ],
                'bodyAttrs' => [
                    'class' => 'regular',
                ],
            ],
            '#edit' => '',
            '#del' => '',
            '#active' => '',
        ]);
    }

    public function renderForm()
    {
        $this->getForm()->setSelectFromArrayInput('level', Model::getLevels());
    }

    public function submitForm()
    {
    }

    public function getFormFields()
    {
        return [
            'login' => [
                'type' => 'string',
                'title' => $this->localized([
                    'ru' => 'Логин',
                    'en' => 'Login',
                ]),
                'required' => true,
                'default' => '',
            ],

            'password' => [
                'type' => 'password',
                'title' => $this->localized([
                    'ru' => 'Пароль',
                    'en' => 'Password',
                ]),
                'default' => '',
            ],

            'first_name' => [
                'type' => 'string',
                'title' => $this->localized([
                    'ru' => 'Имя',
                    'en' => 'First name',
                ]),
                'default' => '',
            ],

            'last_name' => [
                'type' => 'string',
                'title' => $this->localized([
                    'ru' => 'Фамилия',
                    'en' => 'Last name',
                ]),
                'default' => '',
            ],

            'email' => [
                'type' => 'email',
                'title' => 'E-mail',
                'default' => '',
            ],

            'phone' => [
                'type' => 'tel',
                'title' => $this->localized([
                    'ru' => 'Телефон',
                    'en' => 'Phone',
                ]),
                'default' => '',
            ],

            'address' => [
                'type' => 'string',
                'title' => $this->localized([
                    'ru' => 'Адрес',
                    'en' => 'Address',
                ]),
                'default' => '',
            ],

            'level' => [
                'type' => 'enum',
                'title' => $this->localized([
                    'ru' => 'Уровень доступа',
                    'en' => 'Access level',
                ]),
                'default' => current(array_keys(Model::getLevels())),
                'values' => array_keys(Model::getLevels()),
            ],

            'date' => [
                'type' => 'datetime_str',
                'title' => $this->localized([
                    'ru' => 'Дата добавления',
                    'en' => 'Date created',
                ]),
                'default' => \diDateTime::sqlFormat(),
                'flags' => ['static'],
            ],
        ];
    }

    public function getLocalFields()
    {
        return [];
    }

    public function getModuleCaption()
    {
        return [
            'ru' => 'Админы',
            'en' => 'Admins',
        ];
    }
}
