<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 27.12.2017
 * Time: 12:44
 */

namespace diCore\Controller;

use diCore\Data\Types;
use diCore\Entity\User\Model;
use diCore\Tool\Auth as AuthTool;

class Auth extends \diBaseController
{
	const BACK_KEY = 'oAuth2Back';

	protected static $language = [
		'en' => [
			'enter_new_password.sign_out_first' => 'You are signed in, enter new password in cabinet',
			'enter_new_password.email_not_valid' => 'E-mail is not valid',
			'enter_new_password.key_not_valid' => 'Key is not valid',
			'enter_new_password.keys_not_match' => 'Keys do not match',
			'enter_new_password.password_not_valid' => 'Password is not valid (min length is 6 chars)',
			'enter_new_password.passwords_not_match' => 'Passwords do not match',
			'enter_new_password.user_not_exist' => 'User does not exist',
			'enter_new_password.user_not_active' => 'User is not active',
		],
		'ru' => [
			'enter_new_password.sign_out_first' => 'Вы авторизованы, задайте новый пароль в личном кабинете',
			'enter_new_password.email_not_valid' => 'Некорректный E-mail',
			'enter_new_password.key_not_valid' => 'Некорректный код',
			'enter_new_password.keys_not_match' => 'Коды не совпадают',
			'enter_new_password.password_not_valid' => 'Некорректный пароль (мин.длина - 6 символов)',
			'enter_new_password.passwords_not_match' => 'Пароли не совпадают',
			'enter_new_password.user_not_exist' => 'Пользователь не существует',
			'enter_new_password.user_not_active' => 'Пользователь не активен',
		],
	];

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

	protected function getEmptyUserUidErrorMessage()
	{
		return 'Введите E-mail';
	}

	protected function getUserUidForReset()
	{
		$email = \diRequest::post('email');

		if (!$email)
		{
			throw new \Exception($this->getEmptyUserUidErrorMessage());
		}

		return $email;
	}

	protected function getUserForReset()
	{
		return Model::create(Types::user, $this->getUserUidForReset(), 'slug');
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
			$user = $this->getUserForReset();

			if (!$user->exists())
			{
				throw new \Exception('Пользователь не найден');
			}

			if (!$user->active())
			{
				throw new \Exception('Аккаунт отключён, свяжитесь с администратором');
			}

			$user->notifyAboutResetPasswordByEmail($this->getTwig());

			$ar['ok'] = true;
		} catch (\Exception $e) {
			$ar['message'] = $e->getMessage();
		}

		return $ar;
	}

	public function enterNewPasswordAction()
	{
		$ar = [
			'ok' => false,
		];

		$email = \diRequest::post('email', '');
		$key = \diRequest::post('key', '');
		$password = \diRequest::post('password', '');
		$password2 = \diRequest::post('password2', '');

		try {
			if (AuthTool::i()->authorized())
			{
				throw new \Exception(self::L('enter_new_password.sign_out_first'));
			}

			if (!\diEmail::isValid($email))
			{
				throw new \Exception(self::L('enter_new_password.email_not_valid'));
			}

			if (!Model::isActivationKeyValid($key))
			{
				throw new \Exception(self::L('enter_new_password.key_not_valid'));
			}

			if (Model::isPasswordValid($password))
			{
				throw new \Exception(self::L('enter_new_password.password_not_valid'));
			}

			if ($password != $password2)
			{
				throw new \Exception(self::L('enter_new_password.passwords_not_match'));
			}

			/** @var \diCore\Entity\User\Model $user */
			$user = \diModel::create(\diTypes::user, $email, 'slug');

			if (!$user->exists())
			{
				throw new \Exception(self::L('enter_new_password.user_not_exist'));
			}

			if (!$user->active())
			{
				throw new \Exception(self::L('enter_new_password.user_not_active'));
			}

			if ($user->getActivationKey() != $key)
			{
				throw new \Exception(self::L('enter_new_password.keys_not_match'));
			}

			$user
				->setPasswordExt($password)
				->setActivationKey(Model::generateActivationKey())
				->save();

			if ($user->authenticateAfterEnteringNewPassword())
			{
				AuthTool::i()->forceAuthorize($user, true);
			}

			$ar['ok'] = true;
		} catch (\Exception $e) {
			$ar['message'] = $e->getMessage();
		}

		return $ar;
	}
}