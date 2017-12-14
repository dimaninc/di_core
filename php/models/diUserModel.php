<?php
/**
 * Class diUserCustomModel
 * Methods list for IDE
 *
 * @method string	getPassword
 * @method string	getActivationKey
 *
 * @method bool hasPassword
 * @method bool hasActivationKey
 *
 * @method diUserModel setPassword($value)
 * @method diUserModel setActivationKey($value)
 */
class diUserModel extends diBaseUserModel
{
	const type = diTypes::user;
	protected $table = "users";
	protected $slugFieldName = "login";

	public function __toString()
	{
		return $this->get($this->slugFieldName);
	}

	public function appearanceForAdmin()
	{
		return $this->exists()
			? join(', ', array_filter([
				$this->get('name'),
				$this->get('first_name'),
				$this->get('last_name'),
				$this->get('login'),
				$this->get('email'),
			])) . " [<a href='{$this->getAdminHref()}'>ссылка</a>]"
			: '---';
	}

	public static function generateActivationKey()
	{
		return get_unique_id();
	}

	public static function isActivationKeyValid($key)
	{
		return strlen($key) == 32;
	}

	public function importDataFromOAuthProfile(diOAuth2ProfileModel $profile)
	{
		return $this;
	}
}