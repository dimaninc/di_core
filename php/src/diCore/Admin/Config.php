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
}
