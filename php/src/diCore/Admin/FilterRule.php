<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 06.05.2020
 * Time: 10:32
 */

namespace diCore\Admin;

use diCore\Tool\SimpleContainer;

class FilterRule extends SimpleContainer
{
    const contains = 1;
    const startsWith = 2;
    const endsWith = 3;
    const equals = 4;
    const boolInt = 5;
    const intWithNull = 6;

    public static $names = [
        self::contains => 'contains',
        self::startsWith => 'startsWith',
        self::endsWith => 'endsWith',
        self::equals => 'equals',
        self::boolInt => 'boolInt',
        self::intWithNull => 'intWithNull',
    ];

    public static function callback($id)
    {
        if (is_callable($id)) {
            return $id;
        }

        $method = [static::class, static::name($id)];

        if (is_callable($method)) {
            return $method;
        }

        return null;
    }

    public static function extProps($props = [])
    {
        return extend(
            [
                'field' => null,
                'value' => null,
                'negative' => null,
            ],
            $props
        );
    }

    public static function contains($props = [])
    {
        $props = self::extProps($props);

        return function (\diCollection $col) use ($props) {
            $col->contains($props['field'], $props['value']);
        };
    }

    public static function startsWith($props = [])
    {
        $props = self::extProps($props);

        return function (\diCollection $col) use ($props) {
            $col->startsWith($props['field'], $props['value']);
        };
    }

    public static function endsWith($props = [])
    {
        $props = self::extProps($props);

        return function (\diCollection $col) use ($props) {
            $col->endsWith($props['field'], $props['value']);
        };
    }

    public static function equals($props = [])
    {
        $props = self::extProps($props);

        return function (\diCollection $col) use ($props) {
            $col->filterBy($props['field'], $props['value']);
        };
    }

    public static function boolInt($props = [])
    {
        $props = self::extProps($props);

        return function (\diCollection $col) use ($props) {
            if ($props['value'] == -1) {
                $col->filterBy($props['field'], false);
            } else {
                $col->filterBy($props['field'], !!$props['value']);
            }
        };
    }

    public static function intWithNull($props = [])
    {
        $props = self::extProps($props);

        return function (\diCollection $col) use ($props) {
            if ($props['value'] == -1) {
                $col->filterBy($props['field'], 0);
            } else {
                $col->filterBy($props['field'], $props['value']);
            }
        };
    }
}
