<?php

namespace diCore\Tool\Auth;

use diCore\Entity\AuthorizationPin\Model;

/**
 * Delivers a freshly issued code or token to its target (`$pin->getTarget()`).
 * `PinCode::getDeliverer()` picks one per channel.
 */
interface PinDeliverer
{
    /**
     * @param Model|\diModel $pin the stored row (only the hash of the value is in it)
     * @param string $plainValue the code/token itself – the only place it exists
     * @param array $context free-form data from the caller (user, language, …)
     *   plus purpose_name and ttl added by PinCode
     */
    public function deliver(\diModel $pin, string $plainValue, array $context): void;
}
