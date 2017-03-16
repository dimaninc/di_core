<?php

/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 24.03.2016
 * Time: 15:12
 */
class diCookie
{
	const DEBUG = false;

	/**
	 * Sets the cookie
	 *
	 * @param string $name
	 * @param mixed $value
	 * @param array $optionsOrDate        Options array or expire date
	 * @param string|null $path
	 * @param string|null $domain
	 */
	public static function set($name, $value = null, $optionsOrDate = null, $path = null, $domain = null)
	{
		if (is_array($optionsOrDate))
		{
			$options = $optionsOrDate;
		}
		else
		{
			$options = array(
				"expire" => $optionsOrDate,
				"path" => $path,
				"domain" => $domain,
			);
		}

		$options = extend(array(
			"expire" => null,
			"path" => $path,
			"domain" => $domain,
			"secure" => null,
			"httpOnly" => null,
		), $options);

		if ($options["expire"] !== null)
		{
			$options["expire"] = diDateTime::timestamp($options["expire"]);
		}

		if ($options["domain"] === true)
		{
			$options["domain"] = static::getDomainForAll();
		}

		setcookie($name, $value, $options["expire"], $options["path"], $options["domain"], $options["secure"], $options["httpOnly"]);

		if (static::DEBUG)
		{
			if ($options["expire"])
			{
				$options["expire"] = diDateTime::format("d.m.Y H:i:s", $options["expire"]);
			}

			static::log("Cookie set: '$name' = '$value', " . var_export($options, true));
		}
	}

	/**
	 * Reads the cookie
	 *
	 * @param string $name
	 * @return mixed
	 */
	public static function get($name)
	{
		if (static::DEBUG)
		{
			static::log("Cookie get: '$name'");
		}

		return diRequest::cookie($name);
	}

	/**
	 * Removes the cookie
	 *
	 * @param $name
	 * @param null $path
	 * @param null $domain
	 * @param array $options
	 */
	public static function remove($name, $path = null, $domain = null, $options = array())
	{
		if (static::DEBUG)
		{
			static::log("Cookie remove: '$name'");
		}

		static::set($name, "", $options, $path, $domain);
	}

	/**
	 * @return string
	 */
	public static function getDomainForAll()
	{
		$host = diRequest::server("HTTP_HOST");

		return substr($host, 0, 4) == "www."
			? substr($host, 3)
			: "." . $host;
	}

	protected static function log($message)
	{
	}
}