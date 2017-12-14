<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 13.10.15
 * Time: 10:23
 */

use diCore\Helper\ArrayHelper;
use diCore\Admin\BasePage;

class diAdminGrid
{
	/** @var BasePage */
	private $AdminPage;

	/** @var array */
	private $buttonsAr;

	/** @var diModel */
	private $curModel;

	/** @var array */
	private $options;

	public function __construct(BasePage $AdminPage, $options = [])
	{
		$this->AdminPage = $AdminPage;

		$this->options = extend([
		], $options);
	}

	protected function getAdminPage()
	{
		return $this->AdminPage;
	}

	protected function getLanguage()
	{
		return $this->getAdminPage()->getAdmin()->getLanguage();
	}

	public function addButtons($ar)
	{
		$this->buttonsAr = extend($this->buttonsAr, $ar);

		return $this;
	}

	public function buttonExists($name)
	{
		return isset($this->buttonsAr[$name]);
	}

	/**
	 * @param string|array $names
	 * @param string|array $attr
	 * @param mixed|null $value
	 * @return $this
	 */
	public function setButtonAttr($names, $attr, $value = null)
	{
		if (!is_array($names))
		{
			$names = [$names];
		}

		foreach ($names as $name)
		{
			if (!$this->buttonExists($name))
			{
				$this->buttonsAr[$name] = [];
			}

			if (!is_array($this->buttonsAr[$name]))
			{
				$this->buttonsAr[$name] = [
					"title" => $this->buttonsAr[$name],
				];
			}

			if (!is_array($attr))
			{
				$attr = [
					$attr => $value,
				];
			}

			$this->buttonsAr[$name] = extend($this->buttonsAr[$name], $attr);
		}

		return $this;
	}

	public function removeButton($names)
	{
		if (!is_array($names))
		{
			$names = [$names];
		}

		foreach ($names as $name)
		{
			if (isset($this->buttonsAr[$name]))
			{
				unset($this->buttonsAr[$name]);
			}
		}

		return $this;
	}

	public function replaceButton($name, array $newButtons)
	{
		$this
			->insertButtonsBefore($name, $newButtons)
			->removeButton($name);

		return $this;
	}

	public function insertButtonsBefore($name, array $newButtons)
	{
		$this->buttonsAr = ArrayHelper::addItemsToAssocArrayBeforeKey($this->buttonsAr, $name, $newButtons);

		return $this;
	}

	public function insertButtonsAfter($name, array $newButtons)
	{
		$this->buttonsAr = ArrayHelper::addItemsToAssocArrayAfterKey($this->buttonsAr, $name, $newButtons);

		return $this;
	}

	public function getTable()
	{
		return $this->getAdminPage()->getTable();
	}

	protected function getTpl()
	{
		return $this->getAdminPage()->getTpl();
	}

	protected function setCurModel(diModel $model)
	{
		$this->curModel = $model;

		return $this;
	}

	public function getCurModel()
	{
		return $this->curModel;
	}

	public function printElement(diModel $model)
	{
		$this->setCurModel($model);

		$buttons = array_intersect_key($this->buttonsAr, diNiceTableButtons::$titles[$this->getLanguage()]);
		$htmlButtons = [];

		// edit href always needed
		$editHref = diAdminBase::getPageUri($this->getTable(), "form", [
			"id" => $model->getId(),
		]);

		$this->getTpl()
			->assign([
				"EDIT_HREF" => $editHref,
			]);
		//

		foreach ($buttons as $action => $settings)
		{
			$settings = extend([
				"allowed" => null,
			], $settings);

			$options = [];

			switch ($action)
			{
				case "edit":
					$options["href"] = $editHref;
					break;

				default:
					$options["state"] = $model->get($action);
					break;
			}

			$html = !is_callable($settings["allowed"]) || $settings["allowed"]($model, $action)
				? diNiceTableButtons::getButton($action, $options)
				: null;

			if ($html)
			{
				$htmlButtons[strtoupper($action) . "_BUTTON"] = $html;
			}
		}

		$this->getTpl()
			->assign($model->getTemplateVarsExtended(), "R_")
			->assign($htmlButtons)
			->assign([
				"IMG_URL_PREFIX" => $this->getAdminPage()->getImgUrlPrefix($model),
				"BUTTONS" => join(" ", $htmlButtons),
			])
			->parse("GRID_ROWS", ".grid_row");

		return $this;
	}
}