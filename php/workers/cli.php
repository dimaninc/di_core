<?php
require dirname(__FILE__) . '/../cliHelper.php';

// for cli commands execution

if (\diRequest::get('controller') && \diRequest::get('action')) {
    try {
        \diBaseController::autoCreate(
            \diRequest::get('controller'),
            \diRequest::get('action'),
            explode('/', \diRequest::get('params', ''))
        );
    } catch (\Exception $e) {
        \diBaseController::autoError($e);
    }
}
