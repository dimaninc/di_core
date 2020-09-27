<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 06.01.16
 * Time: 10:40
 */

namespace diCore\Module;

use diCore\Base\CMS;
use diCore\Entity\User\Model;
use diCore\Tool\Auth;

class EnterNewPassword extends \diModule
{
	public function render()
	{
		$email = $this->getRoute(1);
		$key = $this->getRoute(2);

		/** @var Model $user */
		$user = !Auth::i()->authorized() && \diEmail::isValid($email) && Model::isActivationKeyValid($key)
			? \diModel::create(\diTypes::user, $email, 'slug')
			: \diModel::create(\diTypes::user);

		if (
		    $user->exists() &&
            $user->getActivationKey() == $key &&
            $user->active()
        ) {
			if ($this->useTwig()) {
				$this->getTwig()->renderPage('enter_new_password/form');
			} else {
				$this->getTpl()
					->define('enter_new_password', [
						'page',
					]);
			}

			$password = \diRequest::post('password', '');
			$password2 = \diRequest::post('password2', '');

			if ($password && $password2 && $password == $password2) {
				$user
                    ->setValidationNeeded(false)
					->setPasswordExt($password)
					->setActivationKey(Model::generateActivationKey())
					->save();

				Auth::i()->forceAuthorize($user, true);

				$this->redirectToDone();
			}
		} elseif ($this->getRoute(1) == 'done') {
			if ($this->useTwig()) {
				$this->getTwig()->renderPage('enter_new_password/done');
			} else {
				$this->getTpl()
					->define('enter_new_password/done', [
						'page',
					]);
			}
		} else {
			if ($this->useTwig()) {
				$this->getTwig()->renderPage('enter_new_password/error');
			} else {
				$this->getTpl()
					->define('enter_new_password/error', [
						'page',
					]);
			}
		}
	}

	public function redirectToDone()
	{
		header('Location: ' . $this->getZ()->getLanguageHrefPrefix() . '/' .
			CMS::ct('enter_new_password') . '/done/');
	}

	protected function useTwig()
	{
		return $this->getTwig()->exists('enter_new_password/form');
	}
}