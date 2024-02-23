<?php

namespace diCore\Admin;

use diCore\Traits\BasicCreate;

/**
 * Admin config
 */
class Config
{
    use BasicCreate;

    const FILTER__SHOW_COPY_LINK_TO_CLIPBOARD_BUTTON = false;

    public static function shouldFilterShowCopyLinkToClipboardButton()
    {
        return static::basicCreate()::FILTER__SHOW_COPY_LINK_TO_CLIPBOARD_BUTTON;
    }

    public static function getDateRangeFilterGlue()
    {
        return '<span class="date-sep">.</span>';
    }

    public static function getDateRangeFilterSeparator()
    {
        return '<span class="sel-sep">...</span>';
    }

    public static function getDateRangeFilterEmptyContent($idx)
    {
        $glue = static::getDateRangeFilterGlue();

        return "&mdash;&mdash;$glue&mdash;&mdash;$glue&mdash;&mdash;&mdash;&mdash;";
    }
}
