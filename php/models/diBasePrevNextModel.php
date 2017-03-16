<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 24.06.2015
 * Time: 19:23
 */
class diBasePrevNextModel extends diModel
{
	const CONDITION_TYPE_SAME = 1;

	protected $languageAr = array(
		"prev" => "Предыдущая",
		"next" => "Следующая",
	);

	protected $htmlAr = array(
		"siblingHref" => '<a href="%2$s">%1$s</a>', // word and link
		"siblingNoHref" => '<span>%1$s</span>', // just word, no link
	);

	protected $orderByOptions = array(
		"circular" => false,
		"reuseSelfIfNoSiblings" => false,
		"reverse" => false,
		"conditions" => array(),
		"fields" => array(
			array(
				"field" => "date",
				"dir" => "DESC",
			),
			array(
				"field" => "%slug%",
				"dir" => "DESC",
			),
		),
	);

	protected $customOrderByOptions = array();

	public function __construct($r = null)
	{
		parent::__construct($r);

		$this->orderByOptions = array_merge($this->orderByOptions, $this->customOrderByOptions);
	}

	public function getCustomTemplateVars()
	{
		return extend(parent::getCustomTemplateVars(), $this->getPrevNextTemplateVars());
	}

	protected function getPrevNextHtml($name, $r = null)
	{
		return sprintf($this->htmlAr[$r ? "siblingHref" : "siblingNoHref"],
			$this->languageAr[$name],
			$r ? static::href($r) : ""
		);
	}

	private function decodeOrderField($f)
	{
		$that = $this;

		$f = preg_replace_callback('/%([a-z0-9_]+)%/i', function($matches) use ($that) {
			switch ($matches[1])
			{
				case "id":
					$matches[1] = $that->getIdFieldName();
					break;

				case "slug":
					$matches[1] = $that->getSlugFieldName();
					break;
			}

			return $matches[1];
		}, $f);

		return $f;
	}

	private function getPrevNextQueries($i)
	{
		$conditionSet = array_slice($this->orderByOptions["fields"], 0, $i);
		$orderSet = array_slice($this->orderByOptions["fields"], $i);

		$conditions = array(
			"visible='1'",
			"id!='{$this->getId()}'"
		);

		$prevConditions = $nextConditions = array();
		$prevOrders = $nextOrders = array();

		foreach ($conditionSet as $fAr)
		{
			$field = $this->decodeOrderField($fAr["field"]);

			$conditions[] = $field."='{$this->get($field)}'";
		}

		foreach ($this->orderByOptions["conditions"] as $cAr)
		{
			$field = $this->decodeOrderField($cAr["field"]);

			switch ($cAr["type"])
			{
				case self::CONDITION_TYPE_SAME:
					$condition = "{$field}='{$this->get($field)}'";
					break;

				default:
					$condition = "";
					break;
			}

			if ($condition)
			{
				$conditions[] = $condition;
			}
		}

		foreach ($orderSet as $oAr)
		{
			$field = $this->decodeOrderField($oAr["field"]);
			$isAsc = strtoupper($oAr["dir"]) == "ASC" && !$this->orderByOptions["reverse"];

			$prevSign = $isAsc ? "<" : ">";
			$nextSign = $isAsc ? ">" : "<";
			$prevDir = $isAsc ? "DESC" : "ASC";
			$nextDir = $isAsc ? "ASC" : "DESC";

			$prevConditions[] = $field.$prevSign."'{$this->get($field)}'";
			$nextConditions[] = $field.$nextSign."'{$this->get($field)}'";

			$prevOrders[] = $field." ".$prevDir;
			$nextOrders[] = $field." ".$nextDir;
		}

		return array(
			"conditions" => $conditions,
			"prevConditions" => $prevConditions,
			"nextConditions" => $nextConditions,
			"prevOrders" => $prevOrders,
			"nextOrders" => $nextOrders,
		);
	}

	public function getPrevNextTemplateVars()
	{
		$ordersBy = count($this->orderByOptions["fields"]);

		$prev_r = $next_r = null;

		for ($i = $ordersBy - 1; $i >= 0; $i--)
		{
			$q = $this->getPrevNextQueries($i);

			/*
			echo "prev: WHERE ".join(" AND ", array_merge($q["conditions"], $q["prevConditions"]))." ORDER BY ".join(",", $q["prevOrders"])."<br>";
			echo "next: WHERE ".join(" AND ", array_merge($q["conditions"], $q["nextConditions"]))." ORDER BY ".join(",", $q["nextOrders"])."<br>";
			*/

			$prev_r = $prev_r ?: $this->getDb()->r($this->getTable(),
				"WHERE ".join(" AND ", array_merge($q["conditions"], $q["prevConditions"])).
				" ORDER BY ".join(",", $q["prevOrders"])
			);

			$next_r = $next_r ?: $this->getDb()->r($this->getTable(),
				"WHERE ".join(" AND ", array_merge($q["conditions"], $q["nextConditions"])).
				" ORDER BY ".join(",", $q["nextOrders"])
			);
		}

		if ($this->orderByOptions["circular"])
		{
			$q = $this->getPrevNextQueries(0);

			if (!$prev_r)
			{
				$prev_r = $this->getDb()->r($this->getTable(),
					"WHERE ".join(" AND ", $q["conditions"]).
					" ORDER BY ".join(",", $q["prevOrders"])
				);
			}

			if (!$prev_r && $this->orderByOptions["reuseSelfIfNoSiblings"])
			{
				$prev_r = $this->getWithId();
			}

			if (!$next_r)
			{
				$next_r = $this->getDb()->r($this->getTable(),
					"WHERE ".join(" AND ", $q["conditions"]).
					" ORDER BY ".join(",", $q["nextOrders"])
				);
			}

			if (!$next_r && $this->orderByOptions["reuseSelfIfNoSiblings"])
			{
				$next_r = $this->getWithId();
			}
		}

		return [
			"prev_href" => $prev_r ? static::href($prev_r) : "#no-prev",
			"next_href" => $next_r ? static::href($next_r) : "#no-next",

			"prev" => $prev_r ? $this->getPrevNextHtml("prev", $prev_r) : "",
			"next" => $next_r ? $this->getPrevNextHtml("next", $next_r) : "",
		];
	}
}