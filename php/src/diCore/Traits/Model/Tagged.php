<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 28.05.2018
 * Time: 10:19
 */

namespace diCore\Traits\Model;

use diCore\Data\Types;

/**
 * Trait Model
 * @package diCore\Entity\Tagged
 *
 * @method array prepareIdAndFieldForGetRecord($id, $fieldAlias = null)
 */
trait Tagged
{
    private $tagsField = 'tags';

    public function getTags()
    {
        return $this->getRelated($this->tagsField) ?: [];
    }

    /*
    protected function getRecord($id, $fieldAlias = null)
    {
        return $this->getTaggedRecord($id, $fieldAlias);
    }
    */
    protected function getTaggedRecord($id, $field = null)
    {
        $a = $this->prepareIdAndFieldForGetRecord($id, $field);

        $col = \diCollection::create(static::type);
        $col
            ->filterBy($a['field'], $a['id']);

        return $col->getFirstItem();
    }

    /*
    public function initFrom($r)
    {
        parent::initFrom($r);

        $this->afterTaggedInit();

        return $this;
    }
     */
    protected function afterTaggedInit()
    {
        if ($this->exists())
        {
            $this->parseTags();
        }

        return $this;
    }

    private function parseTags()
    {
        $tags = $this->get($this->tagsField);

        if ($tags && is_string($tags))
        {
            $tags = trim($tags, Tagged::$TAG_INFO_SEPARATOR . Tagged::$TAG_SEPARATOR);
            $tags = array_filter(explode(Tagged::$TAG_SEPARATOR, $tags));

            foreach ($tags as &$tag)
            {
                $info = explode(Tagged::$TAG_INFO_SEPARATOR, $tag);

                $tag = \diModel::create(Types::tag, [
                    'id' => $info[0],
                    'slug' => $info[1],
                    'title' => $info[2],
                ]);
            }

            $this
                ->set($this->tagsField, '')
                ->setRelated($this->tagsField, $tags);
        }

        return $this;
    }
}