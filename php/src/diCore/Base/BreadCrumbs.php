<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 09.10.15
 * Time: 17:33
 */

namespace diCore\Base;

use diCore\Data\Configuration;
use diCore\Entity\Content\Model;
use diCore\Helper\StringHelper;

class BreadCrumbs
{
    /**
     * @var array
     */
    protected $elements = [];

    protected $skippedContentTypes = ['virtual', 'logged_in_menu'];

    /**
     * @var CMS
     */
    private $Z;

    private $type;

    private $divider = ' / ';

    private $useTpl;

    /**
     * @var callable|null
     */
    private $titleGetter = null;

    public function __construct(CMS $Z)
    {
        $this->Z = $Z;
        $this->type = $this->getZ()->content_table;
        $this->useTpl =
            $this->getTpl() && $this->getTpl()->exists('top_title_divider');

        $this->reset();
    }

    /**
     * @param CMS $Z
     * @return BreadCrumbs
     */
    public static function create(CMS $Z)
    {
        $className = \diLib::getChildClass(self::class);

        return new $className($Z);
    }

    /**
     * @return CMS
     */
    protected function getZ()
    {
        return $this->Z;
    }

    /** @deprecated  */
    protected function getTpl()
    {
        return $this->getZ()->getTpl();
    }

    protected function getTwig()
    {
        return $this->getZ()->getTwig();
    }

    public function setTitleGetter(callable $getter)
    {
        $this->titleGetter = $getter;

        return $this;
    }

    public function reset()
    {
        $this->elements = [];

        return $this;
    }

    protected function hrefNeeded(\diModel $m)
    {
        return !$m->exists('to_show_content') ||
            ($m->has('to_show_content') &&
                $m->getId() !=
                    $this->getZ()
                        ->getContentModel()
                        ->getId());
    }

    public function init()
    {
        $this->reset();

        if ($this->useTpl) {
            $this->setDivider($this->getTpl()->parse('top_title_divider'));
        }

        /** @var Model $m */
        foreach (
            $this->getZ()
                ->getContentFamily()
                ->get()
            as $m
        ) {
            if (in_array($m->getType(), $this->skippedContentTypes)) {
                continue;
            }

            $this->add([
                'href' => $this->hrefNeeded($m) ? $m->getHref() : null,
                'hrefPrefixNeeded' => false,
                'model' => $m,
            ]);
        }

        return $this;
    }

    public function setDivider($divider)
    {
        $this->divider = $divider;

        return $this;
    }

    public function addHref($index = -1, $href = null)
    {
        if (!count($this->elements)) {
            return $this;
        }

        if ($href === null) {
            $href = $this->getZ()
                ->getContentFamily()
                ->getMemberByLevel($index)
                ->getHref();
        }

        $this->update($index, [
            'href' => $href,
        ]);

        return $this;
    }

    public function remove($index = -1)
    {
        if ($index < 0) {
            $index += count($this->elements);
        }

        array_splice($this->elements, $index, 1);

        return $this;
    }

    protected function prepareElement($element)
    {
        /** @var \diModel $m */
        $m = $element['model'];

        if ($m->exists()) {
            if (!$element['title']) {
                $element['title'] = $m->localized('title');
            }

            if (!$element['href'] && $this->hrefNeeded($m)) {
                $element['href'] = $m->getHref();
            }
        }

        if ($element['position'] < 0) {
            $element['position'] += count($this->elements) + 1;
        }

        if ($element['wordWrap']) {
            $element['title'] = trim(
                StringHelper::divideLongWords(
                    $element['title'],
                    Configuration::get('page_title_word_max_len'),
                    ' '
                )
            );
        }

        return $element;
    }

    public function add($titleOrElement, $href = '', $class = '', $word_wrap = false)
    {
        $element = extend(
            [
                'title' => null,
                'href' => null,
                'hrefPrefixNeeded' => true,
                'class' => null,
                'wordWrap' => false,
                'position' => -1,
                'model' => \diModel::create($this->type),
            ],
            !is_array($titleOrElement)
                ? [
                    'title' => $titleOrElement,
                    'href' => $href,
                    'class' => $class,
                    'wordWrap' => $word_wrap,
                ]
                : $titleOrElement
        );

        $element = $this->prepareElement($element);

        array_splice($this->elements, $element['position'], 0, [$element]);

        return $this;
    }

    public function update($index, $options = [])
    {
        if ($index < 0) {
            $index += count($this->elements);
        }

        if (isset($this->elements[$index])) {
            $this->elements[$index] = $this->prepareElement(
                extend($this->elements[$index], $options)
            );
        }

        return $this;
    }

    public function getTitleOfElement($element)
    {
        if ($cb = $this->titleGetter) {
            return $cb($element);
        }

        return $element['title'];
    }

    protected function getProcessedElements()
    {
        $ar = [];

        foreach ($this->elements as $element) {
            $hrefPrefix =
                $element['href'] && $element['hrefPrefixNeeded']
                    ? $this->getZ()->getLanguageHrefPrefix()
                    : '';

            $ar[] = [
                'title' => $this->getTitleOfElement($element),
                'href' => $hrefPrefix . $element['href'],
                'class' => $element['class'],
            ];
        }

        return $ar;
    }

    protected function getHtmlElements()
    {
        $ar = [];

        foreach ($this->getProcessedElements() as $e) {
            $ar[] = $this->getTpl()
                ->assign($e, 'TT_')
                ->parse(
                    'TOP_TITLE_ELEMENT',
                    $e['href'] ? 'top_title_href' : 'top_title_nohref'
                );
        }

        return $ar;
    }

    public function finish()
    {
        if ($this->useTpl) {
            $ar = $this->getHtmlElements();

            $this->getTpl()->assign([
                'TOP_TITLE' => join($this->divider, $ar),
            ]);

            if ($this->getZ()->needToPrintBreadCrumbs()) {
                $this->getTpl()->parse('top_title_div');
            }

            $this->getTwig()->assign([
                'top_title' => join($this->divider, $ar),
                'top_title_div' => $this->getTpl()->getAssigned('TOP_TITLE_DIV'),
            ]);
        } else {
            $this->getTwig()->assign([
                'bread_crumbs' => $this->getProcessedElements(),
            ]);
        }

        return $this;
    }
}
