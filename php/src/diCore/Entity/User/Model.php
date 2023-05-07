<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 27.12.2017
 * Time: 13:05
 */

namespace diCore\Entity\User;

use diCore\Base\CMS;
use diCore\Controller\Cabinet;
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
    const table = 'users';
    const slug_field_name = 'email';
    protected $table = 'users';

    const MIN_PASSWORD_LENGTH = 6;

    protected $instantSend = false;

    protected $mailBodyTemplates = [
        'sign_up' => 'emails/sign_up/customer',
        'password_forgotten' => 'emails/password_forgotten/customer',
        'password_changed' => 'emails/password_changed/customer',
    ];
    protected $mailSubjects = [
        'sign_up' => 'Регистрация',
        'password_forgotten' => 'Восстановление пароля',
        'password_changed' => 'Ваш пароль изменён',
    ];

    public function __toString()
    {
        return $this->getSlug();
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

    public static function generatePassword()
    {
        return get_unique_id(static::MIN_PASSWORD_LENGTH);
    }

    public static function isPasswordValid($rawPassword)
    {
        return strlen($rawPassword) >= static::MIN_PASSWORD_LENGTH;
    }

    public function getFullActivationHref()
    {
        return \diPaths::defaultHttp() . $this->getActivationHref();
    }

    public function getActivationHref()
    {
        return '/api/auth/activate/' .
            $this->getSlug() .
            '/' .
            $this->getActivationKey();
    }

    public function getFullEnterNewPasswordHref()
    {
        return \diPaths::defaultHttp() . $this->getEnterNewPasswordHref();
    }

    public function getEnterNewPasswordHref()
    {
        return '/' .
            CMS::ct('enter_new_password') .
            '/' .
            $this->getEmail() .
            '/' .
            $this->getActivationKey() .
            '/';
    }

    public function getLoginForAdminHref($back = '')
    {
        return '/api/auth/login_for_admin/' .
            $this->getId() .
            '/' .
            $this->getActivationKey() .
            ($back ? '?back=' . urlencode($back) : '');
    }

    public function importDataFromOAuthProfile(\diOAuth2ProfileModel $profile)
    {
        return $this;
    }

    public function setInitiatingValues()
    {
        $this->setPasswordExt(static::generatePassword())->setActivationKey(
            static::generateActivationKey()
        );

        return $this;
    }

    public function setMainValues($options = [])
    {
        return $this;
    }

    public function fastSignUp($options = [])
    {
        if ($options instanceof \diTwig) {
            $options = [
                'twig' => $options,
            ];
        }

        $options = extend(
            [
                'twig' => null,
            ],
            $options
        );

        if (!$options['twig']) {
            $options['twig'] = \diTwig::create();
        }

        $this->setInitiatingValues()
            ->setMainValues($options)
            ->save()
            ->notifyAboutRegistrationByEmail($options['twig']);

        return $this;
    }

    public function cabinetSubmitPassword()
    {
        $oldPassword = \diRequest::post('old_password', '');
        $newPassword = \diRequest::post('new_password', '');
        $newPassword2 = \diRequest::post('new_password2', '');

        if (
            !$oldPassword ||
            static::hash($oldPassword, 'db') != $this->getPassword()
        ) {
            throw new \Exception(Cabinet::L('set_password.wrong_old_password'));
        }

        if (!static::isPasswordValid($newPassword)) {
            throw new \Exception(Cabinet::L('set_password.password_not_valid'));
        }

        if ($newPassword != $newPassword2) {
            throw new \Exception(Cabinet::L('set_password.passwords_not_match'));
        }

        $this->setValidationNeeded(false)
            ->setPasswordExt($newPassword)
            ->save()
            ->setValidationNeeded(true);

        return $this;
    }

    protected function sendEmail(
        $from,
        $to,
        $subj,
        $body,
        $settings = [],
        $attaches = []
    ) {
        return $this->instantSend
            ? Queue::basicCreate()->addAndSend(
                $from,
                $to,
                $subj,
                $body,
                $settings,
                $attaches
            )
            : Queue::basicCreate()->add(
                $from,
                $to,
                $subj,
                $body,
                $settings,
                $attaches
            );
    }

    protected function getSender($reason)
    {
        return \diConfiguration::get('sender_email');
    }

    protected function getMailSubject($reason)
    {
        return $this->mailSubjects[$reason];
    }

    protected function getMailBodyData($reason)
    {
        return [
            'user' => $this,
            'title' => Config::getSiteTitle(),
            'domain' => Config::getMainDomain(),
        ];
    }

    protected function getMailInnerBody(\diTwig $twig, $reason)
    {
        return $twig->parse(
            $this->mailBodyTemplates[$reason],
            $this->getMailBodyData($reason)
        );
    }

    protected function getMailBody(\diTwig $twig, $reason)
    {
        /*
	    if (!$twig->exists($this->mailBodyTemplates[$reason])) {
	        return null;
        }
        */
        return $twig->parse(
            'emails/email_html_base',
            extend($this->getMailBodyData($reason), [
                'body' => $this->getMailInnerBody($twig, $reason),
            ])
        );
    }

    protected function getMailSettings(\diTwig $twig, $reason)
    {
        return [];
    }

    protected function getMailAttaches(\diTwig $twig, $reason)
    {
        return [];
    }

    public function notifyAboutRegistrationByEmail(\diTwig $twig)
    {
        if ($this->hasEmail()) {
            $this->sendEmail(
                $this->getSender('sign_up'),
                $this->getEmail(),
                $this->getMailSubject('sign_up'),
                $this->getMailBody($twig, 'sign_up'),
                $this->getMailSettings($twig, 'sign_up'),
                $this->getMailAttaches($twig, 'sign_up')
            );
        }

        return $this;
    }

    public function notifyAboutResetPasswordByEmail(\diTwig $twig)
    {
        if (!$this->hasActivationKey()) {
            $this->setActivationKey(static::generateActivationKey())->save();
        }

        if ($this->hasEmail()) {
            $twig->assign([
                'reset_href' => $this->getFullEnterNewPasswordHref(),
            ]);

            $this->sendEmail(
                $this->getSender('password_forgotten'),
                $this->getEmail(),
                $this->getMailSubject('password_forgotten'),
                $this->getMailBody($twig, 'password_forgotten'),
                $this->getMailSettings($twig, 'password_forgotten'),
                $this->getMailAttaches($twig, 'password_forgotten')
            );
        }

        return $this;
    }

    public function notifyAboutPasswordChangeByEmail(\diTwig $twig)
    {
        if (
            $this->hasEmail() &&
            ($body = $this->getMailBody($twig, 'password_changed'))
        ) {
            $this->sendEmail(
                $this->getSender('password_changed'),
                $this->getEmail(),
                $this->getMailSubject('password_changed'),
                $body,
                $this->getMailSettings($twig, 'password_changed'),
                $this->getMailAttaches($twig, 'password_changed')
            );
        }

        return $this;
    }

    public function deactivate()
    {
        $this->hardDestroy();

        return $this;
    }

    public function authenticateAfterEnteringNewPassword()
    {
        return true;
    }

    public function getCustomTemplateVars()
    {
        return extend(parent::getCustomTemplateVars(), [
            'full_activation_href' => $this->getFullActivationHref(),
            'activation_href' => $this->getActivationHref(),
            'full_enter_new_password_href' => $this->getFullEnterNewPasswordHref(),
            'enter_new_password_href' => $this->getEnterNewPasswordHref(),
        ]);
    }
}
