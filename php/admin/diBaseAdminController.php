<?php

class diBaseAdminController extends diBaseController
{
	public function __construct($params = [])
	{
	    parent::__construct($params);

		$this
			->initAdmin()
			->adminRightsHardCheck();
	}

	protected function redirect()
	{
		$back = \diRequest::get('back', \diRequest::referrer('/_admin/'));

		header("Location: $back");
	}
}
