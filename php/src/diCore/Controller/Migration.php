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
        $this->Manager->run($this->param(0), true);

        $this->redirect();
    }

    public function downAction()
    {
        $this->Manager->run($this->param(0), false);

        $this->redirect();
    }

    /**
     * Runs all new local project migrations
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
