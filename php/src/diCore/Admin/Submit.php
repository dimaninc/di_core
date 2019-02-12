<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 08.06.2017
 * Time: 16:58
 */

namespace diCore\Admin;

use diCore\Data\Config;
use diCore\Data\Configuration;
use diCore\Data\Types;
use diCore\Database\Connection;
use diCore\Helper\ArrayHelper;
use diCore\Helper\FileSystemHelper;
use diCore\Helper\Slug;
use diCore\Helper\StringHelper;

class Submit
{
	const FILE_NAMING_RANDOM = 1;
	const FILE_NAMING_ORIGINAL = 2;

	const FILE_NAME_RANDOM_LENGTH = 10;
	const FILE_NAME_GLUE = '-';

	public static $defaultDynamicPicCallback = [self::class, 'storeDynamicPicCallback'];
	const dynamicPicsTable = 'dipics';

	public static $defaultSlugSourceFieldsAr = ['slug_source', 'menu_title', 'title'];
	public static $allowedDynamicPicsFieldsAr = [
		'id',
		'_table',
		'_field',
		'_id',
		'title',
		'content',
		'orig_fn',
		'pic',
		'pic_t',
		'pic_w',
		'pic_h',
		'pic_tn',
		'pic_tn_t',
		'pic_tn_w',
		'pic_tn_h',
		'pic_tn2_t',
		'pic_tn2_w',
		'pic_tn2_h',
		'date',
		'by_default',
		'visible',
		'order_num',
		'color_id',
	];

	const FILE_CHMOD = 0664;
	const DIR_CHMOD = 0775;

	const IMAGE_TYPE_MAIN = 0;
	const IMAGE_TYPE_PREVIEW = 1;
	const IMAGE_TYPE_PREVIEW2 = 2;
	const IMAGE_TYPE_PREVIEW3 = 3;
	const IMAGE_TYPE_ORIG = 10;
	const IMAGE_TYPE_BIG = 11;

	/** @var \diCore\Admin\BasePage */
	private $AdminPage;

	public $table;

	/** @deprecated */
	public $data;

	public $_form_fields;
	public $_local_fields;
	public $_all_fields;
	public $_ff;
	public $_lf;
	public $_af;

	public $page;
	public $redirect_href_ar;

	private $slugFieldName = 'clean_title';

    /** @var \diModel */
	private $model;

	public function __construct($table, $id = 0)
	{
		if (gettype($table) == 'object')
		{
			$this->AdminPage = $table;

			$this->table = $this->AdminPage->getTable();
			$id = $this->AdminPage->getId();

			$this->_form_fields = $this->AdminPage->getFormFieldsFiltered();
			$this->_local_fields = $this->AdminPage->getLocalFieldsFiltered();
			$this->_all_fields = $this->AdminPage->getAllFields();
			$this->_ff = $this->AdminPage->getFormFieldNames();
			$this->_lf = $this->AdminPage->getLocalFieldNames();
			$this->_af = $this->AdminPage->getAllFieldNames();
		}
		else //back compatibility
		{
			$this->table = $table;

			$this->_form_fields = isset($GLOBALS[$this->table . '_form_fields']) ? $GLOBALS[$this->table . '_form_fields'] : [];
			$this->_local_fields = isset($GLOBALS[$this->table . '_local_fields']) ? $GLOBALS[$this->table . '_local_fields'] : [];
			$this->_all_fields = isset($GLOBALS[$this->table . '_all_fields']) ? $GLOBALS[$this->table . '_all_fields'] : [];
			$this->_ff = isset($GLOBALS[$this->table . '_ff']) ? $GLOBALS[$this->table . '_ff'] : [];
			$this->_lf = isset($GLOBALS[$this->table . '_lf']) ? $GLOBALS[$this->table . '_lf'] : [];
			$this->_af = isset($GLOBALS[$this->table . '_af']) ? $GLOBALS[$this->table . '_af'] : [];
		}

        $this->model = \diModel::createForTableNoStrict($this->getTable(), $id, 'id');
		$this->model
            ->setFieldsOnSaveCallback(function($ar) {
                return ArrayHelper::filterByKey($ar, $this->_af);
            });

        $this->setSlugFieldName();

		$this->page = \diRequest::post('page', 0);

		$this->redirect_href_ar = [
			'path' => $this->table,
		];

		if ($this->page)
		{
			$this->redirect_href_ar['page'] = $this->page;
		}

		if (!empty($_POST['make_preview']))
		{
			foreach ($_POST['make_preview'] as $k => $_tmp)
			{
				if ($this->isFlag($k, 'preview'))
				{
					$this->redirect_href_ar['path'] = "{$this->table}_form";
					$this->redirect_href_ar['id'] = $this->getId();
					$this->redirect_href_ar["make_preview[$k]"] = 1;
				}
			}
		}
	}

	private function setSlugFieldName($field = null)
	{
		if ($field)
		{
			$this->slugFieldName = $field;

			return $this;
		}

		if ($this->_af && in_array('slug', $this->_af))
		{
			$this->slugFieldName = 'slug';
		}

		return $this;
	}

	public function getDb()
	{
		return Connection::get()->getDb();
	}

	public function getModel()
    {
        return $this->model;
    }

	/** @deprecated  */
	public function getCurRec($field = null)
	{
		return $this->getModel()->getOrigData($field);
	}

	public function wasFieldChanged($field)
	{
	    return $this->getModel()->changed($field);
	}

	/**
	 * @param $type integer
	 * @return string
	 * @throws \Exception
	 */
	public static function getPreviewSuffix($type)
	{
		switch ($type)
		{
			case self::IMAGE_TYPE_MAIN:
				return '';

			case self::IMAGE_TYPE_PREVIEW:
				return '_tn';

			case self::IMAGE_TYPE_PREVIEW2:
				return '_tn2';

			case self::IMAGE_TYPE_PREVIEW3:
				return '_tn3';

			case self::IMAGE_TYPE_ORIG:
				return '_orig';

			case self::IMAGE_TYPE_BIG:
				return '_big';

			default:
				throw new \Exception("Unknown type '$type'");
		}
	}

	public static function parseImageType($name)
	{
		if (isInteger($name))
		{
			return $name;
		}
		else
		{
			switch ($name)
			{
				case 'orig':
					return self::IMAGE_TYPE_ORIG;

				case 'big':
					return self::IMAGE_TYPE_BIG;

				case '':
				case 'main':
					return self::IMAGE_TYPE_MAIN;

				default:
					throw new \Exception('Unknown image type: ' . $name);
			}
		}
	}

	function redirect()
	{
		$params_ar = [];
		foreach ($this->redirect_href_ar as $k => $v)
		{
			$params_ar[] = "$k=$v";
		}

		$params = join('&', $params_ar);

		header("Location: ../index.php?$params");
	}

	function set_redirect_param($k, $v)
	{
		$this->redirect_href_ar[$k] = $v;

		return $this;
	}

	/** @deprecated */
	public function is_submit()
	{
		return $this->isSubmit();
	}

	public function isSubmit()
	{
		foreach ($this->_form_fields as $f => $v)
		{
			if (
				!isset($_POST[$f]) &&
				!isset($_POST[$f . Form::NEW_FIELD_SUFFIX]) &&
				!isset($_FILES[$f]) &&
				!in_array($v['type'], ['checkbox', 'checkboxes', 'dynamic', 'dynamic_pics', 'dynamic_files', 'separator']) &&
				!$this->isFlag($f, 'virtual')
			)
			{
				//echo $f;

				return false;
			}
		}

		return true;
	}

	/**
	 * @param $field string|array
	 * @param $callback callable
	 * @return $this
	 */
	public function processData($field, $callback)
	{
		if (!is_array($field))
		{
            $field = [$field];
        }

        foreach ($field as $f)
		{
			$this->setData($f, $callback($this->getData($f), $f));
		}

		return $this;
	}

	public function setData($field, $value = null)
	{
		$this->getModel()
			->set($field, $value);

		return $this;
	}

	public function getData($field = null)
	{
	    return $this->getModel()->get($field);
	}

	public function getId()
	{
		return $this->getModel()->getId();
	}

	public function getTable()
	{
		return $this->table;
	}

	/** @deprecated */
	function process_data()
	{
		return $this->gatherData();
	}

	public function gatherData()
	{
		foreach ($this->_form_fields as $f => $v)
		{
			if (!isset($v['default']))
			{
				$v['default'] = '';
			}

			switch ($v['type'])
			{
				case 'password':
					$this
						->setData($f, \diRequest::post($f, $v['default'], 'string'))
						->setData($f . '2', \diRequest::post($f . '2', $v['default'], 'string'));
					break;

				case 'date':
				case 'date_str':
					$this->make_datetime($f, true, false);
					break;

				case 'time':
				case 'time_str':
					$this->make_datetime($f, false, true);
					break;

				case 'datetime':
				case 'datetime_str':
					$this->make_datetime($f, true, true);
					break;

				case 'checkbox':
					$this->setData($f, \diRequest::post($f) ? 1 : 0);
					break;

				case 'separator':
					break;

				default:
				    $type = in_array($v['type'], ['float', 'double', 'int'])
                        ? $v['type']
                        : null;
					$this->setData($f, \diRequest::post($f, $v['default'], $type));
					break;
			}
		}

		// new fields
		foreach ($this->_ff as $f)
		{
			if (!empty($_POST[$f . Form::NEW_FIELD_SUFFIX]))
			{
				$this->setData($f, \diRequest::post($f . Form::NEW_FIELD_SUFFIX));
			}
		}

		// local fields
		foreach ($this->_local_fields as $f => $v)
		{
			if (!$this->getData($f))
			{
				$this->setData($f, $v['default']);
			}
		}

		// adjusting fields type
		foreach ($this->_all_fields as $f => $v)
		{
			if ($this->isFlag($f, 'virtual') || in_array($v['type'], ['separator']))
			{
				continue;
			}

			switch ($v['type'])
			{
				case 'order_num':
					$direction = isset($v['direction']) ? $v['direction'] : 1;
					$queryEnding = isset($v['queryEnding']) ? $v['queryEnding'] : '';
					$force = !empty($v['force']);

					if (is_callable($queryEnding))
					{
						$queryEnding = $queryEnding($this);
					}

					if (!$queryEnding)
					{
						$model = \diModel::createForTableNoStrict($this->getTable())->initFromRequest();

						$qAr = $model->getQueryArForMove();

						if ($qAr)
						{
							$queryEnding = 'WHERE ' . join(' AND ', $qAr);
						}
					}

					$this->make_order_num($direction, $queryEnding, $force);
					break;

				case 'int':
				case 'tinyint':
				case 'smallint':
				case 'integer':
				case 'date':
				case 'time':
				case 'datetime':
					$this->processData($f, function($v) {
						return intval($v);
					});
					break;

				case 'float':
					$this->processData($f, function($v) {
						return floatval(StringHelper::fixFloatDot($v));
					});
					break;

				case 'double':
					$this->processData($f, function($v) {
						return doubleval(StringHelper::fixFloatDot($v));
					});
					break;

				case 'pic':
				case 'file':
					if (!$this->getData($f))
					{
						$this->setData($f, '');
					}
					break;

				case 'password':
					if ($this->getData($f) && $this->getData($f) == $this->getData($f . '2'))
					{
						$this->processData($f, function($v) {
							return md5($v);
						});
					}
					else
					{
						$this->setData($f, $this->getModel()->getOrigData($f) ?: '');
					}
					break;

				case 'checkbox':
					$this->setData($f, $this->getData($f) ? 1 : 0);
					break;

				case 'ip':
					$this->processData($f, 'ip2bin');
					break;

				case 'enum':
					if (!in_array($this->getData($f), $v['values']))
					{
						$this->setData($f, $v['default']);
					}
					break;
			}
		}

		return $this;
	}

	/** @deprecated */
	public function store_data()
	{
		return $this->storeData();
	}

	public function storeData()
	{
		//$dbAr = [];
		$dynamicFields = [];
		$dynamicPicsFields = [];

		if ($this->getId())
		{
			foreach ($this->_all_fields as $f => $v)
			{
				if (
					(in_array($v['type'], ['pic', 'file']) && !$this->getData($f)) ||
					//(in_array($f, $this->_lf) && (!$this->data[$f] || $this->data[$f] == $v['default'])) ||
					in_array($v['type'], ['separator']) ||
					$this->isFlag($f, 'virtual') ||
					$this->isFlag($f, 'untouchable')
				) {
					// just ignore
				}
				elseif (
				    in_array($v['type'], ['date_str', 'time_str', 'datetime_str']) &&
                    !$this->getData($f)
                ) {
					//$dbAr["*$f"] = 'NULL';

					$this->getModel()
						->set($f, null);
				}
				else
				{
                    if (in_array($v['type'], ['dynamic_pics', 'dynamic_files'])) {
                        $dynamicPicsFields[] = $f;
                    } elseif ($v['type'] == 'dynamic') {
                        $dynamicFields[] = $f;
                    } else {
                        //$dbAr[$f] = StringHelper::in($this->getData($f));

                        $this->getModel()
                            ->set($f, $this->getData($f));
                    }
				}
			}

            $this->getModel()
                ->save();

            /*
            if ($this->getModel() instanceof MongoModel)
            {
                $this->getModel()
                    ->save();
            }
            else
            {
                if (!$this->getDb()->update($this->table, $dbAr, $this->id))
                {
                    $this->getDb()->dierror();
                }
            }
            */
		}
		else
		{
			foreach ($this->_all_fields as $f => $v)
			{
                if (
                    $this->isFlag($f, 'virtual') || $this->isFlag($f, 'untouchable') ||
                    in_array($v['type'], ['separator'])
                ) {
                    // just ignore
                } elseif (
                    in_array($v['type'], ['date_str', 'time_str', 'datetime_str']) &&
                    !$this->getData($f)
                ) {
                    //$dbAr["*$f"] = 'NULL';

                    $this->getModel()
                        ->set($f, null);
                } else {
                    if (in_array($v['type'], ['dynamic_pics', 'dynamic_files'])) {
                        $dynamicPicsFields[] = $f;
                    } elseif ($v['type'] == 'dynamic') {
                        $dynamicFields[] = $f;
                    }
                    /*
                    else
                    {
                        //$dbAr[$f] = StringHelper::in($this->getData($f));

                        $this->getModel()
                            ->set($f, $this->getData($f));
                    }
                    */
                }
            }

            $this->getModel()
                ->save();

            /*
            if ($this->getModel() instanceof MongoModel)
			{
				$this->getModel()
                    ->save();

				$this->id = $this->getModel()->getId();
			}
			else
			{
				$this->id = $this->getDb()->insert($this->table, $dbAr);
				if ($this->id === false)
				{
					$this->getDb()->dierror();
				}

				$this->getSubmittedModel()
					->setId($this->id);
			}
            */

			if ($this->AdminPage)
			{
				$this->AdminPage->setId($this->getId());
			}

			$this->set_redirect_param('id', $this->getId());
		}

		foreach ($dynamicPicsFields as $f)
		{
			$this->store_dynamic_pics($f);
		}

		foreach ($dynamicFields as $f)
		{
			$this->store_dynamic($f);
		}

		foreach ($this->_all_fields as $f => $v)
		{
			if ($v['type'] == 'tags')
			{
				$this->storeTags($f);
			}
		}

		return $this->getId();
	}

	function storeTags($field)
	{
		/** @var \diTags $class */
		$class = $this->getFieldOption($field, 'class') ?: 'diTags';

		$class::saveFromPost(\diTypes::getId($this->getTable()), $this->getId(), $field);

		return $this;
	}

	public function getOptionsFor($field)
	{
		return $this->getFieldOption($field);
	}

	public function getWatermarkOptionsFor($field, $type)
	{
		$opts = $this->getOptionsFor($field);

		if ($opts && isset($opts['watermarks']))
		{
			foreach ($opts['watermarks'] as $o)
			{
				if (isset($o['type']) && $o['type'] == $type)
				{
					return $o;
				}
			}
		}

		return [
			'name' => null,
			'x' => null,
			'y' => null,
		];
	}

	public function getFieldProperty($field, $property = null)
	{
		$o = isset($this->_all_fields[$field])
			? (array)$this->_all_fields[$field]
			: [];

		if (is_null($property))
		{
			return $o;
		}
		else
		{
			return isset($o[$property]) ? $o[$property] : null;
		}
	}

	public function getFieldOption($field, $option = null)
	{
		$o = isset($this->_all_fields[$field]['options'])
			? (array)$this->_all_fields[$field]['options']
			: [];

		if (is_null($option))
		{
			return $o;
		}
		else
		{
			return isset($o[$option]) ? $o[$option] : null;
		}
	}

	/** @deprecated */
	public function is_flag($field, $flag)
	{
		return $this->isFlag($field, $flag);
	}

	public function isFlag($field, $flag)
	{
		if (is_string($field) && isset($this->_all_fields[$field]['flags']))
		{
			$f_ar = $this->_all_fields[$field]['flags'];
		}
		elseif (is_array($field) && isset($field['flags']))
		{
			$f_ar = $field['flags'];
		}
		else
		{
			return false;
		}

		return is_array($f_ar)
            ? in_array($flag, $f_ar)
            : $f_ar == $flag;
	}

	public function getOriginForSlug()
	{
		return $this->getModel()->getSourceForSlug() ?: self::$defaultSlugSourceFieldsAr;
	}

	public function getSlugFieldName()
	{
		return $this->slugFieldName;
	}

	public function makeSlug($origin = null)
	{
		if (is_null($origin))
		{
			$origin = $this->getOriginForSlug();
		}

		if (is_array($origin))
		{
			foreach ($origin as $field)
			{
				if ($origin = $this->getData($field))
				{
					break;
				}
			}
		}

		$this->setData($this->slugFieldName, Slug::generate($origin, $this->getTable(), $this->getId(),
			'id', $this->slugFieldName
		));

		return $this;
	}

	/** @deprecated */
	function make_clean_title($origin = null)
	{
		return $this->makeSlug($origin);
	}

	// dir == -1/+1, shows - to increase or decrease new value's order num
	function make_order_num($dir, $q_ending = '', $force = false)
	{
		if (!$this->getId() || $force)
		{
			$init_value = $dir > 0 ? 1 : 65000;
			$sign = $dir > 0 ? 1 : -1;
			$min_max = $dir > 0 ? 'MAX' : 'MIN';

			$order_r = $this->getDb()->r($this->table, $q_ending, "$min_max(order_num) AS num,COUNT(id) AS cc");
			$this->setData('order_num', $order_r && $order_r->cc ? intval($order_r->num) + $sign : $init_value);
		}
		/*
		else
		{
			if ($this->getModel()->existsOrig())
			{
				$this->setData('order_num', $this->getModel()->getOrigData('order_num'));
			}
		}
		*/

		return $this;
	}

	public function makeOrderAndLevelNum()
	{
		if (!$this->getId())
		{
			$h = new \diHierarchyTable($this->getTable());

			$skipIdsAr = $this->getData('parent')
				? $h->getChildrenIdsAr($this->getData('parent'), [$this->getData('parent')])
				: [];

			$r = $this->getDb()->r($this->getTable(), $skipIdsAr ?: '', 'MAX(order_num) AS num');

			$this
				->setData('level_num', $h->getChildLevelNum($this->getData('parent')))
				->setData('order_num', (int)$r->num + 1);

			$this->getDb()->update($this->getTable(), [
				'*order_num' => 'order_num + 1',
			], "WHERE order_num >= '{$this->getData('order_num')}'");
		}
		else
		{
			$r = $this->getDb()->r($this->getTable(), $this->getId(), 'level_num,order_num');
			if ($r)
			{
				$this
					->setData('level_num', $r->level_num)
					->setData('order_num', $r->order_num);
			}
		}

		return $this;
	}

	static function get_datetime_from_ar($post, $date = true, $time = false, $format = 'int')
	{
		$ar = getdate();

		if ($date)
		{
			if (isset($post['dd'])) $ar['mday'] = (int)$post['dd'];
			if (isset($post['dm'])) $ar['mon'] = (int)$post['dm'];
			if (isset($post['dy'])) $ar['year'] = (int)$post['dy'];
		}

		if ($time)
		{
			if (isset($post['th'])) $ar['hours'] = (int)$post['th'];
			if (isset($post['tm'])) $ar['minutes'] = (int)$post['tm'];
			if (isset($post['ts'])) $ar['seconds'] = (int)$post['ts'];
		}

		$ar['seconds'] = 0;
		$value = null;

		if (
			($date && $ar['mday'] && $ar['mon'] && $ar['year']) ||
			($time && $post['th'] !== '' && $post['tm'] !== '')
		   )
		{
			$value = mktime($ar['hours'], $ar['minutes'], $ar['seconds'], $ar['mon'], $ar['mday'], $ar['year']);
		}

		/*
		$value = !$date || ()
			? mktime($ar['hours'], $ar['minutes'], $ar['seconds'], $ar['mon'], $ar['mday'], $ar['year'])
			: 0;
		*/

		if ($format == 'str')
		{
			$tpl = [];

			if ($date)
			{
				$tpl[] = 'Y-m-d';
			}

			if ($time)
			{
				$tpl[] = 'H:i:s';
			}

			$value = $value
				? date(join(' ', $tpl), $value)
				: '';
		}

		return $value;
	}

	function make_datetime($field, $date = true, $time = false)
	{
		if ($this->isFlag($field, 'static') || $this->isFlag($field, 'hidden'))
		{
			if (substr($this->_all_fields[$field]['type'], -4) == '_str')
			{
				$this->setData($field, \diRequest::post($field, ''));
			}
			else
			{
				$this->setData($field, \diRequest::post($field, 0));
			}
		}
		else
		{
			$this->setData($field, $this->get_datetime_from_ar(
				isset($_POST[$field]) ? $_POST[$field] : [],
				$date,
				$time,
				substr($this->_all_fields[$field]['type'], -4) == '_str' ? 'str' : 'int'
			));
		}

		return $this;
	}

	public static function getFolderByImageType($type)
	{
		global $big_folder, $orig_folder, $tn_folder, $tn2_folder, $tn3_folder;

		switch ($type)
		{
			case self::IMAGE_TYPE_MAIN:
				return '';

			case self::IMAGE_TYPE_BIG:
				return $big_folder;

			case self::IMAGE_TYPE_PREVIEW:
			case self::IMAGE_TYPE_PREVIEW2:
			case self::IMAGE_TYPE_PREVIEW3:
				return ${'tn' . ($type != self::IMAGE_TYPE_PREVIEW ? $type : '') . '_folder'};

			case self::IMAGE_TYPE_ORIG:
				return $orig_folder;
		}

		throw new \Exception("No folder for image type '$type' defined");
	}

	/**
	 * @param $field
	 * @param callable|string|array|null $callbackOrFolder
	 * @return Submit
	 * @throws \Exception
	 */
	public function storeFile($field, $callbackOrFolder = null)
	{
		return $this->storeImage($field, $callbackOrFolder);
	}

	public function storeImage($field, $filesOptions = [])
	{
		// back compatibility
		if (is_callable($filesOptions) || !$filesOptions || (is_string($filesOptions) && $filesOptions))
		{
			return $filesOptions
				? $this->store_pics($field, $filesOptions)
				: $this->store_pics($field);
		}
		//

		$callback = [static::class, 'storeImageCallback'];

		// preparing options
		foreach ($filesOptions as &$opts)
		{
			$opts = extend([
				'type' => self::IMAGE_TYPE_MAIN,
				'folder' => $this->getModel()->getPicsFolder()
                    ?: get_pics_folder($this->getTable(), Config::getUserAssetsFolder()),
				'subfolder' => null,
				'resize' => null,
				'quality' => null,
				'afterSave' => null,
				'watermark' => [
					'name' => null,
					'x' => null,
					'y' => null,
				],
			], $opts);

			if ($opts['type'] != self::IMAGE_TYPE_MAIN && is_null($opts['subfolder']))
			{
				$opts['subfolder'] = self::getFolderByImageType($opts['type']);
			}

			FileSystemHelper::createTree(\diPaths::fileSystem($this->getModel(), true, $field),
				$opts['folder'] . $opts['subfolder'], self::DIR_CHMOD);
		}
		//

		if (!is_array($field))
		{
			$field = explode(',', $field);
		}

		if (empty($filesOptions[0]['folder']))
		{
			throw new \Exception("You should define non-empty 'folder'");
		}

		$baseFolder = $filesOptions[0]['folder'];

		foreach ($field as $f)
		{
			//$this->setData($f, $this->getCurRec($f));

			if (!empty($_FILES[$f]) && empty($_FILES[$f]['error']))
			{
				$oldExt = strtolower(StringHelper::fileExtension($this->getData($f)));
				$newExt = strtolower(StringHelper::fileExtension($_FILES[$f]['name']));

				if (!$this->getData($f))
				{
					$this->generateFilename($f, $baseFolder, $_FILES[$f]['name']);
				}
				elseif ($oldExt != $newExt)
				{
					$this->setData($f, StringHelper::replaceFileExtension($this->getData($f), $newExt));
				}

				if (is_callable($callback))
				{
					foreach ($filesOptions as &$opts)
					{
						$suffix = self::getPreviewSuffix($opts['type']);

						$widthParam = Configuration::exists([
							$this->getTable() . '_' . $f . $suffix . '_width',
							$this->getTable() . $suffix . '_width',
							$this->getTable() . '_width',
						]);
						$heightParam = Configuration::exists([
							$this->getTable() . '_' . $f . $suffix . '_height',
							$this->getTable() . $suffix . '_height',
							$this->getTable() . '_height',
						]);

						$opts = extend([
							'width' => Configuration::safeGet($widthParam),
							'height' => Configuration::safeGet($heightParam),
						], $opts);
					}

					$callback($this, $f, $filesOptions, $_FILES[$f]);
				}
				else
				{
					throw new \Exception('Callback is now callable: ' . print_r($callback, true));
				}
			}
		}

		return $this;
	}

	public static function getGeneratedFilename($folder, $origFilename, $naming)
	{
		$baseName = transliterate_rus_to_eng(StringHelper::fileBaseName($origFilename)) ?: get_unique_id(self::FILE_NAME_RANDOM_LENGTH);
		$endingIdx = 0;
		$extension = '.' . strtolower(StringHelper::fileExtension($origFilename));

		do {
			switch ($naming)
			{
				default:
				case self::FILE_NAMING_RANDOM:
					$filename = get_unique_id(self::FILE_NAME_RANDOM_LENGTH);
					break;

				case self::FILE_NAMING_ORIGINAL:
					$filename = $baseName;

					if ($endingIdx)
					{
						$filename .= self::FILE_NAME_GLUE . $endingIdx;
					}

					$endingIdx++;
					break;
			}
		} while (is_file($folder . $filename . $extension));

		return $filename . $extension;
	}

	protected function generateFilename($field, $folder, $origFilename)
	{
		$this->setData($field, self::getGeneratedFilename(
			\diPaths::fileSystem($this->getModel(), true, $field) . $folder,
			$origFilename,
			$this->getFieldProperty($field, 'naming')
		));

		return $this;
	}

	// $callback is a function($_FILES[$f], $field, $pics_folder, $fn, &$this)
	public function store_pics($pic_fields, $callbackOrFolder = null)
	{
		$defaultCallback = [static::class, 'storeFileCallback'];

		$callback = is_callable($callbackOrFolder) ? $callbackOrFolder : $defaultCallback;
		$folder = is_callable($callbackOrFolder) || !$callbackOrFolder ? get_pics_folder($this->table) : $callbackOrFolder;

		$pic_fields_ar = is_array($pic_fields) ? $pic_fields : explode(',', $pic_fields);

		FileSystemHelper::createTree(\diPaths::fileSystem($this->getModel(), true, $pic_fields_ar[0]),
			$folder . get_tn_folder(),
			self::DIR_CHMOD);

		foreach ($pic_fields_ar as $field)
		{
			if (!$field)
			{
				continue;
			}

			//$this->setData($field, $this->getCurRec($field));

			if (isset($_FILES[$field]) && !$_FILES[$field]['error'])
			{
				$old_file_ext = $this->getData($field) ? strtolower(get_file_ext($this->getData($field))) : '';
				$new_file_ext = strtolower(get_file_ext($_FILES[$field]['name']));

				if (!$this->getData($field))
				{
					$this->generateFilename($field, $folder, $_FILES[$field]['name']);
				}
				elseif ($old_file_ext != $new_file_ext)
				{
					$this->setData($field, StringHelper::replaceFileExtension($this->getData($field), $new_file_ext));
				}

				// new arguments order for static method callback
				if (is_array($callback))
				{
					$callback($this, $field, [
						'folder' => $folder,
					], $_FILES[$field]);
				}
				else
				{
					$callback($_FILES[$field], $field, $folder, $this->getData($field), $this);
				}
			}
		}

		return $this;
	}

	public static function checkBase64Files($field, $id)
    {
        if (empty($_FILES[$field]['name'][$id]) && !empty($_POST['base64_' . $field][$id]))
        {
            $base64 = $_POST['base64_' . $field][$id];
            preg_match("#^data:(.+/[^;]+);base64,(.+)$#", $base64, $regs);
            $raw = $regs[2];

            $_FILES[$field]['name'][$id] = 'clipboard-image.png';
            $_FILES[$field]['type'][$id] = $regs[1];
            $_FILES[$field]['tmp_name'][$id] = tempnam(sys_get_temp_dir(), 'clipboard-image');
            $_FILES[$field]['error'][$id] = 0;

            if (!$_FILES[$field]['tmp_name'][$id])
            {
                throw new \Exception('Unable to create temporary file for clipboard image');
            }

            file_put_contents($_FILES[$field]['tmp_name'][$id], base64_decode($raw));

            $_FILES[$field]['size'][$id] = filesize($_FILES[$field]['tmp_name'][$id]);

            unset($_POST['base64_' . $field][$id]);
        }
    }

	private function store_dynamic_pics($field)
	{
		if (empty($_POST["{$field}_order_num"]))
		{
			return $this;
		}

		$ar = $_POST["{$field}_order_num"];
		$pics_folder = get_pics_folder($this->getTable());

		$root = \diPaths::fileSystem($this->getModel());

		$ids_ar = array();

		FileSystemHelper::createTree($root, [
			$pics_folder . get_tn_folder(),
			$pics_folder . get_tn_folder(2),
			$pics_folder . get_tn_folder(3),
		], self::DIR_CHMOD);

		$w = "_table='{$this->getTable()}' and _field='$field' and _id='{$this->getId()}'";

		foreach ($ar as $id => $order_num)
		{
			if (!(int)$id)
			{
				continue;
			}

			$test_r = $id > 0 ? $this->getDb()->r(self::dynamicPicsTable, "WHERE $w and id='$id'") : false;

			$db_ar = [
                'order_num' => (int)$order_num,
                'by_default' => isset($_POST[$field . '_by_default']) && $_POST[$field . '_by_default'] == $id ? 1 : 0,
                'visible' => !empty($_POST[$field . '_visible'][$id]) ? 1 : 0,
                'title' => isset($_POST[$field . '_title'][$id]) ? str_in($_POST[$field . '_title'][$id]) : '',
                'content' => isset($_POST[$field . '_content'][$id]) ? str_in($_POST[$field . '_content'][$id]) : '',
			];

            if (isset($_POST[$field . '_alt_title'][$id])) {
                $db_ar['alt_title'] = str_in($_POST[$field . '_alt_title'][$id]);
            }

            if (isset($_POST[$field . '_html_title'][$id])) {
                $db_ar['html_title'] = str_in($_POST[$field . '_html_title'][$id]);
            }

			// pic
			$f = 'pic';

            self::checkBase64Files("{$field}_{$f}", $id);

			if (isset($_FILES["{$field}_{$f}"]['name'][$id]) && !$_FILES["{$field}_{$f}"]['error'][$id])
			{
				$ext = '.' . strtolower(get_file_ext($_FILES["{$field}_{$f}"]['name'][$id]));

				if ($test_r && $test_r->$f)
				{
					$db_ar[$f] = replace_file_ext($test_r->$f, $ext);
				}
				else
				{
					$db_ar[$f] = self::getGeneratedFilename(
						\diPaths::fileSystem($this->getModel()) . $pics_folder,
						$_FILES["{$field}_{$f}"]['name'][$id],
						$this->getFieldProperty($field, 'naming')
					);
				}

				$db_ar['orig_fn'] = str_in($_FILES["{$field}_{$f}"]['name'][$id]);

				$callback = isset($this->_all_fields[$field]['callback']) ? $this->_all_fields[$field]['callback'] : self::$defaultDynamicPicCallback;

				$F = [
					'name' => $_FILES["{$field}_{$f}"]['name'][$id],
					'type' => $_FILES["{$field}_{$f}"]['type'][$id],
					'tmp_name' => $_FILES["{$field}_{$f}"]['tmp_name'][$id],
					'error' => $_FILES["{$field}_{$f}"]['error'][$id],
					'size' => $_FILES["{$field}_{$f}"]['size'][$id],
				];

				if (is_callable($callback))
				{
					$callback($F, $this, [
						'field' => $field,
						'what' => $f,
					], $db_ar, $pics_folder);
				}
			}
			//

			// pic tn
			$f = 'pic_tn';

            self::checkBase64Files("{$field}_{$f}", $id);

            if (isset($_FILES["{$field}_{$f}"]['name'][$id]) && !$_FILES["{$field}_{$f}"]['error'][$id])
			{
				if ($test_r && $test_r->$f)
				{
					$db_ar[$f] = $test_r->$f;
				}
				else
				{
					$db_ar[$f] = self::getGeneratedFilename(
						\diPaths::fileSystem($this->getModel()) . $pics_folder,
						$_FILES["{$field}_{$f}"]['name'][$id],
						$this->getFieldProperty($field, 'naming')
					);
				}

                $callback = isset($this->_all_fields[$field]['callback'])
                    ? $this->_all_fields[$field]['callback'] . '_tn'
                    : '';

				$F = [
					'name' => $_FILES["{$field}_{$f}"]['name'][$id],
					'type' => $_FILES["{$field}_{$f}"]['type'][$id],
					'tmp_name' => $_FILES["{$field}_{$f}"]['tmp_name'][$id],
					'error' => $_FILES["{$field}_{$f}"]['error'][$id],
					'size' => $_FILES["{$field}_{$f}"]['size'][$id],
				];

				if ($callback && is_callable($callback))
				{
					$callback($F, $this, [
						'field' => $field,
						'what' => $f,
					], $db_ar, $pics_folder);
				}
			}
			//

			$callback = isset($this->_all_fields[$field]['after_submit_callback'])
                ? $this->_all_fields[$field]['after_submit_callback']
                : '';

			if ($callback && is_callable($callback))
			{
				$callback($id, $field, $test_r, $db_ar, $this);
			}

			$db_ar = array_intersect_key($db_ar, array_flip(self::$allowedDynamicPicsFieldsAr));

			if ($test_r)
			{
				$this->getDb()->update(self::dynamicPicsTable, $db_ar, $test_r->id) or $this->getDb()->dierror();;

				$ids_ar[] = $test_r->id;
			}
			else
			{
				$db_ar['_table'] = $this->getTable();
				$db_ar['_field'] = $field;
				$db_ar['_id'] = $this->getId();

				$ids_ar[] = $this->getDb()->insert(self::dynamicPicsTable, $db_ar) or $this->getDb()->dierror();
			}
		}

		// it's killing time!
		$killCol = \diCore\Entity\DynamicPic\Collection::createByTarget($this->getTable(), $this->getId(), $field);
		$killCol
			->filterById($ids_ar, '!=')
			->hardDestroy();

		// making order num to look ok
		$order_num = 0;

		$orderCol = \diCore\Entity\DynamicPic\Collection::createByTarget($this->getTable(), $this->getId(), $field);
		$orderCol
			->orderByOrderNum()
			->orderById();

		/** @var \diCore\Entity\DynamicPic\Model $m */
		foreach ($orderCol as $m)
		{
			$m
				->setOrderNum(++$order_num)
				->save();
		}

		return $this;
	}

	public static function rebuildDynamicPics($module, $field = null, $id = null)
	{
		$className = \diCore\Admin\Base::getModuleClassName($module);
		$adminBaseClassName = \diLib::getChildClass(Base::class);

		/** @var Base $X */
		$X = new $adminBaseClassName(\diCore\Admin\Base::INIT_MODE_LITE);
		/** @var \diCore\Admin\BasePage $Page */
		$Page = new $className($X);
		$Page->tryToInitTable();
		$Submit = new self($Page);

		$callback = isset($Submit->_all_fields[$field]['callback'])
			? $Submit->_all_fields[$field]['callback']
			: self::$defaultDynamicPicCallback;

		$id = (int)$id;

		$ar = [];

		/** @var \diCore\Entity\DynamicPic\Collection $pics */
		$pics = \diCollection::create(Types::dynamic_pic);
		$pics
			->filterByTargetTable($Submit->getTable());

		if ($field)
		{
			$pics->filterByTargetField($field);
		}

		if ($id)
		{
			$pics->filterByTargetId($id);
		}

			/** @var \diCore\Entity\DynamicPic\Model $pic */
		foreach ($pics as $pic)
		{
			$fn = \diPaths::fileSystem() . get_pics_folder($Page->getTable()) .
				get_orig_folder() . $pic->getPic();

			$ar[] = $fn;

			$F = [
				'name' => $pic->getOrigFn(),
				'type' => 'image/jpeg',
				'tmp_name' => $fn,
				'error' => 0,
				'size' => filesize($fn),
			];

			$db_ar = [
				'pic' => $pic->getPic(),
			];

			if (is_callable($callback))
			{
				$callback($F, $Submit, [
					'field' => $pic->getTargetField(),
					'what' => 'pic',
				], $db_ar, get_pics_folder($Page->getTable()));
			}

			$pic
				->set($db_ar)
				->save();
		}

		return $ar;
	}

	function store_dynamic($field)
	{
		$dr = new \diDynamicRows($this->AdminPage, $field);

		$dr->submit();

		return $this;
	}

	/**
	 * @param $obj Submit
	 * @param $field string
	 * @param $options array
	 * @param $F array
	 */
	public static function storeImageCallback(&$obj, $field, $options, $F)
	{
		$needToUnlink = true;

		$I = new \diImage();
		$I->open($F['tmp_name']);

		foreach ($options as $opts)
		{
			$suffix = Submit::getPreviewSuffix($opts['type']);

			$fn = \diPaths::fileSystem($obj->getModel(), true, $field) .
				$opts['folder'] . $opts['subfolder'] . $obj->getData($field);

			if (is_file($fn))
			{
				unlink($fn);
			}

			if (!$opts['resize'] && (move_uploaded_file($F['tmp_name'], $fn) || rename($F['tmp_name'], $fn)))
			{
				$needToUnlink = false;
			}
			else
			{
				if (!empty($opts['quality']))
				{
					$I->set_jpeg_quality($opts['quality']);
				}

				$I->make_thumb_or_copy(
					$opts['resize'],
					$fn,
					$opts['width'],
					$opts['height'],
					false,
					$opts['watermark']['name'], $opts['watermark']['x'], $opts['watermark']['y']
				);
			}

			chmod($fn, Submit::FILE_CHMOD);

			if (\diSwiffy::is($fn))
			{
				list($w, $h, $t) = \diSwiffy::getDimensions($fn);
			}
			else
			{
				list($w, $h, $t) = getimagesize($fn);
			}

			$obj->setData([
				$field . $suffix . '_w' => (int)$w,
				$field . $suffix . '_h' => (int)$h,
				$field . $suffix . '_t' => (int)$t,
			]);

			if (!empty($opts['afterSave']))
			{
				$afterSave = $opts['afterSave'];

				if (is_callable($afterSave))
				{
					$afterSave($field, $fn, $obj->getModel());
				}
			}
		}

		$I->close();
		unset($I);

		if ($needToUnlink)
		{
			unlink($F['tmp_name']);
		}
	}

	/**
	 * @param $obj Submit
	 * @param $field string
	 * @param $options array
	 * @param $F array
	 */
	public static function storeFileCallback(&$obj, $field, $options, $F)
	{
		$options = extend([
			'folder' => '',
			'subfolder' => '',
			'filename' => '',
		], $options);

		$fn = \diPaths::fileSystem($obj->getModel(), true, $field) .
			$options['folder'] . $options['subfolder'] . ($options['filename'] ?: $obj->getData($field));

		if (is_file($fn))
		{
			unlink($fn);
		}

		if (!(move_uploaded_file($F['tmp_name'], $fn) || rename($F['tmp_name'], $fn)))
		{
			dierror("Unable to copy file {$F['name']} to {$fn}");
		}

		chmod($fn, self::FILE_CHMOD);

		$info = getimagesize($fn);
		$obj
			->setData($field . '_w', $info[0])
			->setData($field . '_h', $info[1])
			->setData($field . '_t', $info[2]);
	}

	public static function storeDynamicPicCallback($F, $tableOrSubmit, $options, &$ar, $folder)
	{
		if (is_object($tableOrSubmit))
		{
			/** @var Submit $Submit */
			$Submit = $tableOrSubmit;
			$table = $Submit->getTable();
		}
		else
		{
			$Submit = null;
			$table = $tableOrSubmit;
		}

		if (is_array($options))
		{
			$options = extend([
				'field' => null,
				'what' => null,
				'group_field' => null,
			], $options);

			$field = $options['field'];
			$groupField = $options['group_field'];
			$what = $options['what'];
		}
		else
		{
			$what = $options;
			$field = null;
			$groupField = null;
			$options = [];
		}

		$options = extend([
			'fileOptions' => [],
		], $options);

		$getFileOptions = function($imageType) use($options) {
			$type = static::parseImageType($imageType);

			if ($options['fileOptions'])
			{
				foreach ($options['fileOptions'] as $o)
				{
					if (isset($o['type']) && $o['type'] == $type)
					{
						return $o;
					}
				}
			}

			return [];
		};

		$fn = $ar[$what];

		$root = \diPaths::fileSystem();
		$full_fn = $root . $folder . $fn;
		$big_fn = $root . $folder . get_big_folder() . $fn;
		$orig_fn = $root . $folder . get_orig_folder() . $fn;

		FileSystemHelper::createTree($root, [
			$folder . get_big_folder(),
			$folder . get_orig_folder(),
		], self::DIR_CHMOD);

		$mode = $F['tmp_name'] == $orig_fn ? 'rebuilding' : 'uploading';

		if ($mode == 'uploading')
		{
			if (is_file($full_fn)) unlink($full_fn);
			if (is_file($big_fn)) unlink($big_fn);
			if (is_file($orig_fn)) unlink($orig_fn);
		}

		list($tmp, $tmp, $imgType) = getimagesize($F['tmp_name']);

		if (\diImage::isImageType($imgType))
		{
			$I = new \diImage();
			$I->open($F['tmp_name']);

			// thumbnail photos

			for ($i = 1; $i < 10; $i++)
			{
				$suffix = $i > 1 ? "$i" : '';

				$widthParam = Configuration::exists([
					$table . '_' . $groupField . '_' . $field . '_tn' . $suffix . '_width',
					$table . '_' . $groupField . '_tn' . $suffix . '_width',
					$table . '_tn' . $suffix . '_width',
					$groupField . '_tn' . $suffix . '_width',
				]);
				$heightParam = Configuration::exists([
					$table . '_' . $groupField . '_' . $field . '_tn' . $suffix . '_height',
					$table . '_' . $groupField . '_tn' . $suffix . '_height',
					$table . '_tn' . $suffix . '_height',
					$groupField . '_tn' . $suffix . '_height',
				]);

				if ($widthParam || $heightParam)
				{
					$tn_fn = $root . $folder . get_tn_folder($i) . $fn;

					if ($mode == 'uploading')
					{
						if (is_file($tn_fn))
						{
							unlink($tn_fn);
						}
					}

					$fileOptionsTn = extend([
						'resize' => DI_THUMB_CROP, //| DI_THUMB_EXPAND_TO_SIZE
						'watermark' => [],
					], $getFileOptions($i));
					$tnWM = $Submit->getWatermarkOptionsFor($field, constant('self::IMAGE_TYPE_PREVIEW' . $suffix));

					if (!$tnWM['name'])
					{
						$tnWM = extend($tnWM, $fileOptionsTn['watermark']);
					}

					$I->make_thumb_or_copy($fileOptionsTn['resize'], $tn_fn,
						Configuration::safeGet($widthParam),
						Configuration::safeGet($heightParam),
						false,
						$tnWM['name'], $tnWM['x'], $tnWM['y']
					);

					chmod($tn_fn, self::FILE_CHMOD);
					list(
						$ar['pic_tn' . $suffix . '_w'],
						$ar['pic_tn' . $suffix . '_h'],
						$ar['pic_tn' . $suffix . '_t']
					) = getimagesize($tn_fn);
				}
			}

			// main photo

			$widthParam = Configuration::exists([
				$table . '_' . $groupField . '_' . $field . '_width',
				$table . '_' . $groupField . '_width',
				$table . '_width',
				$groupField . '_width',
			]);
			$heightParam = Configuration::exists([
				$table . '_' . $groupField . '_' . $field . '_height',
				$table . '_' . $groupField . '_height',
				$table . '_height',
				$groupField . '_height',
			]);

			$fileOptionsMain = extend([
				'resize' => DI_THUMB_FIT,
				'watermark' => [],
			], $getFileOptions(self::IMAGE_TYPE_MAIN));
			$mainWM = $Submit->getWatermarkOptionsFor($field, self::IMAGE_TYPE_MAIN);
			if (!$mainWM['name'])
			{
				$mainWM = extend($mainWM, $fileOptionsMain['watermark']);
			}
			$I->make_thumb_or_copy($fileOptionsMain['resize'], $full_fn,
				Configuration::safeGet($widthParam),
				Configuration::safeGet($heightParam),
				false,
				$mainWM['name'], $mainWM['x'], $mainWM['y']
			);

			// big photo

			$widthParam = Configuration::exists([
				$table . '_' . $groupField . '_' . $field . '_big_width',
				$table . '_' . $groupField . '_big_width',
				$table . '_big_width',
				$groupField . '_big_width',
			]);
			$heightParam = Configuration::exists([
				$table . '_' . $groupField . '_' . $field . '_big_height',
				$table . '_' . $groupField . '_big_height',
				$table . '_big_height',
				$groupField . '_big_height',
			]);

			$fileOptionsBig = extend([
				'resize' => DI_THUMB_FIT,
				'watermark' => [],
			], $getFileOptions(self::IMAGE_TYPE_BIG));
			$bigWM = $Submit->getWatermarkOptionsFor($field, self::IMAGE_TYPE_BIG);
			if (!$bigWM['name'])
			{
				$bigWM = extend($bigWM, $fileOptionsBig['watermark']);
			}
			$I->make_thumb_or_copy($fileOptionsBig['resize'], $big_fn,
				Configuration::safeGet($widthParam, 10000),
				Configuration::safeGet($heightParam, 10000),
				false,
				$bigWM['name'], $bigWM['x'], $bigWM['y']
			);
			$I->close();

			// orig photo

			if ($mode == 'uploading')
			{
				move_uploaded_file($F['tmp_name'], $orig_fn) || rename($F['tmp_name'], $orig_fn);
			}

			chmod($full_fn, self::FILE_CHMOD);
			chmod($big_fn, self::FILE_CHMOD);
			chmod($orig_fn, self::FILE_CHMOD);

			list($ar['pic_w'], $ar['pic_h'], $ar['pic_t']) = getimagesize($full_fn);
		}
		else
		{
			list($ar['pic_w'], $ar['pic_h'], $ar['pic_t']) = [0, 0, 0];

			if ($mode == 'uploading')
			{
				move_uploaded_file($F['tmp_name'], $full_fn) || rename($F['tmp_name'], $orig_fn);
			}
		}
	}
}

/* @deprecated */
function diasStoreImage(&$obj, $field, $options, $F)
{
	Submit::storeImageCallback($obj, $field, $options, $F);
}

/* @deprecated */
function dias_sharpen_img($img)
{
	return \diImage::sharpMask($img, 80, 0.5, 0);
}

/** @deprecated  */
function dias_save_file($F, $field, $pics_folder, $fn, Submit $obj)
{
	Submit::storeFileCallback($obj, $field, [
		'folder' => $pics_folder,
		'subfolder' => '',
		'filename' => $fn,
	], $F);
}

/** @deprecated */
function dias_save_dynamic_pic($F, $tableOrSubmit, $what, &$ar, $pics_folder)
{
	Submit::storeDynamicPicCallback($F, $tableOrSubmit, $what, $ar, $pics_folder);
}

/** @deprecated */
function diasSaveDynamicPic($F, $tableOrSubmit, $what, &$ar, $pics_folder)
{
	Submit::storeDynamicPicCallback($F, $tableOrSubmit, $what, $ar, $pics_folder);
}
