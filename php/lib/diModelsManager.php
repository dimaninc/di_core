<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 02.07.2015
 * Time: 14:38
 */

use diCore\Helper\StringHelper;
use diCore\Helper\FileSystemHelper;
use diCore\Data\Config;

class diModelsManager
{
	const fileChmod = 0664;

	const defaultModelFolder = "_cfg/models";
	const defaultCollectionFolder = "_cfg/collections";

	protected $fieldsByTable = [];

	private $namespace;

	public static $skippedInAnnotationFields = [
		"id",
		"clean_title",
		"slug",
	];

	public static $skippedInCollectionAnnotationFields = [];

	/** @var diDB */
	private $db;

	public function __construct()
	{
		global $db;

		$this->db = $db;
	}

	protected function getModelTemplate()
	{
		return <<<'EOF'
<?php
/**
 * Created by \diModelsManager
 * Date: %1$s
 * Time: %2$s
 */
%12$s
/**
 * Class %3$s
 * Methods list for IDE
 *
%5$s
 *
%6$s
 *
%7$s%8$s
 */
class %3$s extends \diModel
{
	const type = \diTypes::%11$s;
	protected $table = '%4$s';%10$s%9$s
}
EOF;
	}

	protected function getCollectionTemplate()
	{
		return <<<'EOF'
<?php
/**
 * Created by \diModelsManager
 * Date: %1$s
 * Time: %2$s
 */
%9$s
/**
 * Class %3$s
 * Methods list for IDE
 *
%6$s
 *
%7$s
 *
%8$s%10$s
 */
class %3$s extends \diCollection
{
	const type = \diTypes::%5$s;
	protected $table = '%4$s';
	protected $modelType = '%5$s';
}
EOF;
	}

	public function createModel($table, $needed, $className, $collectionNeeded = false, $collectionClassName = "",
		$namespace = '')
	{
		$this->setNamespace($namespace);

		if ($needed)
		{
			$typeName = self::getModelNameByTable($table);
			$className = $className ?: self::getModelClassNameByTable($table, $this->getNamespace());
			$annotations = $this->getModelMethodsAnnotations($this->getFieldsOfTable($table), $className);

			$slugFieldName = $this->doesTableHaveField($table, 'slug')
				? "\n\tprotected \$slugFieldName = self::SLUG_FIELD_NAME;"
				: '';

			$contents = sprintf($this->getModelTemplate(),
				date('d.m.Y'),
				date('H:i'),
				basename($className),
				$table,
				join("\n", $annotations["get"]),
				join("\n", $annotations["has"]),
				join("\n", $annotations["set"]),
				$annotations["localized"] ? "\n *\n" . join("\n", $annotations["localized"]) : "",
				$annotations["localized"] ? "\n\tprotected \$localizedFields = [" .
					join(", ", array_map(function ($val) {
						return "'" . $val . "'";
					}, array_keys($annotations["localized"]))) .
					"];" : '',
				$slugFieldName,
				$typeName,
				$this->getNamespace() ? "\nnamespace " . dirname($className) . ";\n" : ''
			);

			$fn = $this->getModelFilename($className);

			if (is_file(Config::getSourcesFolder() . $fn))
			{
				throw new \Exception("Model $fn already exists");
			}

			FileSystemHelper::createTree(Config::getSourcesFolder(), dirname($fn));

			file_put_contents(Config::getSourcesFolder() . $fn, $contents);
			chmod(Config::getSourcesFolder() . $fn, self::fileChmod);
		}

		if ($collectionNeeded)
		{
			$typeName = self::getModelNameByTable($table);
			$collectionClassName = $collectionClassName ?: self::getCollectionClassNameByTable($table, $this->getNamespace());
			$collectionAnnotations = $this->getCollectionMethodsAnnotations($this->getFieldsOfTable($table), $collectionClassName);

			$contents = sprintf($this->getCollectionTemplate(),
				date('d.m.Y'),
				date('H:i'),
				basename($collectionClassName),
				$table,
				$typeName,
				join("\n", $collectionAnnotations["filterBy"]),
				join("\n", $collectionAnnotations["orderBy"]),
				join("\n", $collectionAnnotations["select"]),
				$this->getNamespace() ? "\nnamespace " . dirname($collectionClassName) . ";\n" : '',
				$collectionAnnotations["filterByLocalized"]
					? "\n *\n" . join("\n", $collectionAnnotations["filterByLocalized"]) .
					  "\n *\n" . join("\n", $collectionAnnotations["orderByLocalized"]) .
					  "\n *\n" . join("\n", $collectionAnnotations["selectLocalized"])
					: ""
			);

			$fn = $this->getCollectionFilename($collectionClassName);

			if (is_file(Config::getSourcesFolder() . $fn))
			{
				throw new Exception("Collection $fn already exists");
			}

			FileSystemHelper::createTree(Config::getSourcesFolder(), dirname($fn));

			file_put_contents(Config::getSourcesFolder() . $fn, $contents);
			chmod(Config::getSourcesFolder() . $fn, self::fileChmod);
		}
	}

	public static function getModelNameByTable($table)
	{
		if (in_array($table, ["news"]))
		{
			return $table;
		}

		if (substr($table, -3) == 'ies')
		{
			$table = substr($table, 0, -3) . 'y';
		}
		elseif (substr($table, -1) == "s" && substr($table, -2) != "ss")
		{
			$table = substr($table, 0, -1);
		}

		return $table;
	}

	public static function getModelClassNameByTable($table, $namespace = '')
	{
		return $namespace
			? $namespace . '\\Entity\\' . camelize(self::getModelNameByTable($table), false) . '\\Model'
			: camelize("di_" . self::getModelNameByTable($table) . "_model");
	}

	public static function getCollectionClassNameByTable($table, $namespace = '')
	{
		return $namespace
			? $namespace . '\\Entity\\' . camelize(self::getModelNameByTable($table), false) . '\\Collection'
			: camelize("di_" . self::getModelNameByTable($table) . "_collection");
	}

	protected function getModelMethodsAnnotations($fields, $className)
	{
		$ar = [
			"get" => [],
			"has" => [],
			"set" => [],
			"localized" => [],
		];
		$localizedNeeded = [];

		if ($this->getNamespace())
		{
			$className = basename($className);
		}

		foreach ($fields as $field => $type)
		{
			if (in_array($field, static::$skippedInAnnotationFields))
			{
				continue;
			}

			$ar["get"][] = " * @method " . self::tuneType($type) . "\t" . camelize("get_" . $field);
			$ar["has"][] = " * @method bool " . camelize("has_" . $field);
			$ar["set"][] = " * @method $className " . camelize("set_" . $field) . "(\$value)";

			// localization tests
			$fieldComponents = explode("_", $field);

			if ($fieldComponents && in_array($fieldComponents[0], diCurrentCMS::$possibleLanguages))
			{
				$f = substr($field, strlen($fieldComponents[0]) + 1);

				if (isset($fields[$f]))
				{
					$localizedNeeded[$f] = $type;
				}
			}
			//
		}

		foreach ($localizedNeeded as $field => $type)
		{
			$ar["localized"][$field] = " * @method " . self::tuneType($type) . "\t" . camelize("localized_" . $field);
		}

		return $ar;
	}

	protected function getCollectionMethodsAnnotations($fields, $className)
	{
		$ar = [
			"filterBy" => [],
			"filterByLocalized" => [],
			"orderBy" => [],
			"orderByLocalized" => [],
			"select" => [],
			"selectLocalized" => [],
		];
		$localizedNeeded = [];

		if ($this->getNamespace())
		{
			$className = basename($className);
		}

		foreach ($fields as $field => $type)
		{
			if (in_array($field, static::$skippedInCollectionAnnotationFields))
			{
				continue;
			}

			$ar["filterBy"][] = " * @method $className " . camelize("filter_by_" . $field) . "(\$value, \$operator = null)";
			$ar["orderBy"][] = " * @method $className " . camelize("order_by_" . $field) . "(\$direction = null)";
			$ar["select"][] = " * @method $className " . camelize("select_" . $field) . "()";

			// localization tests
			$fieldComponents = explode("_", $field);

			if ($fieldComponents && in_array($fieldComponents[0], diCurrentCMS::$possibleLanguages))
			{
				$f = substr($field, strlen($fieldComponents[0]) + 1);

				if (isset($fields[$f]))
				{
					$localizedNeeded[$f] = $type;
				}
			}
			//
		}

		foreach ($localizedNeeded as $field => $type)
		{
			$ar["filterByLocalized"][] = " * @method $className " . camelize("filter_by_localized_" . $field) . "(\$value, \$operator = null)";
			$ar["orderByLocalized"][] = " * @method $className " . camelize("order_by_localized_" . $field) . "(\$direction = null)";
			$ar["selectLocalized"][] = " * @method $className " . camelize("select_localized_" . $field) . "()";
		}

		return $ar;
	}

	public static function tuneType($type)
	{
		$type = preg_replace("/\(\d+\)(\sunsigned)?$/", '', strtolower($type));

		switch ($type)
		{
			case "tinyint":
			case "mediumint":
			case "int":
			case "bigint":
				return "integer";

			case "float":
			case "double":
				return "double";

			default:
				return "string";
		}
	}

	protected function getFieldsOfTable($table)
	{
		if (!isset($this->fieldsByTable[$table]))
		{
			$this->fieldsByTable[$table] = [];

			$rs = $this->getDb()->q("SHOW FIELDS FROM " . $table);
			while ($r = $this->getDb()->fetch($rs))
			{
				$this->fieldsByTable[$table][$r->Field] = $r->Type;
			}
		}

		return $this->fieldsByTable[$table];
	}

	protected function doesTableHaveField($table, $field)
	{
		$allFields = $this->getFieldsOfTable($table);

		return isset($allFields[$field]);
	}

	protected function getDb()
	{
		return $this->db;
	}

	protected function getModelFilename($className)
	{
		if ($this->getNamespace())
		{
			$className = basename(dirname($className));
		}

		return $this->getModelFolder() . $className . ($this->getNamespace()
			? '/Model'
			: '') . '.php';
	}

	protected function getCollectionFilename($className)
	{
		if ($this->getNamespace())
		{
			$className = basename(dirname($className));
		}

		return $this->getCollectionFolder() . $className . ($this->getNamespace()
			? '/Collection'
			: '') . '.php';
	}

	protected function getFolderForNamespace()
	{
		/*
		$pathPrefix = \diLib::isNamespaceRoot($this->getNamespace())
			? Config::getSourcesFolder()
			: '';
		*/

		return 'src/' . $this->getNamespace() . '/Entity/';
	}

	protected function getModelFolder()
	{
		return StringHelper::slash($this->getNamespace()
			? $this->getFolderForNamespace()
			: static::defaultModelFolder);
	}

	protected function getCollectionFolder()
	{
		return StringHelper::slash($this->getNamespace()
			? $this->getFolderForNamespace()
			: static::defaultCollectionFolder);
	}

	/**
	 * @return string
	 */
	public function getNamespace()
	{
		return $this->namespace;
	}

	/**
	 * @param mixed $namespace
	 */
	public function setNamespace($namespace)
	{
		$this->namespace = $namespace;

		return $this;
	}
}