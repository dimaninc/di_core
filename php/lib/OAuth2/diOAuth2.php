<?php
abstract class diOAuth2
{
	const REQUEST_GET = 1;
	const REQUEST_POST = 2;

	const appId = "";
	const secret = "";
	const publicKey = "";

	const loginUrlBase = "";
	const authUrlBase = "";

	const callbackParam = "callback";
	const unlinkParam = "unlink";

	protected $vendorId;

	/** @var diOAuth2ProfileModel */
	protected $profile;

	/** @var array */
	protected $profileRawData = array();
	/** @var string */
	protected $profileRetrieveErrorInfo;
	public static $lastRequestError;

	/** @var  callable|null */
	protected $signUpCallback;
	/** @var  callable|null */
	protected $signInCallback;
	/** @var  callable|null */
	protected $postCallback;

	public function __construct()
	{
		$this->profile = diModel::create(diTypes::o_auth2_profile);
	}

	/**
	 * @param integer|string $vendor
	 * @param array $options
	 * @return diOAuth2
	 * @throws Exception
	 */
	public static function create($vendor, $options = array())
	{
		$vendorName = diOAuth2Vendors::name(diOAuth2Vendors::id($vendor));

		if (!$vendorName)
		{
			throw new Exception("No OAuth2 vendor#" . $vendor . " found");
		}

		$className = self::existsFor($vendorName);

		if (!$className)
		{
			throw new Exception("OAuth2 class doesn't exist: " . $className);
		}

		/** @var diOAuth2 $o */
		$o = new $className($options);

		return $o;
	}

	/**
	 * @param string $vendorName
	 * @return bool|string
	 */
	public static function existsFor($vendorName)
	{
		if (isInteger($vendorName))
		{
			$vendorName = diOAuth2Vendors::name($vendorName);
		}

		$className = camelize("di_o_auth2_" . $vendorName . "_custom");

		if (!diLib::exists($className))
		{
			$className = camelize("di_o_auth2_" . $vendorName);

			if (!diLib::exists($className))
			{
				return false;
			}
		}

		return $className;
	}

	/**
	 * @return diOAuth2ProfileModel
	 */
	public function getProfile()
	{
		return $this->profile;
	}

	public function getVendorId()
	{
		return $this->vendorId;
	}

	public static function getWorkerPath($method, $params = array())
	{
		if (!is_array($params))
		{
			$params = array($params);
		}

		array_splice($params, 0, 0, array($method));

		return diLib::getWorkerPath("auth", "oauth2", $params);
	}

	public static function makeHttpRequest($url, $params = array(), $method = self::REQUEST_GET)
	{
		$ch = curl_init();

		switch ($method)
		{
			case self::REQUEST_GET:
				$url = static::makeUrl($url, $params);
				break;

			case self::REQUEST_POST:
				curl_setopt($ch, CURLOPT_POST, 1);
			    curl_setopt($ch, CURLOPT_POSTFIELDS, static::buildQuery($params));
				break;

			default:
				throw new \Exception("Unknown method '$method'");
				break;
		}

		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		//curl_setopt($ch, CURLOPT_PORT, $_SERVER['SERVER_PORT']);
		//curl_setopt($ch, CURLOPT_SSLVERSION, 2);
		//curl_setopt($ch, CURLOPT_DNS_USE_GLOBAL_CACHE, false);
		//curl_setopt($ch, CURLOPT_DNS_CACHE_TIMEOUT, 2);
		//curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
		curl_setopt($ch, CURLOPT_USERAGENT, 'diOAuth2');
		$query = curl_exec($ch);
		self::$lastRequestError = curl_error($ch);
		curl_close($ch);

		return $query;

		//return join('', file($url));
		//return file_get_contents($url);
	}

	public static function buildQuery($params)
	{
		return http_build_query($params); //urldecode(
	}

	public static function makeUrl($base, $params = [])
	{
		$glue = $params ? "?" : "";

		return $base . $glue . static::buildQuery($params);
	}

	protected function getBackUrlParts()
	{
		return array(
			"scheme" => diRequest::protocol(),
			"host" => diRequest::server("HTTP_HOST"),
			"path" => static::getWorkerPath(diOAuth2Vendors::name($this->vendorId), static::callbackParam),
		);
	}

	public function getBackUrl()
	{
		return http_build_url($this->getBackUrlParts());
	}

	public function getLoginUrl()
	{
		return static::makeUrl(static::loginUrlBase, $this->getLoginUrlParams());
	}

	public function getAuthUrl()
	{
		return static::makeUrl(static::authUrlBase, $this->getAuthUrlParams());
	}

	public function redirectToLogin()
	{
		header("Location: " . $this->getLoginUrl());

		return $this;
	}

	public function unlink()
	{
		if (diAuth::i()->authorized())
		{
			diAuth::i()->getUserModel()
				->set(diOAuth2Vendors::name($this->vendorId) . "_id", 0)
				->set(diOAuth2Vendors::name($this->vendorId) . "_login", "")
				->save();

			return true;
		}

		return false;
	}

	/**
	 * @param callable $signUpCallback
	 * @return $this
	 */
	public function setSignUpCallback($signUpCallback)
	{
		$this->signUpCallback = $signUpCallback;
		return $this;
	}

	/**
	 * @param callable $signInCallback
	 * @return $this
	 */
	public function setSignInCallback($signInCallback)
	{
		$this->signInCallback = $signInCallback;
		return $this;
	}

	/**
	 * @param callable $postCallback
	 * @return $this
	 */
	public function setPostCallback($postCallback)
	{
		$this->postCallback = $postCallback;
		return $this;
	}

	public function processReturn()
	{
		if ($this->isReturn())
		{
			$user = $this
				->retrieveProfile()
				->syncWithUser();

			if (!diAuth::i()->authorized())
			{
				diAuth::i()->forceAuthorize($user);
			}

			if (is_callable($callback = $this->postCallback))
			{
				$callback($this, $user);
			}
		}

		return $this;
	}

	protected function getUserModelByProfile()
	{
		if (diAuth::i()->authorized())
		{
			$user = diAuth::i()->getUserModel();
		}
		else
		{
			$q = array(
				diOAuth2Vendors::name($this->vendorId) . "_id='" . $this->getProfile()->getUid() . "'",
			);

			if ($this->getProfile()->hasEmail())
			{
				$q[] = "email='{$this->getProfile()->getEmail()}'";
			}

			$user = diCollection::create(diTypes::user, "WHERE " . join(" OR ", $q))->getFirstItem();
		}

		return $user;
	}

	/**
	 * @return \diCore\Entity\User\Model
	 * @throws Exception
	 */
	protected function syncWithUser()
	{
		if (!$this->getProfile()->exists())
		{
			throw new \Exception("No profile retrieved");
		}

		$user = $this->getUserModelByProfile();

		$this->storeProfileTo($user);

		return $user;
	}

	/**
	 * @param \diCore\Entity\User\Model $user
	 * @return $this
	 */
	protected function storeProfileTo(\diCore\Entity\User\Model $user)
	{
		$user->importDataFromOAuthProfile($this->getProfile());

		$signUp = !$user->hasId();

		if ($user->changed())
		{
			$user->save();

			if ($signUp)
			{
				if (is_callable($callback = $this->signUpCallback))
				{
					$callback($this, $user);
				}
			}
			else
			{
				if (is_callable($callback = $this->signInCallback))
				{
					$callback($this, $user);
				}
			}
		}

		return $this;
	}

	protected function setProfileError($m = null)
	{
		$this->profileRetrieveErrorInfo = $m;

		return $this;
	}

	protected function setProfileRawData($data = array())
	{
		$this->profileRawData = $data;

		if ($data)
		{
			var_debug("OAuth2 data " . diOAuth2Vendors::name($this->getVendorId()), $data);
		}

		return $this;
	}

	protected function downloadData()
	{
		$this
			->setProfileRawData()
			->setProfileError();

		return $this;
	}

	protected function retrieveProfile()
	{
		$this->getProfile()->setVendorId($this->getVendorId());

		$this->downloadData();

		if ($this->profileRawData)
		{
			$this->getProfile()->import(new diModel($this->profileRawData));
		}
		else
		{
			throw new \Exception($this->profileRetrieveErrorInfo . " " . self::$lastRequestError);
		}

		return $this;
	}

	protected function isReturn()
	{
		return !!diRequest::get("code");
	}

	protected function getLoginUrlParams()
	{
		return array(
			'client_id'     => static::appId,
			'response_type' => 'code',
			'redirect_uri'  => $this->getBackUrl(),
		);
	}

	protected function getAuthUrlParams()
	{
		return array(
			'client_id'     => static::appId,
			'client_secret' => static::secret,
			'code'          => diRequest::get("code"),
			'redirect_uri'  => $this->getBackUrl(),
		);
	}
}