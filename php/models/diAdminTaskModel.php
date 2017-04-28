<?php
/**
 * Created by diModelsManager
 * Date: 11.09.2015
 * Time: 11:29
 */
/**
 * Class diAdminTaskModel
 * Methods list for IDE
 *
 * @method string	getTitle
 * @method string	getContent
 * @method integer	getVisible
 * @method integer	getStatus
 * @method integer	getPriority
 * @method string	getDueDate
 * @method string	getDate
 * @method integer	getAdminId
 *
 * @method bool hasTitle
 * @method bool hasContent
 * @method bool hasVisible
 * @method bool hasStatus
 * @method bool hasPriority
 * @method bool hasDueDate
 * @method bool hasDate
 * @method bool hasAdminId
 *
 * @method diAdminTaskModel setTitle($value)
 * @method diAdminTaskModel setContent($value)
 * @method diAdminTaskModel setVisible($value)
 * @method diAdminTaskModel setStatus($value)
 * @method diAdminTaskModel setPriority($value)
 * @method diAdminTaskModel setDueDate($value)
 * @method diAdminTaskModel setDate($value)
 * @method diAdminTaskModel setAdminId($value)
 */
class diAdminTaskModel extends diModel
{
	const type = diTypes::admin_task;
	protected $table = "admin_tasks";

	// statuses
	const STATUS_PENDING = 10;
	const STATUS_IN_PROGRESS = 20;
	const STATUS_RESOLVED = 30;
	const STATUS_TESTED = 40;
	const STATUS_CLOSED = 50;

	const STATUS_REFINE_NEEDED = 80;
	const STATUS_PAUSED = 89;
	const STATUS_DELAYED = 90;
	const STATUS_CANCELLED = 100;

	public static $statusesActual = [
		self::STATUS_PENDING,
		self::STATUS_REFINE_NEEDED,
		self::STATUS_IN_PROGRESS,
		self::STATUS_RESOLVED,
		self::STATUS_TESTED,
		self::STATUS_PAUSED,
		self::STATUS_DELAYED,
	];

	public static $statuses = [
		self::STATUS_PENDING => "Не начата",
		self::STATUS_IN_PROGRESS => "Выполняется",
		self::STATUS_RESOLVED => "Ожидает тестирования",
		self::STATUS_TESTED => "Протестирована",
		self::STATUS_CLOSED => "Закрыта",

		self::STATUS_REFINE_NEEDED => "Требуется уточнение",
		self::STATUS_PAUSED => 'На паузе',
		self::STATUS_DELAYED => "Отложена",
		self::STATUS_CANCELLED => "Отменена",
	];
	//

	// priorities
	const PRIORITY_MINOR = 1;
	const PRIORITY_MAJOR = 10;
	const PRIORITY_CRITICAL = 20;
	const PRIORITY_BLOCKER = 30;

	public static $priorities = [
		self::PRIORITY_MINOR => "Минимальный",
		self::PRIORITY_MAJOR => "Средний",
		self::PRIORITY_CRITICAL => "Высокий",
		self::PRIORITY_BLOCKER => "Молния",
	];
	//

	public function getHref()
	{
		return $this->getAdminHref();
	}

	public function getCustomTemplateVars()
	{
		$contentHtml = nl2br(diStringHelper::out($this->getContent()));
		$contentHtmlWithLinks = nl2br(highlight_urls(diStringHelper::out($this->getContent())));

		return extend(parent::getCustomTemplateVars(), [
			'content_html' => $contentHtml,
			'content_html_with_links' => $contentHtmlWithLinks,

			'status_str' => $this->getStatusStr(),
			'priority_str' => $this->getPriorityStr(),
		]);
	}

	public function getStatusStr()
	{
		return static::statusStr($this->getStatus());
	}

	public function getPriorityStr()
	{
		return static::priorityStr($this->getPriority());
	}

	public static function statusesActual()
	{
		return static::$statusesActual;
	}

	public static function statusStr($status = null)
	{
		if ($status === null)
		{
			return static::$statuses;
		}

		return isset(static::$statuses[$status])
			? static::$statuses[$status]
			: null;
	}

	public static function priorityStr($priority = null)
	{
		if ($priority === null)
		{
			return static::$priorities;
		}

		return isset(static::$priorities[$priority])
			? static::$priorities[$priority]
			: null;
	}
}