<?php

class diAdminUser extends diAuth
{
	const CUSTOM_CLASS_NAME = "diCustomAdminUser";

	const COOKIE_USER_ID = "auth_admin_id";
	const COOKIE_SECRET = "auth_admin_secret";
	const COOKIE_REMEMBER = "auth_admin_remember";

	const COOKIE_PATH = "/_admin/";

	const POST_LOGIN_FIELD = "admin_login";
	const POST_PASSWORD_FIELD = "admin_password";
	const POST_REMEMBER_FIELD = "admin_remember";

	const USER_MODEL_TYPE = diTypes::admin;

	const SESSION_USER_ID_FIELD = "admin_id";

	/**
	 * @return diAdminModel
	 */
	public function getModel()
	{
		return $this->getUserModel();
	}

	public function authorized()
	{
		return $this->authorizedForSetup() || $this->reallyAuthorized();
	}

	public function authorizedForSetup()
	{
		return defined("FIRST_LOGIN");
	}

	protected function updateAuthorizedUserData()
	{
		// parent's method updates user model

		return $this;
	}
}