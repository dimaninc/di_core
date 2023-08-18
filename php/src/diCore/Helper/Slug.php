<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 02.07.2015
 * Time: 17:51
 */

namespace diCore\Helper;

class Slug
{
    public static function prepare($source, $delimiter = '-', $lowerCase = true)
    {
        if (!$source) {
            return '';
        }

        $source = trim($source, ' \"\'');
        $source = str_replace([' ', '/', '\\', '_', '-'], $delimiter, $source);
        $source = preg_replace('/\&\#?[a-z0-9]+\;/', '', $source);
        $source = transliterate_rus_to_eng($source, $lowerCase);
        $source = preg_replace("/[^a-zA-Z0-9{$delimiter}]/", '', $source);
        $source = preg_replace("/{$delimiter}{2,}/", $delimiter, $source);

        return $source;
    }

    public static function unique($slug, $table, $id, $options = [])
    {
        $options = extend(
            [
                'idFieldName' => 'id',
                'slugFieldName' => 'slug',
                'lowerCase' => true,
                'delimiter' => '-',
                'queryConditions' => [],
                'uniqueMaker' => function ($origSlug, $delimiter, $index) {
                    return $origSlug . $delimiter . $index;
                },
                'db' => null,
            ],
            $options
        );

        $i = 1;

        if ($slug) {
            $origSlug = $slug;
        } else {
            $origSlug = self::prepare(
                \diTypes::getNameByTable($table),
                $options['delimiter'],
                $options['lowerCase']
            );
            $slug = $origSlug . $options['delimiter'] . strval($i++);
        }

        /** @var \diDB $db */
        $db = $options['db'];
        $slugField = $db
            ? $db->escapeField($options['slugFieldName'])
            : $options['slugFieldName'];
        $slugValue = $db ? $db->escapeValue($slug) : "'$slug'";

        $queryAr = array_merge(
            [$slugField . ' = ' . $slugValue],
            $options['queryConditions']
        );

        while (true) {
            $model = \diCollection::createForTable(
                $table,
                'WHERE ' . join(' AND ', $queryAr)
            )->getFirstItem();

            if (
                !$model->exists() ||
                $id == $model->get($options['idFieldName'])
            ) {
                break;
            }

            $slug = $options['uniqueMaker'](
                $origSlug,
                $options['delimiter'],
                $i++
            );
            $slugValue = $db ? $db->escapeValue($slug) : "'$slug'";

            $queryAr = array_merge(
                [$slugField . ' = ' . $slugValue],
                $options['queryConditions']
            );
        }

        return $slug;
    }

    public static function generate(
        $source,
        $table,
        $id = null,
        $idFieldName = 'id',
        $slugFieldName = 'slug',
        $delimiter = '-',
        $extraOptions = []
    ) {
        if (is_object($table) && $table instanceof \diModel) {
            $delimiter = $id ?: $delimiter;
            $id = $table->getId();
            $idFieldName = $table->getIdFieldName();
            $slugFieldName = $table->getSlugFieldName();

            $table = $table->getTable();
        }

        $extraOptions = extend(
            [
                'idFieldName' => $idFieldName,
                'slugFieldName' => $slugFieldName,
                'delimiter' => $delimiter,
                'lowerCase' => true,
                'db' => null,
            ],
            $extraOptions
        );

        return self::unique(
            self::prepare(
                $source,
                $extraOptions['delimiter'],
                $extraOptions['lowerCase']
            ),
            $table,
            $id,
            $extraOptions
        );
    }
}
