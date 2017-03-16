<?php

/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 03.01.16
 * Time: 17:39
 */

class diAuthController extends diBaseController
{
	const BACK_KEY = "oAuth2Back";

	public function loginAction()
	{
		$Auth = diAuth::create(false);

		return [
			'ok' => $Auth->authorized(),
		];
	}

	public function logoutAction()
	{
		$Auth = diAuth::create();
		$Auth->logout();

		$this->redirect();
	}

	public function oauth2Action()
	{
		$a = diOAuth2::create($this->param(0));

		if ($this->param(1) == diOAuth2::callbackParam)
		{
			$a->processReturn();

			$this->redirectTo(diSession::getAndKill(self::BACK_KEY) ?: "/");
		}
		elseif ($this->param(1) == diOAuth2::unlinkParam)
		{
			$this->defaultResponse([
				"ok" => $a->unlink(),
			]);
		}
		else
		{
			diSession::set(self::BACK_KEY, diRequest::get("back") ?: diRequest::server("HTTP_REFERER"));

			$a->redirectToLogin();
		}
	}

	public function activateAction()
	{
	}

	public function signUpAction()
	{
	}

	public function resetAction()
	{
	}
}
