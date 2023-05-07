<?php

/**
 * @deprecated
 */
class diBaseUserModel extends diModel
{
    const slug_field_name = 'login';

    public function active()
    {
        return !!intval($this->get('active'));
    }
}
