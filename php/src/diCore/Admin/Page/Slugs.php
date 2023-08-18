<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 29.07.2015
 * Time: 14:58
 */

namespace diCore\Admin\Page;

use diCore\Admin\FilterRule;
use diCore\Entity\Slug\Model;

class Slugs extends \diCore\Admin\BasePage
{
    protected $options = [
        'filters' => [
            'defaultSorter' => [
                'sortBy' => 'full_slug',
                'dir' => 'ASC',
            ],
        ],
    ];

    protected function initTable()
    {
        $this->setTable('slugs');
    }

    protected function setupFilters()
    {
        $this->getFilters()
            ->addFilter([
                'field' => 'id',
                'type' => 'int',
                'title' => 'ID',
            ])
            ->addFilter([
                'field' => 'target_type',
                'type' => 'int',
                'title' => 'Target type',
            ])
            ->addFilter([
                'field' => 'target_id',
                'type' => 'int',
                'title' => 'Target ID',
            ])
            ->addFilter([
                'field' => 'slug',
                'type' => 'string',
                'title' => 'Слаг',
                'rule' => FilterRule::contains,
            ])
            ->addFilter([
                'field' => 'full_slug',
                'type' => 'string',
                'title' => 'Полный слаг',
                'rule' => FilterRule::contains,
            ])
            ->buildQuery();
    }

    public function renderList()
    {
        $this->getList()->addColumns([
            'id' => 'ID',
            '#href' => [],
            'target_type' => [
                'headAttrs' => [
                    'width' => '20%',
                ],
                'value' => function (Model $s) {
                    return \diTypes::getTitle($s->getTargetType());
                },
                'noHref' => true,
            ],
            'target_id' => [
                'headAttrs' => [
                    'width' => '25%',
                ],
                'value' => function (Model $s) {
                    return $s->getTargetModel()->exists()
                        ? join(
                            ', ',
                            array_filter([
                                $s->getTargetModel()->get('title'),
                                '#' . $s->getTargetModel()->getId(),
                            ])
                        )
                        : 'Not exists: ' .
                                \diTypes::getName($s->getTargetType()) .
                                '#' .
                                $s->getTargetId();
                },
                'noHref' => true,
            ],
            'level_num' => [
                'headAttrs' => [
                    'width' => '5%',
                ],
                'bodyAttrs' => [
                    'class' => 'dt',
                ],
                'noHref' => true,
            ],
            'slug' => [
                'headAttrs' => [
                    'width' => '20%',
                ],
                'bodyAttrs' => [
                    'class' => 'lite',
                ],
                'noHref' => true,
            ],
            'full_slug' => [
                'headAttrs' => [
                    'width' => '30%',
                ],
                'bodyAttrs' => [
                    'class' => 'lite',
                ],
                'noHref' => true,
            ],
            //'#edit' => '',
            //'#del' => '',
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
        return [];
    }

    public function getLocalFields()
    {
        return [
            'target_type' => [
                'type' => 'string',
                'title' => 'Тип',
                'default' => '',
            ],

            'target_id' => [
                'type' => 'string',
                'title' => 'Элемент',
                'default' => '',
            ],

            'level_num' => [
                'type' => 'int',
                'title' => 'Уровень вложенности',
                'default' => 0,
            ],

            'slug' => [
                'type' => 'string',
                'title' => 'Slug',
                'default' => '',
            ],

            'full_slug' => [
                'type' => 'string',
                'title' => 'Полный Slug',
                'default' => '',
            ],
        ];
    }

    public function getModuleCaption()
    {
        return 'Slugs';
    }

    public function addButtonNeededInCaption()
    {
        return false;
    }
}
