<?php

namespace diCore\Admin;

use diCore\Traits\BasicCreate;

class FormFieldTitle
{
    use BasicCreate;

    public static $custom = [
        /*
        'field' => [
            'ru' => 'Название',
            'en' => 'Title',
        ],
        */
    ];

    public static $default = [
        'id' => 'ID',
        'href' => [
            'ru' => 'Ссылка',
            'en' => 'Link',
        ],
        'en_href' => [
            'ru' => 'Ссылка',
            'en' => 'Link',
        ],
        'link' => [
            'ru' => 'Ссылка',
            'en' => 'Link',
        ],
        'en_link' => [
            'ru' => 'Ссылка',
            'en' => 'Link',
        ],
        'website' => [
            'ru' => 'Вебсайт',
            'en' => 'Website',
        ],
        'category_id' => [
            'ru' => 'Категория',
            'en' => 'Category',
        ],
        'slug' => [
            'ru' => 'Слаг',
            'en' => 'Slug',
        ],
        'slug_source' => [
            'ru' => 'Название для URL',
            'en' => 'Slug source',
        ],
        'parent' => [
            'ru' => 'Родитель',
            'en' => 'Parent',
        ],
        'type' => [
            'ru' => 'Тип',
            'en' => 'Type',
        ],
        'user_id' => [
            'ru' => 'Пользователь',
            'en' => 'User',
        ],
        'email' => [
            'ru' => 'E-mail',
            'en' => 'E-mail',
        ],
        'login' => [
            'ru' => 'Логин',
            'en' => 'Login',
        ],
        'password' => [
            'ru' => 'Пароль',
            'en' => 'Password',
        ],
        'name' => [
            'ru' => 'ФИО',
            'en' => 'Name',
        ],
        'first_name' => [
            'ru' => 'Имя',
            'en' => 'First name',
        ],
        'middle_name' => [
            'ru' => 'Отчество',
            'en' => 'Middle name',
        ],
        'last_name' => [
            'ru' => 'Фамилия',
            'en' => 'Last name',
        ],
        'position' => [
            'ru' => 'Должность',
            'en' => 'Position',
        ],
        'phone' => [
            'ru' => 'Телефон',
            'en' => 'Phone',
        ],
        'address' => [
            'ru' => 'Адрес',
            'en' => 'Address',
        ],
        'title' => [
            'ru' => 'Название',
            'en' => 'Title',
        ],
        'caption' => [
            'ru' => 'Заголовок',
            'en' => 'Caption',
        ],
        'short_content' => [
            'ru' => 'Краткий текст',
            'en' => 'Short content',
        ],
        'content' => [
            'ru' => 'Полный текст',
            'en' => 'Content',
        ],
        'description' => [
            'ru' => 'Описание',
            'en' => 'Description',
        ],
        'properties' => [
            'ru' => 'Свойства',
            'en' => 'Properties',
        ],
        'en_title' => [
            'ru' => 'Название',
            'en' => 'Title',
        ],
        'en_short_content' => [
            'ru' => 'Краткий текст',
            'en' => 'Short content',
        ],
        'en_content' => [
            'ru' => 'Полный текст',
            'en' => 'Content',
        ],
        'en_description' => [
            'ru' => 'Описание',
            'en' => 'Description',
        ],
        'ru_title' => [
            'ru' => 'Название',
            'en' => 'Title',
        ],
        'ru_short_content' => [
            'ru' => 'Краткий текст',
            'en' => 'Short content',
        ],
        'ru_content' => [
            'ru' => 'Полный текст',
            'en' => 'Content',
        ],
        'ru_description' => [
            'ru' => 'Описание',
            'en' => 'Description',
        ],
        'pic' => [
            'ru' => 'Изображение',
            'en' => 'Pic',
        ],
        'en_pic' => [
            'ru' => 'Изображение',
            'en' => 'Pic',
        ],
        'ico' => [
            'ru' => 'Иконка',
            'en' => 'Icon',
        ],
        'avatar' => [
            'ru' => 'Аватар',
            'en' => 'Avatar',
        ],
        'logo' => [
            'ru' => 'Логотип',
            'en' => 'Logo',
        ],
        'color' => [
            'ru' => 'Цвет',
            'en' => 'Color',
        ],
        'visible' => [
            'ru' => 'Отображать на сайте',
            'en' => 'Visible',
        ],
        'en_visible' => [
            'ru' => 'Отображать на сайте',
            'en' => 'Visible',
        ],
        'active' => [
            'ru' => 'Активно',
            'en' => 'Active',
        ],
        'en_active' => [
            'ru' => 'Активно',
            'en' => 'Active',
        ],
        'top' => [
            'ru' => 'В топе',
            'en' => 'Promote',
        ],
        'en_top' => [
            'ru' => 'В топе',
            'en' => 'Promote',
        ],
        'like_count' => [
            'ru' => 'Количество лайков',
            'en' => 'Likes count',
        ],
        'dislike_count' => [
            'ru' => 'Количество дизлайков',
            'en' => 'Dislikes count',
        ],
        'comment_count' => [
            'ru' => 'Количество комментариев',
            'en' => 'Comments count',
        ],
        'comment_last_date' => [
            'ru' => 'Дата/время последнего комментария',
            'en' => 'Date/time of last comment',
        ],
        'comment_enabled' => [
            'ru' => 'Комментарии разрешены',
            'en' => 'Comments enabled',
        ],
        'meta_title' => [
            'ru' => 'Meta-заголовок',
            'en' => 'Meta-title',
        ],
        'meta_keywords' => [
            'ru' => 'Meta-ключевые слова',
            'en' => 'Meta-keywords',
        ],
        'meta_description' => [
            'ru' => 'Meta-описание',
            'en' => 'Meta-description',
        ],
        'ru_meta_title' => [
            'ru' => 'Meta-заголовок',
            'en' => 'Meta-title',
        ],
        'ru_meta_keywords' => [
            'ru' => 'Meta-ключевые слова',
            'en' => 'Meta-keywords',
        ],
        'ru_meta_description' => [
            'ru' => 'Meta-описание',
            'en' => 'Meta-description',
        ],
        'en_meta_title' => [
            'ru' => 'Meta-заголовок',
            'en' => 'Meta-title',
        ],
        'en_meta_keywords' => [
            'ru' => 'Meta-ключевые слова',
            'en' => 'Meta-keywords',
        ],
        'en_meta_description' => [
            'ru' => 'Meta-описание',
            'en' => 'Meta-description',
        ],
        'html_title' => [
            'ru' => 'Meta-заголовок',
            'en' => 'Meta-title',
        ],
        'html_keywords' => [
            'ru' => 'Meta-ключевые слова',
            'en' => 'Meta-keywords',
        ],
        'html_description' => [
            'ru' => 'Meta-описание',
            'en' => 'Meta-description',
        ],
        'created_at' => [
            'ru' => 'Дата/время создания',
            'en' => 'Date/time of creation',
        ],
        'updated_at' => [
            'ru' => 'Дата/время последнего изменения',
            'en' => 'Date/time of update',
        ],
        'applied_at' => [
            'ru' => 'Дата/время применения',
            'en' => 'Date/time of use',
        ],
        'done_at' => [
            'ru' => 'Дата/время выполнения',
            'en' => 'Date/time of completion',
        ],
        'deleted_at' => [
            'ru' => 'Дата/время удаления',
            'en' => 'Date/time of deletion',
        ],
        'seen_at' => [
            'ru' => 'Последнее посещение',
            'en' => 'Seen at',
        ],
        'ip' => [
            'ru' => 'IP-адрес',
            'en' => 'IP address',
        ],
    ];
}
