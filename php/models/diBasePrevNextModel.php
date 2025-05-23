<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 24.06.2015
 * Time: 19:23
 */
class diBasePrevNextModel extends \diModel
{
    const CONDITION_TYPE_SAME = 1;

    /** @var  diBasePrevNextModel */
    protected $prev;
    /** @var  diBasePrevNextModel */
    protected $next;

    protected $languageAr = [
        'prev' => 'Предыдущая',
        'next' => 'Следующая',
    ];

    protected $htmlAr = [
        'siblingHref' => '<a href="%2$s">%1$s</a>', // word and link
        'siblingNoHref' => '<span>%1$s</span>', // just word, no link
    ];

    protected $orderByOptions = [
        'circular' => false,
        'reuseSelfIfNoSiblings' => false,
        'reverse' => false,
        'conditions' => [],
        'fields' => [
            [
                'field' => 'date',
                'dir' => 'DESC',
            ],
            [
                'field' => '%slug%',
                'dir' => 'DESC',
            ],
        ],
    ];

    protected $customOrderByOptions = [];

    public function __construct($ar = null, $table = null)
    {
        parent::__construct($ar);

        $this->setupOrderByOptions();
    }

    protected function setupOrderByOptions()
    {
        $this->orderByOptions = extend(
            $this->orderByOptions,
            $this->customOrderByOptions
        );

        return $this;
    }

    public function getCustomTemplateVars()
    {
        return extend(
            parent::getCustomTemplateVars(),
            $this->getPrevNextTemplateVars()
        );
    }

    protected function getPrevNextHtml($name, \diModel $model)
    {
        return sprintf(
            $this->htmlAr[$model->exists() ? 'siblingHref' : 'siblingNoHref'],
            $this->languageAr[$name],
            $model->getHref()
        );
    }

    private function decodeOrderField($f)
    {
        $that = $this;

        $f = preg_replace_callback(
            '/%([a-z0-9_]+)%/i',
            function ($matches) use ($that) {
                switch ($matches[1]) {
                    case 'id':
                        $matches[1] = $that->getIdFieldName();
                        break;

                    case 'slug':
                        $matches[1] = $that->getSlugFieldName();
                        break;
                }

                return $matches[1];
            },
            $f
        );

        return $f;
    }

    protected function getBasePrevNextConditions()
    {
        return array_merge($this->getQueryArForMove(), ["id != '{$this->getId()}'"]);
    }

    private function getPrevNextQueries($i)
    {
        $conditionSet = array_slice($this->orderByOptions['fields'], 0, $i);
        $orderSet = array_slice($this->orderByOptions['fields'], $i);

        $conditions = $this->getBasePrevNextConditions();

        $prevConditions = $nextConditions = [];
        $prevOrders = $nextOrders = [];

        foreach ($conditionSet as $fAr) {
            $field = $this->decodeOrderField($fAr['field']);

            $conditions[] = $field . " = '{$this->get($field)}'";
        }

        foreach ($this->orderByOptions['conditions'] as $cAr) {
            $field = $this->decodeOrderField($cAr['field']);

            switch ($cAr['type']) {
                case self::CONDITION_TYPE_SAME:
                    $condition = "$field = '{$this->get($field)}'";
                    break;

                default:
                    $condition = '';
                    break;
            }

            if ($condition) {
                $conditions[] = $condition;
            }
        }

        foreach ($orderSet as $j => $oAr) {
            $field = $this->decodeOrderField($oAr['field']);
            $isAsc =
                strtoupper($oAr['dir']) == 'ASC' &&
                !$this->orderByOptions['reverse'];

            $prevSign = $isAsc ? '<' : '>';
            $nextSign = $isAsc ? '>' : '<';
            $prevDir = $isAsc ? 'DESC' : 'ASC';
            $nextDir = $isAsc ? 'ASC' : 'DESC';

            /* todo: check this stuff when count of fields > 2 */
            if ($i < count($this->orderByOptions['fields']) - 1 && $j > 0) {
                // no condition needed
            } else {
                $prevConditions[] = "$field $prevSign '{$this->get($field)}'";
                $nextConditions[] = "$field $nextSign '{$this->get($field)}'";
            }

            $prevOrders[] = $field . ' ' . $prevDir;
            $nextOrders[] = $field . ' ' . $nextDir;
        }

        /* * /
		if ($this->getTable() == '#' && $this->getId() == 0)
		{
			\diCore\Tool\Logger::getInstance()->variable(
				'$this->orderByOptions["fields"]', $this->orderByOptions["fields"],
				'$i = ' . $i,
				'$orderSet', $orderSet,
				'$conditionSet', $conditionSet,
				'$nextConditions', $nextConditions,
				'$nextOrders', $nextOrders
			);
		}
		/* */

        return [
            'conditions' => $conditions,
            'prevConditions' => $prevConditions,
            'nextConditions' => $nextConditions,
            'prevOrders' => $prevOrders,
            'nextOrders' => $nextOrders,
        ];
    }

    /**
     * @return diBasePrevNextModel
     */
    public function getPrev()
    {
        $this->initPrevNextModels();

        return $this->prev;
    }

    /**
     * @return diBasePrevNextModel
     */
    public function getNext()
    {
        $this->initPrevNextModels();

        return $this->next;
    }

    protected function initPrevNextModels()
    {
        if ($this->prev && $this->next) {
            return $this;
        }

        $ordersBy = count($this->orderByOptions['fields']);

        $prev = $next = [];

        for ($i = $ordersBy - 1; $i >= 0; $i--) {
            $q = $this->getPrevNextQueries($i);

            $prev =
                $prev ?:
                $this->getDb()->ar(
                    $this->getTable(),
                    'WHERE ' .
                        join(
                            ' AND ',
                            array_merge($q['conditions'], $q['prevConditions'])
                        ) .
                        ' ORDER BY ' .
                        join(',', $q['prevOrders'])
                );

            $next =
                $next ?:
                $this->getDb()->ar(
                    $this->getTable(),
                    'WHERE ' .
                        join(
                            ' AND ',
                            array_merge($q['conditions'], $q['nextConditions'])
                        ) .
                        ' ORDER BY ' .
                        join(',', $q['nextOrders'])
                );

            /* * /
			if ($this->getTable() == '#' && $this->getId() == 0)
			{
				\diCore\Tool\Logger::getInstance()->variable("prev: WHERE " .
					join(" AND ", array_merge($q["conditions"], $q["prevConditions"])) .
					" ORDER BY " . join(",", $q["prevOrders"]), $prev);
				\diCore\Tool\Logger::getInstance()->log("next: WHERE " .
					join(" AND ", array_merge($q["conditions"], $q["nextConditions"])) .
					" ORDER BY " . join(",", $q["nextOrders"]));
				\diCore\Tool\Logger::getInstance()->variable('$next', $next);
			}
			/* */
        }

        if ($this->orderByOptions['circular']) {
            $q = $this->getPrevNextQueries(0);

            if (!$prev) {
                $prev = $this->getDb()->ar(
                    $this->getTable(),
                    'WHERE ' .
                        join(' AND ', $q['conditions']) .
                        ' ORDER BY ' .
                        join(',', $q['prevOrders'])
                );
            }

            if (!$prev && $this->orderByOptions['reuseSelfIfNoSiblings']) {
                $prev = $this->getWithId();
            }

            if (!$next) {
                $next = $this->getDb()->ar(
                    $this->getTable(),
                    'WHERE ' .
                        join(' AND ', $q['conditions']) .
                        ' ORDER BY ' .
                        join(',', $q['nextOrders'])
                );
            }

            if (!$next && $this->orderByOptions['reuseSelfIfNoSiblings']) {
                $next = $this->getWithId();
            }
        }

        $this->prev = \diModel::create($this->modelType(), $prev);
        $this->next = \diModel::create($this->modelType(), $next);

        return $this;
    }

    public function getPrevNextTemplateVars()
    {
        $this->initPrevNextModels();

        return [
            'prev_href' => $this->getPrev()->getHref(),
            'next_href' => $this->getNext()->getHref(),

            'prev_model' => $this->getPrev(),
            'next_model' => $this->getNext(),

            'prev' => $this->getPrevNextHtml('prev', $this->getPrev()),
            'next' => $this->getPrevNextHtml('next', $this->getNext()),
        ];
    }
}
