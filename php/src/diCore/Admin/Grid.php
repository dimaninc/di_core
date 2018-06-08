<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 13.10.15
 * Time: 10:23
 */

namespace diCore\Admin;

use diCore\Helper\ArrayHelper;

class Grid
{
    /** @var BasePage */
    private $AdminPage;

    /** @var array */
    private $buttonsAr;

    /** @var \diModel */
    private $curModel;

    /** @var array */
    private $options;

    /** @var string */
    private $templateName = 'admin/_index/grid/page';

    public function __construct(BasePage $AdminPage, $options = [])
    {
        $this->AdminPage = $AdminPage;

        $this->options = extend([
        ], $options);
    }

    protected function getAdminPage()
    {
        return $this->AdminPage;
    }

    protected function getLanguage()
    {
        return $this->getAdminPage()->getAdmin()->getLanguage();
    }

    public function addButtons($ar)
    {
        $this->buttonsAr = extend($this->buttonsAr, $ar);

        return $this;
    }

    public function buttonExists($name)
    {
        return isset($this->buttonsAr[$name]);
    }

    /**
     * @param string|array $names
     * @param string|array $attr
     * @param mixed|null $value
     * @return $this
     */
    public function setButtonAttr($names, $attr, $value = null)
    {
        if (!is_array($names))
        {
            $names = [$names];
        }

        foreach ($names as $name)
        {
            if (!$this->buttonExists($name))
            {
                $this->buttonsAr[$name] = [];
            }

            if (!is_array($this->buttonsAr[$name]))
            {
                $this->buttonsAr[$name] = [
                    'title' => $this->buttonsAr[$name],
                ];
            }

            if (!is_array($attr))
            {
                $attr = [
                    $attr => $value,
                ];
            }

            $this->buttonsAr[$name] = extend($this->buttonsAr[$name], $attr);
        }

        return $this;
    }

    public function removeButton($names)
    {
        if (!is_array($names))
        {
            $names = [$names];
        }

        foreach ($names as $name)
        {
            if (isset($this->buttonsAr[$name]))
            {
                unset($this->buttonsAr[$name]);
            }
        }

        return $this;
    }

    public function replaceButton($name, array $newButtons)
    {
        $this
            ->insertButtonsBefore($name, $newButtons)
            ->removeButton($name);

        return $this;
    }

    public function insertButtonsBefore($name, array $newButtons)
    {
        $this->buttonsAr = ArrayHelper::addItemsToAssocArrayBeforeKey($this->buttonsAr, $name, $newButtons);

        return $this;
    }

    public function insertButtonsAfter($name, array $newButtons)
    {
        $this->buttonsAr = ArrayHelper::addItemsToAssocArrayAfterKey($this->buttonsAr, $name, $newButtons);

        return $this;
    }

    public function getTable()
    {
        return $this->getAdminPage()->getTable();
    }

    protected function getTwig()
    {
        return $this->getAdminPage()->getTwig();
    }

    protected function getTpl()
    {
        return $this->getAdminPage()->getTpl();
    }

    protected function setCurModel(\diModel $model)
    {
        $this->curModel = $model;

        return $this;
    }

    public function getCurModel()
    {
        return $this->curModel;
    }

    public function setTemplateName($templateName)
    {
        $this->templateName = $templateName;

        return $this;
    }

    protected function getTemplateName()
    {
        return $this->templateName;
    }

    public function printElements(\diCollection $collection)
    {
        /** @var \diModel $model */
        foreach ($collection as $model)
        {
            $this->setCurModel($model);

            $editHref = Base::getPageUri($this->getTable(), 'form', [
                'id' => $model->getId(),
            ]);

            $buttons = array_intersect_key($this->buttonsAr, \diNiceTableButtons::$titles[$this->getLanguage()]);
            $htmlButtons = [];

            foreach ($buttons as $action => $settings)
            {
                $settings = extend([
                    'allowed' => null,
                ], $settings);

                $options = [];

                switch ($action)
                {
                    case 'edit':
                        $options['href'] = $editHref;
                        break;

                    default:
                        $options['state'] = $model->get($action);
                        break;
                }

                $html = !is_callable($settings['allowed']) || $settings['allowed']($model, $action)
                    ? \diNiceTableButtons::getButton($action, $options)
                    : null;

                if ($html)
                {
                    $htmlButtons[$action] = $html;
                }
            }

            $model
                ->setRelated([
                    'edit_href' => $editHref,
                    'img_url_prefix' => $this->getAdminPage()->getImgUrlPrefix($model),
                    'buttons' => $htmlButtons,
                ]);
        }

        $this->getAdminPage()
            ->setRenderCallback(function() use($collection) {
                return $this->getTwig()
                    ->importFromFastTemplate($this->getTpl(), [
                        'filters',
                        'before_table',
                        'after_table',
                        'navy',
                    ])
                    ->parse($this->getTemplateName(), [
                        'table' => $this->getTable(),
                        'rows' => $collection,
                    ]);
            });

        return $this;
    }
}