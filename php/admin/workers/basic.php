<?php
require "../../functions.php";
require \diCore\Data\Config::getConfigurationFolder() . '_cfg/common.php';

try {
	diBaseController::autoCreate();
} catch (Exception $e) {
	diBaseController::autoError($e);
}
