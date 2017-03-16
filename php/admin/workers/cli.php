<?php
if (empty($_SERVER["HTTP_HOST"]))
{
	$_SERVER["HTTP_HOST"] = $_SERVER["HOSTNAME"];
}

if (empty($_SERVER["DOCUMENT_ROOT"]))
{
	$_SERVER["DOCUMENT_ROOT"] = realpath(dirname(__FILE__));
	$_SERVER["DOCUMENT_ROOT"] = substr($_SERVER["DOCUMENT_ROOT"], 0, strpos($_SERVER["DOCUMENT_ROOT"], "/_core/php/"));
}

require $_SERVER["DOCUMENT_ROOT"] . "/_core/php/functions.php";
require $_SERVER["DOCUMENT_ROOT"] . "/_cfg/common.php";

$info = diRequest::convertFromCommandLine();

if (isset($info["controller"]) && isset($info["action"]))
{
	diBaseAdminController::autoCreate($info["controller"], $info["action"]);
}
