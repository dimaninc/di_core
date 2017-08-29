<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 26.08.2017
 * Time: 12:08
 */

namespace diCore\Tool\Mail;

use diCore\Traits\BasicCreate;
use diCore\Tool\Logger;
use PHPMailer\PHPMailer\PHPMailer;

class Sender
{
	use BasicCreate;

	const transport = Transport::SENDMAIL;

	const defaultVendor = Vendor::google;
	const defaultFromName = 'Robot';
	const defaultFromEmail = 'noreply@domain.com';

	const secureSending = true;
	const debugSending = false;

	protected static $localHost = '127.0.0.1';

	protected static $accounts = [
		/*
		 * simple format: only password
		'james@hetfield.com' => 'password',
		 *
		 * or extended format: password + vendor
		'fred@durst.com' => [
			'vendor' => Vendor::yandex,
			'password' => 'password',
		],
		*/
	];

	public static function getTransport()
	{
		return static::transport;
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
	public static function send($from, $to, $subject, $bodyPlain, $bodyHtml, $attachments = [], $options = [])
	{
		switch (static::getTransport())
		{
			case Transport::SENDMAIL:
				return static::viaSendmail($from, $to, $subject, $bodyPlain, $bodyHtml, $attachments, $options);
				break;

			case Transport::SMTP:
				return static::viaSmtp($from, $to, $subject, $bodyPlain, $bodyHtml, $attachments, $options);
				break;

			default:
				throw new \Exception('Unknown mail transport: ' . static::transport);
		}
	}

	public static function viaSendmail($from, $to, $subject, $bodyPlain, $bodyHtml, $attachments = [], $options = [])
	{
		return \diEmail::fastSend($from, $to, $subject, $bodyPlain, $bodyHtml, $attachments, $options);
	}

	public static function viaSmtp($from, $to, $subject, $bodyPlain, $bodyHtml, $attachments = [], $options = [])
	{
		if (!is_array($to))
		{
			$to = [$to];
		}

		if (!is_array($from))
		{
			if (preg_match("/^(.+)\s+<(.+)>$/", $from, $matches))
			{
				$from = [
					'name' => $matches[1],
					'email' => $matches[2],
				];
			}
			else
			{
				$from = [
					'email' => $from,
				];
			}
		}

		$from = extend([
			'name' => static::defaultFromName,
			'email' => static::defaultFromEmail,
		], $from);

		$mail = static::createPhpMailerInstance($from['email']);

		$mail->setFrom($from['email'], $from['name']);
		$mail->WordWrap = 150;
		$mail->isHTML(!$bodyPlain);
		$mail->Subject = $subject;
		$mail->Body = $bodyPlain ?: $bodyHtml;

		if (!empty($options['replyTo']))
		{
			if (is_array($options['replyTo']))
			{
				$mail->addReplyTo($options['replyTo']['email'], $options['replyTo']['name']);
			}
			else
			{
				$mail->addReplyTo($options['replyTo']);
			}
		}

		foreach ($to as $recipient)
		{
			$mail->addAddress($recipient);
		}

		if ($attachments)
		{
			foreach ($attachments as $attachment)
			{
				if (!empty($attachment['data']))
				{
					$mail->addStringEmbeddedImage(
						$attachment['data'],
						!empty($attachment['content_id']) ? $attachment['content_id'] : '',
						$attachment['filename'],
						'base64',
						$attachment['content_type']
					);
				}
				elseif (!empty($attachment['path']))
				{
					$mail->addEmbeddedImage(
						$attachment['path'],
						!empty($attachment['content_id']) ? $attachment['content_id'] : '',
						$attachment['filename']
					);
				}
				else
				{
					Logger::getInstance()
						->log('attachment error: no file contents')
						->variable($attachment);
				}
			}
		}

		$res = $mail->send();

		if (!$res)
		{
			Logger::getInstance()->log('mail error: ' . $mail->ErrorInfo);
		}

		return $res;
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
		$mail->Host = static::$localHost;

		if (static::debugSending)
		{
			$mail->SMTPDebug = 3;
		}

		$mail->Debugoutput = function($str, $level) {
			Logger::getInstance()->log($str, 'Mailer/' . $level);
		};

		if ($fromEmail)
		{
			$vendor = static::getAccountVendor($fromEmail);

			$mail->Host = Vendor::smtpHost($vendor);
			$mail->Password = static::getAccountPassword($fromEmail);

			if (!$mail->Host)
			{
				throw new \Exception('SMTP host not defined for ' . $fromEmail);
			}

			if (!$mail->Password)
			{
				throw new \Exception('SMTP password not defined for ' . $fromEmail);
			}

			$mail->isSMTP();

			if ($mail->Mailer == 'smtp')
			{
				$mail->SMTPAuth = true;
				$mail->SMTPSecure = 'tls';
				$mail->Port = Vendor::smtpPort($vendor, static::secureSending);
				$mail->Username = $fromEmail;

				$mail->SMTPOptions = [
					'ssl' => [
						'verify_peer' => true,
						'verify_depth' => 3,
						'allow_self_signed' => true,
					],
				];
			}
		}

		return $mail;
	}

	protected static function getAccountVendor($email)
	{
		$a = isset(static::$accounts[$email])
			? static::$accounts[$email]
			: null;

		return isset($a['vendor'])
			? $a['vendor']
			: static::defaultVendor;
	}

	protected static function getAccountPassword($email)
	{
		$a = isset(static::$accounts[$email])
			? static::$accounts[$email]
			: null;

		return isset($a['password'])
			? $a['password']
			: $a;
	}
}