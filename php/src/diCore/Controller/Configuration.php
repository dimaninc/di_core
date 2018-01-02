<?php

namespace diCore\Controller;

class Configuration extends \diBaseAdminController
{
	public function storeAction()
	{
	    global $cfg;

		$cfg->store();

		$this->redirect();
	}

	public function delPicAction()
	{
	    global $cfg;

		$k = \diDB::_in($this->param(0));

		if ($k && \diConfiguration::get($k))
		{
			$fn = \diConfiguration::getFolder() . \diConfiguration::get($k);
			$full_fn = \diPaths::fileSystem() . $fn;

			if (is_file($full_fn))
			{
				unlink($full_fn);
			}

			$cfg->setToDB($k, "");
			$cfg->updateCache();
		}

		$this->redirect();
	}

	protected function redirect()
	{
		header("Location: " . \diCore\Admin\Base::getPageUri("configuration", "", ["saved" => 1]));
	}
}