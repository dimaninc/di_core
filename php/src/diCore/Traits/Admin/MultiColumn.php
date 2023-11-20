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
    protected static function processLangTitle($fieldProperties, $lang)
    {
        $fn = $fieldProperties['langTitleGetter'] ?? null;

        if ($fn && is_callable($fn)) {
            return $fn($fieldProperties['title'], $lang);
        }

        return $lang !== Collection::getDefaultLanguage()
            ? "($lang)"
            : "{$fieldProperties['title']} ($lang)";
    }

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
                    // fn (string $fieldTitle, string $lang) => string
                    'langTitleGetter' => null,
                ],
                $titleOrProps
            );
        } else {
            $properties = [
                'title' => $titleOrProps,
                'type' => $type ?: 'string',
                'default' => $default,
                'tab' => $tab,
                'langTitleGetter' => null,
            ];
        }

        foreach (Collection::getPossibleLanguages() as $lang) {
            $fieldTitle = self::processLangTitle($properties, $lang);

            $ar[\diModel::getLocalizedFieldName($field, $lang)] = extend(
                $properties,
                [
                    'title' => $fieldTitle,
                ]
            );
        }

        return $ar;
    }

    protected function getMultiTitle($optionsOrTab = null)
    {
        $options = [
            'title' => 'Title',
            'type' => 'string',
        ];

        if (is_scalar($optionsOrTab)) {
            $options['tab'] = $optionsOrTab;
        } else {
            $options = extend($options, $optionsOrTab);
        }

        return $this->getMultiColumn('title', $options);
    }

    protected function getMultiContent($optionsOrTab = null)
    {
        $options = [
            'title' => 'Description',
            'type' => 'text',
        ];

        if (is_scalar($optionsOrTab)) {
            $options['tab'] = $optionsOrTab;
        } else {
            $options = extend($options, $optionsOrTab);
        }

        return $this->getMultiColumn('content', $options);
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
