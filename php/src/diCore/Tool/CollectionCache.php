<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 26.10.2016
 * Time: 13:42
 */

namespace diCore\Tool;

class CollectionCache
{
    const PREFIX_REDIS = 'CollectionCache:';

    const STORAGE_RAM = 1;
    const STORAGE_PREDIS = 2;

    protected static $storage = self::STORAGE_RAM;

    /**
     * @var \Predis\Client
     */
    protected static $predisClient = null;

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
                case self::STORAGE_PREDIS:
                    self::$predisClient->set(
                        self::getRedisKey($modelType),
                        json_encode($col->asDataArray())
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
                case self::STORAGE_PREDIS:
                    self::$predisClient->del(
                        self::$predisClient->keys(self::getRedisKey('*'))
                    );
                    break;

                default:
                    self::$data = [];
                    break;
            }
        } else {
            if (!is_array($modelTypes)) {
                $modelTypes = [$modelTypes];
            }

            foreach ($modelTypes as $modelType) {
                $modelType = \diTypes::getId($modelType);

                switch (self::$storage) {
                    case self::STORAGE_PREDIS:
                        self::$predisClient->del(self::getRedisKey($modelType));
                        break;

                    default:
                        if (isset(self::$data[$modelType])) {
                            unset(self::$data[$modelType]);
                        }
                        break;
                }
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
            case self::STORAGE_PREDIS:
                $json = self::$predisClient->get(self::getRedisKey($modelType));
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

                return isset(self::$data[$modelType])
                    ? self::$data[$modelType]
                    : null;
        }
    }

    /**
     * @param int|string $modelType
     * @return boolean
     */
    public static function exists($modelType)
    {
        $modelType = \diTypes::getId($modelType);

        switch (self::$storage) {
            case self::STORAGE_PREDIS:
                return self::$predisClient->exists(self::getRedisKey($modelType));

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

    public static function getRedisKey($modelType)
    {
        return self::PREFIX_REDIS . 'type=' . $modelType;
    }

    public static function useRedis(\Predis\Client $client)
    {
        self::$storage = self::STORAGE_PREDIS;

        self::$predisClient = $client;
    }
}
