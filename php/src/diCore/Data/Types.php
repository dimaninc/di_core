<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 15.09.2016
 * Time: 11:36
 */

namespace diCore\Data;

/**
 * Class Types
 * @package diCore\Data
 *
 * List of target types for comments and tags
 * Conversion from type_id to table_name
 *
 * Should be overridden in project as diTypes
 *
 */
class Types
{
	const content = 2001;
	const news = 2002;
	const item = 2003;
	const order = 2004;
	const user = 2005;
	const category = 2006;
	const ad = 2007;
	const ad_block = 2008;
	const banner = 2009;
	const font = 2010;
	const mail_queue = 2011;
	const tag = 2012;
	const album = 2013;
	const photo = 2014;
	const video = 2015;
	const cart = 2016;
	const cart_item = 2017;
	const order_item = 2018;
	const order_status = 2019;
	const comment = 2020;
	const o_auth2_profile = 2021;
	const payment_draft = 2022;
	const payment_receipt = 2023;
	const news_attach = 2024;
	const feedback = 2025;
	const banner_daily_stat = 2026;
	const geo_ip_cache = 2027;
	const redirect = 2028;
	const localization = 2029;
	const module_cache = 2030;
	const comment_cache = 2031;

	const admin_task = 1000;
	const admin_wiki = 1001;
	const admin = 1002;
	const di_migrations_log = 1003;
	const dynamic_pic = 1004;
	const slug = 1005;
	const di_actions_log = 1006;
	const admin_task_participant = 1007;
	const admin_table_edit_log = 1008;

	public static $commonTables = [
		self::content => "content",
		self::news => "news",
		self::item => "items",
		self::order => "orders",
		self::user => "users",
		self::category => "categories",
		self::ad => "ads",
		self::ad_block => "ad_blocks",
		self::banner => "banners",
		self::font => "fonts",
		self::mail_queue => "mail_queue",
		self::tag => "tags",
		self::album => "albums",
		self::photo => "photos",
		self::video => "videos",
		self::cart => "carts",
		self::cart_item => "cart_items",
		self::order_item => "order_items",
		self::order_status => "order_statuses",
		self::comment => "comments",
		self::o_auth2_profile => "#no table",
		self::payment_draft => "payment_drafts",
		self::payment_receipt => "payment_receipts",
		self::news_attach => "news_attaches",
		self::feedback => "feedback",
		self::banner_daily_stat => "banner_daily_stat",
		self::geo_ip_cache => "geo_ip_cache",
		self::redirect => "redirects",
		self::localization => "localization",
		self::module_cache => 'module_cache',
		self::comment_cache => 'comment_cache',

		self::admin_task => "admin_tasks",
		self::admin_wiki => "admin_wiki",
		self::admin => "admins",
		self::di_migrations_log => "di_migrations_log",
		self::dynamic_pic => "dipics",
		self::slug => "slugs",
		self::di_actions_log => "di_actions_log",
		self::admin_task_participant => "admin_task_participants",
		self::admin_table_edit_log => "admin_table_edit_log",
	];

	public static $commonNames = [
		self::content => "content",
		self::news => "news",
		self::item => "item",
		self::order => "order",
		self::user => "user",
		self::category => "category",
		self::ad => "ad",
		self::ad_block => "ad_block",
		self::banner => "banner",
		self::font => "font",
		self::mail_queue => "mail_queue",
		self::tag => "tag",
		self::album => "album",
		self::photo => "photo",
		self::video => "video",
		self::cart => "cart",
		self::cart_item => "cart_item",
		self::order_item => "order_item",
		self::order_status => "order_status",
		self::comment => "comment",
		self::o_auth2_profile => "o_auth2_profile",
		self::payment_draft => "payment_draft",
		self::payment_receipt => "payment_receipt",
		self::news_attach => "news_attach",
		self::feedback => "feedback",
		self::banner_daily_stat => "banner_daily_stat",
		self::geo_ip_cache => "geo_ip_cache",
		self::redirect => "redirect",
		self::localization => "localization",
		self::module_cache => 'module_cache',
		self::comment_cache => 'comment_cache',

		self::admin_task => "admin_task",
		self::admin_wiki => "admin_wiki",
		self::admin => "admin",
		self::di_migrations_log => "di_migrations_log",
		self::dynamic_pic => "dynamic_pic",
		self::slug => "slug",
		self::di_actions_log => "di_actions_log",
		self::admin_task_participant => "admin_task_participant",
		self::admin_table_edit_log => "admin_table_edit_log",
	];

	public static $commonTitles = [
		self::content => "Страница",
		self::news => "Новость",
		self::item => "Товар",
		self::order => "Заказ",
		self::user => "Пользователь",
		self::category => "Категория",
		self::ad => "Рекламный слайд",
		self::ad_block => "Рекламный блок",
		self::banner => "Баннер",
		self::font => "Шрифт",
		self::mail_queue => "Письмо в очереди",
		self::tag => "Тег",
		self::album => "Альбом",
		self::photo => "Фото",
		self::video => "Видео",
		self::cart => "Корзина",
		self::cart_item => "Товар в корзине",
		self::order_item => "Товар в заказе",
		self::order_status => "Статус заказа",
		self::comment => "Комментарий",
		self::o_auth2_profile => "Профиль из соц.сети",
		self::payment_draft => "Заготовка под платеж",
		self::payment_receipt => "Квитанция платежа",
		self::news_attach => "Приложение к рассылке",
		self::feedback => "Сообщение обратной связи",
		self::banner_daily_stat => "Статистика баннера за день",
		self::geo_ip_cache => "Кеш IP регионов",
		self::redirect => "Редирект",
		self::localization => "Локализация",
		self::module_cache => 'Кеш страницы модуля',
		self::comment_cache => 'Кеш блока комментариев',

		self::admin_task => "Задача",
		self::admin_wiki => "Wiki",
		self::admin => "Админ",
		self::di_migrations_log => "Лог миграций",
		self::dynamic_pic => "Картинка",
		self::slug => "Слаг",
		self::di_actions_log => "Действие",
		self::admin_task_participant => "Исполнитель задачи",
		self::admin_table_edit_log => "Изменение данных в таблице",
	];

	/* override this in child class */
	public static $tables = [];
	/* override this in child class */
	public static $names = [];
	/* override this in child class */
	public static $titles = [];

	public static function tables()
	{
		return extend(static::$commonTables, static::$tables);
	}

	public static function names()
	{
		return extend(static::$commonNames, static::$names);
	}

	public static function titles()
	{
		return extend(static::$commonTitles, static::$titles);
	}

	public static function getId($type)
	{
		if (isInteger($type))
		{
			$ar = static::tables();

			if (isset($ar[$type]))
			{
				return $type;
			}
		}

		$id = array_search($type, static::tables());

		if ($id === false)
		{
			$id = defined("static::$type") ? constant("static::$type") : null;
		}

		return $id;
	}

	public static function getTable($id)
	{
		$ar = static::tables();

		if (!isset($ar[$id]))
		{
			throw new \Exception("No table for type#$id");
		}

		return $ar[$id];
	}

	public static function getName($id)
	{
		$ar = static::names();

		if (!isset($ar[$id]))
		{
			throw new \Exception("No name for type#$id");
		}

		return $ar[$id];
	}

	public static function getTitle($id)
	{
		$ar = static::titles();

		if (!isset($ar[$id]))
		{
			throw new \Exception("No title for type#$id");
		}

		return $ar[$id];
	}

	public static function getNameByTable($table)
	{
		$tableId = \diTypes::getId($table);

		return $tableId ? static::getName($tableId) : null;
	}

	public static function getTableByName($name)
	{
		$id = \diTypes::getId($name);

		return $id ? static::getTable($id) : null;
	}

	public static function get($type)
	{
		if (isInteger($type))
		{
			return static::getTable($type);
		}
		else
		{
			return static::getId($type);
		}
	}
}