<?php
require '../functions.php';
require \diCore\Data\Config::getConfigurationFolder() . '_cfg/common.php';

var_dump(\diCore\Data\Config::getConfigurationFolder());

try {
	diBaseController::autoCreate();
} catch (Exception $e) {
	diBaseController::autoError($e);
}
