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
	private $lastTime;
	private $times = [];

	public function __construct()
	{
		$this->start = $this->lastTime = utime();
	}

	public function save($title = '')
	{
		$time = utime();

		$this->times[] = [
			'period' => $time - $this->lastTime,
			'from_start' => $time - $this->start,
			'time' => $time,
			'title' => $title,
		];

		$this->lastTime = $time;

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