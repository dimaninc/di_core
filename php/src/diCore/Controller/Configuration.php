<?php

namespace diCore\Controller;

use diCore\Admin\Base;
use diCore\Admin\Page\Configuration as ConfigurationPage;
use diCore\Data\Configuration as Cfg;
use diCore\Entity\AdminTableEditLog\Model as EditLog;

class Configuration extends \diBaseAdminController
{
    public function storeAction()
    {
        $this->runWithEditLog(function () {
            Cfg::getInstance()->store();
        });

        $this->redirectBack();
    }

    public function delPicAction()
    {
        $this->runWithEditLog(function () {
            $k = $this->param(0);

            if ($k && Cfg::exists($k) && Cfg::get($k)) {
                $fn = Cfg::getFolder() . Cfg::get($k);
                $full_fn = \diPaths::fileSystem() . $fn;

                if (is_file($full_fn)) {
                    unlink($full_fn);
                }

                Cfg::getInstance()
                    ->setToDB($k, '')
                    ->updateCache();
            }
        });

        $this->redirectBack();
    }

    protected function redirectBack()
    {
        return $this->redirectTo(
            Base::getPageUri(ConfigurationPage::ADMIN_MODULE, '', ['saved' => 1])
        );
    }

    /**
     * Runs a settings-changing action between two snapshots and stores their
     * difference as a single edit-log record.
     *
     * A failing $action is not logged and is not swallowed – an action that threw
     * has no "after" state worth recording.
     *
     * @param callable $action
     * @return $this
     */
    protected function runWithEditLog(callable $action)
    {
        $before = $this->takeConfigurationSnapshot();

        $action();

        return $this->logConfigurationChanges($before);
    }

    /**
     * Values before the action, or null when there is nothing to log – the tail of
     * the process then costs nothing. Reading the settings must not be able to
     * break the action either, hence the guard.
     *
     * @return array|null
     */
    protected function takeConfigurationSnapshot()
    {
        if (!$this->isEditLogEnabled()) {
            return null;
        }

        try {
            return $this->readConfigurationValues();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Setting key => value, for exactly the settings the admin page shows and is
     * able to change (the rest can't be edited, so a change in them is not an edit).
     *
     * Always from the database: store() refreshes self::$data for unchecked
     * checkboxes only, so getData() alone would answer with pre-store values for
     * everything else.
     *
     * @return array
     */
    protected function readConfigurationValues()
    {
        Cfg::getInstance()->loadAllFromDB();

        return $this->filterConfigurationValues();
    }

    /**
     * The same two conditions Admin\Page\Configuration applies when it builds the
     * settings table, so the log holds exactly the settings an admin can see and
     * change there.
     *
     * @return array
     */
    protected function filterConfigurationValues()
    {
        $values = [];

        foreach (Cfg::getData() as $k => $v) {
            if (!isset($v['title']) || Cfg::hasFlag($k, 'hidden')) {
                continue;
            }

            $values[$k] = $v['value'] ?? '';
        }

        return $values;
    }

    /**
     * One record per action, holding just the settings that changed: the keys play
     * the role of field names, which is what makes the existing diff rendering
     * (templates/admin/admin_table_edit_log/form_field.html.twig) work as is.
     *
     * The whole thing is best-effort: the log has no right to break saving the
     * settings, so even a broken store only costs the record.
     *
     * @param array|null $before
     * @return $this
     */
    protected function logConfigurationChanges($before)
    {
        if ($before === null) {
            return $this;
        }

        try {
            $after = $this->readConfigurationValues();

            $old = $new = [];

            foreach (array_keys($before + $after) as $k) {
                $oldValue = $before[$k] ?? '';
                $newValue = $after[$k] ?? '';

                if (
                    $this->editLogValue($oldValue) === $this->editLogValue($newValue)
                ) {
                    continue;
                }

                $old[$k] = $oldValue;
                $new[$k] = $newValue;
            }

            if (!$old) {
                return $this;
            }

            $this->createEditLogRecord()
                ->setTargetTable(Cfg::getInstance()->getTableName())
                ->setTargetId(ConfigurationPage::EDIT_LOG_TARGET_ID)
                ->setAdminId($this->getEditLogAdminId())
                ->setOldData(serialize($old))
                ->setNewData(serialize($new))
                ->save();
        } catch (\Throwable $e) {
            // the log must never break the settings being saved
        }

        return $this;
    }

    /**
     * Settings are typed (a checkbox comes back as int), so compare them as the
     * strings they are stored and rendered as instead of by ===.
     */
    private function editLogValue($value)
    {
        return is_scalar($value) || $value === null
            ? (string) $value
            : json_encode($value);
    }

    /**
     * By MODULE, not by table. The gate resolves an admin page from the name it is
     * given, and this page's table is not fixed: Data\Configuration::setTableName()
     * renames it under the page, and 'my_settings' then resolves to no page at all –
     * so the gate would answer "not logged" while the page still shows the tab, and
     * the journal would stay empty forever without a single error.
     */
    protected function isEditLogEnabled()
    {
        return Base::isEditLogEnabledForModule(ConfigurationPage::ADMIN_MODULE);
    }

    /**
     * @return EditLog
     */
    protected function createEditLogRecord()
    {
        return EditLog::create();
    }

    /**
     * No admin in CLI. The record is then refused by Model::validate() (diModel
     * treats 0 as no id at all) and the catch above drops it – same as a list
     * toggle made from a worker.
     */
    protected function getEditLogAdminId()
    {
        $admin = $this->getAdmin() ? $this->getAdminModel() : null;

        return $admin ? $admin->getId() : 0;
    }
}
