<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 27.12.2017
 * Time: 12:44
 */

namespace diCore\Controller;

use diCore\Base\CMS;
use diCore\Data\Config;
use diCore\Data\Types;
use diCore\Entity\User\Model;
use diCore\Tool\Auth as AuthTool;

class Auth extends \diBaseController
{
    const BACK_KEY = 'oAuth2Back';

    protected static $language = [
        'en' => [
            'common.enter_email' => 'Enter E-mail',
            'sign_in.unsuccessful' => 'E-mail or password is wrong',

            'enter_new_password.sign_out_first' =>
                'You are signed in, enter new password in cabinet',
            'enter_new_password.email_not_valid' => 'E-mail is not valid',
            'enter_new_password.key_not_valid' => 'Key is not valid',
            'enter_new_password.keys_not_match' => 'Keys do not match',
            'enter_new_password.password_not_valid' =>
                'Password is not valid (min length is 6 chars)',
            'enter_new_password.passwords_not_match' =>
                'Passwords do not match',
            'enter_new_password.user_not_exist' => 'User does not exist',
            'enter_new_password.user_not_active' => 'User is not active',

            'activate.sign_out_first' =>
                'Unable to activate account while you are authenticated',
            'activate.account_not_found' => 'Account not found',
            'activate.account_already_activated' =>
                'Account has been already activated',
            'activate.key_not_match' => 'Activation key does not match',
            'activate.key_is_empty' => 'Activation key is empty',
            'activate.unknown_error' => 'Unknown error',
            'activate.success' => 'Account successfully activated',
        ],
        'ru' => [
            'common.enter_email' => 'Введите E-mail',
            'sign_in.unsuccessful' => 'E-mail или пароль не подходят',

            'enter_new_password.sign_out_first' =>
                'Вы авторизованы, задайте новый пароль в личном кабинете',
            'enter_new_password.email_not_valid' => 'Некорректный E-mail',
            'enter_new_password.key_not_valid' => 'Некорректный код',
            'enter_new_password.keys_not_match' => 'Коды не совпадают',
            'enter_new_password.password_not_valid' =>
                'Некорректный пароль (мин.длина - 6 символов)',
            'enter_new_password.passwords_not_match' => 'Пароли не совпадают',
            'enter_new_password.user_not_exist' => 'Пользователь не существует',
            'enter_new_password.user_not_active' => 'Пользователь не активен',

            'activate.sign_out_first' =>
                'Вы не можете активировать аккаунт, т.к. вы авторизованы',
            'activate.account_not_found' => 'Пользователь не найден',
            'activate.account_already_activated' =>
                'Аккаунт уже активирован ранее',
            'activate.key_not_match' => 'Код активации не подходит',
            'activate.key_is_empty' => 'Код активации пуст',
            'activate.unknown_error' => 'Неизвестная ошибка',
            'activate.success' => 'Активация прошла успешно',
        ],
    ];

    public function loginAction()
    {
        $lang = \diRequest::post('language') ?: \diRequest::rawPost('language');
        $Auth = AuthTool::create(false);

        if (Config::isRestApiSupported()) {
            if (!$Auth->authorized()) {
                return $this->unauthorized();
            }

            if (Config::isUserSessionUsed()) {
                return $this->ok([
                    'token' => $Auth->getUserSession()->getToken(),
                ]);
            }
        }

        return [
            'ok' => $Auth->authorized(),
            'message' => $Auth->authorized()
                ? ''
                : self::L('sign_in.unsuccessful', $lang),
        ];
    }

    public function logoutAction()
    {
        $Auth = AuthTool::create();
        $Auth->logout();

        if (!\diRequest::request('redirect') && Config::isRestApiSupported()) {
            return $this->unauthorized([
                'ok' => true,
            ]);
        }

        $this->redirect();

        return null;
    }

    public function checkAction()
    {
        $Auth = AuthTool::create();

        if (!$Auth->authorized()) {
            return $this->unauthorized();
        }

        return $this->okay();
    }

    public function loginForAdminAction()
    {
        $res = [
            'ok' => false,
        ];

        try {
            $this->initAdmin();

            if (
                !$this->isAdminAuthorized() ||
                $this->getAdminModel()->getLevel() != 'root'
            ) {
                throw new \Exception(
                    'This action is allowed only for root admins'
                );
            }

            $userId = $this->param(0);
            $key = $this->param(1);
            $back = \diRequest::get('back');

            /** @var Model $user */
            $user = \diModel::create(Types::user, $userId);

            if (!$user->exists()) {
                throw new \Exception('User not found, ID=' . $userId);
            }

            if (!$user->hasActive()) {
                throw new \Exception('User is not active');
            }

            if ($user->getActivationKey() != $key) {
                throw new \Exception('User key not match');
            }

            $A = new AuthTool();
            $A->forceAuthorize($user, true);

            if ($back) {
                header('Location: ' . $back);
                die();
            }

            $res['ok'] = true;
        } catch (\Exception $e) {
            $res['message'] = $e->getMessage();
        }

        return $res;
    }

    protected function getOAuth()
    {
        return \diOAuth2::create($this->param(0));
    }

    public function oauth2Action()
    {
        $a = $this->getOAuth();

        if ($this->param(1) == \diOAuth2::callbackParam) {
            $a->processReturn();

            $this->redirectTo(\diSession::getAndKill(self::BACK_KEY) ?: '/');
        } elseif ($this->param(1) == \diOAuth2::unlinkParam) {
            return [
                'ok' => $a->unlink(),
            ];
        } else {
            \diSession::set(
                self::BACK_KEY,
                \diRequest::get('back') ?: \diRequest::referrer()
            );

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
            if (!AuthTool::i()->authorized()) {
                throw new \Exception(
                    'Для удаления аккаунта необходимо войти в систему'
                );
            }

            AuthTool::i()
                ->getUserModel()
                ->deactivate();

            $ar['ok'] = true;
        } catch (\diValidationException $e) {
            $ar['errors'] = $e->getErrors();
        }

        return $ar;
    }

    protected function getActivateRedirectUrl($success, $token = null)
    {
        $params = [];

        if ($success) {
            $params['success'] = 1;
            $params['activate_message'] = 'activate.success';
        }

        if ($token) {
            $params['activate_message'] = $token;
        }

        return '/' .
            CMS::ct('registration') .
            '/' .
            ($params ? '?' . http_build_query($params) : '');
    }

    public function activateAction()
    {
        try {
            if (AuthTool::i()->authorized()) {
                throw new \Exception('activate.sign_out_first');
            }

            /** @var Model $user */
            $user = $this->getUserForActivate();

            if (!$user->exists()) {
                throw new \Exception('activate.account_not_found');
            }

            if ($user->active()) {
                throw new \Exception('activate.account_already_activated');
            }

            if ($user->getActivationKey() != $this->getKeyForActivate()) {
                throw new \Exception('activate.key_not_match');
            }

            if ($user->exists('activated')) {
                $user->setActivated(1);
            }

            $user
                ->setActive(1)
                ->setActivationKey(Model::generateActivationKey())
                ->save();

            $href = $this->getActivateRedirectUrl(true);
        } catch (\Exception $e) {
            $href = $this->getActivateRedirectUrl(false, $e->getMessage());
        }

        $this->redirectTo($href);
    }

    public function signUpAction()
    {
        $ar = [
            'ok' => false,
            'errors' => [],
        ];

        /** @var Model $user */
        $user = Model::create();

        try {
            $user->fastSignUp([
                'twig' => $this->getTwig(),
            ]);

            $ar['ok'] = true;

            AuthTool::i()->forceAuthorize($user, true);
        } catch (\diValidationException $e) {
            $ar['errors'] = $user::getMessagesOfValidationException($e);
        } catch (\Exception $e) {
            $ar['message'] = $user::getMessageOfSaveException($e);
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
        return self::L('common.enter_email');
    }

    protected function getUserUidForReset()
    {
        $email = \diRequest::post('email');

        if (!$email) {
            throw new \Exception($this->getEmptyUserUidErrorMessage());
        }

        return $email;
    }

    protected function getUserForReset()
    {
        return Model::create(Types::user, $this->getUserUidForReset(), 'slug');
    }

    protected function getUserUidForActivate()
    {
        $email = $this->param(0);

        if (!$email) {
            throw new \Exception('common.enter_email');
        }

        return $email;
    }

    protected function getKeyForActivate()
    {
        $key = $this->param(1);

        if (!$key) {
            throw new \Exception(self::L('activate.key_is_empty'));
        }

        return $key;
    }

    protected function getUserForActivate()
    {
        return Model::create(
            Types::user,
            $this->getUserUidForActivate(),
            'slug'
        );
    }

    public function resetAction()
    {
        $ar = [
            'ok' => false,
        ];

        try {
            if (AuthTool::i()->authorized()) {
                throw new \Exception(
                    'Вы не можете сбросить пароль, т.к. вы авторизованы'
                );
            }

            /** @var Model $user */
            $user = $this->getUserForReset();

            if (!$user->exists()) {
                throw new \Exception('Пользователь не найден');
            }

            if (!$user->active()) {
                throw new \Exception(
                    'Аккаунт отключён, свяжитесь с администратором'
                );
            }

            $user
                ->setValidationNeeded(false)
                ->notifyAboutResetPasswordByEmail($this->getTwig());

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
            if (AuthTool::i()->authorized()) {
                throw new \Exception(
                    self::L('enter_new_password.sign_out_first')
                );
            }

            if (!\diEmail::isValid($email)) {
                throw new \Exception(
                    self::L('enter_new_password.email_not_valid')
                );
            }

            if (!Model::isActivationKeyValid($key)) {
                throw new \Exception(
                    self::L('enter_new_password.key_not_valid')
                );
            }

            if (Model::isPasswordValid($password)) {
                throw new \Exception(
                    self::L('enter_new_password.password_not_valid')
                );
            }

            if ($password != $password2) {
                throw new \Exception(
                    self::L('enter_new_password.passwords_not_match')
                );
            }

            $user = Model::createBySlug($email);

            if (!$user->exists()) {
                throw new \Exception(
                    self::L('enter_new_password.user_not_exist')
                );
            }

            if (!$user->active()) {
                throw new \Exception(
                    self::L('enter_new_password.user_not_active')
                );
            }

            if ($user->getActivationKey() != $key) {
                throw new \Exception(
                    self::L('enter_new_password.keys_not_match')
                );
            }

            $user
                ->setValidationNeeded(false)
                ->setPasswordExt($password)
                ->setActivationKey(Model::generateActivationKey())
                ->save();

            if ($user->authenticateAfterEnteringNewPassword()) {
                AuthTool::i()->forceAuthorize($user, true);
            }

            $ar['ok'] = true;
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

        if (!AuthTool::i()->authorized()) {
            $ar['message'] = 'Авторизуйтесь для смены пароля';
        } else {
            try {
                /** @var Model $user */
                $user = AuthTool::i()->getUserModel();
                $user
                    ->cabinetSubmitPassword()
                    ->notifyAboutPasswordChangeByEmail($this->getTwig());

                $ar['ok'] = true;
            } catch (\Exception $e) {
                $ar['message'] = $e->getMessage();
            }
        }

        return $ar;
    }
}
