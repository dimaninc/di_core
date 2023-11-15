<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 31.10.2017
 * Time: 20:17
 */

namespace diCore\Tool;

use diCore\Data\Config;
use diCore\Data\Types;
use diCore\Entity\User\Model as User;
use diCore\Entity\UserSession\Model as UserSession;
use diCore\Traits\BasicCreate;

class Auth
{
    use BasicCreate;

    const COOKIE_PROVIDER = \diCookie::class;

    // post request
    const SOURCE_POST = 1;
    // stored cookies
    const SOURCE_COOKIE = 2;
    // php session
    const SOURCE_SESSION = 3;
    // stored in cookies or received in headers user_session token
    const SOURCE_USER_SESSION = 4;

    const CLEAR_USER_ID_COOKIE_ON_SIGN_OUT = true;

    const COOKIE_LIFE_TIME_REMEMBERED = '+2 weeks';
    const COOKIE_LIFE_TIME_GUEST = '+30 min';

    const COOKIE_PATH = '/';

    const COOKIE_USER_ID = 'auth_user_id';
    const COOKIE_SECRET = 'auth_secret';
    const COOKIE_REMEMBER = 'auth_remember';

    // new way of auth using user_session
    const HEADER_TOKEN = 'auth_token';

    const POST_LOGIN_FIELD = 'vm_login';
    const POST_PASSWORD_FIELD = 'vm_password';
    const POST_REMEMBER_FIELD = 'vm_remember';

    const TEMPLATE_VAR_PREFIX = 'LOGGED_IN_';

    const USER_MODEL_TYPE = Types::user;

    const SESSION_USER_ID_FIELD = 'user_id';

    /** @var Auth */
    protected static $instance;

    /** @var User */
    private $user;
    /** @var UserSession */
    private $userSession;
    /** @var int */
    private $authSource;
    /** @var integer|null */
    private $errorCode = null;
    /** @var \FastTemplate */
    private $tpl;
    /** @var bool */
    private $redirectAllowed = true;
    /**
     * Ids of super users
     * @var array
     */
    protected static $superUsers = [];

    public function __construct($redirectAllowed = true)
    {
        \diSession::start();

        $this->setRedirectAllowed($redirectAllowed)
            ->authUsingSession()
            ->authUsingCookies()
            ->authUsingHeaders()
            ->authUsingPost()
            ->storeSession()
            ->storeCookies()
            ->redirectIfNeeded();

        static::$instance = $this;
    }

    public static function getSuperUserIds()
    {
        return static::$superUsers;
    }

    /**
     * @return Auth
     */
    public static function create($redirectAllowed = true)
    {
        return static::basicCreate($redirectAllowed);
    }

    /**
     * @return Auth
     */
    public static function i()
    {
        if (!static::$instance) {
            static::$instance = static::create();
        }

        return static::$instance;
    }

    public static function isFirstVisit()
    {
        if (\diRequest::cookie(static::COOKIE_USER_ID) || self::i()->authorized()) {
            return false;
        }

        return true;
    }

    public function getUserModel()
    {
        return $this->user;
    }

    public function getUserSession()
    {
        return $this->userSession ?: UserSession::create();
    }

    public function authorized()
    {
        return $this->reallyAuthorized();
    }

    public function reallyAuthorized()
    {
        return $this->getUserModel()->exists() && $this->getUserModel()->active();
    }

    protected function setErrorCode($code)
    {
        $this->errorCode = $code;

        return $this;
    }

    public function getErrorCode()
    {
        return $this->errorCode;
    }

    public function getUserId()
    {
        return $this->getUserModel()->getId();
    }

    public function isSuperUser()
    {
        return in_array($this->getUserId(), self::getSuperUserIds());
    }

    public function isRedirectAllowed()
    {
        return $this->redirectAllowed;
    }

    public function setRedirectAllowed($redirectAllowed)
    {
        $this->redirectAllowed = $redirectAllowed;

        return $this;
    }

    public function logout()
    {
        return $this->clearCookies()
            ->clearSession()
            ->clearUserSession();
    }

    private function storeSession()
    {
        if ($this->reallyAuthorized()) {
            if (Config::isUserSessionUsed()) {
                if (
                    $this->authSource === self::SOURCE_POST &&
                    !$this->getUserSession()->exists()
                ) {
                    $this->userSession = UserSession::fastCreate(
                        $this->getUserModel()
                    )->save();
                }
            }

            $_SESSION[static::SESSION_USER_ID_FIELD] = $this->getUserId();
        } else {
            $this->clearSession()->clearUserSession();
        }

        return $this;
    }

    private function clearSession()
    {
        unset($_SESSION[static::SESSION_USER_ID_FIELD]);

        return $this;
    }

    private function clearUserSession()
    {
        if (Config::isUserSessionUsed()) {
            $this->getUserSession()->hardDestroy();
        }

        return $this;
    }

    protected function getDomainForCookie()
    {
        /** @var \diCookie $className */
        $className = static::COOKIE_PROVIDER;

        return $className::getDomainForAll();
    }

    protected function rememberUser()
    {
        return \diRequest::post(static::POST_REMEMBER_FIELD, '') ||
            \diCookie::get(static::COOKIE_REMEMBER);
    }

    protected function needToStoreCookies($force = false)
    {
        return $force ||
            in_array($this->authSource, [self::SOURCE_POST, self::SOURCE_COOKIE]);
    }

    protected function setCookie(
        $name,
        $value = null,
        $date = null,
        $path = null,
        $domain = null
    ) {
        /** @var \diCookie $className */
        $className = static::COOKIE_PROVIDER;
        $className::set(
            $name,
            $value,
            $date,
            $path ?: static::COOKIE_PATH,
            $domain ?: $this->getDomainForCookie()
        );

        return $this;
    }

    protected function removeCookie($name, $path = null, $domain = null)
    {
        /** @var \diCookie $className */
        $className = static::COOKIE_PROVIDER;
        $className::remove(
            $name,
            $path ?: static::COOKIE_PATH,
            $domain ?: $this->getDomainForCookie()
        );

        return $this;
    }

    protected function storeCookies($remember = false)
    {
        if ($this->reallyAuthorized() && $this->needToStoreCookies($remember)) {
            $cookieTime = strtotime(
                $this->rememberUser() || $remember
                    ? static::COOKIE_LIFE_TIME_REMEMBERED
                    : static::COOKIE_LIFE_TIME_GUEST
            );

            $id = $this->getUserId();
            $secret = \diBaseUserModel::hash(
                $this->getUserModel()->getPassword(),
                'cookie',
                'db'
            );

            $this->setCookie(static::COOKIE_USER_ID, $id, $cookieTime)->setCookie(
                static::COOKIE_SECRET,
                $secret,
                $cookieTime
            );

            if ($this->rememberUser() || $remember) {
                $this->setCookie(static::COOKIE_REMEMBER, 1, $cookieTime);
            }
        }

        return $this;
    }

    protected function clearCookies()
    {
        $this->removeCookie(static::COOKIE_SECRET)->removeCookie(
            static::COOKIE_REMEMBER
        );

        if (static::CLEAR_USER_ID_COOKIE_ON_SIGN_OUT) {
            $this->removeCookie(static::COOKIE_USER_ID);
        }

        return $this;
    }

    // todo: check activated status
    private function authorize($id, $passwordHash, $source = self::SOURCE_POST)
    {
        if ($this->authorized()) {
            return false;
        }

        if ($source === self::SOURCE_USER_SESSION) {
            $this->userSession = UserSession::fastRestore($id);

            if (!$this->userSession->exists()) {
                return false;
            }

            $this->user = $this->userSession->getUser();
            $this->userSession->updateSeenAt()->save();
            $passwordOk = true;
        } else {
            $this->user = \diModel::create(static::USER_MODEL_TYPE, $id);
            $sourceStr = $source === self::SOURCE_POST ? 'raw' : 'cookie';
            $passwordOk = $this->getUserModel()->isPasswordOk(
                $passwordHash,
                $sourceStr
            );
        }

        if (
            $this->getUserModel()->exists() &&
            $this->getUserModel()->active() &&
            $passwordOk
        ) {
            $this->storeSession();

            return true;
        }

        $this->getUserModel()->destroy();

        return false;
    }

    public function forceAuthorize(\diBaseUserModel $user, $storeCookies = false)
    {
        $this->user = $user;

        $this->storeSession()->storeCookies($storeCookies);

        return $this;
    }

    protected function updateAuthorizedUserData()
    {
        if ($this->authorized()) {
            $this->getUserModel()
                ->setValidationNeeded(false)
                ->setLastVisitDate(\diDateTime::sqlFormat())
                ->setIp(ip2bin())
                ->save()
                ->setValidationNeeded(true);
        }

        return $this;
    }

    private function authUsingSession()
    {
        $id = \diRequest::session(static::SESSION_USER_ID_FIELD, 0);

        $this->user = \diModel::create(static::USER_MODEL_TYPE, $id, 'id');

        if ($this->authorized()) {
            $this->authSource = self::SOURCE_SESSION;

            $this->updateAuthorizedUserData();
        } else {
            $this->getUserModel()->destroy();
        }

        return $this;
    }

    private function authUsingCookies()
    {
        if ($this->authorized()) {
            return $this;
        }

        $id = (int) \diCookie::get(static::COOKIE_USER_ID);
        $password = \diCookie::get(static::COOKIE_SECRET);

        if ($this->authorize($id, $password, self::SOURCE_COOKIE)) {
            $this->authSource = self::SOURCE_COOKIE;

            $this->updateAuthorizedUserData();
        }

        return $this;
    }

    private function authUsingHeaders()
    {
        if ($this->authorized()) {
            return $this;
        }

        $token = \diRequest::header(static::HEADER_TOKEN);

        if ($token && $this->authorize($token, null, self::SOURCE_USER_SESSION)) {
            $this->authSource = self::SOURCE_USER_SESSION;

            $this->updateAuthorizedUserData();
        }

        return $this;
    }

    private function authUsingPost()
    {
        if ($this->authorized()) {
            return $this;
        }

        $login = \diRequest::postExt(static::POST_LOGIN_FIELD);
        $password = \diRequest::postExt(static::POST_PASSWORD_FIELD);

        if (
            $login &&
            $password &&
            $this->authorize($login, $password, self::SOURCE_POST)
        ) {
            $this->authSource = self::SOURCE_POST;

            $this->updateAuthorizedUserData();
        }

        return $this;
    }

    private function redirectIfNeeded()
    {
        if ($this->isRedirectAllowed() && $this->redirectNeeded()) {
            header('Location: ' . \diRequest::requestUri());

            die();
        }

        return $this;
    }

    private function redirectNeeded()
    {
        return in_array($this->authSource, [self::SOURCE_POST]); //, self::SOURCE_COOKIE
    }

    /** @deprecated  */
    public function assignTemplateVariables(\FastTemplate $tpl)
    {
        $this->tpl = $tpl;

        $tpl->assign(
            $this->getUserModel()->getTemplateVarsExtended(),
            static::TEMPLATE_VAR_PREFIX
        )->assign(
            [
                'BOOLEAN' => $this->authorized() ? 'true' : 'false',
            ],
            static::TEMPLATE_VAR_PREFIX
        );

        if ($this->authorized()) {
            if ($tpl->exists('user_panel')) {
                $tpl->process('LOGIN_PANEL', 'user_panel');
            }
        } else {
            $tpl->exists('auth_panel') && $tpl->process('LOGIN_PANEL', 'auth_panel');
            $tpl->exists('auth_popup') && $tpl->process('LOGIN_POPUP', 'auth_popup');
        }

        return $this;
    }

    public function assignTwig(\diTwig $twig, $clearTpl = true)
    {
        $twig->assign([
            'authUser' => $this->getUserModel(),
        ]);

        if (!$this->tpl) {
            return $this;
        }

        $twig->importFromFastTemplate(
            $this->tpl,
            ['login_panel', 'login_popup'],
            $clearTpl
        );

        return $this;
    }
}
