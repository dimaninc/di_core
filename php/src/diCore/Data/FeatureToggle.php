<?php

namespace diCore\Data;

use diCore\Traits\BasicCreate;

class FeatureToggle
{
    use BasicCreate;

    public static function getX()
    {
        global $X;

        if (!isset($X)) {
            throw new \Exception('FeatureToggle: Not in admin interface');
        }

        return $X;
    }

    public static function getZ()
    {
        global $Z;

        if (!isset($Z)) {
            throw new \Exception('FeatureToggle: diCMS not initialized');
        }

        return $Z;
    }

    public static function coreAsArray(): array
    {
        return [
            'isAdditionalTemplateEnabled' => static::isAdditionalTemplateEnabled(),
        ];
    }

    public static function projectAsArray(): array
    {
        return [];
    }

    public static function asArray(): array
    {
        return extend(static::coreAsArray(), static::projectAsArray());
    }

    public static function isAdditionalTemplateEnabled()
    {
        return false;
    }
}
