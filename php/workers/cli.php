<?php
require dirname(__FILE__) . '/../cliHelper.php';

if (\diRequest::get('controller') && \diRequest::get('action'))
{
	\diBaseController::autoCreate(\diRequest::get('controller'), \diRequest::get('action'));
}
