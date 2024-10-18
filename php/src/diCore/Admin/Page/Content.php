<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 31.05.2015
 * Time: 21:01
 */

namespace diCore\Admin\Page;

use diCore\Admin\Data\FormFlag;
use diCore\Entity\Content\Model;

class Content extends \diCore\Admin\BasePage
{
    const MAX_LEVEL_NUM = 2;

    protected $options = [
        'updateSearchIndexOnSubmit' => true,
        'showControlPanel' => true,
        'showHeader' => true,
        'filters' => [
            'defaultSorter' => [
                'sortBy' => 'order_num',
                'dir' => 'ASC',
            ],
        ],
    ];

    protected function initTable()
    {
        $this->setTable(Model::table);
    }

    protected function getButtonsArForList()
    {
        return [
            '#edit' => '',
            '#create' => [
                'maxLevelNum' => static::MAX_LEVEL_NUM,
            ],
            '#del' => '',
            '#visible' => '',
            '#up' => '',
            '#down' => '',
        ];
    }

    public function renderList()
    {
        $this->getList()
            ->addColumns([
                '_checkbox' => '',
                '_expand' => '',
                'id' => '',
                '#href' => [],
                'title' => [
                    'headAttrs' => [
                        'width' => '80%',
                    ],
                ],
                'type' => [
                    'headAttrs' => [
                        'width' => '20%',
                    ],
                    'bodyAttrs' => [
                        'class' => 'regular',
                    ],
                ],
            ])
            ->addColumns($this->getButtonsArForList());
    }

    protected function hideFieldsForType(
        $contentTypes,
        $fields,
        $allLanguages = true
    ) {
        if ($allLanguages) {
            $fields = \diCurrentCMS::extendFieldsWithAllLanguages($fields);
        }

        if (!is_array($contentTypes)) {
            $contentTypes = [$contentTypes];
        }

        $this->getForm()->setInputAttribute($fields, [
            'data-hide-for-type' => $contentTypes,
        ]);

        return $this;
    }

    public function renderForm()
    {
        $h = new \diHierarchyContentTable();

        $parents = $this->getId()
            ? $h->getParents($this->getId())
            : $h->getParentsByParentId(
                $this->getForm()
                    ->getModel()
                    ->get('parent')
            );

        $parentsAr = [];
        /** @var Model $parent */
        foreach ($parents as $parent) {
            $parentsAr[] = strip_tags($parent->getTitle());
        }

        if ($parentsAr) {
            $this->getForm()->setInput('parent', join(' / ', $parentsAr));
        } else {
            $this->getForm()->setHiddenInput('parent');
        }

        $typesAr = \diContentTypes::get($this->getLanguage());
        array_walk($typesAr, function (&$opts, $type) {
            $module = camelize($type, false);
            $opts = $opts['title'] . ' ///' . $module;
        });

        $this->getForm()->setSelectFromArrayInput('type', $typesAr);
    }

    public function submitForm()
    {
        $this->generateSlugOnSubmit();

        $this->getSubmit()
            ->storeImage(
                ['pic', 'pic2', 'ico'],
                [
                    [
                        'type' => \diCore\Admin\Submit::IMAGE_TYPE_MAIN,
                        //'resize' => diImage::DI_THUMB_FIT,
                    ],
                ]
            )
            ->makeOrderAndLevelNum();
    }

    protected function generateSlugOnSubmit()
    {
        $this->getSubmit()->makeSlug();

        return $this;
    }

    protected function afterSubmitForm()
    {
        parent::afterSubmitForm();

        $Z = new \diCurrentCMS();
        $Z->build_content_table_cache();
    }

    public function getFormFields()
    {
        return [
            'parent' => [
                'type' => 'int',
                'title' => $this->localized([
                    'ru' => 'Родитель',
                    'en' => 'Parent',
                ]),
                'default' => -1,
                'flags' => ['static'],
            ],

            'type' => [
                'type' => 'string',
                'title' => $this->localized([
                    'ru' => 'Тип',
                    'en' => 'Type',
                ]),
                'default' => 'user',
                'values' => array_keys(\diContentTypes::get($this->getLanguage())),
            ],

            'title' => [
                'type' => 'string',
                'title' => $this->localized([
                    'ru' => 'Название',
                    'en' => 'Title',
                ]),
                'default' => '',
                'tab' => 'content',
            ],

            'menu_title' => [
                'type' => 'string',
                'title' => $this->localized([
                    'ru' => 'Название для URL',
                    'en' => 'Slug source',
                ]),
                'default' => '',
            ],

            'caption' => [
                'type' => 'string',
                'title' => $this->localized([
                    'ru' => 'Заголовок для меню',
                    'en' => 'Menu caption',
                ]),
                'default' => '',
                'flags' => ['hidden'],
            ],

            'short_content' => [
                'type' => 'wysiwyg',
                'title' => $this->localized([
                    'ru' => 'Краткое наполнение',
                    'en' => 'Short content',
                ]),
                'default' => '',
                'flags' => 'hidden',
                'tab' => 'content',
            ],

            'content' => [
                'type' => 'wysiwyg',
                'title' => $this->localized([
                    'ru' => 'Наполнение',
                    'en' => 'Content',
                ]),
                'default' => '',
                'tab' => 'content',
            ],

            'links_content' => [
                'type' => 'text',
                'title' => $this->localized([
                    'ru' => 'Блок со ссылками',
                    'en' => 'Links block content',
                ]),
                'default' => '',
                'tab' => 'content',
                'flags' => ['hidden'],
            ],

            'html_title' => [
                'type' => 'string',
                'default' => '',
                'tab' => 'meta',
            ],

            'html_keywords' => [
                'type' => 'string',
                'default' => '',
                'tab' => 'meta',
            ],

            'html_description' => [
                'type' => 'string',
                'default' => '',
                'tab' => 'meta',
            ],

            'comments_enabled' => [
                'type' => 'checkbox',
                'title' => $this->localized([
                    'ru' => 'Возможность комментировать страницу',
                    'en' => 'Comments feature enabled',
                ]),
                'default' => 0,
                'flags' => ['hidden'],
            ],

            'background_color' => [
                'type' => 'color',
                'title' => $this->localized([
                    'ru' => 'Цвет фона',
                    'en' => 'Background color',
                ]),
                'default' => '',
                'tab' => 'content',
                'flags' => ['hidden'],
            ],

            'color' => [
                'type' => 'color',
                'title' => $this->localized([
                    'ru' => 'Цвет ссылки',
                    'en' => 'Link color',
                ]),
                'default' => '',
                'tab' => 'content',
                'flags' => ['hidden'],
            ],

            'menu_class' => [
                'type' => 'string',
                'title' => $this->localized([
                    'ru' => 'CSS-класс пункта меню',
                    'en' => 'CSS-class of menu item',
                ]),
                'default' => '',
                'flags' => ['hidden'],
            ],

            'class' => [
                'type' => 'string',
                'title' => $this->localized([
                    'ru' => 'CSS-класс заголовка',
                    'en' => 'CSS-class of caption',
                ]),
                'default' => '',
                'flags' => ['hidden'],
            ],

            'pic' => [
                'type' => 'pic',
                'title' => $this->localized([
                    'ru' => 'Картинка',
                    'en' => 'Pic',
                ]),
                'default' => '',
                'tab' => 'pics',
                'flags' => ['hidden'],
            ],

            'pic2' => [
                'type' => 'pic',
                'title' => $this->localized([
                    'ru' => 'Вторая картинка',
                    'en' => '2nd pic',
                ]),
                'default' => '',
                'tab' => 'pics',
                'flags' => ['hidden'],
            ],

            'ico' => [
                'type' => 'pic',
                'title' => $this->localized([
                    'ru' => 'Иконка',
                    'en' => 'Ico',
                ]),
                'default' => '',
                'tab' => 'pics',
                'flags' => ['hidden'],
            ],

            'properties' => [
                'type' => 'json',
                'default' => null,
                'flags' => [FormFlag::hidden],
            ],

            'ad_block_id' => [
                'type' => 'int',
                'title' => $this->localized([
                    'ru' => 'Рекламный блок',
                    'en' => 'Ad block',
                ]),
                'default' => '',
                'flags' => ['hidden'],
            ],
        ];
    }

    public function getLocalFields()
    {
        return [
            'clean_title' => [
                'type' => 'string',
                'title' => 'Clean title',
                'default' => '',
            ],

            'level_num' => [
                'type' => 'int',
                'title' => 'Level num',
                'default' => 0,
            ],

            'to_show_content' => [
                'type' => 'int',
                'title' => 'Show',
                'default' => 0,
            ],

            'order_num' => [
                'type' => 'int',
                'title' => 'Order num',
                'default' => 0,
            ],

            'pic_w' => [
                'type' => 'int',
                'title' => 'Изображение w',
                'default' => 0,
            ],

            'pic_h' => [
                'type' => 'int',
                'title' => 'Изображение h',
                'default' => 0,
            ],

            'pic_t' => [
                'type' => 'int',
                'title' => 'Изображение t',
                'default' => 0,
            ],

            'pic2_w' => [
                'type' => 'int',
                'title' => 'Изображение w',
                'default' => 0,
            ],

            'pic2_h' => [
                'type' => 'int',
                'title' => 'Изображение h',
                'default' => 0,
            ],

            'pic2_t' => [
                'type' => 'int',
                'title' => 'Изображение t',
                'default' => 0,
            ],

            'ico_w' => [
                'type' => 'int',
                'title' => 'Изображение w',
                'default' => 0,
            ],

            'ico_h' => [
                'type' => 'int',
                'title' => 'Изображение h',
                'default' => 0,
            ],

            'ico_t' => [
                'type' => 'int',
                'title' => 'Изображение t',
                'default' => 0,
            ],
        ];
    }

    public function getFormTabs()
    {
        return [
            'meta' => 'SEO',
        ];
    }

    public function getModuleCaption()
    {
        return [
            'ru' => 'Страницы',
            'en' => 'Pages',
        ];
    }
}
