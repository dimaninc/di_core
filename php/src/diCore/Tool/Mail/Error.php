<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 29.08.2017
 * Time: 17:58
 */

namespace diCore\Tool\Mail;

use diCore\Tool\SimpleContainer;

class Error extends SimpleContainer
{
    const NONE = 0;
    const QUEUE_IS_EMPTY = 1;
    const NO_CREDENTIALS = 2;

    const UNKNOWN_FATAL = 100;
}
