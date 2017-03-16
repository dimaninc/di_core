<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 06.01.16
 * Time: 10:40
 */

class diEnterNewPasswordModule extends diModule
{
	public function redirect()
	{
		header("Location: " . $this->getZ()->getLanguageHrefPrefix() . "/" . diCurrentCMS::ct("enter_new_password") . "/done/");
	}

	public function render()
	{
		$email = $this->getRoute(1);
		$key = $this->getRoute(2);

		/** @var diUserModel $user */
		$user = !diAuth::i()->authorized() && diEmail::isValid($email) && diUserModel::isActivationKeyValid($key)
			? diModel::create(diTypes::user, $email, "slug")
			: diModel::create(diTypes::user);

		if ($user->exists() && $user->getActivationKey() == $key && $user->active())
		{
			$this->getTpl()
				->define("enter_new_password", array(
					"page",
				));

			$password = diRequest::post("password", "");
			$password2 = diRequest::post("password2", "");

			if ($password && $password2 && $password == $password2)
			{
				$user
					->setPassword($password)
					->setActivationKey(diUserModel::generateActivationKey())
					->save();

				diAuth::i()->forceAuthorize($user, true);

				$this->redirect();
			}
		}
		elseif ($this->getRoute(1) == "done")
		{
			$this->getTpl()
				->define("enter_new_password/done", array(
					"page",
				));
		}
		else
		{
			$this->getTpl()
				->define("enter_new_password/error", array(
					"page",
				));
		}
	}
}