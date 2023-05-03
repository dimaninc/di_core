<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 03.05.2023
 * Time: 11:50
 */

namespace diCore\Data\Http;

class Response
{
    protected $responseCode = HttpCode::OK;
    protected $returnData;

    public function setReturnData($data)
    {
        $this->returnData = $data;

        return $this;
    }

    public function getReturnData()
    {
        return $this->returnData;
    }

    public function hasReturnData()
    {
        return !!$this->returnData;
    }

    /**
     * @return int
     */
    public function getResponseCode()
    {
        return $this->responseCode;
    }

    /**
     * @return bool
     */
    public function isResponseCode($code)
    {
        return $this->getResponseCode() == $code;
    }

    /**
     * @param int $responseCode
     * @return $this
     */
    public function setResponseCode($responseCode)
    {
        $this->responseCode = $responseCode;

        return $this;
    }
}
