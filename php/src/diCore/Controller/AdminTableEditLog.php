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
    /**
     * Cursor shape: a decimal SQL id or a hex Mongo ObjectId (24 chars). The value
     * is escaped by the collection anyway; this only turns garbage into a 400
     * instead of an "unavailable" notice (an invalid ObjectId throws inside load()).
     */
    const CURSOR_PATTERN = '/^[0-9a-f]{1,24}$/i';

    const MAX_ID_LENGTH = 64;

    public function pageAction()
    {
        $module = \diRequest::get('module');
        $id = \diRequest::get('id');
        $lastId = \diRequest::get('last_id');

        if (
            !$module ||
            !$id ||
            !is_scalar($module) ||
            !is_scalar($id) ||
            strlen((string) $id) > static::MAX_ID_LENGTH
        ) {
            return $this->badRequest([
                'message' => 'module and id are required',
            ]);
        }

        if ($lastId === '') {
            $lastId = null;
        }

        if (
            $lastId !== null &&
            (!is_scalar($lastId) || !preg_match(static::CURSOR_PATTERN, (string) $lastId))
        ) {
            return $this->badRequest([
                'message' => 'Malformed last_id',
            ]);
        }

        $className = Base::getModuleClassName($module);

        if (!\diLib::exists($className)) {
            return $this->notFound([
                'message' => "Unknown module '$module'",
            ]);
        }

        // Rights first, the page second: liteCreate() runs the page's constructor,
        // which may have side effects (see Admin\Base::isEditLogEnabledForModule()),
        // and an admin without access to the module must not trigger them.
        // Checked by the module's plain (list) path: every registered module grants
        // that one, including a page like Configuration that has no separate
        // "_form" path of its own – and a group's permissions don't differ by path,
        // only their presence in $groupOpts['paths'] does (see Admin\Base::getAdminMenuRow()).
        $admin = $this->createLiteAdmin();

        if (!$admin->canAccessModule($module)) {
            return $this->forbidden([
                'message' => "No access to module '$module'",
            ]);
        }

        $adminPage = $this->createPage($module, $admin);

        // Neither id is cast to int: a Mongo-backed entity or log has ObjectId
        // strings there, and (int) turns them into a wrong number. SQL compares a
        // quoted decimal against a bigint column numerically.
        $adminPage->setId((string) $id, true);

        return $this->okay(
            $adminPage->loadEditLogPage($lastId !== null ? (string) $lastId : null)
        );
    }

    /**
     * @return Base
     */
    protected function createLiteAdmin()
    {
        $adminBaseClassName = \diLib::getChildClass(Base::class);

        return new $adminBaseClassName(Base::INIT_MODE_LITE);
    }

    /**
     * @return BasePage
     */
    protected function createPage($module, Base $admin)
    {
        return BasePage::liteCreate($module, $admin);
    }
}
