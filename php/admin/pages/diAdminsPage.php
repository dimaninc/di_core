<?php
class diAdminsPage extends diAdminBasePage
{
	protected $options = [
		"filters" => [
			"defaultSorter" => [
				"sortBy" => "login",
				"dir" => "ASC",
			],
		],
	];

	public static $baseLevelsAr = [
		'root' => [
			'ru' => 'Главный админ',
			'en' => 'Root admin',
		],
	];

	public static $levelsAr = [];

	public function __construct(\diCore\Admin\Base $X)
	{
		parent::__construct($X);

		static::$levelsAr = diAdminsPage::translateLevels(
			array_merge(static::$baseLevelsAr, static::$levelsAr),
			$this->getAdmin()->getLanguage()
		);
	}

	public static function translateLevels($levels = [], $language = null)
	{
		foreach ($levels as $name => &$title)
		{
			if (is_array($title))
			{
				$title = $title[$language];
			}
		}

		return $levels;
	}

	public static function getLevelsAr()
	{
		return static::$levelsAr;
	}

	public static function getLevelTitle($level)
	{
		return isset(static::$levelsAr[$level]) ? static::$levelsAr[$level] : $level;
	}

	protected function initTable()
	{
		$this->setTable("admins");
	}

	public function renderList()
	{
		$this->getList()->addColumns([
			"id" => "ID",
			"login" => [
				"attrs" => [
					"width" => "70%",
				],
			],
			"level" => [
				"value" => function(diAdminModel $model) {
					return $this->getLevelTitle($model->getLevel());
				},
				"attrs" => [
					"width" => "30%",
				],
				"bodyAttrs" => [
					"class" => "regular",
				],
			],
			"#edit" => "",
			"#del" => "",
			"#active" => "",
		]);
	}

	public function renderForm()
	{
		$this->getForm()
			->setSelectFromArrayInput("level", self::getLevelsAr());
	}

	public function submitForm()
	{
	}

	public function getFormFields()
	{
		return [
			"login" => [
				"type" => "string",
				"title" => $this->localized([
					'ru' => 'Логин',
					'en' => 'Login',
				]),
				"required" => true,
				"default" => "",
			],

			"password" => [
				"type" => "password",
				"title" => $this->localized([
					'ru' => "Пароль",
					'en' => 'Password',
				]),
				"default" => "",
			],

			"first_name" => [
				"type" => "string",
				"title" => $this->localized([
					'ru' => "Имя",
					'en' => 'First name',
				]),
				"default" => "",
			],

			"last_name" => [
				"type" => "string",
				"title" => $this->localized([
					'ru' => "Фамилия",
					'en' => 'Last name',
				]),
				"default" => "",
			],

			"email" => [
				"type" => "email",
				"title" => 'E-mail',
				"default" => "",
			],

			"phone" => [
				"type" => "tel",
				"title" => $this->localized([
					'ru' => "Телефон",
					'en' => 'Phone',
				]),
				"default" => "",
			],

			"level" => [
				"type" => "enum",
				"title" => $this->localized([
					'ru' => "Уровень доступа",
					'en' => 'Access level',
				]),
				"default" => current(array_keys(static::$levelsAr)),
				"values" => array_keys(static::$levelsAr),
			],

			"date" => [
				"type" => "datetime_str",
				"title" => $this->localized([
					'ru' => "Дата добавления",
					'en' => 'Date created',
				]),
				"default" => date("Y-m-d H:i:s"),
				"flags" => ["static"],
			],
		];
	}

	public function getLocalFields()
	{
		return [];
	}

	public function getModuleCaption()
	{
		return [
			'ru' => 'Админы',
			'en' => 'Admins',
		];
	}
}