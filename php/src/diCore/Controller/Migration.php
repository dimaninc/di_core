<?php

namespace diCore\Controller;

use diCore\Database\Tool\MigrationsManager;

class Migration extends \diBaseAdminController
{
    /** @var MigrationsManager */
    private $Manager;

    public function __construct($params = [])
    {
        parent::__construct($params);

        $this->Manager = MigrationsManager::basicCreate();
    }

    public function upAction()
    {
        $this->Manager->run($this->param(0) ?: \diRequest::get('idx'), true);

        $this->redirect();
    }

    public function downAction()
    {
        $this->Manager->run($this->param(0) ?: \diRequest::get('idx'), false);

        $this->redirect();
    }

    /**
     * Shows all new local project migrations
     * php vendor/dimaninc/di_core/php/admin/workers/cli.php controller=migration action=show_new
     */
    public function showNewAction()
    {
        try {
            $migrations = $this->Manager->getNewList();
        } catch (\Exception $e) {
            return $this->internalServerError([
                'message' => $e->getMessage(),
            ]);
        }

        return [
            'migrations' => $migrations,
        ];
    }

    /**
     * Runs all new local project migrations
     * php vendor/dimaninc/di_core/php/admin/workers/cli.php controller=migration action=up_new
     */
    public function upNewAction()
    {
        try {
            $migrationsExecuted = $this->Manager->upNew();
        } catch (\Exception $e) {
            return $this->internalServerError([
                'message' => $e->getMessage(),
            ]);
        }

        $this->redirect();

        return [
            'migrationsExecuted' => $migrationsExecuted,
        ];
    }

    /**
     * Runs last migration
     */
    public function upLastNotExecutedAction()
    {
        try {
            $migrationExecuted = $this->Manager->upLastNotExecuted();
        } catch (\Exception $e) {
            return $this->internalServerError([
                'message' => $e->getMessage(),
            ]);
        }

        $this->redirect();

        return [
            'migrationExecuted' => $migrationExecuted,
        ];
    }

    /**
     * Rollbacks last migration
     */
    public function downLastAction()
    {
        try {
            $migrationRolledBack = $this->Manager->downLast();
        } catch (\Exception $e) {
            return $this->internalServerError([
                'message' => $e->getMessage(),
            ]);
        }

        $this->redirect();

        return [
            'migrationRolledBack' => $migrationRolledBack,
        ];
    }
}
