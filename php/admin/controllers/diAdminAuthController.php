<?php

class diAdminAuthController extends diBaseAdminController
{
    public function logoutAction()
    {
        $adminUser = diAdminUser::create();
        $adminUser->logout();

        $this->redirect();
    }
}
