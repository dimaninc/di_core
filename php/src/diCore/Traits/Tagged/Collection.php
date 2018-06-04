<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 28.05.2018
 * Time: 10:19
 */

namespace diCore\Traits\Tagged;

/**
 * Class Collection
 * @package diCore\Entity\Tagged
 *
 * @method $this resetAlias
 * @method $this addAliasToField($field, $alias = null)
 * @method $this addAliasToTable($table, $alias = null)
 * @method $this select($fields, $append = false)
 * @method $this selectExpression($fields, $append = false)
 * @method $this filterManual($expression)
 * @method $this groupBy($fields)
 * @method string getTable
 */
trait Collection
{
    protected $tagId = null;

    private $realType;
    /** @var \diTags */
    private $classInstance;

    protected $map_table_alias = 'l';
    protected $tag_table_alias = 't';

    public static $TAG_INFO_SEPARATOR = '^';
    public static $TAG_SEPARATOR = ',';

    public function filterByTagId($tagId)
    {
        $this->tagId = $tagId;

        return $this;
    }

    /*
    public function __construct($table = null)
    {
        parent::__construct($table);

        $this->taggedConstructor();
    }
    */
    protected function taggedConstructor($options = [])
    {
        $options = extend([
            'realType' => null,
            'groupBy' => ['id'],
            'tagsClass' => \diTags::class,
        ], $options);

        $options['realType'] = $options['realType'] ?: static::realType;

        if (!$options['realType'])
        {
            throw new \Exception('taggedConstructor: realType not defined');
        }

        $this->realType = $options['realType'];
        $this->classInstance = new $options['tagsClass'];

        $this
            ->select('*')
            ->selectExpression("GROUP_CONCAT(COALESCE(t.id, ''), '" . self::$TAG_INFO_SEPARATOR . "', COALESCE(t.slug, ''), '" . self::$TAG_INFO_SEPARATOR . "', COALESCE(t.title, '')) AS tags", true)
            ->groupBy($options['groupBy']);
    }

    /*
    protected function getQueryTable()
    {
        return $this->getTaggedQueryTable();
    }
     */
    protected function getTaggedQueryTable()
    {
        $q = $this->addAliasToTable($this->getTable(), true) .
            ' LEFT JOIN ' . $this->getMapTable() .
            ' ON ' . $this->getTargetIdField() . ' = ' . $this->getMainIdField() .
            ' AND ' . $this->getTargetTypeField() . ' = ' . $this->realType .
            ' LEFT JOIN ' . $this->getTagTable() .
            ' ON ' . $this->getTagIdField() . ' = ' . $this->getGenericTagIdField();

        if ($this->tagId !== null)
        {
            $q .=
                ' INNER JOIN ' . $this->getMapTable(2) .
                ' ON ' . $this->getTargetIdField(2) . ' = ' . $this->getMainIdField() .
                ' AND ' . $this->getTargetTypeField(2) . ' = ' . $this->realType;

            $this->filterManual($this->getTagIdField(2) . ' = ' . $this->tagId);
        }

        return $q;
    }

    private function getTagTable()
    {
        return $this->addAliasToTable($this->classInstance->getTableName('tags'), $this->tag_table_alias);
    }

    private function getMapTable($index = '')
    {
        return $this->addAliasToTable($this->classInstance->getTableName('map'), $this->map_table_alias . $index);
    }

    private function getTagIdField($index = '')
    {
        return $this->addAliasToField($this->classInstance->getFieldName('tag_id'), $this->map_table_alias . $index);
    }

    private function getGenericTagIdField($index = '')
    {
        return $this->addAliasToField('id', $this->tag_table_alias . $index);
    }

    private function getTargetIdField($index = '')
    {
        return $this->addAliasToField($this->classInstance->getFieldName('target_id'), $this->map_table_alias . $index);
    }

    private function getTargetTypeField($index = '')
    {
        return $this->addAliasToField($this->classInstance->getFieldName('target_type'), $this->map_table_alias . $index);
    }

    private function getMainIdField()
    {
        return $this->addAliasToField('id', true);
    }
}