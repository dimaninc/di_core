<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 13.04.2023
 * Time: 15:18
 */

namespace diCore\Traits\Admin;

use diCore\Entity\Localization\Collection;

trait MultiColumn
{
    protected function getMultiColumn(
        $field,
        $titleOrProps,
        $type = null,
        $tab = null
    ) {
        $ar = [];
        $default = '';

        if (is_array($titleOrProps) && $type === null && $tab === null) {
            $properties = extend(
                [
                    'title' => '',
                    'type' => 'string',
                    'default' => $default,
                    'tab' => null,
                ],
                $titleOrProps
            );
        } else {
            $properties = [
                'title' => $titleOrProps,
                'type' => $type ?: 'string',
                'default' => $default,
                'tab' => $tab,
            ];
        }

        foreach (Collection::getPossibleLanguages() as $lang) {
            $fieldTitle =
                $lang !== Collection::getDefaultLanguage()
                    ? "($lang)"
                    : "{$properties['title']} ($lang)";

            $ar[\diModel::getLocalizedFieldName($field, $lang)] = extend(
                $properties,
                [
                    'title' => $fieldTitle,
                ]
            );
        }

        return $ar;
    }

    protected function getMultiTitle($tab = null)
    {
        return $this->getMultiColumn('title', 'Title', 'string', $tab);
    }

    protected function getMultiContent($tab = null)
    {
        return $this->getMultiColumn('content', 'Description', 'text', $tab);
    }

    protected function getListMultiColumn($field, $widthSum, $props = [])
    {
        $ar = [];
        $width = $widthSum / count(Collection::getPossibleLanguages());

        foreach (Collection::getPossibleLanguages() as $lang) {
            $ar[\diModel::getLocalizedFieldName($field, $lang)] = extend(
                [
                    'headAttrs' => [
                        'width' => $width . '%',
                    ],
                ],
                $props
            );
        }

        return $ar;
    }

    protected function getListMultiTitle($widthSum)
    {
        return $this->getListMultiColumn('title', $widthSum);
    }

    protected function addMultiColumnFilters($field, $props)
    {
        foreach (Collection::getPossibleLanguages() as $lang) {
            $fieldTitle = "{$props['title']} ($lang)";

            $this->getFilters()->addFilter(
                extend($props, [
                    'field' => \diModel::getLocalizedFieldName($field, $lang),
                    'title' => $fieldTitle,
                ])
            );
        }

        return $this;
    }
}
