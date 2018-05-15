<?php
class diEmail
{
	public static $childClassName = "diCustomEmail";
	public static $headersNL = "\n";

	protected $options = [
		"quotedPrintable" => false,
		"addReturnPathHeader" => true,
		"addReturnPathSendMailParam" => false,
	];

	protected $customOptions = [
	];

	public function __construct()
	{
		$this->options = extend($this->options, $this->customOptions);
	}

	public static function isValid($email)
	{
		return preg_match("/^[0-9a-z]([-_.0-9a-z])*@[0-9a-z]([-._]?[0-9a-z])*\.[a-z]{2,4}$/i", $email);
	}

	public function setOption($option, $value = null)
	{
		if (is_array($option) && is_null($value))
		{
			$this->options = extend($this->options, $option);
		}
		elseif (is_scalar($option))
		{
			$this->options[$option] = $value;
		}
		else
		{
			throw new Exception("Option name should be scalar");
		}

		return $this;
	}

	public function setOptions($options)
	{
		return $this->setOption($options);
	}

	public function getOption($option = null)
	{
		if (is_null($option))
		{
			return $this->options;
		}
		else
		{
			return isset($this->options[$option]) ? $this->options[$option] : null;
		}
	}

	public function getOptions()
	{
		return $this->getOption();
	}

	public static function parseNameAndEmail($nameAndEmail, $defaultEmail = '', $defaultName = '')
	{
		if (is_array($nameAndEmail))
		{
			return extend([
				'name' => $defaultName,
				'email' => $defaultEmail,
			], $nameAndEmail);
		}

		if (self::isValid($nameAndEmail))
		{
			$name = '';
			$email = $nameAndEmail;
		}
		else
		{
			preg_match("/^(.+)\s+\<(.+)\>$/", $nameAndEmail, $regs);

			if ($regs)
			{
				$name = $regs[1];
				$email = $regs[2];
			}
			else
			{
				$name = $defaultName;
				$email = $nameAndEmail;
			}
		}

		return [
			'name' => $name,
			'email' => $email,
		];
	}

	/*
		each element in attachment_ar should look like this:
		[0] => [
			"filename" => "filename.jpg",
			"content_type" => "image/jpeg",
			"data" => "[binary_data]",
		],
	*/
	public static function fastSend($from, $to, $subject, $message, $body_html, $attachments = [], $options = [])
	{
		$class = in_array(get_called_class(), ['\diEmail', 'diEmail']) ? static::$childClassName : get_called_class();

		if (!\diLib::exists($class))
		{
			$class = '\diEmail';
		}

		/** @var diEmail $m */
		$m = new $class();

		$options = extend([
			'replyTo' => '',
		], $options);

		$encoding = defined('DIMAILENCODING') ? DIMAILENCODING : 'UTF8';
		$mailEncodings = [
			"CP1251" => "CP1251",
			"UTF8" => "UTF-8",
		];

		$headerEnc = $mailEncodings[$encoding];
		$enc = \diCore\Data\Http\Charset::title(\diCore\Data\Http\Charset::id($encoding));

		$content_transfer_encoding = $m->getOption("quotedPrintable") ? "quoted-printable" : "8bit";

		// from
		$from = self::parseNameAndEmail($from);
		$fromName = $from['name'];
		$fromEmail = $from['email'];
		$fromEmailInBrackets = "<{$from['email']}>";

		// to
		$to = self::parseNameAndEmail($to);
		$toName = $to['name'];
		$toEmail = $to['email'];

		// encoding
		$from = $fromName ? "=?{$headerEnc}?B?" . base64_encode($fromName) . "?= <$fromEmail>" : $fromEmail;
		$to = $toName ? "=?{$headerEnc}?B?" . base64_encode($toName) . "?= <$toEmail>" : $toEmail;
		$subject = "=?{$headerEnc}?B?" . base64_encode($subject) . "?=";

		// making headers
		$headers = [
			"From: $from",
			"Reply-To: " . ($options['replyTo'] ?: $fromEmailInBrackets),
			"X-Sender: $fromEmailInBrackets",
			"X-Mailer: diEmail v2.5",
		];

		if ($m->getOption("addReturnPathHeader"))
		{
			$headers[] = "Return-Path: $fromEmailInBrackets";
		}

		if ($body_html || ($attachments && count($attachments)))
		{
			$mime_boundary = "==Multipart_Boundary_x".md5(mt_rand())."x";

			$headers[] = "MIME-Version: 1.0";
			$headers[] = "Content-Transfer-Encoding: binary";
			$headers[] = "Content-Type: multipart/mixed; boundary=\"{$mime_boundary}\"";

			if ($message)
			{
				if ($m->getOption("quotedPrintable"))
				{
					$message = quoted_printable_encode($message);
				}

				$message = "\n\n--{$mime_boundary}\n".
					"Content-Type: text/plain; charset=\"{$enc}\"\n".
					"Content-Transfer-Encoding: $content_transfer_encoding\n".
					"\n$message\n";
			}

			if ($body_html)
			{
				if ($m->getOption("quotedPrintable"))
				{
					$body_html = quoted_printable_encode($body_html);
				}

				$message .= "\n\n--{$mime_boundary}\n".
					"Content-Type: text/html; charset=\"{$enc}\"\n".
					"Content-Transfer-Encoding: $content_transfer_encoding\n".
					"\n$body_html\n";
			}

			//$message .= "\n--{$mime_boundary}--\n";

			if ($attachments)
			{
				foreach ($attachments as $attachment)
				{
					if (empty($attachment['data']) && !empty($attachment['path']))
					{
						$attachment['data'] = file_get_contents($attachment['path']);

						if (empty($attachment['filename']))
						{
							$attachment['filename'] = basename($attachment['path']);
						}

						if (empty($attachment['content_type']))
						{
							$attachment['content_type'] = \diCore\Helper\StringHelper::mimeTypeByFilename($attachment['filename']);
						}
					}

					$attachment["data"] = chunk_split(base64_encode($attachment["data"]));

					$cidLine = !empty($attachment["content_id"])
						? "Content-ID: <{$attachment["content_id"]}>\n"
						: "";

					$message .= "\n--{$mime_boundary}\n" .
						"Content-Disposition: inline; filename=\"{$attachment["filename"]}\"\n" .
						$cidLine .
						"Content-Type: {$attachment["content_type"]}; name=\"{$attachment["filename"]}\"\n" .
						"Content-Transfer-Encoding: base64\n" .
						"\n{$attachment["data"]}\n";
				}
			}

			$message .= "\n--{$mime_boundary}--\n";
		}
		else
		{
			$headers[] = "Content-Type: text/plain; charset=\"{$enc}\"";
			$headers[] = "Content-Transfer-Encoding: $content_transfer_encoding";

			if ($m->getOption("quotedPrintable"))
			{
				$message = quoted_printable_encode($message);
			}
		}

		$additionalParams = null;

		if ($m->getOption("addReturnPathSendMailParam"))
		{
			$additionalParams = "-f{$fromEmail}";
		}

		$result = mail($to, $subject, $message, join($m::$headersNL, $headers), $additionalParams);

		return $result;
	}
}