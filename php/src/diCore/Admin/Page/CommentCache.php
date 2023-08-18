<?php
/**
 * Created by \diAdminPagesManager
 * Date: 25.06.2017
 * Time: 16:50
 */

namespace diCore\Admin\Page;

use diCore\Entity\CommentCache\Model;
use diCore\Helper\ArrayHelper;

class CommentCache extends \diCore\Admin\BasePage
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
        $this->setTable('comment_cache');
    }

    protected function setupFilters()
    {
        $this->getFilters()
            ->addFilter([
                'field' => 'target_type',
                'type' => 'int',
                'title' => 'Тип объекта',
            ])
            ->addFilter([
                'field' => 'target_id',
                'type' => 'int',
                'title' => 'ID объекта',
            ])
            ->buildQuery()
            ->setSelectFromArrayInput(
                'target_type',
                $this->getUsedTargetTypesTitles(),
                [0 => 'Все типы']
            );
    }

    protected function getUsedTargetTypes()
    {
        return array_keys(\diTypes::titles());
    }

    protected function getUsedTargetTypesTitles()
    {
        return ArrayHelper::filterByKey(
            \diTypes::titles(),
            $this->getUsedTargetTypes()
        );
    }

    public function renderList()
    {
        $this->getList()->addColumns([
            'id' => 'ID',
            'target' => [
                'headAttrs' => [
                    'width' => '80%',
                ],
                'title' => 'Цель',
                'value' => function (Model $m) {
                    return \diTypes::getTitle($m->getTargetType()) .
                        '#' .
                        $m->getTargetId() .
                        ($m->has('title') ? ' (' . $m->get('title') . ')' : '');
                },
            ],
            'html' => [
                'headAttrs' => [
                    //'width' => '10%',
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
            'target_type' => [
                'type' => 'int',
                'title' => 'Цель, тип',
                'default' => '',
            ],

            'target_id' => [
                'type' => 'int',
                'title' => 'Цель, ID',
                'default' => '',
            ],

            'update_every_minutes' => [
                'type' => 'int',
                'title' => 'Частота обновления, в минутах',
                'default' => '',
            ],

            'html' => [
                'type' => 'text',
                'title' => 'Кеш',
                'default' => '',
                'flags' => ['static'],
            ],

            'created_at' => [
                'type' => 'datetime_str',
                'title' => 'Дата создания',
                'default' => '',
                'flags' => ['static', 'untouchable'],
            ],

            'updated_at' => [
                'type' => 'datetime_str',
                'title' => 'Дата последнего обновления',
                'default' => '',
                'flags' => ['static', 'untouchable'],
            ],
        ];
    }

    public function getLocalFields()
    {
        return [];
    }

    public function getModuleCaption()
    {
        return 'Кеш блока комментариев';
    }
}
