<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 28.05.2015
 * Time: 22:20
 */

namespace diCore\Admin\Page;

use diCore\Data\Types;
use diCore\Entity\MailIncut\Collection as IncutCollection;
use diCore\Entity\MailIncut\Model as IncutModel;
use diCore\Entity\News\Model;
use diCore\Helper\StringHelper;
use diCore\Tool\Mail\Queue;

class News extends \diCore\Admin\BasePage
{
    const DISPATCH_MODE_TEST = 1;
    const DISPATCH_MODE_STANDARD = 2;

    protected $slugFieldName = 'clean_title';
    protected $slugSourceFieldName = 'menu_title';

    protected $options = [
        'updateSearchIndexOnSubmit' => true,
        'filters' => [
            'defaultSorter' => [
                'sortBy' => 'date',
                'dir' => 'DESC',
            ],
            'sortByAr' => [
                'date' => [
                    'ru' => 'По дате',
                    'en' => 'By date',
                ],
            ],
        ],
    ];

    protected function initTable()
    {
        $this->setTable('news');
    }

    protected function setupFilters()
    {
        $this->getFilters()
            ->addFilter([
                'field' => 'date',
                'type' => 'date_str_range',
                'title' => [
                    'ru' => 'За период',
                    'en' => 'Time period',
                ],
            ])
            ->buildQuery();
    }

    public function renderList()
    {
        $this->getList()->addColumns([
            'id' => 'ID',
            '#href' => [],
            'date' => [
                'value' => function (Model $n) {
                    return \diDateTime::simpleFormat($n->getDate());
                },
                'attrs' => [
                    'width' => '10%',
                ],
                'headAttrs' => [],
                'bodyAttrs' => [
                    'class' => 'dt',
                ],
            ],
            'title' => [
                'attrs' => [
                    'width' => '90%',
                ],
            ],
            '#edit' => [],
            '#del' => [],
            '#visible' => [],
        ]);
    }

    public function renderForm()
    {
    }

    public function submitForm()
    {
        $this->getSubmit()
            ->makeSlug()
            ->storeImage('pic');
    }

    protected function afterSubmitForm()
    {
        parent::afterSubmitForm();

        $this->dispatchNewsletter();
    }

    protected function dispatchNewsletter()
    {
        $mq = Queue::basicCreate();
        /** @var Model $m */
        $m = \diModel::create(
            Types::news,
            $this->getSubmit()
                ->getModel()
                ->getId()
        );

        if (
            !\diRequest::post('dispatch', 0) &&
            !\diRequest::post('dispatch_test', 0)
        ) {
            return $this;
        }

        $mode = \diRequest::post('dispatch_test', 0)
            ? self::DISPATCH_MODE_TEST
            : self::DISPATCH_MODE_STANDARD;
        $sender = \diConfiguration::get('newsletter_email');
        $subject = $m->getTitle();
        $content = $m->getContent();

        $newsLetterFlag = true;

        // attaches
        $attaches = [];
        $fileReplaces = [];

        $fn = \diPaths::fileSystem() . $m->getPicsFolder() . $m->getPic();
        $fileReplaces['{PIC}'] = '';

        if ($m->hasPic() && is_file($fn)) {
            $imgContent = file_get_contents($fn);

            $ext = strtolower(StringHelper::fileExtension($m->getPic()));
            $contentType = StringHelper::mimeTypeByFilename($m->getPic());

            $cid = get_unique_id();

            $attaches[] = [
                'filename' => 'news.' . $ext,
                'content_type' => $contentType,
                'data' => $imgContent,
                'content_id' => $cid,
            ];

            $fileReplaces['{PIC}'] = "<img src=\"cid:$cid\">";

            IncutCollection::createBinaryAttachment()
                ->filterByTargetType(Types::news)
                ->filterByTargetId($m->getId())
                ->hardDestroy();

            IncutModel::createBinaryAttachment(
                serialize($attaches),
                Types::news,
                $m->getId()
            )->save();

            $content = str_replace(
                array_keys($fileReplaces),
                array_values($fileReplaces),
                $content
            );
        }
        //

        $body_prefix = <<<EOF
<!DOCTYPE html>
<html>
<head><title>domain.com</title>
<meta http-equiv="content-type" content="text/html; charset=utf-8" />
<style>body{font-family:Verdana,sans-serif;font-size:14px;}</style>
</head>

<body>

<b>{$subject}</b><br />
{$content}<br />
EOF;

        $users = \diCollection::create(
            Types::user,
            $mode == self::DISPATCH_MODE_TEST
                ? 'WHERE email' .
                    \diDB::in(
                        preg_split(
                            "/[\r\n\s,;]+/",
                            \diConfiguration::get('newsletter_test_emails')
                        )
                    )
                : "WHERE email!='' and notify_news='1' and newsletter_flag='0' and active='1' ORDER BY id ASC"
        );
        /** @var \diCore\Entity\User\Model $user */
        foreach ($users as $user) {
            $recipient = $user->getEmail();

            $bodySuffix = <<<EOF

<br />
<br />
---<br />
<a href="https://domain.com/">domain.com</a>
</body>
EOF;

            /*
			<br /><br /><br />
			Отписаться от данной рассылки можно, перейдя по ссылке:
			<a href="http://domain.com/unsubscribe/$user_r->id-$user_r->activation_key/">http://domain.com/unsubscribe/$user_r->id-$user_r->activation_key/</a><br />
			*/

            $mq->add(
                $sender,
                $recipient,
                $subject,
                $body_prefix . $bodySuffix,
                false,
                $m->getId()
            );

            if ($newsLetterFlag) {
                $user->setNewsletterFlag(1)->save();
            }
        }

        if ($newsLetterFlag) {
            $this->getDb()->update('users', ['newsletter_flag' => 0]);
        }

        return $this;
    }

    public function getFormTabs()
    {
        return [
            //'pics' => 'Фотографии',
            'meta' => 'SEO',
        ];
    }

    public function getFormFields()
    {
        return [
            'date' => [
                'type' => 'datetime_str',
                'title' => $this->localized([
                    'ru' => 'Дата публикации',
                    'en' => 'Date/time',
                ]),
                'default' => \diDateTime::sqlFormat(),
            ],

            'title' => [
                'type' => 'string',
                'title' => $this->localized([
                    'ru' => 'Заголовок',
                    'en' => 'Title',
                ]),
                'default' => '',
            ],

            $this->slugSourceFieldName => [
                'type' => 'string',
                'title' => $this->localized([
                    'ru' => 'Название для URL',
                    'en' => 'Slug source',
                ]),
                'default' => '',
            ],

            'href' => [
                'type' => 'href',
            ],

            'short_content' => [
                'type' => 'text',
                'title' => $this->localized([
                    'ru' => 'Краткое наполнение',
                    'en' => 'Short content',
                ]),
                'default' => '',
            ],

            'content' => [
                'type' => 'wysiwyg',
                'title' => $this->localized([
                    'ru' => 'Полный текст',
                    'en' => 'Content',
                ]),
                'default' => '',
                //'notes'		=> array('Токен {PIC} будет заменен на подгруженную картинку'),
            ],

            'pic' => [
                'type' => 'pic',
                'title' => $this->localized([
                    'ru' => 'Изображение',
                    'en' => 'Picture',
                ]),
                'default' => '',
                'tab' => 'pics',
            ],

            'html_title' => [
                'type' => 'string',
                'title' => $this->localized([
                    'ru' => 'Meta-заголовок',
                    'en' => 'Meta-title',
                ]),
                'default' => '',
                'tab' => 'meta',
            ],

            'html_keywords' => [
                'type' => 'string',
                'title' => $this->localized([
                    'ru' => 'Meta-ключевые слова',
                    'en' => 'Meta-keywords',
                ]),
                'default' => '',
                'tab' => 'meta',
            ],

            'html_description' => [
                'type' => 'string',
                'title' => $this->localized([
                    'ru' => 'Meta-описание',
                    'en' => 'Meta-description',
                ]),
                'default' => '',
                'tab' => 'meta',
            ],
        ];
    }

    public function getLocalFields()
    {
        return [
            $this->slugFieldName => [
                'type' => 'string',
                'default' => '',
            ],

            'order_num' => [
                'type' => 'order_num',
                'default' => 0,
                'direction' => -1,
            ],

            'pic_t' => [
                'type' => 'int',
                'default' => '',
            ],

            'pic_w' => [
                'type' => 'int',
                'default' => '',
            ],

            'pic_h' => [
                'type' => 'int',
                'default' => '',
            ],

            'pic_tn_w' => [
                'type' => 'int',
                'default' => '',
            ],

            'pic_tn_h' => [
                'type' => 'int',
                'default' => '',
            ],
        ];
    }

    public function getModuleCaption()
    {
        return [
            'ru' => 'Новости',
            'en' => 'News',
        ];
    }
}
