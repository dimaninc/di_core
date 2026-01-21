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
    protected $headers = [];

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
        return $this->returnData !== null;
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

    public function setHeaders($headers)
    {
        $this->headers = $headers;

        return $this;
    }

    public function addNoIndexHeader()
    {
        return $this->addHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public static function sendNoIndexHeader()
    {
        header('X-Robots-Tag: noindex, nofollow');
    }

    public function addHeader($name, $value)
    {
        $this->headers[$name] = $value;

        return $this;
    }

    public function removeHeader($name)
    {
        unset($this->headers[$name]);

        return $this;
    }

    public function getHeaders()
    {
        return $this->headers;
    }

    public function getHeader($name)
    {
        return $this->headers[$name] ?? null;
    }

    public function headers()
    {
        if (!$this->headers) {
            return $this;
        }

        foreach ($this->headers as $name => $value) {
            header("$name: $value");
        }

        return $this;
    }
}
