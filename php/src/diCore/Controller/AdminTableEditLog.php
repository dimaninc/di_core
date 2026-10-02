<?php

namespace diCore\Controller;

use diCore\Admin\Base;
use diCore\Admin\BasePage;

/**
 * Backs the lazy-loaded edit-log tab (BasePage::shouldLazyLoadEditLog()): one
 * chunk of records per request, fetched through a BasePage::liteCreate()'d
 * instance so createEditLogCollection()/useEditLog() overrides apply exactly as
 * they would inside the real admin form. Same approach Controller\Files and
 * Submit::rebuildDynamicPics() already use for other admin-page logic needed
 * outside the normal admin Base lifecycle.
 *
 * Addressed by MODULE, not by table: a table name is only the module slug for an
 * ordinary entity, and diverges for a page like Admin\Page\Configuration that
 * renames the table it stores into (Data\Configuration::setTableName()) while
 * staying reachable at its own module route – see Admin\Base::isEditLogEnabledForTable()'s
 * docblock for the same distinction on the write side. renderEditLogLazyContainer()
 * hands the browser the page's own getModule(), so the lazy container always
 * carries the right one.
 */
class AdminTableEditLog extends \diBaseAdminController
{
    public function pageAction()
    {
        $module = \diRequest::get('module');
        $id = \diRequest::get('id');
        $lastId = \diRequest::get('last_id');

        if (!$module || !$id) {
            return $this->badRequest([
                'message' => 'module and id are required',
            ]);
        }

        $className = Base::getModuleClassName($module);

        if (!\diLib::exists($className)) {
            return $this->notFound([
                'message' => "Unknown module '$module'",
            ]);
        }

        $adminPage = BasePage::liteCreate($module);

        // liteCreate() only constructs the page – it runs none of the routing
        // checks Admin\Base::work() normally would, so a restricted admin could
        // otherwise read any module's history just by knowing its slug. Checked
        // by the module's plain (list) path: every registered module grants that
        // one, including a page like Configuration that has no separate "_form"
        // path of its own – and a group's permissions don't differ by path, only
        // their presence in $groupOpts['paths'] does (see Admin\Base::getAdminMenuRow()).
        if (!$adminPage->getAdmin()->canAccessModule($module)) {
            return $this->forbidden([
                'message' => "No access to module '$module'",
            ]);
        }

        $adminPage->setId((int) $id, true);

        return $this->okay(
            $adminPage->loadEditLogPage($lastId !== null && $lastId !== '' ? (int) $lastId : null)
        );
    }
}
