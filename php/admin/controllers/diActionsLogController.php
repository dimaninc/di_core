<?php

use diCore\Helper\StringHelper;

class diActionsLogController extends diBaseAdminController
{
    public function getAction()
    {
        $data = diActionsLog::get(
            diActionsLog::getTargetTypeFromStr($this->param(0)),
            $this->param(1)
        );

        foreach ($data as $i => &$r) {
            $r->user = diActionsLog::getUserAppearance($r);
            $r->date = date('d.m.Y H:i', strtotime($r->date));
            $r->actionStr = diActionsLog::getActionStr($r->action);
            $r->info = diActionsLog::getActionInfoStr($r);
        }

        StringHelper::printJson([
            'ok' => 1,
            'data' => $data,
        ]);
    }
}
