<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 22.10.2017
 * Time: 9:55
 */

namespace diCore\Admin\Page;

use diCore\Admin\Reference\PhotosOfAlbum;
use diCore\Admin\Submit;
use diCore\Entity\Album\Model;
use diCore\Data\Types;

class Albums extends \diCore\Admin\BasePage
{
    protected $options = [
        'filters' => [
            'defaultSorter' => [
                'sortBy' => 'order_num',
                'dir' => 'ASC',
            ],
        ],
    ];

    protected function initTable()
    {
        $this->setTable('albums');
    }

    public function renderList()
    {
        $this->getList()->addColumns([
            'id' => 'ID',
            'title' => [
                'headAttrs' => [
                    'width' => '90%',
                ],
            ],
            'photos_count' => [
                'bodyAttrs' => [
                    'class' => 'dt',
                ],
            ],
            'videos_count' => [
                'bodyAttrs' => [
                    'class' => 'dt',
                ],
            ],
            'date' => [
                'value' => function (Model $m) {
                    return \diDateTime::simpleFormat($m->getDate());
                },
                'attrs' => [],
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
            '#pic' => '',
            '#video' => '',
            '#up' => '',
            '#down' => '',
        ]);
    }

    public function renderForm()
    {
        if (!$this->getId()) {
            $this->getForm()->setHiddenInput([
                'token',
                'cover_photo_id',
                'date',
                'comments_last_date',
                'photos_count',
                'videos_count',
                'comments_count',
                'force_pic',
            ]);
        } else {
            $this->getForm()->setData(
                'token',
                $this->getForm()
                    ->getModel()
                    ->getToken()
            );
        }
    }

    public function submitForm()
    {
        $this->getSubmit()
            ->makeSlug()
            ->storeImage('pic', [
                [
                    'type' => Submit::IMAGE_TYPE_MAIN,
                    'resize' => \diImage::DI_THUMB_FIT,
                ],
                [
                    'type' => Submit::IMAGE_TYPE_PREVIEW,
                    'resize' => \diImage::DI_THUMB_FIT,
                ],
            ]);
    }

    protected function afterSubmitForm()
    {
        parent::afterSubmitForm();

        if ($this->getSubmit()->getData('force_pic')) {
            /** @var Model $album */
            $album = \diModel::create(Types::album);
            $album
                ->initFrom($this->getSubmit()->getData())
                ->setId($this->getSubmit()->getId());

            $album->generateThumbnail();
        }
    }

    public function getFormTabs()
    {
        return [
            'photos' => $this->localized([
                'en' => 'Photos',
                'ru' => 'Фотографии',
            ]),
        ];
    }

    protected function photosOfAlbumOptions()
    {
        return [];
    }

    public function getFormFields()
    {
        /** @var PhotosOfAlbum $photosOfAlbum */
        $photosOfAlbum = PhotosOfAlbum::basicCreate();

        return [
            'token' => [
                'type' => 'string',
                'title' => $this->localized([
                    'en' => 'Token',
                    'ru' => 'Токен',
                ]),
                'default' => '',
                'flags' => ['static', 'virtual'],
            ],

            'title' => [
                'type' => 'string',
                'title' => $this->localized([
                    'en' => 'Title',
                    'ru' => 'Название',
                ]),
                'default' => '',
            ],

            'content' => [
                'type' => 'wysiwyg',
                'title' => $this->localized([
                    'en' => 'Description',
                    'ru' => 'Описание',
                ]),
                'default' => '',
            ],

            'slug_source' => [
                'type' => 'string',
                'title' => $this->localized([
                    'en' => 'Slug source',
                    'ru' => 'Название для URL',
                ]),
                'default' => '',
            ],

            'cover_photo_id' => [
                'type' => 'int',
                'title' => $this->localized([
                    'en' => 'Cover photo',
                    'ru' => 'Заглавное фото',
                ]),
                'default' => 0,
            ],

            'pic' => [
                'type' => 'pic',
                'title' => $this->localized([
                    'en' => 'Cover',
                    'ru' => 'Обложка',
                ]),
                'default' => '',
            ],

            'force_pic' => [
                'type' => 'checkbox',
                'title' => $this->localized([
                    'en' => 'Force recreate cover',
                    'ru' => 'Сгенерировать обложку заново',
                ]),
                'default' => '',
                'flags' => ['virtual'],
            ],

            'visible' => [
                'type' => 'checkbox',
                'title' => $this->localized([
                    'en' => 'Visible',
                    'ru' => 'Отображать на сайте',
                ]),
                'default' => 1,
            ],

            'date' => [
                'type' => 'datetime_str',
                'title' => $this->localized([
                    'en' => 'Created at',
                    'ru' => 'Дата создания',
                ]),
                'default' => '',
                'flags' => ['static', 'untouchable', 'initially_hidden'],
            ],

            'photos_count' => [
                'type' => 'int',
                'title' => $this->localized([
                    'en' => 'Photos count',
                    'ru' => 'Кол-во фото',
                ]),
                'default' => 0,
                'flags' => ['static'],
            ],

            'videos_count' => [
                'type' => 'int',
                'title' => $this->localized([
                    'en' => 'Videos count',
                    'ru' => 'Кол-во видео',
                ]),
                'default' => 0,
                'flags' => ['static'],
            ],

            'comments_enabled' => [
                'type' => 'checkbox',
                'title' => $this->localized([
                    'en' => 'Comments enabled',
                    'ru' => 'Разрешено комментировать',
                ]),
                'default' => 1,
            ],

            'comments_count' => [
                'type' => 'int',
                'title' => $this->localized([
                    'en' => 'Comments count',
                    'ru' => 'Кол-во комментариев',
                ]),
                'default' => 0,
                'flags' => ['static'],
            ],

            'comments_last_date' => [
                'type' => 'datetime_str',
                'title' => $this->localized([
                    'en' => 'Last comment date',
                    'ru' => 'Дата последнего комментария',
                ]),
                'default' => '',
                'flags' => ['static'],
            ],

            'photos' => $photosOfAlbum::getFormFieldArray(
                $this->photosOfAlbumOptions()
            ),
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

            'order_num' => [
                'type' => 'order_num',
                'title' => 'ord',
                'default' => 0,
                'direction' => -1,
            ],

            'pic_w' => [
                'type' => 'int',
                'title' => 'Pic w',
                'default' => 0,
            ],

            'pic_h' => [
                'type' => 'int',
                'title' => 'Pic h',
                'default' => 0,
            ],

            'pic_t' => [
                'type' => 'int',
                'title' => 'Pic h',
                'default' => 0,
            ],
        ];
    }

    public function getModuleCaption()
    {
        return [
            'en' => 'Albums',
            'ru' => 'Альбомы',
        ];
    }
}
