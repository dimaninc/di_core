<?php

namespace diCore\Controller;

use diCore\Admin\Base;
use diCore\Data\Configuration as Cfg;

class Configuration extends \diBaseAdminController
{
	public function storeAction()
	{
		Cfg::getInstance()->store();

		// $this->redirectBack();
        return 'oh yeah?';
	}

	public function delPicAction(){
		$k = $this->param(0);

		if ($k && Cfg::exists($k) && Cfg::get($k)) {
			$fn = Cfg::getFolder() . Cfg::get($k);
			$full_fn = \diPaths::fileSystem() . $fn;

			if (is_file($full_fn)) {
				unlink($full_fn);
			}

            Cfg::getInstance()
                ->setToDB($k, '')
			    ->updateCache();
		}

		$this->redirectBack();
	}

	protected function redirectBack()
	{
		return $this->redirectTo(Base::getPageUri("configuration", "", ["saved" => 1]));
	}
}
