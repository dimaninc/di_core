<?php

namespace diCore\Queue;

/**
 * A retry would change nothing (the recipient is gone, a delivery that must not be repeated
 * is unconfirmed): the task is failed at once with this text. An outcome, not a fault – it
 * does not reach Setup::report().
 */
class PermanentFailure extends \RuntimeException
{
}
