<?php
/**
 * Created by AdminPagesManager
 * Date: 11.09.2024
 * Time: 15:48
 */

namespace diCore\Admin\Page;

use diCore\Admin\Data\FormFlag;
use diCore\Entity\AdditionalVariable\Model;

class AdditionalVariable extends \diCore\Admin\BasePage
{
    protected $options = [
        'filters' => [
            'defaultSorter' => [
                'sortBy' => 'name',
                'dir' => 'ASC',
            ],
        ],
    ];

    protected function initTable()
    {
        $this->setTable('additional_variable');
    }

    public function renderList()
    {
        $this->getList()->addColumns([
            'id' => 'ID',
            '#href' => [],
            'target_type' => [
                'headAttrs' => [
                    'width' => '10%',
                ],
            ],
            'name' => [
                'headAttrs' => [
                    'width' => '10%',
                ],
            ],
            'properties' => [
                'headAttrs' => [
                    'width' => '10%',
                ],
            ],
            'active' => [
                'headAttrs' => [
                    'width' => '10%',
                ],
            ],
            'created_at' => [
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
                'value' => function (Model $m) {
                    return \diDateTime::simpleFormat($m->getUpdatedAt());
                },
                'headAttrs' => [
                    'width' => '10%',
                ],
                'bodyAttrs' => [
                    'class' => 'dt',
                ],
            ],
            '#edit' => [],
            '#del' => [],
            '#active' => [],
        ]);
    }

    public function renderForm()
    {
        $names = extend(\diTypes::$names);
        asort($names);

        $this->getForm()->setSelectFromArrayInput('target_type', $names);

        // todo: inputs for json properties
    }

    public function submitForm()
    {
        // todo: accept pic as default value
        // $this->getSubmit()->storeImage(['pic']);
    }

    public function getFormTabs()
    {
        return [];
    }

    public function getFormFields()
    {
        return [
            'target_type' => [
                'type' => 'int',
                'title' => $this->localized([
                    'ru' => 'Тип данных',
                    'en' => 'Data type',
                ]),
                'default' => '',
            ],

            'name' => [
                'type' => 'string',
                'title' => $this->localized([
                    'ru' => 'Название',
                    'en' => 'Name',
                ]),
                'default' => '',
            ],

            'properties' => [
                'type' => 'json',
                'title' => $this->localized([
                    'ru' => 'Свойства',
                    'en' => 'Properties',
                ]),
                'default' => '',
            ],

            'active' => [
                'type' => 'checkbox',
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
        ];
    }

    public function getLocalFields()
    {
        return [
            'order_num' => [
                'type' => 'order_num',
                'default' => '',
                'direction' => 1,
            ],
        ];
    }

    public function getModuleCaption()
    {
        return [
            'ru' => 'Дополнительные переменные',
            'en' => 'Additional variables',
        ];
    }
}
