<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 05.04.2017
 * Time: 12:01
 */

namespace diCore\Tool\Debug;


class Timing
{
	private $start;
	private $times = [];

	public function __construct()
	{
		$this->start = utime();
	}

	public function save($title = '')
	{
		$time = utime();

		$this->times[] = [
			'period' => $time - $this->start,
			'time' => $time,
			'title' => $title,
		];

		return $this;
	}

	public function getTimes()
	{
		return $this->times;
	}

	public function getTotalTime($format = false)
	{
		$total = 0;

		array_map(function($item) use(&$total) {
			$total += $item['period'];
		}, $this->times);

		return $format
			? sprintf('%.6f', $total)
			: $total;
	}

	public function getPeriodsPrinted()
	{
		return join("\n", array_map(function($item) {
			return sprintf('%.6f', $item['period']) . ': ' . $item['title'];
		}, $this->times));
	}
}