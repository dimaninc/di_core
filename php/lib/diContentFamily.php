<?php

use diCore\Base\CMS;
use diCore\Entity\Content\Model;

/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 09.10.15
 * Time: 17:38
 */
class diContentFamily
{
    protected $table = 'content';

    /** @var Model */
    private $model;

    /** @var array */
    private $family = [];

    /** @var CMS */
    private $Z;

    private static $childClassName = 'diCustomContentFamily';

    /**
     * @param CMS $Z
     */
    public function __construct(CMS $Z)
    {
        $this->Z = $Z;

        $this->setModel($this->getEmptyModel());
    }

    /**
     * @param CMS $Z
     * @return mixed
     */
    public static function create(CMS $Z)
    {
        $className = diLib::exists(self::$childClassName)
            ? self::$childClassName
            : get_called_class();

        $o = new $className($Z);

        return $o;
    }

    /**
     * @return Model
     */
    public function getModel()
    {
        return $this->model ?: Model::create();
    }

    /**
     * @return diContentFamily
     */
    public function setModel(Model $model)
    {
        $this->model = $model;

        return $this;
    }

    /**
     * @param int|null $level
     * @return array|Model
     */
    public function get($level = null)
    {
        if ($level === null) {
            return $this->family;
        }

        return $this->getMemberByLevel($level);
    }

    /**
     * @param $id
     * @return Model
     */
    public function getMemberById($id)
    {
        /** @var Model $content */
        foreach ($this->family as $content) {
            if ($content->getId() == $id) {
                return $content;
            }
        }

        return $this->getEmptyModel();
    }

    /**
     * @param $level
     * @return Model
     */
    public function getMemberByLevel($level)
    {
        if ($level < 0) {
            $level += count($this->family);
        }

        if (!isset($this->family[$level])) {
            return $this->getEmptyModel();
        }

        return $this->family[$level];
    }

    public function getMemberByNonEmptyField($field)
    {
        $pi = new diPropertyInheritance();

        foreach ($this->get() as $model) {
            $pi->push($model);
        }

        return $pi->getRec($field);
    }

    /**
     * @return Model
     * @throws Exception
     */
    private function getEmptyModel()
    {
        return Model::create();
    }

    /**
     * @return diCurrentCMS
     */
    protected function getZ()
    {
        return $this->Z;
    }

    /**
     * @return $this|diContentFamily
     */
    public function init()
    {
        $this->beforeRoutesCheck()
            ->findModel()
            ->afterRoutesCheck();

        if (!$this->getModel()->exists()) {
            return $this->error();
        }

        $this->family[(int) $this->getModel()->getLevelNum()] = $this->getModel();

        $parent = $this->getModel()->getParent();
        while (isset($this->getZ()->tables[$this->table][$parent])) {
            /** @var Model $m */
            $m = $this->getZ()->tables[$this->table][$parent];

            $this->family[(int) $m->getLevelNum()] = $m;

            $parent = $m->getParent();
        }

        ksort($this->family);

        $this->findOtherModels();

        return $this;
    }

    protected function isModelSuitable(Model $content)
    {
        return $content->getSlug() == $this->getZ()->getRoute(0);
    }

    protected function getContentCollection()
    {
        return $this->getZ()->tables[$this->table];
    }

    protected function findModel()
    {
        /**
         * @var int $id
         * @var Model $content
         */
        foreach ($this->getContentCollection() as $id => $content) {
            if ($this->isModelSuitable($content)) {
                $this->setModel($content);

                break;
            }
        }

        return $this;
    }

    protected function findOtherModels()
    {
        return $this;
    }

    protected function beforeRoutesCheck()
    {
        return $this;
    }

    protected function afterRoutesCheck()
    {
        return $this;
    }

    private function error()
    {
        $this->getZ()->errorNotFound();

        return $this;
    }
}
