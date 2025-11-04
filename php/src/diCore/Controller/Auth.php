<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 27.12.2017
 * Time: 12:44
 */

namespace diCore\Controller;

use diCore\Base\CMS;
use diCore\Base\Exception\HttpException;
use diCore\Data\Config;
use diCore\Data\Http\HttpCode;
use diCore\Data\Types;
use diCore\Entity\Admin\Level;
use diCore\Entity\User\Model;
use diCore\Tool\Auth as AuthTool;

class Auth extends \diBaseController
{
    const BACK_KEY = 'oAuth2Back';
    const REDIRECT_AFTER_LOGIN = true;

    protected static $language = [
        'en' => [
            'common.enter_email' => 'Enter E-mail',
            'sign_in.unsuccessful' => 'E-mail or password is wrong',

            'reset_password.sign_out_first' =>
                'You are signed in, unable to reset password',
            'reset_password.user_not_found' => 'User not found',
            'reset_password.user_not_active' => 'User is not active',
            'reset_password.no_email' => 'No email provided',
            'reset_password.no_token' => 'No token provided',

            'enter_new_password.sign_out_first' =>
                'You are signed in, enter new password in cabinet',
            'enter_new_password.email_not_valid' => 'E-mail is not valid',
            'enter_new_password.key_not_valid' => 'Key is not valid',
            'enter_new_password.keys_not_match' => 'Keys do not match',
            'enter_new_password.password_not_valid' =>
                'Password is not valid (min length is 6 chars)',
            'enter_new_password.passwords_not_match' => 'Passwords do not match',
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

            'reset_password.sign_out_first' =>
                'Вы не можете сбросить пароль, т.к. вы авторизованы',
            'reset_password.user_not_found' => 'Пользователь не найден',
            'reset_password.user_not_active' => 'Пользователь не активен',
            'reset_password.no_email' => 'Email не передан',
            'reset_password.no_token' => 'Токен не передан',

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
            'activate.account_already_activated' => 'Аккаунт уже активирован ранее',
            'activate.key_not_match' => 'Код активации не подходит',
            'activate.key_is_empty' => 'Код активации пуст',
            'activate.unknown_error' => 'Неизвестная ошибка',
            'activate.success' => 'Активация прошла успешно',
        ],
    ];

    /**
     * @var AuthTool
     */
    protected $Auth;

    public function __construct($params = [])
    {
        parent::__construct($params);

        $this->Auth = AuthTool::create(static::REDIRECT_AFTER_LOGIN);
    }

    public function loginAction()
    {
        $lang = \diRequest::postExt('language');
        $message = $this->Auth->authorized()
            ? ''
            : static::L('sign_in.unsuccessful', $lang);

        if (static::isRestApiSupported()) {
            if (!$this->Auth->authorized()) {
                return $this->unauthorized(['message' => $message]);
            }

            if (Config::isUserSessionUsed()) {
                return $this->ok($this->getLoginSuccessResponseBody());
            }
        }

        return [
            'ok' => $this->Auth->authorized(),
            'message' => $message,
        ];
    }

    protected function getLoginSuccessResponseBody()
    {
        return [
            'token' => $this->Auth->getUserSession()->getToken(),
        ];
    }

    public function logoutAction()
    {
        $this->Auth->logout();

        if (!\diRequest::request('redirect') && static::isRestApiSupported()) {
            return $this->unauthorized([
                'ok' => true,
            ]);
        }

        $this->redirect();

        return null;
    }

    public function checkAction()
    {
        if (!$this->Auth->authorized()) {
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
                $this->getAdminModel()->getLevel() !== Level::root
            ) {
                throw new \Exception('This action is allowed only for root admins');
            }

            $userId = $this->param(0);
            $key = $this->param(1);
            $back = \diRequest::get('back');

            /** @var Model $user */
            $user = \diModel::create(Types::user, $userId);

            if (!$user->exists()) {
                throw new \Exception("User not found, ID=$userId");
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
                header("Location: $back");
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

            $this->redirectTo(\diSession::getAndKill(static::BACK_KEY) ?: '/');
        } elseif ($this->param(1) == \diOAuth2::unlinkParam) {
            return [
                'ok' => $a->unlink(),
            ];
        } else {
            \diSession::set(
                static::BACK_KEY,
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
                ->generateAndSetToken()
                ->save();

            $href = $this->getActivateRedirectUrl(true);
        } catch (\Exception $e) {
            $href = $this->getActivateRedirectUrl(false, $e->getMessage());
        }

        $this->redirectTo($href);
    }

    protected function beforeSignUp(Model $user)
    {
        return $this;
    }

    protected function afterSuccessfulSignUp(Model $user)
    {
        return $this;
    }

    public function signUpAction()
    {
        $ar = [
            'ok' => false,
            'errors' => [],
        ];

        $user = Model::create();

        try {
            $this->beforeSignUp($user);

            $user->fastSignUp([
                'twig' => $this->getTwig(),
            ]);

            $ar['ok'] = true;

            $this->afterSuccessfulSignUp($user);

            AuthTool::i()->forceAuthorize($user, true);

            if (Config::isUserSessionUsed()) {
                return $this->ok($this->getLoginSuccessResponseBody());
            }
        } catch (\diValidationException $e) {
            $this->getResponse()->setResponseCode(HttpCode::BAD_REQUEST);

            $ar['errors'] = $user::getMessagesOfValidationException($e);
            $ar['message'] = join(
                "<br />\n",
                $user::getMessagesOfValidationException($e)
            );
        } catch (HttpException $e) {
            $this->defaultResponse($e, true);
        } catch (\diDatabaseException $e) {
            $this->getResponse()->setResponseCode(HttpCode::CONFLICT);

            $ar['message'] = 'Такой пользователь уже зарегистрирован в системе';
        } catch (\Exception $e) {
            $ar['message'] = $user::getMessageOfSaveException($e);

            $this->getResponse()->setResponseCode(HttpCode::INTERNAL_SERVER_ERROR);
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
        return static::L('common.enter_email');
    }

    protected function getUserUidForReset()
    {
        $email = \diRequest::postExt('email');

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
            throw new \Exception(static::L('activate.key_is_empty'));
        }

        return $key;
    }

    protected function getUserForActivate()
    {
        return Model::create(Types::user, $this->getUserUidForActivate(), 'slug');
    }

    /**
     * @deprecated
     * Back compatibility
     */
    public function resetAction()
    {
        if (\diRequest::isGet()) {
            return $this->_getResetPasswordAction();
        } elseif (\diRequest::isPost()) {
            return $this->_postResetPasswordAction();
        } else {
            return $this->notFound('Unsupported method');
        }
    }

    /**
     * POST /api/auth/reset_password
     */
    public function _postResetPasswordAction()
    {
        if (AuthTool::i()->authorized()) {
            return $this->badRequest(static::L('reset_password.sign_out_first'));
        }

        /** @var Model $user */
        $user = $this->getUserForReset();

        if (!$user->exists()) {
            return $this->notFound(static::L('reset_password.user_not_found'));
        }

        if (!$user->active()) {
            return $this->badRequest(static::L('reset_password.user_not_active'));
        }

        $user
            ->setValidationNeeded(false)
            ->notifyAboutResetPasswordByEmail($this->getTwig());

        return $this->okay();
    }

    protected function getResetPasswordRedirectLink()
    {
        return CMS::languageHrefPrefix() .
            '/' .
            CMS::ct('reset_password') .
            '/' .
            $this->param(0) .
            '/' .
            $this->param(1) .
            '/';
    }

    /**
     * GET /api/auth/reset_password/:email/:token
     */
    public function _getResetPasswordAction()
    {
        if (AuthTool::i()->authorized()) {
            return $this->badRequest(static::L('reset_password.sign_out_first'));
        }

        if (!$this->param(0)) {
            return $this->badRequest(static::L('reset_password.no_email'));
        }

        if (!$this->param(1)) {
            return $this->badRequest(static::L('reset_password.no_token'));
        }

        $this->redirectTo($this->getResetPasswordRedirectLink());

        return null;
    }

    /**
     * GET /api/auth/enter_new_password
     */
    public function _postEnterNewPasswordAction()
    {
        $email = \diRequest::postExt('email', '');
        $key = \diRequest::postExt('key', '');
        $password = \diRequest::postExt('password', '');
        $password2 = \diRequest::postExt('password2', '');

        if (AuthTool::i()->authorized()) {
            return $this->badRequest(static::L('enter_new_password.sign_out_first'));
        }

        if (!\diEmail::isValid($email)) {
            return $this->badRequest(
                static::L('enter_new_password.email_not_valid')
            );
        }

        if (!Model::isTokenValid($key)) {
            return $this->badRequest(static::L('enter_new_password.key_not_valid'));
        }

        if (Model::isPasswordValid($password)) {
            return $this->badRequest(
                static::L('enter_new_password.password_not_valid')
            );
        }

        if ($password != $password2) {
            return $this->badRequest(
                static::L('enter_new_password.passwords_not_match')
            );
        }

        $user = Model::createBySlug($email);

        if (!$user->exists()) {
            return $this->notFound(static::L('enter_new_password.user_not_exist'));
        }

        if (!$user->active()) {
            return $this->badRequest(
                static::L('enter_new_password.user_not_active')
            );
        }

        if ($user->getActivationKey() != $key) {
            return $this->badRequest(static::L('enter_new_password.keys_not_match'));
        }

        $user
            ->setValidationNeeded(false)
            ->setPasswordExt($password)
            ->generateAndSetToken()
            ->save();

        if ($user->authenticateAfterEnteringNewPassword()) {
            AuthTool::i()->forceAuthorize($user, true);
        }

        return $this->okay();
    }

    /**
     * @deprecated Use Cabinet::setPasswordAction
     */
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
