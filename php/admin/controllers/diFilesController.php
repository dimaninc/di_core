<?php

use diCore\Helper\StringHelper;

class diFilesController extends diBaseAdminController
{
	public function rebuildDynamicPicsAction()
	{
		$module = $this->param(0);
		$field = $this->param(1);
		$id = $this->param(2);

		$redirect = \diRequest::get('redirect', 0);

		$ar = \diAdminSubmit::rebuildDynamicPics($module, $field, $id);

		if ($redirect)
		{
			$this->redirect();
		}
		else
		{
			/*
			$this->defaultResponse(array(
				"ok" => 1,
				"files" => $ar,
			));
			*/
			echo join("<br>", $ar);
		}
	}

	public function delAction()
	{
		$ok = false;
		$table = StringHelper::in($this->param(0));
		$id  = (int)$this->param(1);
		$field = StringHelper::in($this->param(2));

		$redirect = \diRequest::get('redirect', 1);

		$model = \diModel::createForTableNoStrict($table, $id, "id");

		if ($model->exists() && $model->has($field))
		{
			$model
				->killRelatedFiles($field)
				->resetFieldsOfRelatedFiles($field)
				->save();

			$ok = true;
		}

		if ($redirect)
		{
			$this->redirect();

			return null;
		}

		return [
			'ok' => $ok,
		];
	}

	public function delDynamicAction()
	{
		$ok = false;
		$table = StringHelper::in($this->param(0)); // todo: make a check if the model belongs to $table#$id
		$id  = (int)$this->param(1);
		$subTable = StringHelper::in($this->param(2));
		$field = StringHelper::in($this->param(3));
		$subId  = (int)$this->param(4);

		$redirect = \diRequest::get('redirect', 1);

		$model = \diModel::createForTableNoStrict($subTable, $subId, "id");

		if ($model->exists() && $model->has($field))
		{
			$model
				->killRelatedFiles($field)
				->resetFieldsOfRelatedFiles($field)
				->save();

			$ok = true;
		}

		if ($redirect)
		{
			$this->redirect();
			
			return null;
		}

		return [
			'ok' => $ok,
			'table' => $table,
			'id' => $id,
			'subTable' => $subTable,
			'field' => $field,
			'subId' => $subId,
		];
	}
}