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

    protected function getUsedUserFields()
    {
        return ['email'];
    }

    protected function setupFilters()
    {
        $this->getFilters()
            ->addFilter([
                'field' => 'token',
                'type' => 'string',
            ])
            ->addFilter([
                'field' => 'user_id',
                'type' => 'string',
                'where_tpl' => \diAdminFilters::get_user_id_where(
                    $this->getUsedUserFields()
                ),
            ])
            ->buildQuery();
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
                    'width' => '30%',
                ],
                'value' => function (Model $m) {
                    return $m->getUser()->getStringAppearanceForAdmin();
                },
            ],
            'user_agent' => [
                'headAttrs' => [
                    'width' => '30%',
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
                'title' => $this->localized([
                    'ru' => 'Токен',
                    'en' => 'Token',
                ]),
                'default' => '',
                'flags' => [FormFlag::static],
            ],

            'user_id' => [
                'type' => 'int',
                'flags' => [FormFlag::static],
                'default' => '',
            ],

            'user_agent' => [
                'type' => 'string',
                'title' => $this->localized([
                    'ru' => 'Браузер',
                    'en' => 'User agent',
                ]),
                'flags' => [FormFlag::static],
                'default' => '',
            ],

            'ip' => [
                'type' => 'ip',
                'flags' => [FormFlag::static],
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
                'flags' => [FormFlag::static, FormFlag::untouchable],
                'default' => '',
            ],
        ];
    }

    public function getLocalFields()
    {
        return [];
    }

    public function addButtonNeededInCaption()
    {
        return false;
    }

    public function getModuleCaption()
    {
        return [
            'ru' => 'Сессия пользователя',
            'en' => 'User sessions',
        ];
    }
}
