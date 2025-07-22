<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 26.08.2017
 * Time: 12:08
 */

namespace diCore\Tool\Mail;

use diCore\Helper\StringHelper;
use diCore\Traits\BasicCreate;
use diCore\Tool\Logger;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\OAuth;
use League\OAuth2\Client\Provider\GenericProvider;

class Sender
{
    use BasicCreate;

    const transport = Transport::SENDMAIL;

    const defaultVendor = Vendor::google;
    const defaultUseSSL = true;
    const defaultFromName = 'Robot';
    const defaultFromEmail = 'noreply@domain.com';
    const defaultSmtpLogin = null; // needed for selectel
    const defaultSmtpPassword = null; // needed for selectel

    const secureSending = true;
    const debugSending = false;

    protected static $localHost = '127.0.0.1';
    protected static $localPort = 25;

    protected static $accounts = [
        /*
		 * simple format: only password
		'james@hetfield.com' => 'password if needed',
		 *
		 * or extended format: password + vendor
		'fred@durst.com' => [
			'vendor' => Vendor::yandex,
            'login' => 'if differs from email',
			'password' => 'password if needed',
			'useSSL' => true,
		],
         *
		 * if oauth2 used
		'chester@bennington.com' => [
            'authType' => 'xoauth2',
            'clientId' => 'application id',
            'tenantId' => 'directory (tenant) id',
            'secret' => 'secret value',
		],
		*/
    ];

    public static function getTransport()
    {
        return static::transport;
    }

    public static function getHost()
    {
        return static::$localHost;
    }

    public static function getPort()
    {
        return static::$localPort;
    }

    public static function getDebugSending()
    {
        return static::debugSending;
    }

    /*
     * each $attachments element should look like this:
     * [0] => [
     *      'filename' => 'filename.jpg',
     *      'content_id' => 'CID',
     *      'content_type' => 'image/jpeg',
     * 	    'data' => '[binary_data]', // or
     *      'path' => '/full/path/to/filename.jpg'
     * ],
     */
    public static function send(
        $from,
        $to,
        $subject,
        $bodyPlain,
        $bodyHtml,
        $attachments = [],
        $options = []
    ) {
        switch (static::getTransport()) {
            case Transport::SENDMAIL:
                return static::viaSendmail(
                    $from,
                    $to,
                    $subject,
                    $bodyPlain,
                    $bodyHtml,
                    $attachments,
                    $options
                );

            case Transport::SMTP:
                return static::viaSmtp(
                    $from,
                    $to,
                    $subject,
                    $bodyPlain,
                    $bodyHtml,
                    $attachments,
                    $options
                );

            case Transport::CUSTOM:
                return static::viaCustom(
                    $from,
                    $to,
                    $subject,
                    $bodyPlain,
                    $bodyHtml,
                    $attachments,
                    $options
                );

            default:
                throw new \Exception('Unknown mail transport: ' . static::transport);
        }
    }

    public static function viaCustom(
        $from,
        $to,
        $subject,
        $bodyPlain,
        $bodyHtml,
        $attachments = [],
        $options = []
    ) {
        throw new \Exception('Implement custom mail transport first');
    }

    public static function viaSendmail(
        $from,
        $to,
        $subject,
        $bodyPlain,
        $bodyHtml,
        $attachments = [],
        $options = []
    ) {
        return \diEmail::fastSend(
            $from,
            $to,
            $subject,
            $bodyPlain,
            $bodyHtml,
            $attachments,
            $options
        );
    }

    public static function viaSmtp(
        $from,
        $to,
        $subject,
        $bodyPlain,
        $bodyHtml,
        $attachments = [],
        $options = []
    ) {
        if (!is_array($to)) {
            $to = [$to];
        }

        $from = \diEmail::parseNameAndEmail(
            $from,
            static::defaultFromEmail,
            static::defaultFromName
        );

        $mail = static::createPhpMailerInstance($from['email']);

        $mail->setFrom($from['email'], $from['name']);
        $mail->WordWrap = 150;
        $mail->isHTML(!$bodyPlain);
        $mail->Subject = $subject;
        $mail->Body = $bodyPlain ?: $bodyHtml;

        if (!empty($options['replyTo'])) {
            $options['replyTo'] = \diEmail::parseNameAndEmail($options['replyTo']);

            $mail->addReplyTo(
                $options['replyTo']['email'],
                $options['replyTo']['name']
            );
        }

        foreach ($to as $recipient) {
            $recipient = \diEmail::parseNameAndEmail($recipient);

            $mail->addAddress($recipient['email'], $recipient['name']);
        }

        if ($attachments) {
            $attachmentIds = [];

            foreach ($attachments as $attachment) {
                while (
                    empty($attachment['content_id']) ||
                    in_array($attachment['content_id'], $attachmentIds)
                ) {
                    $attachment['content_id'] = static::generateAttachmentId();
                }

                $attachment = static::prepareAttachment($attachment);

                if (!empty($attachment['data'])) {
                    $mail->addStringEmbeddedImage(
                        $attachment['data'],
                        $attachment['content_id'],
                        $attachment['filename'],
                        'base64',
                        $attachment['content_type']
                    );
                } elseif (!empty($attachment['path'])) {
                    $mail->addEmbeddedImage(
                        $attachment['path'],
                        $attachment['content_id'],
                        $attachment['filename']
                    );
                } else {
                    Logger::getInstance()
                        ->log('attachment error: no file contents')
                        ->variable($attachment);
                }

                $attachmentIds[] = $attachment['content_id'];
            }
        }

        $res = $mail->send();

        if (!$res) {
            static::logError($mail->ErrorInfo);
        }

        return $res;
    }

    protected static function logError($message)
    {
        Logger::getInstance()->log(
            "Mail error: $message",
            'Tool/Mail/Sender',
            '-mail'
        );
    }

    protected static function generateAttachmentId()
    {
        return StringHelper::random(8);
    }

    protected static function prepareAttachment($attachment)
    {
        return $attachment;
    }

    /**
     * @param null|string $fromEmail
     * Don't forget to install PHPMailer: composer require phpmailer/phpmailer
     *
     * @return PHPMailer
     */
    public static function createPhpMailerInstance($fromEmail = null)
    {
        $mail = new PHPMailer();
        $mail->CharSet = 'UTF-8';
        $mail->Host = static::getHost();
        $mail->Port = static::getPort();
        $mail->XMailer = null;

        if (static::debugSending) {
            $mail->SMTPDebug = 3;
        }

        $mail->Debugoutput = function ($str, $level) {
            Logger::getInstance()->log($str, "Mailer/$level", '-mail');
        };

        if ($fromEmail) {
            $vendor = static::getAccountVendor($fromEmail);

            if ($vendor != Vendor::own) {
                $mail->Host = Vendor::smtpHost($vendor) ?: $mail->Host;
            }

            $mail->Password = static::getAccountPassword($fromEmail) ?: '';

            if (!$mail->Host) {
                throw new \Exception("SMTP host not defined for $fromEmail");
            }

            /*
			if (!$mail->Password) {
				throw new \Exception("SMTP password not defined for $fromEmail");
			}
			*/

            $mail->isSMTP();

            if ($mail->Mailer == 'smtp') {
                if ($vendor != Vendor::own) {
                    $mail->Port = Vendor::smtpPort($vendor, static::secureSending);
                }

                if (static::getAccountUseSSL($fromEmail)) {
                    switch ($vendor) {
                        case Vendor::yandex:
                            $mail->AuthType = 'LOGIN';
                            $mail->SMTPAuth = true;
                            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                            $mail->SMTPOptions = [
                                'ssl' => [
                                    'verify_peer' => false,
                                    'verify_peer_name' => false,
                                    'allow_self_signed' => true,
                                ],
                            ];
                            break;

                        case Vendor::masterhost:
                            $mail->SMTPAuth = true;
                            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                            $mail->SMTPOptions = [
                                'ssl' => [
                                    'verify_peer' => false,
                                    'verify_peer_name' => false,
                                    'allow_self_signed' => true,
                                ],
                            ];
                            break;

                        default:
                            $mail->SMTPAuth = true;
                            $mail->SMTPAutoTLS = true;
                            $mail->SMTPSecure = 'tls';
                            $mail->SMTPOptions = [
                                'ssl' => [
                                    'verify_peer' => true,
                                    'verify_depth' => 3,
                                    'allow_self_signed' => true,
                                ],
                            ];
                            break;
                    }
                } else {
                    $mail->SMTPAutoTLS = false;
                }

                if ($mail->Password) {
                    $mail->Username = static::getAccountLogin($fromEmail);
                }

                if (static::getAccountAuthType($fromEmail) === 'xoauth2') {
                    $clientId = static::getAccountProp($fromEmail, 'clientId');
                    $clientSecret = static::getAccountProp($fromEmail, 'secret');
                    $tenantId = static::getAccountProp($fromEmail, 'tenantId');

                    $provider = new GenericProvider([
                        'clientId' => $clientId,
                        'clientSecret' => $clientSecret,
                        'redirectUri' => static::getAccountProp($fromEmail, 'redirectUrl'),
                        'urlAuthorize' => "https://login.microsoftonline.com/$tenantId/oauth2/v2.0/authorize",
                        'urlAccessToken' => "https://login.microsoftonline.com/$tenantId/oauth2/v2.0/token",
                        'urlResourceOwnerDetails' => 'https://graph.microsoft.com/oidc/userinfo',
                        'scopes' => 'https://graph.microsoft.com/Mail.Send',
                    ]);

                    $oauth = new OAuth([
                        'provider' => $provider,
                        'clientId' => $clientId,
                        'clientSecret' => $clientSecret,
                        'userName' => $fromEmail,
                        'refreshToken' => static::getAccountProp($fromEmail, 'refreshToken'),
                    ]);

                    $mail->AuthType = 'XOAUTH2';
                    $mail->setOAuth($oauth);
                }
            }
        }

        return $mail;
    }

    protected static function getAccountUseSSL($email)
    {
        $a = static::$accounts[$email] ?? null;

        return $a['useSSL'] ?? static::defaultUseSSL;
    }

    protected static function getAccountVendor($email)
    {
        $a = static::$accounts[$email] ?? null;

        return $a['vendor'] ?? static::defaultVendor;
    }

    protected static function getAccountLogin($email)
    {
        $a = static::$accounts[$email] ?? null;

        return $a['login'] ?? (static::defaultSmtpLogin ?? $email);
    }

    protected static function getAccountPassword($email)
    {
        $a = static::$accounts[$email] ?? null;

        if (is_string($a) && $a) {
            return $a;
        }

        if (!$a || !is_array($a)) {
            return null;
        }

        return ($a['password'] ?? static::defaultSmtpPassword) ?: null;
    }

    protected static function getAccountAuthType($email)
    {
        $a = static::$accounts[$email] ?? null;

        return $a['authType'] ?? null;
    }

    protected static function getAccountProp($email, $prop)
    {
        $a = static::$accounts[$email] ?? null;

        return $a[$prop] ?? null;
    }
}
