<?php

namespace diCore\Entity\AuthorizationPin;

/**
 * Result of `Model::verifyCode()` / `PinCode::check()`: a verdict and, on success,
 * the verified pin. The pin is an empty model when the check failed, never null.
 */
class CheckResult
{
    /** @var string */
    private $verdict;

    /** @var Model|\diModel */
    private $pin;

    /**
     * @param string $verdict one of Verdict constants
     * @param Model|\diModel $pin
     */
    public function __construct(string $verdict, \diModel $pin)
    {
        $this->verdict = $verdict;
        $this->pin = $pin;
    }

    public function getVerdict(): string
    {
        return $this->verdict;
    }

    public function isOk(): bool
    {
        return $this->verdict === Verdict::ok;
    }

    /**
     * @return Model|\diModel
     */
    public function getPin(): \diModel
    {
        return $this->pin;
    }
}
