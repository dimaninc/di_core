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

    protected static function getLanguages($properties)
    {
        return !empty($properties['languages']) && is_array($properties['languages'])
            ? array_intersect(
                Collection::getPossibleLanguages(),
                $properties['languages']
            )
            : Collection::getPossibleLanguages();
    }

    protected function getMultiColumn(
        $field,
        $titleOrProps,
        $type = null,
        $tab = null
    ) {
        $ar = [];
        $default = '';
        $defaultProps = [
            'default' => $default,
            'languages' => null,
            'langTitleGetter' => null,
        ];

        if (is_array($titleOrProps) && $type === null && $tab === null) {
            $properties = extend(
                $defaultProps,
                [
                    'title' => '',
                    'type' => 'string',
                    'tab' => null,
                    // fn (string $fieldTitle, string $lang) => string
                ],
                $titleOrProps
            );
        } else {
            $properties = extend($defaultProps, [
                'title' => $titleOrProps,
                'type' => $type ?: 'string',
                'tab' => $tab,
            ]);
        }

        foreach (self::getLanguages($properties) as $lang) {
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
        $languages = self::getLanguages($props);
        $width = $widthSum / count($languages);

        foreach ($languages as $lang) {
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
        foreach (self::getLanguages($props) as $lang) {
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
