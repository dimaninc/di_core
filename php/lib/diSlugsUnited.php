<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 22.07.2015
 * Time: 12:07
 */

use diCore\Base\CMS;
use diCore\Entity\Slug\Collection;
use diCore\Entity\Slug\Model;
use diCore\Helper\Slug;
use diCore\Tool\Logger;

class diSlugsUnited
{
    private $targetType;
    private $targetId;
    private $levelNum;

    /** @var Model */
    private $model;

    public function __construct($targetType, $targetId, $levelNum = 0)
    {
        $this->targetType = $targetType;
        $this->targetId = $targetId;
        $this->levelNum = $levelNum;

        $col = Collection::create()
            ->filterByTargetType($this->targetType)
            ->filterByTargetId($this->targetId);
        $this->model = $col->getFirstItem();

        if (!$this->getModel()->exists()) {
            $this->getModel()
                ->setTargetType($this->targetType)
                ->setTargetId($this->targetId)
                ->setLevelNum($this->levelNum);
        }
    }

    public static function emulateRealHref(Model $s, CMS $Z)
    {
    }

    public function needed()
    {
        return $this->getModel()->exists();
    }

    public function getModel()
    {
        return $this->model;
    }

    public function generateAndSave($source, $parentSlugs)
    {
        return $this->generate($source)
            ->setFullSlug($parentSlugs)
            ->save();
    }

    public function kill()
    {
        $this->getModel()->hardDestroy();

        return $this;
    }

    protected function getUniqueOptions()
    {
        return [];
    }

    protected function unique($source)
    {
        $getFullSlug = function (string $slug) {
            // simple_debug("diSlugsUnited::unique: {$this->getModel()->getTargetModel()->getHrefBase()} / $slug");

            return join(
                '/',
                array_filter([
                    $this->getModel()
                        ->getTargetModel()
                        ->getHrefBase(),
                    $slug,
                ])
            );
        };

        return Slug::unique(
            $source,
            $this->getModel()->getTable(),
            $this->getModel()->getId(),
            extend(
                [
                    'db' => $this->getModel()
                        ::getConnection()
                        ->getDb(),
                    'collectionFilter' => function (\diCollection $col) {
                        return $col->filterBy('level_num', $this->levelNum);
                    },
                    'getFullSlug' => $getFullSlug,
                ],
                $this->getUniqueOptions()
            )
        );
    }

    protected function prepare($source, $lowerCase = true)
    {
        return Slug::prepare($source, '-', $lowerCase);
    }

    public function generate($source, $options = [])
    {
        $options = extend(
            [
                'prepare' => true,
                'lowerCase' => true,
            ],
            $options
        );

        if ($options['prepare']) {
            $source = $this->prepare($source, $options['lowerCase']);
        }

        $this->setShortSlug($this->unique($source));

        return $this;
    }

    public function setShortSlug($slug)
    {
        $this->getModel()->setSlug($slug);

        return $this;
    }

    public function setFullSlug($parentSlugs = [])
    {
        if (is_scalar($parentSlugs)) {
            $parentSlugs = $parentSlugs ? [$parentSlugs] : [];
        }

        $parentSlugs[] = $this->getModel()->getSlug();

        $this->getModel()->setFullSlug(join('/', $parentSlugs));

        return $this;
    }

    public function setTargetId($id)
    {
        $this->getModel()->setTargetId($id);

        return $this;
    }

    public function save()
    {
        $this->getModel()->save();

        return $this;
    }

    protected function log($message)
    {
        Logger::getInstance()->log($message, 'diSlugsUnited', '-slug');

        return $this;
    }
}
