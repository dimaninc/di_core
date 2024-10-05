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
                /*
                 * @deprecated: use collectionFilter
                 * SQL query conditions joined with AND
                 */
                'queryConditions' => [],
                'collectionFilter' => null, // fn (\diCollection $col, string $testingSlug) => $col->filterBy(...)
                'extraUniqueChecker' => null, // fn (string $fullSlug) => true if unique and no dupes
                'getFullSlug' => null, // fn (string $slug) => string = hrefBase + slug
                'uniqueMaker' => function ($origSlug, $delimiter, $index) {
                    return $origSlug . $delimiter . $index;
                },
                'db' => null,
            ],
            $options
        );

        // simple_debug( "[slug=$slug, table=$table, id=$id]\n" . print_r( ArrayHelper::filterByKey( $options, [], [ 'db', 'collectionFilter', 'extraUniqueChecker', 'getFullSlug', ] ), true ), 'Slug::unique', '-slug' );

        $i = 1;

        if ($slug) {
            $origSlug = $slug;
        } else {
            $origSlug = self::prepare(
                \diTypes::getNameByTable($table),
                $options['delimiter'],
                $options['lowerCase']
            );
            $slug = $origSlug . $options['delimiter'] . $i++;
        }

        // simple_debug("origSlug=$origSlug", 'Slug::unique', '-slug');

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

        $getCol = function ($testingSlug, $queryAr = []) use ($table, $options) {
            $col = \diCollection::createForTable($table);

            if ($options['queryConditions']) {
                $col->setQuery('WHERE ' . join(' AND ', $queryAr));
            } else {
                $col->filterBy($options['slugFieldName'], $testingSlug);

                if ($options['collectionFilter']) {
                    $options['collectionFilter']($col, $testingSlug);
                }
            }

            // simple_debug( $col->getTable() . ': ' . $col->getFullQuery(), 'Slug::unique', '-slug' );

            return $col;
        };

        $extraUniqueChecker = $options['extraUniqueChecker'];
        $getFullSlug = $options['getFullSlug'] ?? fn(string $slug) => $slug;
        // simple_debug('extraUniqueChecker set? ' . ($extraUniqueChecker ? 'yes' : 'no'));
        // simple_debug('getFullSlug set? ' . ($getFullSlug ? 'yes' : 'no'));

        do {
            $col = $getCol($slug, $queryAr);
            $ids = $col->map($options['idFieldName']);

            // var_dump($col->getFullQuery());
            // die();

            $noDupes = !$col->count() || (count($ids) == 1 && in_array($id, $ids));
            $extraUnique = is_callable($extraUniqueChecker)
                ? $extraUniqueChecker($getFullSlug($slug))
                : true;

            if ($noDupes && $extraUnique) {
                break;
            }

            $slug = $options['uniqueMaker']($origSlug, $options['delimiter'], $i++);

            if ($options['queryConditions']) {
                $slugValue = $db ? $db->escapeValue($slug) : "'$slug'";

                $queryAr = array_merge(
                    [$slugField . ' = ' . $slugValue],
                    $options['queryConditions']
                );
            }
        } while (true);

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
