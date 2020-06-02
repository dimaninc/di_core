<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 11.02.16
 * Time: 9:58
 */

use diCore\Helper\ArrayHelper;

class diNiceTableButtons
{
	public static $titles = [
		'en' => [
			'create' => 'Create subsection',
			'edit' => 'Edit',
			'manage' => 'Edit',
			'del' => 'Delete',
			'print' => 'Print',
			'pic' => 'Pics',
			'video' => 'Videos',
			'up' => 'Move up',
			'down' => 'Move down',
			'play' => 'Go',
			'rollback' => 'Rollback',
			'mail' => 'Compose mail',
			'comments' => [0 => 'No comments', 1 => 'Comments'],
			'visible' => [0 => 'Invisible', 1 => 'Visible'],
			'visible_logged_in' => [0 => 'Invisible for logged in', 1 => 'Visible for logged in'],
			'visible_top' => [0 => 'Invisible in top menu', 1 => 'Visible in top menu'],
			'visible_bottom' => [0 => 'Invisible in bottom menu', 1 => 'Visible in bottom menu'],
			'visible_2nd_bottom' => [0 => 'Invisible in 2nd bottom menu', 1 => 'Visible in 2nd bottom menu'],
			'visible_left' => [0 => 'Invisible in left menu', 1 => 'Visible in left menu'],
			'visible_right' => [0 => 'Invisible in right menu', 1 => 'Invisible in right menu'],
			'en_visible' => [0 => 'Invisible', 1 => 'Visible'],
			'en_visible_logged_in' => [0 => 'Invisible for logged in', 1 => 'Visible for logged in'],
			'en_visible_top' => [0 => 'Invisible in top menu', 1 => 'Visible in top menu'],
			'en_visible_bottom' => [0 => 'Invisible in bottom menu', 1 => 'Visible in bottom menu'],
			'en_visible_2nd_bottom' => [0 => 'Invisible in 2nd bottom menu', 1 => 'Visible in 2nd bottom menu'],
			'en_visible_left' => [0 => 'Invisible in left menu', 1 => 'Visible in left menu'],
			'en_visible_right' => [0 => 'Invisible in right menu', 1 => 'Invisible in right menu'],
			'opened' => [0 => 'Closed', 1 => 'Opened'],
			'top' => [0 => 'Not promoted', 1 => 'Promoted'],
			'en_top' => [0 => 'Not promoted', 1 => 'Promoted'],
			'winner' => [0 => 'Not winner', 1 => 'Winner'],
			'new' => [0 => 'Not new', 1 => 'New'],
			'hit' => [0 => 'Not hit', 1 => 'Hit'],
			'hot' => [0 => 'Not hot', 1 => 'Hot'],
			'recommended' => [0 => 'Not recommended', 1 => 'Recommended'],
			'active' => [0 => 'Non-active', 1 => 'Active'],
			'activated' => [0 => 'Non-activated', 1 => 'Activated'],
			'reply' => [0 => 'Reply', 1 => 'Edit reply'],
			'to_show_content' => [0 => 'Content not shown', 1 => 'Content shown'],
			'moderated' => [0 => 'Not yet accepted by admin', 1 => 'Accepted by admin'],
			'accepted' => [0 => 'Not auto-accepted', 1 => 'Accepted automatically'],
		],
		'ru' => [
			'create' => 'Создать подраздел',
			'edit' => 'Редактировать',
			'manage' => 'Управление',
			'del' => 'Удалить',
			'print' => 'Распечатать',
			'pic' => 'Фотографии',
			'video' => 'Видео',
			'up' => 'Переместить вверх',
			'down' => 'Переместить вниз',
			'play' => 'Накатить',
			'rollback' => 'Откатить',
			'mail' => 'Написать письмо',
			'comments' => [0 => 'Нет комментариев', 1 => 'Комментарии'],
			'visible' => [0 => 'Скрыто', 1 => 'Видно'],
			'visible_logged_in' => [0 => 'Скрыто для залогиненных', 1 => 'Видно для залогиненных'],
			'visible_top' => [0 => 'Скрыто в верхнем меню', 1 => 'Видно в верхнем меню'],
			'visible_bottom' => [0 => 'Скрыто в нижнем меню', 1 => 'Видно в нижнем меню'],
			'visible_2nd_bottom' => [0 => 'Скрыто во 2м нижнем меню', 1 => 'Видно во 2-м нижнем меню'],
			'visible_left' => [0 => 'Скрыто в левом меню', 1 => 'Видно в левом меню'],
			'visible_right' => [0 => 'Скрыто в правом меню', 1 => 'Видно в правом меню'],
			'en_visible' => [0 => 'Скрыто', 1 => 'Видно'],
			'en_visible_logged_in' => [0 => 'Скрыто для залогиненных', 1 => 'Видно для залогиненных'],
			'en_visible_top' => [0 => 'Скрыто в верхнем меню', 1 => 'Видно в верхнем меню'],
			'en_visible_bottom' => [0 => 'Скрыто в нижнем меню', 1 => 'Видно в нижнем меню'],
			'en_visible_2nd_bottom' => [0 => 'Скрыто во 2м нижнем меню', 1 => 'Видно во 2-м нижнем меню'],
			'en_visible_left' => [0 => 'Скрыто в левом меню', 1 => 'Видно в левом меню'],
			'en_visible_right' => [0 => 'Скрыто в правом меню', 1 => 'Видно в правом меню'],
			'opened' => [0 => 'Закрыт', 1 => 'Открыт'],
			'top' => [0 => 'Не в топе', 1 => 'В топе'],
			'en_top' => [0 => 'Не в топе', 1 => 'В топе'],
			'winner' => [0 => 'Не победитель', 1 => 'Победитель'],
			'new' => [0 => 'Не новинка', 1 => 'Новинка'],
			'hit' => [0 => 'Не хит продаж', 1 => 'Хит продаж'],
			'hot' => [0 => 'Не в топе', 1 => 'В топе'],
			'recommended' => [0 => 'Не рекомендовано', 1 => 'Рекомендовано'],
			'active' => [0 => 'Отключен', 1 => 'Активен'],
			'activated' => [0 => 'Не активирован', 1 => 'Активирован'],
			'reply' => [0 => 'Ответить', 1 => 'Изменить ответ'],
			'to_show_content' => [0 => 'Не имеет страницы', 1 => 'Имеет страницу'],
			'moderated' => [0 => 'Не принято', 1 => 'Принято админом'],
			'accepted' => [0 => 'Не принято автоматически', 1 => 'Принято автоматически'],
		],
	];

	/**
	 * @param string $action
	 * @param array|string $options
	 */
	public static function getButton($action, $options = [])
	{
		if (gettype($options) == 'string') {
			$options = [
				'href' => $options,
			];
		}

		$options = extend([
			'state' => null,
			'text' => null,
			'href' => null,
			'onclick' => null,
			'language' => 'ru',
            'customTitles' => [], // see BasePage::$$customListButtonTitles
		], $options);

		$titleSource = $options['customTitles'][$options['language']][$action]
            ?? self::$titles[$options['language']][$action];

		$title = is_array($titleSource)
			? $titleSource[(int)$options['state']]
			: $titleSource;

		$tag = $options['href']
            ? 'a'
            : 'div';

		$text = $options['text']
			? "<div class=\"nicetable-text-over-btn\">{$options['text']}</div>"
			: '';

		$attributes = [
			'data-action' => $action,
			'data-state' => $options['state'],
			'title' => $title,
		];

		if ($options['href']) {
			$attributes['href'] = $options['href'];
		}

		if ($options['onclick']) {
			$attributes['onclick'] = $options['onclick'];
		}

		return $text . self::getButtonHtml($tag, $attributes);
	}

	public static function getButtonHtml($tag = 'div', $attributes = [])
	{
		$attributes = extend([
			'class' => 'nicetable-button',
		], $attributes);

		$attributesStr = ArrayHelper::toAttributesString($attributes, true, ArrayHelper::ESCAPE_HTML);

		return "<{$tag} {$attributesStr}></{$tag}>";
	}
}
