<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 02.07.2015
 * Time: 17:39
 */

namespace diCore\Admin\Page;

use diCore\Entity\Tag\Model;

class Tags extends \diCore\Admin\BasePage
{
    protected $options = [
        'filters' => [
            'defaultSorter' => [
                'sortBy' => 'title',
                'dir' => 'ASC',
            ],
        ],
    ];

    protected function initTable()
    {
        $this->setTable('tags');
    }

    public function renderList()
    {
        $this->getList()->addColumns([
            'id' => 'ID',
            'title' => [
                'headAttrs' => [
                    'width' => '40%',
                ],
            ],
            'slug' => [
                'title' => 'Название для URL',
                'attrs' => [],
                'headAttrs' => [
                    'width' => '40%',
                ],
                'bodyAttrs' => [
                    'class' => 'lite',
                ],
            ],
            'date' => [
                'value' => function (Model $tag) {
                    return \diDateTime::simpleFormat($tag->getDate());
                },
                'headAttrs' => [
                    'width' => '20%',
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

    public function submitForm()
    {
        $this->getSubmit()
            ->makeSlug()
            ->store_pics('pic');
    }

    public function getFormTabs()
    {
        return [
            'meta' => 'SEO',
        ];
    }

    public function getFormFields()
    {
        return [
            'title' => [
                'type' => 'string',
                'title' => 'Название',
                'default' => '',
            ],

            'slug_source' => [
                'type' => 'string',
                'title' => 'Название для URL',
                'default' => '',
            ],

            'weight' => [
                'type' => 'int',
                'title' => 'Вес',
                'default' => 0,
                'flags' => ['static'],
            ],

            'content' => [
                'type' => 'wysiwyg',
                'title' => 'Описание',
                'default' => '',
            ],

            'pic' => [
                'type' => 'pic',
                'title' => 'Картинка',
                'default' => '',
                'flags' => ['hidden'],
            ],

            'date' => [
                'type' => 'datetime_str',
                'title' => 'Дата добавления',
                'default' => '',
                'flags' => ['static', 'untouchable', 'initially_hidden'],
            ],

            'html_title' => [
                'type' => 'string',
                'title' => 'Meta-заголовок',
                'default' => '',
                'tab' => 'meta',
            ],

            'html_keywords' => [
                'type' => 'string',
                'title' => 'Meta-ключевые слова',
                'default' => '',
                'tab' => 'meta',
            ],

            'html_description' => [
                'type' => 'string',
                'title' => 'Meta-описание',
                'default' => '',
                'tab' => 'meta',
            ],
        ];
    }

    public function getLocalFields()
    {
        return [
            'slug' => [
                'type' => 'string',
                'title' => 'Slug',
                'default' => '',
            ],
        ];
    }

    public function getModuleCaption()
    {
        return [
            'ru' => 'Теги',
            'en' => 'Tags',
        ];
    }
}
