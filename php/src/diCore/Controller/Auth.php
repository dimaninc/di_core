<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 27.12.2017
 * Time: 12:44
 */

namespace diCore\Controller;

use diCore\Tool\Auth as AuthTool;
use diCore\Data\Types;
use diCore\Entity\User\Model;

class Auth extends \diBaseController
{
	const BACK_KEY = 'oAuth2Back';

	public function loginAction()
	{
		$Auth = AuthTool::create(false);

		return [
			'ok' => $Auth->authorized(),
		];
	}

	public function logoutAction()
	{
		$Auth = AuthTool::create();
		$Auth->logout();

		$this->redirect();
	}

	public function oauth2Action()
	{
		$a = \diOAuth2::create($this->param(0));

		if ($this->param(1) == \diOAuth2::callbackParam)
		{
			$a->processReturn();

			$this->redirectTo(\diSession::getAndKill(self::BACK_KEY) ?: '/');
		}
		elseif ($this->param(1) == \diOAuth2::unlinkParam)
		{
			return [
				'ok' => $a->unlink(),
			];
		}
		else
		{
			\diSession::set(self::BACK_KEY, \diRequest::get('back') ?: \diRequest::referrer());

			$a->redirectToLogin();
		}

		return null;
	}

	public function killAction()
	{
		$ar = [
			'ok' => false,
			'errors' => [],
		];

		try {
			if (!AuthTool::i()->authorized())
			{
				throw new \Exception('Для удаления аккаунта необходимо войти в систему');
			}

			AuthTool::i()->getUserModel()
				->hardDestroy();

			$ar['ok'] = true;
		} catch (\diValidationException $e) {
			$ar['errors'] = $e->getErrors();
		}

		return $ar;
	}

	public function activateAction()
	{
	}

	public function signUpAction()
	{
		$ar = [
			'ok' => false,
			'errors' => [],
		];

		/** @var Model $user */
		$user = \diModel::create(Types::user);

		try {
			$user->fastSignUp([
				'twig' => $this->getTwig(),
			]);

			$ar['ok'] = true;

			AuthTool::i()->forceAuthorize($user, true);
		} catch (\diValidationException $e) {
			$ar['errors'] = $e->getErrors();
		}

		/*
		if ($ar['ok'])
		{
			$this->redirectTo('/' . CMS::ct('registration') . '/thanks/');
			return null;
		}
		*/

		return $ar;
	}

	public function resetAction()
	{
		$ar = [
			'ok' => false,
		];

		try {
			if (AuthTool::i()->authorized())
			{
				throw new \Exception('Вы не можете сбросить пароль, т.к. вы авторизованы');
			}

			/** @var Model $user */
			$user = Model::create(Types::user, \diRequest::post('email', ''), 'slug');

			if (!$user->exists())
			{
				throw new \Exception('E-mail не найден');
			}

			if (!$user->active())
			{
				throw new \Exception('Аккаунт отключён, свяжитесь с администратором');
			}

			$user->notifyAboutResetPasswordByEmail($this->getTwig());

			$ar['ok'] = true;
			$ar['user'] = $user->get();
		} catch (\Exception $e) {
			$ar['message'] = $e->getMessage();
		}

		return $ar;
	}

	public function setPasswordAction()
	{
		$ar = [
			'ok' => false,
		];

		if (!AuthTool::i()->authorized())
		{
			$ar['message'] = 'Авторизуйтесь для смены пароля';
		}
		else
		{
			try {
				/** @var Model $user */
				$user = AuthTool::i()->getUserModel();
				$user->cabinetSubmitPassword();

				$ar['ok'] = true;
			} catch (\Exception $e) {
				$ar['message'] = $e->getMessage();
			}
		}

		return $ar;
	}
}