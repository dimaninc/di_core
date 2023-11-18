<?php

namespace diCore\Admin\Data;

use diCore\Tool\SimpleContainer;

class FormSnippet extends SimpleContainer
{
    const PIC_PLACEHOLDER = 'PIC_PLACEHOLDER';

    public static $names = [
        self::PIC_PLACEHOLDER => 'PIC_PLACEHOLDER',
    ];

    public static $titles = [
        self::PIC_PLACEHOLDER =>
            'Rendered instead of pic placeholder if no pic uploaded',
    ];
}
