<?php
/**
 * Created by \diAdminPagesManager
 * Date: 27.10.2018
 * Time: 13:20
 */

namespace diCore\Admin\Page;

use diCore\Admin\FilterRule;
use diCore\Entity\PageCache\Model;

class PageCache extends \diCore\Admin\BasePage
{
    protected $options = [
        'showControlPanel' => true,
        'filters' => [
            'defaultSorter' => [
                'sortBy' => 'id',
                'dir' => 'DESC',
            ],
        ],
    ];

    protected function initTable()
    {
        $this->setTable('page_cache');
    }

    protected function setupFilters()
    {
        $this->getFilters()
            ->addFilter([
                'field' => 'uri',
                'type' => 'string',
                'title' => 'URI',
                'rule' => FilterRule::contains,
            ])
            ->buildQuery();
    }

    public function renderList()
    {
        $this->getList()->addColumns([
            'id' => 'ID',
            '#checkbox' => [],
            '#href' => [],
            'uri' => [
                'headAttrs' => [
                    'width' => '80%',
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
        if (
            !$this->getForm()
                ->getModel()
                ->hasId()
        ) {
            $this->getForm()->setHiddenInput([
                'created_at',
                'updated_at',
                'content',
            ]);
        }
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
            'uri' => [
                'type' => 'string',
                'title' => 'URI',
                'default' => '',
            ],

            'content' => [
                'type' => 'text',
                'title' => 'Кеш',
                'default' => '',
                //'flags'     => ['static'],
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

            'active' => [
                'type' => 'checkbox',
                'title' => 'Активен',
                'default' => 1,
            ],
        ];
    }

    public function getLocalFields()
    {
        return [];
    }

    public function getModuleCaption()
    {
        return 'Кеш страниц';
    }
}
