<?php
require dirname(__FILE__) . '/../../cliHelper.php';

if (\diRequest::get('controller') && \diRequest::get('action')) {
    \diBaseAdminController::autoCreate(
        \diRequest::get('controller'),
        \diRequest::get('action'),
        explode('/', \diRequest::get('params'))
    );
}
