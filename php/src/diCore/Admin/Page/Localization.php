<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 20.09.15
 * Time: 23:52
 */

namespace diCore\Admin\Page;

use diCore\Admin\FilterRule;
use diCore\Admin\Form;
use diCore\Entity\Localization\Model;
use diCore\Helper\StringHelper;
use diCore\Traits\Admin\MultiColumn;

class Localization extends \diCore\Admin\BasePage
{
    use MultiColumn;

    protected $options = [
        'showControlPanel' => true,
        'filters' => [
            'defaultSorter' => [
                'sortBy' => 'name',
                'dir' => 'ASC',
            ],
            'buttonOptions' => [
                'suffix' =>
                    '<button type="button" name="export" class="blue">Экспорт</button>',
            ],
            'sortByAr' => [
                'name' => 'По токену',
                'value' => 'По рус.значению',
                'en_value' => 'По англ.значению',
                'id' => 'По ID',
            ],
        ],
    ];

    protected function initTable()
    {
        $this->setTable('localization');
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
                'field' => 'name',
                'type' => 'string',
                'title' => [
                    'ru' => 'Токен',
                    'en' => 'Token',
                ],
                'rule' => FilterRule::contains,
            ]);

        $this->addMultiColumnFilters('value', [
            'type' => 'string',
            'title' => $this->localized([
                'ru' => 'Значение',
                'en' => 'Value',
            ]),
            'rule' => FilterRule::contains,
        ]);

        $this->getFilters()->buildQuery();
    }

    public static function valueOut(Model $m, $field)
    {
        $orig = $s = $m->get($field) ?? '';
        $origEscaped = StringHelper::out($orig);

        $s = utf8_wordwrap($s, 21, ' ', true);
        $s = strip_tags($s);
        $s = StringHelper::cutEnd($s, 100);

        return $s .
            "<div class=\"display-none\" data-purpose=\"orig\" data-orig-value=\"$origEscaped\"></div>";
    }

    public function renderList()
    {
        $this->setAfterTableTemplate('admin/localization/after_list');

        $valueOut = function (Model $m, $field) {
            return static::valueOut($m, $field);
        };

        $valuesAr = $this->getListMultiColumn('value', 80, [
            'value' => $valueOut,
        ]);

        $this->getList()->addColumns([
            'id' => 'ID',
            '_checkbox' => '',
            'name' => [
                'headAttrs' => [
                    'width' => '20%',
                ],
                'bodyAttrs' => [
                    'class' => 'regular',
                ],
                'noHref' => true,
            ],
            '#edit' => '',
            '#del' => [
                'active' => function (Model $m, $field) {
                    return $this->getAdmin()->isAdminSuper();
                },
            ],
        ]);

        $this->getList()->insertColumnsAfter('name', $valuesAr);
    }

    public function renderForm()
    {
        $this->setAfterFormTemplate('admin/localization/after_form');

        if (!$this->getAdmin()->isAdminSuper() && $this->getId()) {
            $this->getForm()->setStaticInput('name');
        }

        if ($this->getAdmin()->isAdminSuper() && $this->getId()) {
            $this->getForm()->setSubmitButtonsOptions([
                'show_additional' => 'clone',
            ]);
        }
    }

    public function submitForm()
    {
    }

    protected function afterSubmitForm()
    {
        parent::afterSubmitForm();

        $L = \diCore\Tool\Localization::basicCreate();
        $L->createCache();
    }

    public function getFormTabs()
    {
        return [];
    }

    public function getFormFields()
    {
        $formatter = [Form::class, 'valueFormatterEscapeAmp'];

        $valuesAr = $this->getMultiColumn('value', [
            'type' => 'text',
            'title' => $this->localized([
                'ru' => 'Значение',
                'en' => 'Value',
            ]),
            'default' => '',
            'options' => [
                'rows' => 3,
                'valueFormatter' => $formatter,
            ],
        ]);

        return extend(
            [
                'name' => [
                    'type' => 'string',
                    'title' => $this->localized([
                        'ru' => 'Токен',
                        'en' => 'Token',
                    ]),
                    'default' => '',
                ],
            ],
            $valuesAr
        );
    }

    public function getLocalFields()
    {
        return [];
    }

    public function getModuleCaption()
    {
        return [
            'ru' => 'Локализация',
            'en' => 'Localization',
        ];
    }

    public function useEditLog()
    {
        return true;
    }
}
