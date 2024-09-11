<?php

namespace diCore\Traits\Admin;

use diCore\Admin\BasePage;

trait AdditionalVariable
{
    public function avAddToForm(BasePage $Page, $options = [])
    {
        /** @var \diCore\Traits\Model\AdditionalVariable|\diModel $model */
        $model = $Page->getForm()->getModel();

        // todo: 1) add fields to admin form
    }

    public function avAddToSubmit(BasePage $Page, $options = [])
    {
        /** @var \diCore\Traits\Model\AdditionalVariable|\diModel $model */
        $model = $Page->getSubmit()->getModel();

        // todo: 2) add fields storing to admin submit
    }
}
