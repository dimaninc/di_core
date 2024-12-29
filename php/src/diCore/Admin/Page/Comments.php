<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 22.10.2017
 * Time: 22:45
 */

namespace diCore\Admin\Page;

use diCore\Admin\Data\FormFlag;
use diCore\Data\Types;
use diCore\Entity\Comment\Model;
use diCore\Helper\ArrayHelper;
use diCore\Tool\CollectionCache;

class Comments extends \diCore\Admin\BasePage
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
        $this->setTable(Model::table);
    }

    public static function getUsedTargetTypes()
    {
        return array_keys(Types::titles());
    }

    protected function getUsedTargetTypesTitles()
    {
        return ArrayHelper::filterByKey(
            \diTypes::titles(),
            $this->getUsedTargetTypes()
        );
    }

    protected function getUsedUserFields()
    {
        return ['name', 'login', 'email'];
    }

    protected function setupFilters()
    {
        $this->getFilters()
            ->addFilter([
                'field' => 'target_type',
                'type' => 'int',
            ])
            ->addFilter([
                'field' => 'target_id',
                'type' => 'int',
            ])
            ->addFilter([
                'field' => 'user_id',
                'type' => 'string',
                'where_tpl' => \diAdminFilters::getUserWhere(
                    $this->getUsedUserFields()
                ),
            ])
            ->buildQuery()
            ->setSelectFromArrayInput(
                'target_type',
                $this->getUsedTargetTypesTitles(),
                [0 => $this->getLanguage() == 'ru' ? 'Все типы' : 'All types']
            );
    }

    protected function cacheDataForList()
    {
        parent::cacheDataForList();

        CollectionCache::addManual(
            Types::user,
            'id',
            $this->getListCollection()->map('user_id')
        );

        return $this;
    }

    public function renderList()
    {
        $this->getList()->addColumns([
            'id' => 'ID',
            '#href' => [],
            'target_id' => [
                'headAttrs' => [
                    'width' => '30%',
                ],
                'value' => function (Model $m) {
                    return $m->getDescriptionForAdmin();
                },
            ],
            'user_id' => [
                'headAttrs' => [
                    'width' => '20%',
                ],
                'value' => function (Model $m) {
                    return $m->getUserAppearance();
                },
            ],
            'content' => [
                'headAttrs' => [
                    'width' => '40%',
                ],
                'bodyAttrs' => [
                    'class' => 'lite',
                ],
            ],
            'created_at' => [
                'title' => 'Дата',
                'value' => function (Model $m) {
                    return \diDateTime::simpleFormat(strtotime($m->getCreatedAt()));
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
            '#visible' => '',
        ]);
    }

    public function renderForm()
    {
        /** @var Model $comment */
        $comment = $this->getForm()->getModel();
        $user = $comment->getUserModel();

        $this->getForm()
            ->setInput(
                'target_id',
                $comment->getDescriptionForAdmin() .
                    " [<a href=\"{$comment->getTargetModel()->getHref()}\" target=\"_blank\">ссылка</a>]"
            )
            ->setInput(
                'user_id',
                $comment->getUserAppearance($user) .
                    " [<a href=\"{$user->getTable()}/form/{$user->getId()}/\" target=\"_blank\">ссылка</a>]"
            );
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
                'title' => $this->localized([
                    'ru' => 'Тип записи',
                    'en' => 'Target type',
                ]),
                'default' => '',
                'flags' => ['hidden'],
            ],

            'target_id' => [
                'type' => 'int',
                'title' => $this->localized([
                    'ru' => 'Запись',
                    'en' => 'Target',
                ]),
                'default' => 0,
                'flags' => ['static'],
            ],

            'user_id' => [
                'type' => 'int',
                'default' => 0,
                'flags' => ['static'],
            ],

            'content' => [
                'type' => 'text',
                'title' => $this->localized([
                    'ru' => 'Комментарий',
                    'en' => 'Comment',
                ]),
                'default' => '',
            ],

            'ip' => [
                'type' => 'ip',
                'default' => '',
                'flags' => ['static'],
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

            'karma' => [
                'type' => 'int',
                'title' => $this->localized([
                    'ru' => 'Карма',
                    'en' => 'Karma',
                ]),
                'default' => 0,
                'flags' => ['static'],
            ],

            'evil_score' => [
                'type' => 'int',
                'title' => $this->localized([
                    'ru' => 'Уровень зла (для модерации)',
                    'en' => 'Evil score (for moderation)',
                ]),
                'default' => 0,
                'flags' => ['static'],
            ],
        ];
    }

    public function getLocalFields()
    {
        return [
            'parent' => [
                'type' => 'int',
                'default' => 0,
            ],

            'level_num' => [
                'type' => 'int',
                'default' => 0,
            ],
        ];
    }

    public function getModuleCaption()
    {
        return [
            'ru' => 'Комментарии',
            'en' => 'Comments',
        ];
    }

    public function addButtonNeededInCaption()
    {
        return false;
    }
}
