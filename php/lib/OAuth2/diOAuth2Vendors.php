<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 31.12.15
 * Time: 10:44
 */
class diOAuth2Vendors extends diSimpleContainer
{
	const facebook = 1;
	const google = 2;
	const ok = 3;
	const twitter = 4;
	const vk = 5;
	const mailru = 6;
	const yandex = 7;
	const instagram = 8;

	public static $names = array(
		self::facebook => "facebook",
		self::google => "google",
		self::ok => "ok",
		self::twitter => "twitter",
		self::vk => "vk",
		self::mailru => "mailru",
		self::yandex => "yandex",
		self::instagram => "instagram",
	);

	public static $titles = array(
		self::facebook => "Facebook",
		self::google => "Google",
		self::ok => "Odnoklassniki",
		self::twitter => "Twitter",
		self::vk => 'VK',
		self::mailru => "Mail.ru",
		self::yandex => "Yandex",
		self::instagram => "Instagram",
	);

	public static function href($net = false, $login = false)
	{
		if (is_object($net) && !$login)
		{
			$user_r = $net;
			$net = self::get_social_net_name($user_r);

			$login = $net ? $user_r->$net : "---";
		}
		else
		{
			$user_r = false;
		}

		if ($net == "facebook" && !$login && $user_r)
		{
			$login = "app_scoped_user_id/".$user_r->{$net."_id"}."/";
		}

		switch ($net)
		{
			case "facebook":
				return "https://facebook.com/$login";

			case "vk":
				return "https://vk.com/$login";

			case "twitter":
				return "https://twitter.com/$login";

			default:
				return false;
		}
	}
}