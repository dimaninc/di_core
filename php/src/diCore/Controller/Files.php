<?php

namespace diCore\Controller;

use diCore\Helper\StringHelper;
use diCore\Admin\Submit;

class Files extends \diBaseAdminController
{
	public function rebuildDynamicPicsAction()
	{
		$module = $this->param(0);
		$field = $this->param(1);
		$id = $this->param(2);

		$redirect = \diRequest::get('redirect', 0);

		$ar = Submit::rebuildDynamicPics($module, $field, $id);

		if ($redirect)
		{
			$this->redirect();
		}
		else
		{
			/*
			$this->defaultResponse(array(
				'ok' => 1,
				'files' => $ar,
			));
			*/
			echo join('<br>', $ar);
		}
	}

	public function delAction()
	{
		$table = StringHelper::in($this->param(0));
		$id  = (int)$this->param(1);
		$field = StringHelper::in($this->param(2));

		$redirect = \diRequest::get('redirect', 1);

		$model = \diModel::createForTableNoStrict($table, $id, 'id');

		$ok = $this->delRelatedFiles($model, $field);

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
		$table = StringHelper::in($this->param(0)); // todo: make a check if the model belongs to $table#$id
		$id  = (int)$this->param(1);
		$subTable = StringHelper::in($this->param(2));
		$field = StringHelper::in($this->param(3));
		$subId  = (int)$this->param(4);

		$redirect = \diRequest::get('redirect', 1);

		$model = \diModel::createForTableNoStrict($subTable, $subId, 'id');

		$ok = $this->delRelatedFiles($model, $field);

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

	protected function delRelatedFiles(\diModel $model, $field = null)
	{
		if ($model->exists() && $model->has($field))
		{
			$model
				->killRelatedFiles($field)
				->resetFieldsOfRelatedFiles($field)
				->save();

			return true;
		}

		return false;
	}
}