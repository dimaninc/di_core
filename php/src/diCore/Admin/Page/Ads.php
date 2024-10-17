<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 29.05.2015
 * Time: 23:01
 */

namespace diCore\Admin\Page;

use diCore\Admin\Data\FormFlag;
use diCore\Data\Types;
use diCore\Entity\Ad\Helper;
use diCore\Entity\Ad\HrefTarget;
use diCore\Entity\Ad\Model;
use diCore\Entity\Ad\ShowOnHolidays;
use diCore\Tool\CollectionCache;

class Ads extends \diCore\Admin\BasePage
{
    protected $options = [
        'filters' => [
            'defaultSorter' => [
                'sortBy' => 'order_num',
                'dir' => 'ASC',
            ],
            /*
			'sortByAr' => [
				'order_num' => 'По порядку',
			],
			*/
        ],
    ];

    public function __construct(\diCore\Admin\Base $X)
    {
        parent::__construct($X);

        CollectionCache::add([
            \diCollection::create(Types::ad_block)->orderBy('order_num'),
        ]);
    }

    protected function initTable()
    {
        $this->setTable('ads');
    }

    protected function setupFilters()
    {
        $this->getFilters()
            ->addFilter([
                'field' => 'block_id',
                'type' => 'int',
                'title' => 'Блок',
                'strict' => true,
            ])
            ->buildQuery()
            ->setSelectFromCollectionInput(
                'block_id',
                CollectionCache::get(Types::ad_block)
            );
    }

    public function renderList()
    {
        $this->getList()->addColumns([
            'id' => 'ID',
            'pic' => [
                'title' => 'Слайд',
                'value' => function (Model $ad) {
                    return $ad->hasPic()
                        ? '<img src="/' .
                                $ad->getPicsFolder() .
                                $ad->getPic() .
                                '" height="100">'
                        : '&mdash;';
                },
                'bodyAttrs' => [
                    'class' => 'no-padding',
                ],
            ],
            'title' => [
                'headAttrs' => [
                    'width' => '70%',
                ],
            ],
            'href' => [
                'headAttrs' => [
                    'width' => '30%',
                ],
            ],
            '#edit' => [],
            '#del' => [],
            '#visible' => [],
            '#up' => [],
            '#down' => [],
        ]);
    }

    public function getFormTabs()
    {
        return [
            'schedule' => 'Расписание',
        ];
    }

    public function renderForm()
    {
        $this->getForm()
            ->setSelectFromArrayInput('transition', Helper::$adTransitionsAr)
            ->setSelectFromArrayInput(
                'transition_style',
                Helper::$adTransitionStylesAr
            )
            ->setSelectFromArrayInput('href_target', HrefTarget::$titles)
            ->setSelectFromArrayInput('show_on_holidays', ShowOnHolidays::$titles)
            ->setSelectFromCollectionInput(
                'block_id',
                CollectionCache::get(Types::ad_block)
            );
    }

    public function submitForm()
    {
        $this->getSubmit()->storeImage(['pic']);
    }

    protected function getQueryParamsForRedirectAfterSubmit()
    {
        $ar = parent::getQueryParamsForRedirectAfterSubmit();

        $ar['block_id'] = $this->getSubmit()->getData('block_id');

        return $ar;
    }

    public function getFormFields()
    {
        try {
            $blockHref =
                $this->getId() &&
                $this->getForm() &&
                $this->getForm()->getModel() &&
                $this->getForm()
                    ->getModel()
                    ->has('block_id')
                    ? '/_admin/ad_blocks/form/' .
                        $this->getForm()
                            ->getModel()
                            ->get('block_id') .
                        '/'
                    : null;
        } catch (\Exception $e) {
            $blockHref = null;
        }

        $blockHrefStr = $blockHref
            ? ' берётся из <a href="' .
                $blockHref .
                '" data-link="ad_block">настроек блока</a>'
            : ' берётся из настроек блока';

        return [
            'block_id' => [
                'type' => 'int',
                'title' => 'Блок',
                'default' => 1,
            ],

            'category_id' => [
                'type' => 'int',
                'title' => 'Категория',
                'default' => 0,
                'flags' => ['hidden'],
            ],

            'title' => [
                'type' => 'string',
                'title' => 'Заголовок',
                'default' => '',
            ],

            'button_color' => [
                'type' => 'string',
                'title' => 'Цвет кнопки',
                'default' => '',
                'flags' => ['hidden'],
            ],

            'content' => [
                'type' => 'text',
                'title' => 'Описание',
                'default' => '',
            ],

            'href' => [
                'type' => 'string',
                'title' => 'Ссылка',
                'default' => '',
            ],

            'href_target' => [
                'type' => 'int',
                'title' => 'Где открывать ссылку',
                'default' => 0,
            ],

            'onclick' => [
                'type' => 'string',
                'title' => 'Javascript OnClick',
                'default' => '',
            ],

            'transition' => [
                'type' => 'int',
                'title' => 'Переход',
                'default' => 0,
                'notes' => ['По умолчанию' . $blockHrefStr],
            ],

            'transition_style' => [
                'type' => 'int',
                'title' => 'Стиль перехода (только для скроллинга)',
                'default' => 0,
                'notes' => ['По умолчанию' . $blockHrefStr],
            ],

            'duration_of_show' => [
                'type' => 'int',
                'title' => 'Время показа слайда, мс',
                'default' => -1,
                'notes' => ['Если -1,' . $blockHrefStr],
            ],

            'duration_of_change' => [
                'type' => 'int',
                'title' => 'Время смены слайда, мс',
                'default' => -1,
                'notes' => ['Если -1,' . $blockHrefStr],
            ],

            'pic' => [
                'type' => 'pic',
                'title' => 'Картинка',
                'default' => '',
                //'notes'     => array('Размер: '.diConfiguration::get('ads_width').'x'.diConfiguration::get('ads_height').' пикселей'),
            ],

            'show_date1' => [
                'type' => 'date_str',
                'title' => 'Начало показа (дата)',
                'default' => null,
                'tab' => 'schedule',
            ],

            'show_date2' => [
                'type' => 'date_str',
                'title' => 'Окончание показа (дата)',
                'default' => null,
                'tab' => 'schedule',
            ],

            'show_time1' => [
                'type' => 'time_str',
                'title' => 'Начало показа (время)',
                'default' => null,
                'tab' => 'schedule',
            ],

            'show_time2' => [
                'type' => 'time_str',
                'title' => 'Окончание показа (время)',
                'default' => null,
                'tab' => 'schedule',
            ],

            'show_on_weekdays' => [
                'type' => 'checkboxes',
                'title' => 'Дни недели для показа',
                'default' => '',
                'feed' => \diDateTime::$weekDays,
                'options' => [
                    'externalSeparators' => true,
                ],
                'tab' => 'schedule',
            ],

            'show_on_holidays' => [
                'type' => 'int',
                'title' => 'Показывать на праздники',
                'default' => 0,
                'tab' => 'schedule',
            ],

            'properties' => [
                'type' => 'json',
                'default' => null,
                'flags' => [FormFlag::hidden],
            ],

            'date' => [
                'type' => 'datetime_str',
                'title' => 'Дата создания',
                'default' => \diDateTime::sqlFormat(),
                'flags' => ['static', 'untouchable', 'initially_hidden'],
            ],
        ];
    }

    public function getLocalFields()
    {
        return [
            'order_num' => [
                'type' => 'order_num',
                'default' => 0,
                'direction' => 1,
            ],

            'pic_w' => [
                'type' => 'int',
                'default' => 0,
            ],

            'pic_h' => [
                'type' => 'int',
                'default' => 0,
            ],
        ];
    }

    public function getModuleCaption()
    {
        return [
            'ru' => 'Слайды рекламного блока',
            'en' => 'Ad slides',
        ];
    }
}
