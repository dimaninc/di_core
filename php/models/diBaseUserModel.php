<?php

class diBaseUserModel extends diModel
{
	protected $table = '';
	const slug_field_name = 'login';

	public function active()
	{
		return !!intval($this->get("active"));
	}

	public function hashPassword($password, $field = 'password')
	{
		if ($password)
		{
			$this->set($field, static::hash($password, 'db'));
		}

		return $this;
	}

	/**
	 * @param string $password
	 * @param string $source (raw|db|cookie)
	 *
	 * return boolean
	 */
	public function isPasswordOk($password, $source = "raw")
	{
		switch ($source)
		{
			default:
			case "raw":
				return self::hash($password, "db") == $this->get("password");

			case "db":
				return $password == $this->get("password");

			case "cookie":
				return $password == self::convertPasswordFromDbToCookie($this->get("password"));
		}
	}

	public static function convertPasswordFromDbToCookie($password)
	{
		return md5($password);
	}

	/**
	 * @param string $password
	 * @param string $destination (raw|db|cookie)
	 *
	 * @return string
	 */
	public static function hash($password, $destination = "raw", $source = "raw")
	{
		if ($destination == $source)
		{
			return $password;
		}

		switch ($destination)
		{
			case "raw":
				return $password;

			case "db":
				return md5($password);

			case "cookie":
				if ($source == "raw")
				{
					$password = self::hash($password, "db");
				}

				return self::convertPasswordFromDbToCookie($password);
		}

		return null;
	}
}