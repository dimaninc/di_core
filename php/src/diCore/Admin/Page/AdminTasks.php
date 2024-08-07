<?php

namespace diCore\Admin\Page;

use diCore\Admin\FilterRule;
use diCore\Data\Types;
use diCore\Entity\DynamicPic\Collection as dpCol;
use diCore\Entity\AdminTask\Model;
use diCore\Helper\StringHelper;
use diCore\Tool\CollectionCache;

/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 04.06.2015
 * Time: 14:33
 */

class AdminTasks extends \diCore\Admin\BasePage
{
    protected $options = [
        'filters' => [
            'defaultSorter' => [
                'sortBy' => 'priority',
                'dir' => 'DESC',
            ],
            'sortByAr' => [
                'priority' => 'По приоритету',
                'due_date' => 'По сроку сдачи',
                'date' => 'По дате добавления',
                'id' => 'По ID',
            ],
        ],
    ];

    /** @var dpCol */
    protected $picsBefore;
    /** @var dpCol */
    protected $picsAfter;

    protected function initTable()
    {
        $this->setTable('admin_tasks');
    }

    protected function setupFilters()
    {
        $this->getFilters()
            ->addFilter([
                'field' => 'id',
                'type' => 'string',
                'title' => 'ID задачи',
                'where_tpl' => 'diaf_several_ints',
            ])
            ->addFilter([
                'field' => 'title',
                'type' => 'string',
                'title' => 'Название',
                'rule' => FilterRule::contains,
            ])
            ->addFilter([
                'field' => 'content',
                'type' => 'string',
                'title' => 'Текст',
                'rule' => FilterRule::contains,
            ])
            ->addFilter([
                'field' => 'admin_id',
                'type' => 'int',
                'title' => 'Исполнитель',
                'where_tpl' => 'diaf_minus_one',
            ])
            ->addFilter([
                'field' => 'priority',
                'type' => 'int',
                'title' => 'Приоритет',
            ])
            ->addFilter([
                'field' => 'status',
                'type' => 'string',
                'title' => 'Статус',
                'where_tpl' => 'diaf_several_ints',
                'default_value' => join(',', Model::statusesActual()),
                'strict' => true,
            ])
            ->buildQuery()
            ->setSelectFromCollectionInput(
                'admin_id',
                \diCollection::create(Types::admin)
                    ->filterBy('active', 1)
                    ->orderBy('login'),
                function (\diCore\Entity\Admin\Model $admin) {
                    return [
                        'value' => $admin->getId(),
                        'text' => $admin->getLogin(),
                    ];
                },
                [
                    0 => 'Все исполнители',
                    -1 => 'Не присвоен',
                ]
            )
            ->setSelectFromArrayInput('status', Model::statusStr(), [
                //0 => 'Все',
                join(',', Model::statusesActual()) => '[ Текущие задачи ]',
            ])
            ->setSelectFromArrayInput('priority', Model::$priorities, [
                0 => 'Все',
            ]);
    }

    /*
	protected function getDefaultListRows($options = [])
	{
		$options = $this->extendListQueryOptions($options);

		$orderBy = $options['sortBy'] ? " ORDER BY t.{$options["sortBy"]} t.{$options["dir"]}" : '';

		$col = diCollection::create(Types::admin_task, $this->getDb()->rs(
			$this->getTable() . ' t INNER JOIN admin_task_participants p INNER JOIN admins a '.
			'ON t.id = p.task_id AND a.id = p.admin_id',
			$options['query'] . $orderBy . $options['limit'],
			't.*,' . join(',', diAdminModel::getFieldsWithTablePrefix('a.', diAdminModel::COMPLEX_QUERY_PREFIX))
		));

		return $col;
	}
	*/

    protected function cacheDataForList()
    {
        parent::cacheDataForList();

        CollectionCache::addManual(
            Types::admin,
            'id',
            $this->getListCollection()->map('admin_id')
        );

        return $this;
    }

    public function renderList()
    {
        $this->getList()->addColumns([
            'id' => 'ID',
            'admin_id' => [
                'value' => function (Model $model) {
                    /** @var \diCore\Entity\Admin\Model $admin */
                    $admin = CollectionCache::getModel(
                        Types::admin,
                        $model->getAdminId()
                    );

                    return $admin->exists() ? $admin->getLogin() : '&ndash;';
                },
                'headAttrs' => [
                    'width' => '10%',
                ],
            ],
            'priority' => [
                'title' => 'Приоритет',
                'value' => function (Model $model) {
                    $icon = "<span class=\"admin-task-priority p{$model->getPriority()}\"></span>";

                    return $icon . $model->getPriorityStr();
                },
                'headAttrs' => [
                    'width' => '10%',
                ],
                'bodyAttrs' => [
                    'class' => 'lite',
                ],
            ],
            'status' => [
                'title' => 'Статус',
                'value' => function (Model $model) {
                    return $model->getStatusStr();
                },
                'headAttrs' => [
                    'width' => '10%',
                ],
                'bodyAttrs' => [
                    'class' => 'lite',
                ],
            ],
            'attaches' => [
                'title' => '&#128206;',
                'value' => function (Model $model) {
                    $pics = $this->getAttachedPicsCollection($model->getId());

                    return count($pics) ?: '';
                },
                'bodyAttrs' => [
                    'class' => 'lite',
                ],
            ],
            'title' => [
                'title' => 'Задача',
                'value' => function (Model $model) {
                    return $model->getTitle() .
                        '<div class="lite">' .
                        StringHelper::out(
                            StringHelper::cutEnd($model->getContent(), 150)
                        ) .
                        '</div>';
                },
                'headAttrs' => [
                    'width' => '50%',
                ],
            ],
            'date' => [
                'title' => 'Добавлено',
                'value' => function (Model $model, $field) {
                    return \diDateTime::simpleFormat($model->get($field));
                },
                'headAttrs' => [
                    'width' => '10%',
                ],
                'bodyAttrs' => [
                    'class' => 'dt',
                ],
            ],
            'due_date' => [
                'title' => 'Срок сдачи',
                'value' => function (Model $model, $field) {
                    return \diDateTime::simpleFormat($model->get($field));
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
        ]);

        if (!$this->getAdmin()->isAdminSuper()) {
            $this->getList()->removeColumn(['#del']);
        }
    }

    public function renderForm()
    {
        $this->getForm()
            ->setSelectFromCollectionInput(
                'admin_id',
                \diCollection::create(Types::admin)
                    ->filterBy('active', 1)
                    ->orderBy('login'),
                function (\diCore\Entity\Admin\Model $admin) {
                    return [
                        'value' => $admin->getId(),
                        'text' => $admin->getLogin(),
                    ];
                },
                ['' => 'Не выбран']
            )
            ->setSelectFromArrayInput('status', Model::statusStr())
            ->setSelectFromArrayInput('priority', Model::priorityStr());

        if (!$this->getId()) {
            $this->getForm()
                ->setHiddenInput('log')
                ->setHiddenInput('id');
        } else {
            $this->getForm()->setTemplateForInput(
                'log',
                '`_snippets/actions_log',
                'block'
            );
        }
    }

    public function submitForm()
    {
        if ($this->getId()) {
            /** @var Model $m */
            $m = $this->getSubmit()->getModel();

            if ($m->changed('status')) {
                \diActionsLog::act(
                    Types::admin_task,
                    $this->getId(),
                    \diActionsLog::aStatusChanged,
                    $m->getOrigData('status') . ',' . $m->getStatus()
                );
            }

            if ($m->changed('priority')) {
                \diActionsLog::act(
                    Types::admin_task,
                    $this->getId(),
                    \diActionsLog::aPriorityChanged,
                    $m->getOrigData('priority') . ',' . $m->getPriority()
                );
            }

            if ($m->changed('admin_id')) {
                \diActionsLog::act(
                    Types::admin_task,
                    $this->getId(),
                    \diActionsLog::aOwned,
                    $m->getOrigData('admin_id') . ',' . $m->getAdminId()
                );
            }

            if ($m->changed(['title', 'content', 'date', 'due_date'])) {
                \diActionsLog::act(
                    Types::admin_task,
                    $this->getId(),
                    \diActionsLog::aEdited
                );
            }
        }
    }

    /**
     * @return dpCol
     * @throws \Exception
     */
    public function getAttachedPicsCollection($id = null)
    {
        return dpCol::createByTarget(
            $this->getTable(),
            $id ?: $this->getId(),
            'pics'
        )->load();
    }

    protected function beforeSubmitForm()
    {
        $result = parent::beforeSubmitForm();

        $this->picsBefore = $this->getAttachedPicsCollection();

        return $result;
    }

    protected function afterSubmitForm()
    {
        parent::afterSubmitForm();

        if ($this->isNew()) {
            \diActionsLog::act(
                Types::admin_task,
                $this->getId(),
                \diActionsLog::aAdded
            );
        } else {
            $this->picsAfter = $this->getAttachedPicsCollection();

            /** @var \diDynamicPicModel $pic */
            foreach ($this->getDeletedPics() as $pic) {
                \diActionsLog::act(
                    Types::admin_task,
                    $this->getId(),
                    \diActionsLog::aUploadDeleted,
                    [
                        'info' => $pic->getOrigFn(),
                    ]
                );
            }

            /** @var \diDynamicPicModel $pic */
            foreach ($this->getNewPics() as $pic) {
                \diActionsLog::act(
                    Types::admin_task,
                    $this->getId(),
                    \diActionsLog::aUploaded,
                    [
                        'info' => $pic->getOrigFn(),
                    ]
                );
            }
        }
    }

    protected function getNewPics()
    {
        return $this->getPicsDiff($this->picsAfter, $this->picsBefore);
    }

    protected function getDeletedPics()
    {
        return $this->getPicsDiff($this->picsBefore, $this->picsAfter);
    }

    /**
     * @param $subjectCollection \diCollection
     * @param $objectCollection  \diCollection
     *
     * @return array
     */
    protected function getPicsDiff($subjectCollection, $objectCollection)
    {
        $ar = [];

        /** @var \diDynamicPicModel $subjectModel */
        foreach ($subjectCollection as $subjectModel) {
            $found = false;

            /** @var \diDynamicPicModel $objectModel */
            foreach ($objectCollection as $objectModel) {
                if ($objectModel->getId() == $subjectModel->getId()) {
                    $found = true;

                    break;
                }
            }

            if (!$found) {
                $ar[] = $subjectModel;
            }
        }

        return $ar;
    }

    protected function getDefaultAdminId()
    {
        return 0;
    }

    public function getFormFields()
    {
        return [
            'admin_id' => [
                'type' => 'int',
                'title' => 'Исполнитель',
                'default' => $this->getDefaultAdminId(),
            ],

            'priority' => [
                'type' => 'int',
                'title' => 'Приоритет',
                'default' => 0,
            ],

            'status' => [
                'type' => 'int',
                'title' => 'Статус',
                'default' => 0,
            ],

            'id' => [
                'type' => 'int',
                'title' => 'ID',
                'default' => '',
                'flags' => ['static'],
            ],

            'title' => [
                'type' => 'string',
                'title' => 'Название',
                'default' => '',
            ],

            'content' => [
                'type' => 'text',
                'title' => 'Описание',
                'default' => '',
            ],

            'pics' => [
                'type' => 'dynamic_files',
                'title' => 'Подгруженные файлы',
                'default' => '',
            ],

            'due_date' => [
                'type' => 'datetime_str',
                'title' => 'Дата выполнения',
                'default' => \diDateTime::sqlFormat('+2 weeks'),
            ],

            'date' => [
                'type' => 'datetime_str',
                'title' => 'Дата добавления',
                'default' => \diDateTime::sqlFormat(),
                'flags' => ['static'],
            ],

            'log' => [
                'type' => 'string',
                'title' => 'Журнал изменений',
                'default' => '',
                'flags' => ['virtual', 'static'],
            ],
        ];
    }

    public function getLocalFields()
    {
        return [];
    }

    public function getModuleCaption()
    {
        return 'Задачи';
    }
}
