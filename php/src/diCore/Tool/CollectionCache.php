<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 26.10.2016
 * Time: 13:42
 */

namespace diCore\Tool;

use diCore\Helper\ArrayHelper;

/*
 * учитывать query в ключе
 * отключать для коллекций, созданных вручную
 */
class CollectionCache
{
    const REDIS_SEP = ':';
    const REDIS_PREFIX = 'CollectionCache';
    const REDIS_DEFAULT_LIFETIME = 60; // 1 minute

    const STORAGE_RAM = 1;
    const STORAGE_REDIS = 2;

    protected static $storage = self::STORAGE_RAM;

    /**
     * @var \Redis
     */
    protected static $redisClient = null;

    /**
     * @var array
     * lifetime => cache lifetime in minutes
     */
    protected static $redisOptions = [];

    protected static $data = [];

    public static function addForCollection(
        $modelType,
        \diCollection $col,
        callable $callback,
        $options = []
    ) {
        $options = extend(
            [
                'field' => 'id',
                'queryFields' => null,
            ],
            is_array($options)
                ? $options
                : [
                    'field' => $options,
                ]
        );

        $values = [];

        /** @var \diModel $model */
        foreach ($col as $model) {
            $values[] = $callback($model);
        }

        self::add([
            \diCollection::create(
                $modelType,
                "WHERE {$options['field']}" . \diDB::in(array_unique($values)),
                $options['queryFields']
            ),
        ]);
    }

    public static function add($collections)
    {
        if (!is_array($collections)) {
            $collections = [$collections];
        }

        /** @var \diCollection $col */
        foreach ($collections as $col) {
            $modelType = \diTypes::getId($col->getModelType());

            switch (self::$storage) {
                case self::STORAGE_REDIS:
                    self::$redisClient->set(
                        self::getRedisKey($modelType, $col->getUniqueIdItems()),
                        json_encode($col->asDataArray()),
                        [
                            'ex' => ArrayHelper::get(
                                self::$redisOptions,
                                'lifetime',
                                self::REDIS_DEFAULT_LIFETIME
                            ),
                        ]
                    );
                    break;

                default:
                    self::$data[$modelType] = $col;
                    break;
            }
        }
    }

    public static function addManual($dataType, $field, $values)
    {
        $col = \diCollection::create($dataType);
        $col->filterBy($field, $values);

        self::add($col);
    }

    public static function append(\diCollection $col)
    {
        $type = \diTypes::getId($col->getModelType());
        $existingCol = static::get($type) ?: \diCollection::createEmpty($type);
        $existingCol->addItems($col);
        static::add($existingCol);
    }

    public static function appendModel(\diModel $model)
    {
        $type = \diTypes::getId($model->modelType());
        $existingCol = static::get($type) ?: \diCollection::createEmpty($type);
        $existingCol->addItem($model);
        static::add($existingCol);
    }

    public static function remove($modelTypes = null)
    {
        if ($modelTypes === null) {
            switch (self::$storage) {
                case self::STORAGE_REDIS:
                    self::$redisClient->del(
                        self::$redisClient->keys(self::getRedisKey('*'))
                    );
                    break;

                default:
                    self::$data = [];
                    break;
            }

            return;
        }

        if (!is_array($modelTypes)) {
            $modelTypes = [$modelTypes];
        }

        foreach ($modelTypes as $modelType) {
            $modelType = \diTypes::getId($modelType);

            switch (self::$storage) {
                case self::STORAGE_REDIS:
                    self::$redisClient->del(self::getRedisKey($modelType, ['*']));
                    break;

                default:
                    unset(self::$data[$modelType]);
                    break;
            }
        }
    }

    /**
     * @param int|string $modelType
     * @return \diCollection
     */
    public static function get($modelType, $force = false)
    {
        $modelType = \diTypes::getId($modelType);

        switch (self::$storage) {
            case self::STORAGE_REDIS:
                // todo: тут главный затык – как при гете из кеша узнать ключи?
                $json = self::$redisClient->get(self::getRedisKey($modelType));
                $ar = $json ? json_decode($json, true) : null;

                if ($ar !== null) {
                    $col = \diCollection::createEmpty($modelType)->addItems($ar);
                } elseif ($force) {
                    $col = \diCollection::create($modelType);
                    self::add([$col]);
                } else {
                    $col = null;
                }

                return $col;

            default:
                if (!isset(self::$data[$modelType]) && $force) {
                    self::$data[$modelType] = \diCollection::create($modelType);
                }

                return self::$data[$modelType] ?? null;
        }
    }

    /**
     * @param int|string $modelType
     * @return boolean
     */
    public static function exists($modelType, $idItems = [])
    {
        $modelType = \diTypes::getId($modelType);

        switch (self::$storage) {
            case self::STORAGE_REDIS:
                return self::$redisClient->exists(
                    self::getRedisKey($modelType, $idItems)
                );

            default:
                return isset(self::$data[$modelType]);
        }
    }

    /**
     * @param int|string $modelType
     * @param int        $modelId
     * @return \diModel
     * @throws \Exception
     */
    public static function getModel($modelType, $modelId, $force = false)
    {
        $col = self::get($modelType);

        return $col && $col[$modelId]
            ? $col[$modelId]
            : \diModel::create($modelType, $force ? $modelId : null, 'id');
    }

    public static function getRedisKey($modelType, $idItems = [])
    {
        return join(
            self::REDIS_SEP,
            array_merge([self::REDIS_PREFIX, 'type=' . $modelType], $idItems)
        );
    }

    public static function useRedis(\Redis $client, array $options = [])
    {
        self::$storage = self::STORAGE_REDIS;
        self::$redisClient = $client;
        self::$redisOptions = $options;
    }
}
