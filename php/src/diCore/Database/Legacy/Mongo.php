<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 18.01.2018
 * Time: 20:53
 */

namespace diCore\Database\Legacy;

use diCore\Helper\ArrayHelper;
use MongoDB\BSON\ObjectId;
use MongoDB\Client;
use MongoDB\Database;
use MongoDB\Driver\Cursor;
use MongoDB\Model\CollectionInfo;

/**
 * Class Mongo
 * @package diCore\Database\Legacy
 *
 * @method Database getLink
 */
class Mongo extends \diDB
{
    const CHARSET_INIT_NEEDED = false;
    const DEFAULT_PORT = 27017;

    /**
     * @var Client
     */
    protected $mongo;
    /** @var string|null */
    protected $lastInsertId = null;

    protected function __connect()
    {
        $time1 = utime();

        $this->mongo = new Client($this->getServerConnectionString());
        $this->link = $this->mongo->selectDatabase($this->getDatabase());

        $this->time_log('connect', utime() - $time1);

        return true;
    }

    protected function getServerConnectionString()
    {
        $s = 'mongodb://';

        if ($this->getUsername()) {
            $s .= $this->getUsername() . ':' . $this->getPassword() . '@';
        }

        $s .= $this->getHost();

        if ($this->getPort()) {
            $s .= ':' . $this->getPort();
        }

        $s .= '/';

        /*
		if ($this->getDatabase())
		{
			$s .= $this->getDatabase();
		}
		*/

        return $s;
    }

    public function getCollectionResource($collectionName)
    {
        return $this->getLink()->selectCollection($collectionName);
    }

    public function insert($table, $fieldValues = [])
    {
        $time1 = utime();

        $insertResult = $this->getCollectionResource($table)->insertOne(
            $fieldValues
        );
        /** @var ObjectId $id */
        $id = $insertResult->getInsertedId();

        $this->time_log('insert', utime() - $time1);

        return $this->lastInsertId = (string) $id;
    }

    public function update($table, $fieldValues = [], $filterOrId = '')
    {
        $updated = 0;

        $time1 = utime();

        if (is_scalar($filterOrId)) {
            $r = $this->getCollectionResource($table)->updateOne(
                [
                    '_id' => new ObjectId($filterOrId),
                ],
                [
                    '$set' => $fieldValues,
                ]
            );
            $updated = $r->getModifiedCount();
        } elseif (is_array($filterOrId)) {
            if (ArrayHelper::hasStringKey($filterOrId)) {
                $filter = $filterOrId;
            } else {
                $filter = [
                    '_id' => [
                        '$in' => array_map(function ($id) {
                            return new ObjectId($id);
                        }, $filterOrId),
                    ],
                ];
            }

            $r = $this->getCollectionResource($table)->updateMany($filter, [
                '$set' => $fieldValues,
            ]);
            $updated = $r->getModifiedCount();
        }

        $this->time_log('update', utime() - $time1);

        return $updated;
    }

    public static function convertDirection($direction)
    {
        switch (mb_strtolower($direction)) {
            case 'asc':
                $direction = 1;
                break;

            case 'desc':
                $direction = -1;
                break;
        }

        return $direction;
    }

    public function rs($table, $q_ending = '', $q_fields = '*')
    {
        if (is_array($q_ending)) {
            $ar = extend(
                [
                    'filter' => [],
                    'sort' => [],
                    'skip' => null,
                    'limit' => null,
                ],
                $q_ending
            );

            foreach ($ar['sort'] as $field => &$direction) {
                $direction = static::convertDirection($direction);
            }

            $options = array_filter($ar) ?: [];
            unset($options['filter']);

            /** @var Cursor $cursor */
            $cursor = $this->getCollectionResource($table)->find(
                $ar['filter'],
                $options
            );

            return $cursor;
        } else {
            throw new \Exception(
                'Mongo can not execute queries, array filter needed'
            );
        }
    }

    public function delete($table, $filterOrId = '')
    {
        $deleted = 0;

        $time1 = utime();

        if (is_scalar($filterOrId)) {
            $r = $this->getCollectionResource($table)->deleteOne([
                '_id' => new ObjectId($filterOrId),
            ]);
            $deleted = $r->getDeletedCount();
        } elseif (is_array($filterOrId)) {
            if (ArrayHelper::hasStringKey($filterOrId)) {
                $filter = $filterOrId;
            } else {
                $filter = [
                    '_id' => [
                        '$in' => array_map(function ($id) {
                            return new ObjectId($id);
                        }, $filterOrId),
                    ],
                ];
            }

            $r = $this->getCollectionResource($table)->deleteMany($filter);
            $deleted = $r->getDeletedCount();
        } elseif (!$filterOrId && $filterOrId !== '') {
            $this->getCollectionResource($table)->drop();
            $deleted = true;

            $this->_log(
                "Warning, empty Q_ENDING in delete, collection '$table' dropped",
                false
            );
        }

        $this->time_log('delete', utime() - $time1);

        return $deleted;
    }

    public function drop($table)
    {
        $this->getCollectionResource($table)->drop();

        return true;
    }

    protected function __close()
    {
        return true;
    }

    protected function __error()
    {
        return null;
    }

    protected function __q($q)
    {
        return null;
    }

    protected function __rq($q)
    {
        return null;
    }

    protected function __mq($q)
    {
        return null;
    }

    protected function __mq_flush()
    {
        return true;
    }

    protected function __reset(&$rs)
    {
    }

    /**
     * @param $rs Cursor
     * @return object
     */
    protected function __fetch($rs)
    {
        return (object) $this->__fetch_array($rs);
    }

    /**
     * @param $rs Cursor
     * @return array
     */
    protected function __fetch_array($rs)
    {
        return null;
    }

    protected function __count($options)
    {
        $options = extend(
            [
                'collectionName' => null,
                'filters' => [],
            ],
            $options
        );

        $options['filters'] = extend(
            [
                'filter' => [],
                'sort' => [],
                'skip' => null,
                'limit' => null,
            ],
            ArrayHelper::filterByKey($options['filters'], [
                'filter',
                // 'skip',
                // 'limit',
            ])
        );

        $filter = $options['filters']['filter'];
        $options['filters'] = array_filter($options['filters']) ?: [];
        unset($options['filters']['filter']);

        return $options['collectionName']
            ? $this->getCollectionResource($options['collectionName'])->count(
                $filter,
                $options['filters']
            )
            : null;
    }

    protected function __insert_id()
    {
        return $this->lastInsertId;
    }

    protected function __affected_rows()
    {
        return null;
    }

    public function escape_string($s, $binary = false)
    {
        return $s;
    }

    protected function __set_charset($name)
    {
        return true;
    }

    protected function __get_charset()
    {
        return 'utf8';
    }

    public function getTablesInfo()
    {
        $ar = [];

        foreach ($this->getLink()->getCollectionInfo() as $info) {
            $ar[] = [
                'name' => $info['name'],
                'is_view' => false,
                'rows' => 0,
                'size' => ArrayHelper::get($info, 'options.size', 0),
                'index_size' => 0,
            ];
        }

        return $ar;
    }

    public function getTableNames()
    {
        $ar = [];

        /** @var CollectionInfo $col */
        foreach ($this->getLink()->listCollections() as $col) {
            $ar[] = $col->getName();
        }

        sort($ar);

        return $ar;
    }

    public function getFields($table)
    {
        $fields = [];

        $ar = ArrayHelper::get(
            $this->rs($table, [
                'limit' => 1,
            ])->toArray(),
            0,
            []
        );
        foreach ($ar as $name => $value) {
            $type = gettype($value);

            if (is_array($value)) {
                if (!empty($value['milliseconds'])) {
                    $type = 'timestamp';
                }
            }

            $fields[$name] = $type;
        }

        return $fields;
    }

    public function getDumpCliCommand($options = [])
    {
        throw new \Exception('Implement getDumpCliCommand for mongo');
    }

    public function getFieldMethodForModel($field, $method)
    {
        if ($field === '_id') {
            $field = 'id';
        }

        return $method . ucfirst($field);
    }

    public function getAggregateValues(array $options)
    {
        $options = extend(
            [
                'collectionName' => '',
                'field' => '',
                'filter' => [],
                'count' => false,
                'min' => false,
                'max' => false,
                'sum' => false,
            ],
            $options
        );

        $collection = $this->getCollectionResource($options['collectionName']);
        $pipeline = array_merge(
            $options['filter'] ? [['$match' => $options['filter']]] : [],
            [
                [
                    '$group' => extend(
                        [
                            '_id' => 'getAggregateValues',
                        ],
                        $options['count'] ? ['countValue' => ['$sum' => 1]] : [],
                        $options['min']
                            ? ['minValue' => ['$min' => '$' . $options['field']]]
                            : [],
                        $options['max']
                            ? ['maxValue' => ['$max' => '$' . $options['field']]]
                            : [],
                        $options['sum']
                            ? ['sumValue' => ['$sum' => '$' . $options['field']]]
                            : []
                    ),
                ],
            ]
        );

        $result = $collection->aggregate($pipeline)->toArray();

        if (empty($result)) {
            return null;
        }

        return [
            'min' => $result[0]['minValue'] ?? null,
            'max' => $result[0]['maxValue'] ?? null,
            'sum' => $result[0]['sumValue'] ?? null,
            'count' => $result[0]['countValue'] ?? null,
        ];
    }

    public static function fromBson($item)
    {
        if (
            $item instanceof \MongoDB\Model\BSONDocument ||
            $item instanceof \MongoDB\Model\BSONArray
        ) {
            $array = [];

            foreach ($item as $key => $value) {
                $array[$key] = self::fromBson($value);
            }

            return $array;
        }

        if (is_array($item)) {
            return array_map([self::class, 'fromBson'], $item);
        }

        return $item;
    }
}
