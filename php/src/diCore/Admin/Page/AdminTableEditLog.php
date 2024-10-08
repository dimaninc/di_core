<?php
/**
 * Created by diAdminPagesManager
 * Date: 12.11.2016
 * Time: 12:16
 */

namespace diCore\Admin\Page;

use diCore\Admin\FilterRule;
use diCore\Entity\AdminTableEditLog\Model;
use diCore\Helper\ArrayHelper;
use diCore\Tool\CollectionCache;

class AdminTableEditLog extends \diCore\Admin\BasePage
{
    protected $options = [
        'filters' => [
            'defaultSorter' => [
                'sortBy' => 'id',
                'dir' => 'DESC',
            ],
        ],
    ];

    protected $vocabulary = [
        'ru' => [
            'module.caption' => 'Изменения данных в таблице',

            'admin_id.title' => 'Исполнитель',
            'admin_id.all' => 'Все исполнители',
            'admin_id.not_specified' => 'Не выбран',
            'target_table.title' => 'Тип данных (таблица)',
            'target_table.all' => 'Все таблицы',
            'target_id.title' => 'Запись #',
            'created_at.title' => 'Дата',
            'edit_log.title' => 'Изменения',
        ],
        'en' => [
            'module.caption' => 'Content changes',

            'admin_id.title' => 'Admin',
            'admin_id.all' => 'All admins',
            'admin_id.not_specified' => 'Not specified',
            'target_table.title' => 'Data type (table)',
            'target_table.all' => 'All tables',
            'target_id.title' => 'Record #',
            'created_at.title' => 'When',
            'edit_log.title' => 'Edit log',
        ],
    ];

    protected function initTable()
    {
        $this->setTable('admin_table_edit_log');
    }

    protected function setupFilters()
    {
        $tables = ArrayHelper::combine(array_values(\diTypes::tables()));
        asort($tables);

        $this->getFilters()
            ->addFilter([
                'field' => 'admin_id',
                'type' => 'int',
                'title' => $this->getVocabularyTerm('admin_id.title'),
                'rule' => FilterRule::equals,
            ])
            ->addFilter([
                'field' => 'target_table',
                'type' => 'string',
                'title' => $this->getVocabularyTerm('target_table.title'),
                'rule' => FilterRule::equals,
            ])
            ->addFilter([
                'field' => 'target_id',
                'type' => 'string',
                'title' => $this->getVocabularyTerm('target_id.title'),
                'rule' => FilterRule::equals,
            ])
            ->buildQuery()
            ->setSelectFromCollectionInput(
                'admin_id',
                \diCollection::create(\diTypes::admin)->orderBy('login'),
                function (\diCore\Entity\Admin\Model $admin) {
                    return [
                        'value' => $admin->getId(),
                        'text' => $admin->getLogin(),
                    ];
                },
                [
                    0 => $this->getVocabularyTerm('admin_id.all'),
                ]
            )
            ->setSelectFromArrayInput('target_table', $tables, [
                '' => $this->getVocabularyTerm('target_table.all'),
            ]);
    }

    protected function cacheDataForList()
    {
        parent::cacheDataForList();

        CollectionCache::addManual(
            \diTypes::admin,
            'id',
            $this->getListCollection()->map('admin_id')
        );

        return $this;
    }

    public function renderList()
    {
        $this->getList()->addColumns([
            'id' => 'ID',
            'target_id' => [
                'headAttrs' => [
                    'width' => '15%',
                ],
                'value' => function (Model $model) {
                    return $model->getTargetTable() . '#' . $model->getTargetId();
                },
            ],
            'admin_id' => [
                'value' => function (Model $model) {
                    /** @var \diCore\Entity\Admin\Model $admin */
                    $admin = CollectionCache::getModel(
                        \diTypes::admin,
                        $model->getAdminId()
                    );

                    return $admin->exists() ? $admin->getLogin() : '&mdash;';
                },
                'headAttrs' => [
                    'width' => '15%',
                ],
            ],
            'diff' => [
                'headAttrs' => [
                    'width' => '60%',
                ],
                'value' => function (Model $model) {
                    $model->parseData();

                    $ar = [];

                    foreach ($model->getDataDiff() as $field => $diff) {
                        $ar[] = "<b>$field</b><div class=\"lite\">$diff</div>";
                    }

                    return join('', $ar);
                },
            ],
            'created_at' => [
                'title' => $this->getVocabularyTerm('created_at.title'),
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
            '#edit' => '',
            '#del' => [
                'active' => function (Model $m, $field) {
                    return $this->getAdmin()->isAdminSuper();
                },
            ],
        ]);
    }

    public function renderForm()
    {
        /** @var Model $model */
        $model = $this->getForm()->getModel();
        $model->parseData();

        $this->getForm()
            ->setInput('target_id', $model->getTarget()->appearanceForAdmin())
            ->setSelectFromCollectionInput(
                'admin_id',
                $admins = \diCollection::create(\diTypes::admin)
                    ->filterBy('active', 1)
                    ->orderBy('login'),
                function (\diCore\Entity\Admin\Model $admin) {
                    return [
                        'value' => $admin->getId(),
                        'text' => $admin->getLogin(),
                    ];
                },
                ['' => $this->getVocabularyTerm('admin_id.not_specified')]
            )
            ->setInput(
                'edit_log',
                $this->getTwig()->parse('admin/admin_table_edit_log/form_field', [
                    'records' => [$model],
                    'admins' => $admins,
                ])
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
            'target_table' => [
                'type' => 'string',
                'title' => $this->getVocabularyTerm('target_table.title'),
                'default' => '',
                'flags' => ['static'],
            ],

            'target_id' => [
                'type' => 'int',
                'title' => $this->getVocabularyTerm('target_id.title'),
                'default' => '',
                'flags' => ['static'],
            ],

            'admin_id' => [
                'type' => 'int',
                'title' => $this->getVocabularyTerm('admin_id.title'),
                'default' => '',
                'flags' => ['static'],
            ],

            'edit_log' => [
                'type' => 'string',
                'title' => $this->getVocabularyTerm('edit_log.title'),
                'default' => '',
                'flags' => ['static', 'virtual'],
            ],
        ];
    }

    public function getLocalFields()
    {
        return [];
    }
}
