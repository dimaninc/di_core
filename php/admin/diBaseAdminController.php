<?php

use diCore\Data\Config;

class diBaseAdminController extends diBaseController
{
    public function __construct($params = [])
    {
        parent::__construct($params);

        try {
            $this->initAdmin()->adminRightsHardCheck();
        } catch (\Exception $e) {
            static::autoError($e);
            die();
        }
    }

    protected static function isRestApiSupported()
    {
        return Config::isRestApiInAdminSupported();
    }

    protected function redirect()
    {
        if (self::isCli()) {
            return $this;
        }

        $back = \diRequest::get('back', \diRequest::referrer('/_admin/'));
        $this->redirectTo($back);

        return $this;
    }
}
