<?php
/**
 * @deprecated
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 14.12.15
 * Time: 17:35
 */
class diPayment extends \diCore\Payment\Payment
{
    public static function create($targetType, $targetId, $userId)
    {
        if (\diLib::exists(static::childClassName)) {
            $className = static::childClassName;
        } else {
            $className = get_called_class();
        }

        return new $className($targetType, $targetId, $userId);
    }
}
