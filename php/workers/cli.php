<?php
require dirname(__FILE__) . '/../cliHelper.php';

$info = \diRequest::convertFromCommandLine();

if (isset($info['controller']) && isset($info['action']))
{
	\diBaseController::autoCreate($info['controller'], $info['action']);
}
