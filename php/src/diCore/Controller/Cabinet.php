<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 24.01.2018
 * Time: 10:33
 */

namespace diCore\Controller;

use diCore\Entity\User\Model;
use diCore\Tool\Auth as AuthTool;

class Cabinet extends \diBaseController
{
	protected static $language = [
		'en' => [
			'set_password.sign_in_first' => 'Sign in to set new password',
			'set_password.wrong_old_password' => 'Wrong old password',
			'set_password.password_not_valid' => 'Password is not valid (min length is 6 chars)',
			'set_password.passwords_not_match' => 'Passwords do not match',
		],
		'ru' => [
			'set_password.sign_in_first' => 'Авторизуйтесь для смены пароля',
			'set_password.wrong_old_password' => 'Введён неверный старый пароль',
			'set_password.password_not_valid' => 'Некорректный пароль (мин.длина - 6 символов)',
			'set_password.passwords_not_match' => 'Пароли не совпадают',
		],
	];

	public function setPasswordAction()
	{
		$ar = [
			'ok' => false,
		];

		if (!AuthTool::i()->authorized())
		{
			$ar['message'] = self::L('set_password.sign_in_first');
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