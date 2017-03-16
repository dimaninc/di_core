<?php

/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 09.10.15
 * Time: 17:38
 */
class diContentFamily
{
	protected $table = "content";

	/** @var diContentModel */
	private $model;

	/** @var array */
	private $family = [];

	/** @var diCMS */
	private $Z;

	private static $childClassName = "diCustomContentFamily";

	/**
	 * @param diCMS $Z
	 */
	public function __construct(diCMS $Z)
	{
		$this->Z = $Z;

		$this->setModel($this->getEmptyModel());
	}

	/**
	 * @param diCMS $Z
	 * @return mixed
	 */
	public static function create(diCMS $Z)
	{
		$className = diLib::exists(self::$childClassName)
			? self::$childClassName
			: get_called_class();

		$o = new $className($Z);

		return $o;
	}

	/**
	 * @return diContentModel
	 */
	public function getModel()
	{
		return $this->model ?: diModel::create(diTypes::content);
	}

	/**
	 * @return diContentFamily
	 */
	public function setModel(diContentModel $model)
	{
		$this->model = $model;

		return $this;
	}

	/**
	 * @param int|null $level
	 * @return array|diContentModel
	 */
	public function get($level = null)
	{
		if ($level === null)
		{
			return $this->family;
		}

		return $this->getMemberByLevel($level);
	}

	/**
	 * @param $id
	 * @return diContentModel
	 */
	public function getMemberById($id)
	{
		/** @var diContentModel $content */
		foreach ($this->family as $content)
		{
			if ($content->getId() == $id)
			{
				return $content;
			}
		}

		return $this->getEmptyModel();
	}

	/**
	 * @param $level
	 * @return diContentModel
	 */
	public function getMemberByLevel($level)
	{
		if ($level < 0)
		{
			$level += count($this->family);
		}

		if (!isset($this->family[$level]))
		{
			return $this->getEmptyModel();
		}

		return $this->family[$level];
	}

	public function getMemberByNonEmptyField($field)
	{
		$pi = new diPropertyInheritance();

		foreach ($this->get() as $model)
		{
			$pi->push($model);
		}

		return $pi->getRec($field);
	}

	/**
	 * @return diContentModel
	 * @throws Exception
	 */
	private function getEmptyModel()
	{
		return diModel::create(diTypes::content);
	}

	/**
	 * @return diCurrentCMS
	 */
	protected function getZ()
	{
		return $this->Z;
	}

	/**
	 * @return $this|diContentFamily
	 */
	public function init()
	{
		$this
			->beforeRoutesCheck()
			->findModel();

		if (!$this->getModel()->exists())
		{
			return $this->error();
		}

		$this->family[$this->getModel()->getLevelNum()] = $this->getModel();

		$parent = $this->getModel()->getParent();
		while (isset($this->getZ()->tables[$this->table][$parent]))
		{
			/** @var diContentModel $m */
			$m = $this->getZ()->tables[$this->table][$parent];

			$this->family[$m->getLevelNum()] = $m;

			$parent = $m->getParent();
		}

		ksort($this->family);

		$this
			->findOtherModels();

		return $this;
	}

	protected function findModel()
	{
		/**
		 * @var int $id
		 * @var diContentModel $content
		 */
		foreach ($this->getZ()->tables[$this->table] as $id => $content)
		{
			if ($content->getSlug() == $this->getZ()->getRoute(0))
			{
				$this->setModel($content);

				break;
			}
		}

		return $this;
	}

	protected function findOtherModels()
	{
		return $this;
	}

	protected function beforeRoutesCheck()
	{
		return $this;
	}

	private function error()
	{
		$this->getZ()->errorNotFound();

		return $this;
	}
}