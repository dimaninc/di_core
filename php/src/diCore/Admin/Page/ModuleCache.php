<?php
/**
 * Created by \diAdminPagesManager
 * Date: 08.06.2017
 * Time: 17:37
 */

namespace diCore\Admin\Page;

use diCore\Admin\Data\FormFlag;
use diCore\Entity\ModuleCache\Model;

class ModuleCache extends \diCore\Admin\BasePage
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
        $this->setTable('module_cache');
    }

    public function renderList()
    {
        $this->getList()->addColumns([
            'id' => 'ID',
            'module_id' => [
                'headAttrs' => [
                    'width' => '10%',
                ],
            ],
            'title' => [
                'headAttrs' => [
                    'width' => '10%',
                ],
                'bodyAttrs' => [
                    'class' => 'lite',
                ],
            ],
            'query_string' => [
                'headAttrs' => [
                    'width' => '25%',
                ],
                'bodyAttrs' => [
                    'class' => 'lite',
                ],
            ],
            'bootstrap_settings' => [
                'headAttrs' => [
                    'width' => '25%',
                ],
                'bodyAttrs' => [
                    'class' => 'lite',
                ],
            ],
            'update_every_minutes' => [
                'title' => 'Обновление, мин',
                'headAttrs' => [
                    'width' => '10%',
                ],
            ],
            'content' => [
                'headAttrs' => [
                    //'width' => '10%',
                ],
                'bodyAttrs' => [
                    'class' => 'lite',
                ],
                'value' => function (Model $m, $field) {
                    return $m->has($field) ? '+' : '';
                },
            ],
            'created_at' => [
                'title' => 'Создан',
                'value' => function (Model $m) {
                    return \diDateTime::simpleFormat($m->getCreatedAt());
                },
                'headAttrs' => [
                    'width' => '10%',
                ],
                'bodyAttrs' => [
                    'class' => 'dt',
                ],
            ],
            'updated_at' => [
                'title' => 'Обновлён',
                'value' => function (Model $m) {
                    return $m->hasUpdatedAt()
                        ? \diDateTime::simpleFormat($m->getUpdatedAt())
                        : '&ndash;';
                },
                'headAttrs' => [
                    'width' => '10%',
                ],
                'bodyAttrs' => [
                    'class' => 'dt',
                ],
            ],
            '#edit' => '',
            '#del' => '',
            '#active' => '',
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
            'module_id' => [
                'type' => 'string',
                'title' => 'Модуль',
                'default' => '',
            ],

            'query_string' => [
                'type' => 'string',
                'title' => 'Query string',
                'default' => '',
            ],

            'bootstrap_settings' => [
                'type' => 'string',
                'title' => 'Bootstrap-настройки',
                'default' => '',
            ],

            'title' => [
                'type' => 'string',
                'title' => 'Пояснение',
                'default' => '',
            ],

            'update_every_minutes' => [
                'type' => 'int',
                'title' => 'Частота обновления, в минутах',
                'default' => '',
            ],

            'content' => [
                'type' => 'text',
                'title' => 'Кеш',
                'default' => '',
                'flags' => [FormFlag::static, FormFlag::initially_hidden],
            ],

            'created_at' => [
                'type' => 'datetime_str',
                'title' => 'Дата создания',
                'default' => '',
                'flags' => [
                    FormFlag::static,
                    FormFlag::untouchable,
                    FormFlag::initially_hidden,
                ],
            ],

            'updated_at' => [
                'type' => 'datetime_str',
                'title' => 'Дата последнего обновления',
                'default' => '',
                'flags' => [
                    FormFlag::static,
                    FormFlag::untouchable,
                    FormFlag::initially_hidden,
                ],
            ],
        ];
    }

    public function getLocalFields()
    {
        return [];
    }

    public function getModuleCaption()
    {
        return 'Кеш модулей';
    }
}
