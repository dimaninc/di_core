<?php

/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 03.01.16
 * Time: 10:43
 */

class diAuth
{
	const CUSTOM_CLASS_NAME = "diCustomAuth";
	const COOKIE_PROVIDER = "diCookie";

	const SOURCE_POST = 1;
	const SOURCE_COOKIE = 2;
	const SOURCE_SESSION = 3;

	const COOKIE_LIFE_TIME_REMEMBERED = "+2 weeks";
	const COOKIE_LIFE_TIME_GUEST = "+30 min";

	const COOKIE_PATH = "/";

	const COOKIE_USER_ID = "auth_user_id";
	const COOKIE_SECRET = "auth_secret";
	const COOKIE_REMEMBER = "auth_remember";

	const POST_LOGIN_FIELD = "vm_login";
	const POST_PASSWORD_FIELD = "vm_password";
	const POST_REMEMBER_FIELD = "vm_remember";

	const TEMPLATE_VAR_PREFIX = "LOGGED_IN_";

	const USER_MODEL_TYPE = diTypes::user;

	const SESSION_USER_ID_FIELD = "user_id";

	/** @var diAuth */
	protected static $instance;

	/** @var diUserModel */
	private $user;
	/** @var int */
	private $authSource;
	/** @var integer|null */
	private $errorCode = null;
	/** @var FastTemplate */
	private $tpl;
	/** @var bool */
	private $redirectAllowed = true;

	public function __construct($redirectAllowed = true)
	{
		diSession::start();

		$this
			->setRedirectAllowed($redirectAllowed)
			->authUsingSession()
			->authUsingCookies()
			->authUsingPost()
			->storeSession()
			->storeCookies()
			->redirectIfNeeded();

		static::$instance = $this;
	}

	/**
	 * @return diAuth
	 */
	public static function create($redirectAllowed = true)
	{
		$className = diLib::exists(static::CUSTOM_CLASS_NAME)
			? static::CUSTOM_CLASS_NAME
			: get_called_class();

		$o = new $className($redirectAllowed);

		return $o;
	}

	/**
	 * @return diAuth
	 */
	public static function i()
	{
		if (!static::$instance)
		{
			static::$instance = static::create();
		}

		return static::$instance;
	}

	/**
	 * @return diUserModel|diUserCustomModel
	 */
	public function getUserModel()
	{
		return $this->user;
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
		return $this
			->clearCookies()
			->clearSession();
	}

	private function storeSession()
	{
		if ($this->reallyAuthorized())
		{
			$_SESSION[static::SESSION_USER_ID_FIELD] = $this->getUserId();
		}
		else
		{
			$this->clearSession();
		}

		return $this;
	}

	private function clearSession()
	{
		unset($_SESSION[static::SESSION_USER_ID_FIELD]);

		return $this;
	}

	protected function getDomainForCookie()
	{
		return diCookie::getDomainForAll();
	}

	protected function rememberUser()
	{
		return diRequest::post(static::POST_REMEMBER_FIELD, "") || diCookie::get(static::COOKIE_REMEMBER);
	}

	protected function needToStoreCookies()
	{
		return in_array($this->authSource, [self::SOURCE_POST, self::SOURCE_COOKIE]);
	}

	private function setCookie($name, $value = null, $date = null, $path = null, $domain = null)
	{
		/** @var diCookie $className */
		$className = static::COOKIE_PROVIDER;
		$className::set($name, $value, $date, $path ?: static::COOKIE_PATH, $domain ?: $this->getDomainForCookie());

		return $this;
	}

	private function removeCookie($name, $path = null, $domain = null)
	{
		/** @var diCookie $className */
		$className = static::COOKIE_PROVIDER;
		$className::remove($name, $path ?: static::COOKIE_PATH, $domain ?: $this->getDomainForCookie());

		return $this;
	}

	private function storeCookies($remember = false)
	{
		if ($this->reallyAuthorized() && $this->needToStoreCookies())
		{
			$cookieTime = strtotime($this->rememberUser() || $remember ? static::COOKIE_LIFE_TIME_REMEMBERED : static::COOKIE_LIFE_TIME_GUEST);

			$id = $this->getUserId();
			$secret = diBaseUserModel::hash($this->getUserModel()->getPassword(), "cookie", "db");

			$this
				->setCookie(static::COOKIE_USER_ID, $id, $cookieTime)
				->setCookie(static::COOKIE_SECRET, $secret, $cookieTime);

			if ($this->rememberUser() || $remember)
			{
				$this->setCookie(static::COOKIE_REMEMBER, 1, $cookieTime);
			}
		}

		return $this;
	}

	private function clearCookies()
	{
		$this
			->removeCookie(static::COOKIE_USER_ID)
			->removeCookie(static::COOKIE_SECRET)
			->removeCookie(static::COOKIE_REMEMBER);

		return $this;
	}

	// todo: check activated status
	private function authorize($id, $passwordHash, $source = self::SOURCE_POST)
	{
		if ($this->authorized())
		{
			return false;
		}

		$this->user = diModel::create(static::USER_MODEL_TYPE, $id);
		$sourceStr = $source == self::SOURCE_POST ? "raw" : "cookie";

		if ($this->getUserModel()->exists() && $this->getUserModel()->active() && $this->getUserModel()->isPasswordOk($passwordHash, $sourceStr))
		{
			$this->storeSession();

			return true;
		}
		else
		{
			$this->getUserModel()->destroy();
		}

		return false;
	}

	public function forceAuthorize(diBaseUserModel $user, $storeCookies = false)
	{
		$this->user = $user;

		$this
			->storeSession()
			->storeCookies($storeCookies);

		return $this;
	}

	protected function updateAuthorizedUserData()
	{
		if ($this->authorized())
		{
			$this->getUserModel()
				->setValidationNeeded(false)
				->setLastVisitDate(date("Y-m-d H:i:s"))
				->setIp(ip2bin())
				->save()
				->setValidationNeeded(true);
		}

		return $this;
	}

	private function authUsingSession()
	{
		$id = diRequest::session(static::SESSION_USER_ID_FIELD, 0);

		$this->user = diModel::create(static::USER_MODEL_TYPE, $id, "id");

		if ($this->authorized())
		{
			$this->authSource = self::SOURCE_SESSION;

			$this->updateAuthorizedUserData();
		}
		else
		{
			$this->getUserModel()->destroy();
		}

		return $this;
	}

	private function authUsingCookies()
	{
		if ($this->authorized())
		{
			return $this;
		}

		$id = (int)diCookie::get(static::COOKIE_USER_ID);
		$password = diCookie::get(static::COOKIE_SECRET);

		if ($this->authorize($id, $password, self::SOURCE_COOKIE))
		{
			$this->authSource = self::SOURCE_COOKIE;

			$this->updateAuthorizedUserData();
		}

		return $this;
	}

	private function authUsingPost()
	{
		if ($this->authorized())
		{
			return $this;
		}

		$login = diRequest::post(static::POST_LOGIN_FIELD, "");
		$password = diRequest::post(static::POST_PASSWORD_FIELD, "");

		if ($login && $password && $this->authorize($login, $password, self::SOURCE_POST))
		{
			$this->authSource = self::SOURCE_POST;

			$this->updateAuthorizedUserData();
		}

		return $this;
	}

	private function redirectIfNeeded()
	{
		if ($this->isRedirectAllowed() && $this->redirectNeeded())
		{
			header("Location: " . diRequest::server("REQUEST_URI"));

			die();
		}

		return $this;
	}

	private function redirectNeeded()
	{
		return in_array($this->authSource, [self::SOURCE_POST, self::SOURCE_COOKIE]);
	}

	public function assignTemplateVariables(FastTemplate $tpl)
	{
		$this->tpl = $tpl;

		$tpl
			->assign($this->getUserModel()->getTemplateVarsExtended(), static::TEMPLATE_VAR_PREFIX)
			->assign([
				"BOOLEAN" => $this->authorized() ? "true" : "false",
			], static::TEMPLATE_VAR_PREFIX);

		if ($this->authorized())
		{
			$tpl->process("LOGIN_PANEL", "user_panel");
		}
		else
		{
			$tpl
				->process("LOGIN_PANEL", "auth_panel")
				->process("LOGIN_POPUP", "auth_popup");
		}

		return $this;
	}

	public function assignTwig(diTwig $twig, $clearTpl = true)
	{
		$twig
			->assign([
				'authUser' => $this->getUserModel(),
			]);

		if (!$this->tpl)
		{
			return $this;
		}

		$twig
			->importFromFastTemplate($this->tpl, [
				'login_panel',
				'login_popup',
			], $clearTpl);

		return $this;
	}
}