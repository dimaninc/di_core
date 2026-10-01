<?php

namespace diCore\Entity\AuthorizationPin;

class RateLimitedException extends \Exception
{
    /** @var string */
    private $limit;

    /**
     * @param string $limit one of Model::LIMIT_* constants
     */
    public function __construct(string $limit, string $message = '')
    {
        $this->limit = $limit;

        parent::__construct($message ?: "Too many codes requested ($limit)");
    }

    /**
     * The limit that refused the request (Model::LIMIT_*).
     */
    public function getLimit(): string
    {
        return $this->limit;
    }
}
