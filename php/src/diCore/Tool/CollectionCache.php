<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 26.10.2016
 * Time: 13:42
 */

namespace diCore\Tool;

use diCore\Database\RedisConnection;
use diCore\Helper\ArrayHelper;

/*
 * todo учитывать query в ключе
 * todo отключать для коллекций, созданных вручную
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
     * @var RedisConnection
     */
    protected static $redisConn = null;

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
                    self::redisAddSingle($col);
                    break;

                default:
                    self::$data[$modelType] = $col;
                    break;
            }
        }
    }

    public static function redisAddSingle(\diCollection $col)
    {
        $modelType = \diTypes::getId($col->getModelType());

        self::$redisClient->set(
            self::getRedisKey($modelType, $col->getCacheSuffixAr()),
            json_encode($col->asDataArray()),
            [
                'ex' => ArrayHelper::get(
                    self::$redisOptions,
                    'lifetime',
                    self::REDIS_DEFAULT_LIFETIME
                ),
            ]
        );
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
    public static function get($modelType, $force = false, $cacheIdSuffix = [])
    {
        $modelType = \diTypes::getId($modelType);

        switch (self::$storage) {
            case self::STORAGE_REDIS:
                // todo: тут главный затык – как при гете из кеша узнать ключи?
                // возможно, с вводом $cacheIdSuffix затык исчез
                return self::redisGet($modelType, $force, $cacheIdSuffix);

            default:
                if (!isset(self::$data[$modelType]) && $force) {
                    self::$data[$modelType] = \diCollection::create($modelType);
                }

                return self::$data[$modelType] ?? null;
        }
    }

    public static function redisGet($modelType, $force = false, $cacheIdSuffix = [])
    {
        $modelType = \diTypes::getId($modelType);
        $key = self::getRedisKey($modelType, $cacheIdSuffix);

        $json = self::$redisClient->get($key);
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
    }

    /**
     * @param int|string $modelType
     * @return boolean
     */
    public static function exists($modelType, $cacheIdSuffix = [])
    {
        $modelType = \diTypes::getId($modelType);

        switch (self::$storage) {
            case self::STORAGE_REDIS:
                return self::redisExists($modelType, $cacheIdSuffix);

            default:
                return isset(self::$data[$modelType]);
        }
    }

    public static function redisExists($modelType, $cacheIdSuffix = [])
    {
        $modelType = \diTypes::getId($modelType);

        return self::$redisClient->exists(
            self::getRedisKey($modelType, $cacheIdSuffix)
        );
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

    public static function getRedisKey($modelType, $cacheIdSuffix = [])
    {
        if (!is_array($cacheIdSuffix)) {
            $cacheIdSuffix = [$cacheIdSuffix];
        }

        return join(
            self::REDIS_SEP,
            array_merge(
                array_filter([
                    self::$redisConn->getConnData()->getOtherOptions('prefix'),
                    self::REDIS_PREFIX,
                    "type=$modelType",
                ]),
                $cacheIdSuffix
            )
        );
    }

    public static function setRedisStorage(
        RedisConnection $conn,
        array $options = []
    ) {
        self::$redisConn = $conn;
        self::$redisClient = $conn->getDb();
        self::$redisOptions = $options;
    }

    public static function hasRedisClient()
    {
        return !!self::$redisClient;
    }

    public static function useRedis()
    {
        self::$storage = self::STORAGE_REDIS;
    }

    public static function useRam()
    {
        self::$storage = self::STORAGE_RAM;
    }
}
