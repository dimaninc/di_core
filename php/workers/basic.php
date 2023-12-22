<?php
require dirname(__FILE__, 2) . '/functions.php';
require \diCore\Data\Config::getConfigurationFolder() . '_cfg/common.php';

// for http requests

try {
    \diBaseController::autoCreate();
} catch (\Exception $e) {
    \diBaseController::autoError($e);
}
