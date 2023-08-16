<?php
/**
 * Created by AdminPagesManager
 * Date: 16.08.2023
 * Time: 15:32
 */

namespace diCore\Admin\Page;

use diCore\Admin\Data\FormFlag;
use diCore\Entity\UserSession\Model;

class UserSession extends \diCore\Admin\BasePage
{
    protected $options = [
        'filters' => [
            'defaultSorter' => [
                'sortBy' => 'id',
                'dir' => 'DESC',
            ],
        ],
    ];

    protected function initTable()
    {
        $this->setTable('user_session');
    }

    public function renderList()
    {
        $this->getList()->addColumns([
            'id' => 'ID',
            'token' => [
                'headAttrs' => [
                    'width' => '10%',
                ],
            ],
            'user_id' => [
                'headAttrs' => [
                    'width' => '10%',
                ],
            ],
            'user_agent' => [
                'headAttrs' => [
                    'width' => '10%',
                ],
            ],
            'ip' => [
                'headAttrs' => [
                    'width' => '10%',
                ],
            ],
            'created_at' => [
                'value' => function (Model $m) {
                    return \diDateTime::simpleFormat($m->getCreatedAt()) .
                        '<br>' .
                        \diDateTime::simpleFormat($m->getUpdatedAt());
                },
                'headAttrs' => [
                    'width' => '10%',
                ],
                'bodyAttrs' => [
                    'class' => 'dt',
                ],
            ],
            'seen_at' => [
                'headAttrs' => [
                    'width' => '10%',
                ],
            ],
            '#edit' => [],
            '#del' => [],
        ]);
    }

    public function renderForm()
    {
    }

    public function submitForm()
    {
    }

    public function getFormTabs()
    {
        return [];
    }

    public function getFormFields()
    {
        return [
            'token' => [
                'type' => 'string',
                'default' => '',
            ],

            'user_id' => [
                'type' => 'int',
                'title' => $this->localized([
                    'ru' => 'Пользователь',
                    'en' => 'User',
                ]),
                'default' => '',
            ],

            'user_agent' => [
                'type' => 'string',
                'title' => $this->localized([
                    'ru' => 'Браузер',
                    'en' => 'User agent',
                ]),
                'default' => '',
            ],

            'ip' => [
                'type' => 'ip',
                'title' => $this->localized([
                    'ru' => 'IP-адрес',
                    'en' => 'IP address',
                ]),
                'default' => '',
            ],

            'created_at' => [
                'type' => 'datetime_str',
                'default' => '',
                'flags' => [
                    FormFlag::static,
                    FormFlag::untouchable,
                    FormFlag::initially_hidden,
                ],
            ],

            'updated_at' => [
                'type' => 'datetime_str',
                'default' => '',
                'flags' => [FormFlag::static, FormFlag::initially_hidden],
            ],

            'seen_at' => [
                'type' => 'datetime_str',
                'title' => $this->localized([
                    'ru' => 'Последнее посещение',
                    'en' => 'Seen at',
                ]),
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
        return [
            'ru' => 'Сессия пользователя',
            'en' => 'User sessions',
        ];
    }
}
