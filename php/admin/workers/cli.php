<?php
require dirname(__FILE__) . '/../../cliHelper.php';

$info = \diRequest::convertFromCommandLine();

if (isset($info['controller']) && isset($info['action']))
{
	\diBaseAdminController::autoCreate($info['controller'], $info['action']);
}
