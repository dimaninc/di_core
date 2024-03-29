<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 24.06.2016
 * Time: 14:43
 */

namespace diCore\Tool;

abstract class SimpleContainer
{
    public static $names = [];
    public static $titles = [];
    public static $descriptions = [];

    public static $customNames = [];
    public static $customTitles = [];
    public static $customDescriptions = [];

    public static function name($id)
    {
        return static::names()[$id] ?? null;
    }

    public static function title($id)
    {
        return static::titles()[$id] ?? null;
    }

    public static function description($id)
    {
        return static::descriptions()[$id] ?? null;
    }

    public static function names()
    {
        return extend(static::$names, static::$customNames);
    }

    public static function titles()
    {
        return extend(static::$titles, static::$customTitles);
    }

    public static function descriptions()
    {
        return extend(static::$descriptions, static::$customDescriptions);
    }

    public static function id($name)
    {
        if (isInteger($name)) {
            return isset(static::names()[$name]) ? (int) $name : null;
        }

        $id = array_search($name, static::names());

        if ($id === false) {
            $id = defined("static::$name") ? constant("static::$name") : null;
        }

        return $id;
    }

    public static function idByTitle($title)
    {
        $id = array_search($title, static::titles()) ?: null;

        return $id;
    }

    protected static function getCollectionElement($id)
    {
        return [
            'id' => $id,
            'name' => static::name($id),
            'title' => static::title($id),
            'description' => static::description($id),
        ];
    }

    public static function getCollection()
    {
        $ar = [];

        foreach (static::names() as $id => $name) {
            $ar[] = static::getCollectionElement($id);
        }

        return $ar;
    }
}
