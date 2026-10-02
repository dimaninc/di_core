<?php

namespace diCore\Controller;

use diCore\Admin\Base;
use diCore\Admin\BasePage;

/**
 * Backs the lazy-loaded edit-log tab (BasePage::shouldLazyLoadEditLog()): one
 * page of records per request, fetched through a BasePage::liteCreate()'d
 * instance so createEditLogCollection()/useEditLog() overrides apply exactly as
 * they would inside the real admin form. Same approach Controller\Files and
 * Submit::rebuildDynamicPics() already use for other admin-page logic needed
 * outside the normal admin Base lifecycle.
 */
class AdminTableEditLog extends \diBaseAdminController
{
    public function pageAction()
    {
        $table = \diRequest::get('table');
        $id = \diRequest::get('id');
        $pageNumber = (int) \diRequest::get('page', 1);

        if (!$table || !$id) {
            return $this->badRequest([
                'message' => 'table and id are required',
            ]);
        }

        $className = Base::getModuleClassName($table);

        if (!\diLib::exists($className)) {
            return $this->notFound([
                'message' => "Unknown table '$table'",
            ]);
        }

        $adminPage = BasePage::liteCreate($table);
        $adminPage->setId((int) $id, true);

        return $this->okay($adminPage->loadEditLogPage($pageNumber));
    }
}
