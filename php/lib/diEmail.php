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
		return preg_match("/^[0-9a-z]([-_.]?[0-9a-z])*@[0-9a-z]([-._]?[0-9a-z])*\.[a-z]{2,4}$/i", $email);
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

	/*
		each element in attachment_ar should look like this:
		[0] => [
			"filename" => "filename.jpg",
			"content_type" => "image/jpeg",
			"data" => "[binary_data]",
		],
	*/
	public static function fastSend($from, $to, $subject, $message, $body_html, $attachment_ar = [], $options = [])
	{
		global $html_encodings_ar;

		$class = get_called_class() == "diEmail" ? static::$childClassName : get_called_class();

		if (!diLib::exists($class))
		{
			$class = "diEmail";
		}

		/** @var diEmail $m */
		$m = new $class();

		$encoding = defined('DIMAILENCODING') ? DIMAILENCODING : 'UTF8';
		$mail_encodings_ar = [
			"CP1251" => "CP1251",
			"UTF8" => "UTF-8",
		];

		$headerEnc = $mail_encodings_ar[$encoding];
		$enc = $html_encodings_ar[$encoding];

		$content_transfer_encoding = $m->getOption("quotedPrintable") ? "quoted-printable" : "8bit";

		// from
		if (self::isValid($from))
		{
			$from_email_brackets = "<$from>";

			$from_name = "";
			$from_email = $from;
		}
		else
		{
			preg_match("/^(.+) \<(.+)\>$/", $from, $from_regs);

			if ($from_regs)
			{
				$from_email_brackets = "<".$from_regs[2].">";

				$from_name = $from_regs[1];
				$from_email = $from_regs[2];
			}
			else
			{
				$from_email_brackets = $from;

				$from_name = "";
				$from_email = $from;
			}
		}
		//

		// to
		preg_match("/^(.+) \<(.+)\>$/", $to, $to_regs);

		if (!empty($to_regs))
		{
			$to_name = $to_regs[1];
			$to_email = $to_regs[2];
		}
		else
		{
			$to_name = "";
			$to_email = $to;
		}
		//

		// encoding
		$from = $from_name ? "=?{$headerEnc}?B?" . base64_encode($from_name) . "?= <$from_email>" : $from_email;
		$to = $to_name ? "=?{$headerEnc}?B?" . base64_encode($to_name) . "?= <$to_email>" : $to_email;
		$subject = "=?{$headerEnc}?B?" . base64_encode($subject) . "?=";

		// making headers
		$headersAr = [
			"From: $from",
			"Reply-To: $from_email_brackets",
			"X-Sender: $from_email_brackets",
			"X-Mailer: diEmail v2.5",
		];

		if ($m->getOption("addReturnPathHeader"))
		{
			$headersAr[] = "Return-Path: $from_email_brackets";
		}

		if ($body_html || ($attachment_ar && count($attachment_ar)))
		{
			$mime_boundary = "==Multipart_Boundary_x".md5(mt_rand())."x";

			$headersAr[] = "MIME-Version: 1.0";
			$headersAr[] = "Content-Transfer-Encoding: binary";
			$headersAr[] = "Content-Type: multipart/mixed; boundary=\"{$mime_boundary}\"";

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

			if ($attachment_ar)
			{
				foreach ($attachment_ar as $attachment)
				{
					$attachment["data"] = chunk_split(base64_encode($attachment["data"]));

					$cid_line = !empty($attachment["content_id"]) ? "Content-ID: <{$attachment["content_id"]}>\n" : "";

					$message .= "\n--{$mime_boundary}\n".
						"Content-Disposition: inline; filename=\"{$attachment["filename"]}\"\n".
						$cid_line.
						"Content-Type: {$attachment["content_type"]}; name=\"{$attachment["filename"]}\"\n".
						"Content-Transfer-Encoding: base64\n".
						"\n{$attachment["data"]}\n";
				}
			}

			$message .= "\n--{$mime_boundary}--\n";
		}
		else
		{
			$headersAr[] = "Content-Type: text/plain; charset=\"{$enc}\"";
			$headersAr[] = "Content-Transfer-Encoding: $content_transfer_encoding";

			if ($m->getOption("quotedPrintable"))
			{
				$message = quoted_printable_encode($message);
			}
		}

		$additionalParams = null;

		if ($m->getOption("addReturnPathSendMailParam"))
		{
			$additionalParams = "-f{$from_email}";
		}

		$result = mail($to, $subject, $message, join($m::$headersNL, $headersAr), $additionalParams);

		return $result;
	}
}