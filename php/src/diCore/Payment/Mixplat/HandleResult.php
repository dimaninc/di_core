<?php

/**
 * Результат обработки запроса
 */

namespace diCore\Payment\Mixplat;

class HandleResult
{
	/**
	 * @var bool
	 */

	private $isSignCorrect;

	/**
	 * @var array
	 */

	private $data;

	/**
	 * @param bool $isSignCorrect
	 * @param array $data
	 */

	public function __construct( $isSignCorrect, $data )
	{
		$this->isSignCorrect	= $isSignCorrect;
		$this->data				= $data;
	}

	/**
	 * Флаг корректности подписи
	 * @return bool
	 */

	public function isSignCorrect()
	{
		return $this->isSignCorrect;
	}

	/**
	 * Данные
	 * @return array
	 */

	public function getData($key = null)
	{
		return $key !== null && isset($this->data[$key]) ? $this->data[$key] : $this->data;
	}

	public function isStatusSuccess()
	{
		return $this->getData('status') == ResultStatus::SUCCESS;
	}
}