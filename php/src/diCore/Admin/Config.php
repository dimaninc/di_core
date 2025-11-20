<?php

namespace diCore\Admin;

use diCore\Admin\Data\LoginLayout;
use diCore\Traits\BasicCreate;

/**
 * Admin config
 */
class Config
{
    use BasicCreate;

    const GLOBAL__SHOW_HELP = false;

    const LOGIN__LAYOUT = LoginLayout::classic;

    const FILTER__SHOW_COPY_LINK_TO_CLIPBOARD_BUTTON = false;

    const FORM__SHOW_APPLY_BUTTON = true;
    const FORM__POPULATE_FILTERS_DATA_IF_NEW = false;

    // use <meta http-equiv="refresh"> instead of header('Location:')
    const SUBMIT__USE_HTML_REDIRECT = false;
    // clear exif data of images on upload in admin
    const SUBMIT__CLEAR_EXIF = false;

    public static function shouldShowHelp()
    {
        return static::basicCreate()::GLOBAL__SHOW_HELP;
    }

    public static function getLoginLayout()
    {
        return static::basicCreate()::LOGIN__LAYOUT;
    }

    public static function isCustomLoginLayout()
    {
        return static::basicCreate()::LOGIN__LAYOUT !== LoginLayout::classic;
    }

    public static function shouldFilterShowCopyLinkToClipboardButton()
    {
        return static::basicCreate()::FILTER__SHOW_COPY_LINK_TO_CLIPBOARD_BUTTON;
    }

    public static function shouldFormShowApplyButton()
    {
        return static::basicCreate()::FORM__SHOW_APPLY_BUTTON;
    }

    public static function shouldFormPopulateFiltersDataIfNew()
    {
        return static::basicCreate()::FORM__POPULATE_FILTERS_DATA_IF_NEW;
    }

    public static function shouldSubmitUseHtmlRedirect()
    {
        return static::basicCreate()::SUBMIT__USE_HTML_REDIRECT;
    }

    public static function shouldSubmitClearExif()
    {
        return static::basicCreate()::SUBMIT__CLEAR_EXIF;
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
