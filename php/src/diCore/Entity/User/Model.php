<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 27.12.2017
 * Time: 13:05
 */

namespace diCore\Entity\User;

use diCore\Base\CMS;
use diCore\Data\Config;
use diCore\Data\Types;
use diCore\Tool\Mail\Queue;

/**
 * Class Model
 * Methods list for IDE
 *
 * @method string	getPassword
 * @method string	getActivationKey
 *
 * @method bool hasPassword
 * @method bool hasActivationKey
 *
 * @method Model setPassword($value)
 * @method Model setActivationKey($value)
 */
class Model extends \diBaseUserModel
{
	const type = Types::user;
	protected $table = 'users';
	protected $slugFieldName = 'login';

	const MIN_PASSWORD_LENGTH = 6;

	protected $instantSend = false;

	protected $mailBodyTemplates = [
		'sign_up' => 'emails/sign_up/customer',
		'password_forgotten' => 'emails/password_forgotten/customer',
	];
	protected $mailSubjects = [
		'sign_up' => 'Регистрация',
		'password_forgotten' => 'Восстановление пароля',
	];

	public function __toString()
	{
		return $this->get($this->slugFieldName);
	}

	public function getAppearanceFeedForAdmin()
	{
		return [
			$this->get('name'),
			$this->get('first_name'),
			$this->get('last_name'),
			$this->get('login'),
			$this->get('email'),
		];
	}

	public static function generateActivationKey()
	{
		return get_unique_id();
	}

	public static function isActivationKeyValid($key)
	{
		return strlen($key) == 32;
	}

	public function importDataFromOAuthProfile(\diOAuth2ProfileModel $profile)
	{
		return $this;
	}

	public function setInitiatingValues()
	{
		$this
			->setPassword(get_unique_id(self::MIN_PASSWORD_LENGTH))
			->setActivationKey(static::generateActivationKey());

		return $this;
	}

	public function setMainValues()
	{
		return $this;
	}

	public function fastSignUp($options = [])
	{
		$options = extend([
			'twig' => null,
		], $options);

		$this
			->setInitiatingValues()
			->setMainValues()
			->save()
			->notifyAboutRegistrationByEmail($options['twig']);

		return $this;
	}

	public function cabinetSubmitPassword()
	{
		$oldPassword = \diRequest::post('old_password', '');
		$newPassword = \diRequest::post('new_password', '');
		$newPassword2 = \diRequest::post('new_password2', '');

		if (!$oldPassword || static::hash($oldPassword, 'db') != $this->getPassword())
		{
			throw new \Exception('Введён неверный старый пароль');
		}

		if (!$newPassword)
		{
			throw new \Exception('Введите новый пароль');
		}

		if ($newPassword != $newPassword2)
		{
			throw new \Exception('Введите одинаковые новые пароли');
		}

		$this
			->setPassword($newPassword)
			->save();

		return $this;
	}

	protected function sendEmail($from, $to, $subj, $body)
	{
		return $this->instantSend
			? Queue::basicCreate()->addAndSend($from, $to, $subj, $body)
			: Queue::basicCreate()->add($from, $to, $subj, $body);
	}

	protected function getSender()
	{
		return \diConfiguration::get('sender_email');
	}

	protected function getMailSubject($reason)
	{
		return $this->mailSubjects[$reason];
	}

	protected function getMailBody(\diTwig $twig, $reason)
	{
		$body = $twig->parse($this->mailBodyTemplates[$reason], [
			'user' => $this,
			'title' => Config::getSiteTitle(),
			'domain' => Config::getMainDomain(),
		]);

		$html = $twig->parse('emails/email_html_base', [
			'body' => $body,
			'title' => Config::getSiteTitle(),
			'domain' => Config::getMainDomain(),
		]);

		return $html;
	}

	public function notifyAboutRegistrationByEmail(\diTwig $twig)
	{
		if ($this->hasEmail())
		{
			$this->sendEmail($this->getSender(), $this->getEmail(),
				$this->getMailSubject('sign_up'), $this->getMailBody($twig, 'sign_up'));
		}

		return $this;
	}

	public function notifyAboutResetPasswordByEmail(\diTwig $twig)
	{
		if (!$this->hasActivationKey())
		{
			$this
				->setActivationKey(static::generateActivationKey())
				->save();
		}

		if ($this->hasEmail())
		{
			$twig->assign([
				'reset_href' => \diPaths::defaultHttp() . '/' . CMS::ct('enter_new_password') .
					'/' . $this->getEmail() . '/' . $this->getActivationKey() . '/',
			]);

			$this->sendEmail($this->getSender(), $this->getEmail(),
				$this->getMailSubject('password_forgotten'), $this->getMailBody($twig, 'password_forgotten'));
		}

		return $this;
	}
}