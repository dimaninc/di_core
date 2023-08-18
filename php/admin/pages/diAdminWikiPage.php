<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 15.06.2015
 * Time: 14:33
 */

class diAdminWikiPage extends \diCore\Admin\BasePage
{
    protected $options = [
        'filters' => [
            'defaultSorter' => [
                'sortBy' => 'date',
                'dir' => 'DESC',
            ],
            /*
			"sortByAr" => array(
				"date" => "По дате добавления",
				"id" => "По ID",
			),
			*/
        ],
    ];

    protected function initTable()
    {
        $this->setTable('admin_wiki');
    }

    public function renderList()
    {
        $this->getList()->addColumns([
            'id' => 'ID',
            'title' => [
                'title' => 'Название',
                'value' => function (diAdminWikiModel $model) {
                    return $model->getTitle() .
                        "<div class=\"lite\">" .
                        str_cut_end(strip_tags($model->getContent()), 150) .
                        '</div>';
                },
                'headAttrs' => [
                    'width' => '70%',
                ],
            ],
            'tags' => [
                'title' => 'Теги',
                'value' => function (diAdminWikiModel $model) {
                    return join(
                        ', ',
                        diTags::tagRecords(
                            diTypes::admin_wiki,
                            $model->getId(),
                            '%title%'
                        )
                    );
                },
                'headAttrs' => [
                    'width' => '20%',
                ],
                'bodyAttrs' => [
                    'class' => 'lite',
                ],
            ],
            'date' => [
                'title' => 'Добавлено',
                'value' => function (diAdminWikiModel $model, $field) {
                    return date('d.m.Y H:i', strtotime($model->get($field)));
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
    }

    public function renderForm()
    {
        if (!$this->getId()) {
            $this->getForm()->setHiddenInput('log');
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
            if ($this->getSubmit()->wasFieldChanged(['title', 'content'])) {
                diActionsLog::act(
                    diTypes::admin_wiki,
                    $this->getId(),
                    diActionsLog::aEdited
                );
            }
        }
    }

    protected function afterSubmitForm()
    {
        // watching tag changes only for existing records
        if (!$this->isNew()) {
            /** @var diTags $class */
            $class =
                $this->getSubmit()->getFieldOption('tag_id', 'class') ?:
                'diTags';

            $tagsBefore = $class::tagIdsAr(
                diTypes::getId($this->getTable()),
                $this->getId()
            );
        }

        parent::afterSubmitForm();

        if ($this->isNew()) {
            diActionsLog::act(
                diTypes::admin_wiki,
                $this->getId(),
                diActionsLog::aAdded
            );
        } else {
            $tagsAfter = $class::tagIdsAr(
                diTypes::getId($this->getTable()),
                $this->getId()
            );

            $tagsAdded = array_diff($tagsAfter, $tagsBefore);
            $tagsRemoved = array_diff($tagsBefore, $tagsAfter);

            if ($tagsAdded) {
                diActionsLog::act(
                    diTypes::admin_wiki,
                    $this->getId(),
                    diActionsLog::aTagAdded,
                    $class . ':' . join(',', $tagsAdded)
                );
            }

            if ($tagsRemoved) {
                diActionsLog::act(
                    diTypes::admin_wiki,
                    $this->getId(),
                    diActionsLog::aTagRemoved,
                    $class . ':' . join(',', $tagsRemoved)
                );
            }
        }
    }

    public function getFormFields()
    {
        return [
            'title' => [
                'type' => 'string',
                'title' => 'Заголовок',
                'default' => '',
            ],

            'content' => [
                'type' => 'wysiwyg',
                'title' => 'Текст',
                'default' => '',
            ],

            'pics' => [
                'type' => 'dynamic_pics',
                'title' => 'Подгруженные изображения',
                'default' => '',
            ],

            'tag_id' => [
                'type' => 'tags',
                'title' => 'Теги',
                'options' => [
                    'ableToAddNew' => true,
                    'columns' => 4,
                ],
                'flags' => ['virtual'],
                'default' => '',
            ],

            'date' => [
                'type' => 'datetime_str',
                'title' => 'Дата добавления',
                'default' => date('Y-m-d H:i'),
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
        return 'Wiki';
    }
}
