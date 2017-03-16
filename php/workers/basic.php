<?php
require "../functions.php";
require $_SERVER["DOCUMENT_ROOT"] . "/_cfg/common.php";

try {
	diBaseController::autoCreate();
} catch (Exception $e) {
	diBaseController::autoError($e);
}
