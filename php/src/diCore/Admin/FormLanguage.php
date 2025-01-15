<?php

namespace diCore\Admin;

class FormLanguage
{
    public static $customLngStrings = [
        'en' => [],
        'ru' => [],
    ];

    public static $lngStrings = [
        'en' => [
            'notes_caption' => [
                false => 'Note',
                true => 'Notes',
            ],

            'view_help' => 'View help',
            'save' => 'Save',
            'clone' => 'Save as a new record',
            'cancel' => 'Cancel',
            'quick_save' => 'Apply',
            'create_and_add_another' => 'Create and add another',
            'dispatch' => 'Save and dispatch',
            'dispatch_test' => 'Save and test dispatch',
            'edit' => 'Edit record',
            'calendar' => 'Calendar',
            'clear' => 'Clear',
            'submit_and_add' => 'Save and add new item',
            'submit_and_next' => 'Save and go to next item',
            'submit_and_send' => 'Save and send via email',
            'delete' => 'Delete',
            'delete_pic_confirmation' => 'Delete the pic? Are you sure?',
            'delete_file_confirmation' => 'Delete the file? Are you sure?',
            'rotate_pic_confirmation' => 'Rotate the pic? Are you sure?',
            'rotate_pic.ccw' => 'Rotate pic 90° CCW',
            'rotate_pic.cw' => 'Rotate pic 90° CW',
            'watermark_pic_confirmation' => 'Watermark the pic? Are you sure?',
            'watermark_pic' => 'Watermark pic',

            'placeholder.date.day' => 'DD',
            'placeholder.date.month' => 'MM',
            'placeholder.date.year' => 'YY',
            'placeholder.time.hour' => 'HH',
            'placeholder.time.minute' => 'MM',
            'placeholder.time.second' => 'SS',

            'yes' => 'Yes',
            'no' => 'No',

            'confirm' => 'Confirm',
            'confirm_dispatch' =>
                'Dispatch this record to the subscribers? Are you sure?',
            'confirm_send' => 'Send the reply to email? Are you sure?',

            'or_enter' => 'or enter',
            'your_variant' => 'Your own variant',
            'add_item' => 'Add',
            'link' => 'Link',
            'not_selected' => 'Not selected',

            'tab_general' => 'General',

            'choose_file' => 'Choose file...',
            'rename_to' => 'Rename to &laquo;{{ fn }}&raquo;',
            'rename_to.confirm' => 'Rename to &laquo;{{ fn }}&raquo;?',

            'tag.enter_new' => 'Add new items, comma separated',
            'tag.toggle_on' => 'All on',
            'tag.toggle_off' => 'All off',
            'tag.search_placeholder' => 'Search...',
            'tag.show_all' => 'Show all',
            'tag.none_selected' => 'None selected',
        ],

        'ru' => [
            'notes_caption' => [
                false => 'Примечание',
                true => 'Примечания',
            ],

            'view_help' => 'Помощь',
            'save' => 'Сохранить',
            'clone' => 'Сохранить как новую запись',
            'cancel' => 'Отмена',
            'quick_save' => 'Применить',
            'create_and_add_another' => 'Сохранить и создать ещё',
            'dispatch' => 'Сохранить и произвести рассылку',
            'dispatch_test' => 'Сохранить и произвести тестовую рассылку',
            'edit' => 'Редактировать',
            'calendar' => 'Календарь',
            'clear' => 'Очистить',
            'submit_and_add' => 'Сохранить и добавить новый товар',
            'submit_and_next' => 'Сохранить и перейти к следующей записи',
            'submit_and_send' => 'Сохранить и отправить письмо',
            'delete' => 'Удалить',
            'delete_pic_confirmation' => 'Удалить картинку? Вы уверены?',
            'delete_file_confirmation' => 'Удалить файл? Вы уверены?',
            'rotate_pic_confirmation' => 'Повернуть картинку? Вы уверены?',
            'rotate_pic.ccw' => 'Повернуть на 90° против часовой стрелки',
            'rotate_pic.cw' => 'Повернуть на 90° по часовой стрелке',
            'watermark_pic_confirmation' => 'Наложить водяной знак?',
            'watermark_pic' => 'Наложить водяной знак',

            'placeholder.date.day' => 'ДД',
            'placeholder.date.month' => 'ММ',
            'placeholder.date.year' => 'ГГГГ',
            'placeholder.time.hour' => 'ЧЧ',
            'placeholder.time.minute' => 'ММ',
            'placeholder.time.second' => 'СС',

            'yes' => 'Да',
            'no' => 'Нет',

            'confirm' => 'Подтвердите',
            'confirm_dispatch' =>
                'Пустить этот материал в рассылку подписчикам? Вы уверены?',
            'confirm_send' => 'Отправить ответ на почту? Вы уверены?',

            'or_enter' => 'или введите',
            'your_variant' => 'Свой вариант',
            'add_item' => 'Добавить',
            'link' => 'Ссылка',
            'not_selected' => 'Не выбрано',

            'tab_general' => 'Основное',

            'choose_file' => 'Выбрать файл...',
            'rename_to' => 'Переименовать в &laquo;{{ fn }}&raquo;',
            'rename_to.confirm' => 'Переименовать в &laquo;{{ fn }}&raquo;?',

            'tag.enter_new' => 'Добавить новые, через запятую',
            'tag.toggle_on' => 'Выделить все',
            'tag.toggle_off' => 'Снять все',
            'tag.search_placeholder' => 'Поиск...',
            'tag.show_all' => 'Показать все',
            'tag.none_selected' => 'Ничего не выбрано',
        ],
    ];
}
