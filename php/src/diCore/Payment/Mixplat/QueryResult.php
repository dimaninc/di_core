<?php

/**
 * Результат запроса
 */

namespace diCore\Payment\Mixplat;

class QueryResult
{
    /**
     * @var bool
     */

    private $isSuccess;

    /**
     * @var array
     */

    private $data;

    /**
     * @var string
     */

    private $error;

    /**
     * @var int
     */

    private $errorCode;

    /**
     * @param bool $isSuccess
     * @param array $data
     * @param string $error
     * @param int $errorCode
     */

    public function __construct($isSuccess, $data, $error, $errorCode)
    {
        $this->isSuccess = $isSuccess;
        $this->data = $data;
        $this->error = $error;
        $this->errorCode = $errorCode;
    }

    /**
     * Флаг успешности запроса
     * @return bool
     */

    public function isSuccess()
    {
        return $this->isSuccess;
    }

    /**
     * Данные
     * @return array
     */

    public function getData($key = null)
    {
        return $key !== null && isset($this->data[$key])
            ? $this->data[$key]
            : $this->data;
    }

    /**
     * Ошибка
     * @return string
     */

    public function getError()
    {
        return $this->error;
    }

    /**
     * Код ошибки
     * @return int
     */

    public function getErrorCode()
    {
        return $this->errorCode;
    }
}
