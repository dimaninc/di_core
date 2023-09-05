<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 24.01.2018
 * Time: 10:33
 */

namespace diCore\Controller;

use diCore\Entity\User\Model as User;
use diCore\Tool\Auth as AuthTool;

class Cabinet extends \diBaseController
{
    protected static $language = [
        'en' => [
            'set_password.sign_in_first' => 'Sign in to set new password',
            'set_password.wrong_old_password' => 'Wrong old password',
            'set_password.password_not_valid' =>
                'Password is not valid (min length is 6 chars)',
            'set_password.passwords_not_match' => 'Passwords do not match',
        ],
        'ru' => [
            'set_password.sign_in_first' => 'Авторизуйтесь для смены пароля',
            'set_password.wrong_old_password' => 'Введён неверный старый пароль',
            'set_password.password_not_valid' =>
                'Некорректный пароль (мин.длина - 6 символов)',
            'set_password.passwords_not_match' => 'Пароли не совпадают',
        ],
    ];

    public function setPasswordAction()
    {
        if (!AuthTool::i()->authorized()) {
            return $this->unauthorized([
                'message' => static::L('set_password.sign_in_first'),
            ]);
        }

        try {
            /** @var User $user */
            $user = AuthTool::i()->getUserModel();
            $user->cabinetSubmitPassword();
        } catch (\Exception $e) {
            return $this->internalServerError([
                'message' => $e->getMessage(),
            ]);
        }

        return $this->okay();
    }
}
