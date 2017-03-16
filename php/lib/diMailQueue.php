<?php
/*
	// dimaninc

	// 2010/06/22
		* plain/html body switcher added
*/

class diMailQueue
{
	const className = "diCustomMailQueue";

	private $table;
	private $incuts_ar;
	private $global_use_queue;
	/**
	 * @var diDB
	 */
	private $db;

	public function __construct($table = "mail_queue", $global_use_queue = false) //if false - messages get sent immediately
	{
	    global $db;

		$this->table = $table;
		$this->incuts_ar = array();
		$this->global_use_queue = $global_use_queue;
		$this->db = $db;
	}

	/**
	 * @return diMailQueue
	 */
	public static function create()
	{
		$className = diLib::exists(self::className)
			? self::className
			: get_called_class();

		$o = new $className();

		return $o;
	}

	/**
	 * @return diDB
	 */
	private function getDb()
	{
		return $this->db;
	}

	public function getTable()
	{
		return $this->table;
	}

	public function add($from, $to, $subj, $body, $plain_body = false, $attachment_ar = [], $incut_ids = "")
	{
		$ar = [
			"sender" => $this->getDb()->escape_string($from),
			"recipient" => $this->getDb()->escape_string($to),
			"subject" => $this->getDb()->escape_string($subj),
			"body" => $this->getDb()->escape_string($body),
			"plain_body" => $plain_body,
			"incut_ids" => $incut_ids,
			"sent" => 0,
		];

		if (!is_array($attachment_ar) && $attachment_ar)
		{
			$ar["attachment"] = "";
			$ar["news_id"] = (int)$attachment_ar;
		}
		else
		{
			$ar["attachment"] = addslashes(serialize($attachment_ar));
		}

		if (strlen($ar["subject"]) > 254)
		{
			$ar["subject"] = substr($ar["subject"], 0, 254);
		}

		return $this->getDb()->insert($this->table, $ar);
	}

	public function add_and_send($from, $to, $subj, $body, $plain_body = false, $attachment_ar = [])
	{
		$id = $this->add($from, $to, $subj, $body, $plain_body, $attachment_ar);

		return $this->send($id);
	}

	public function add_and_may_be_send($from, $to, $subj, $body, $plain_body = false, $attachment_ar = [])
	{
		$id = $this->add($from, $to, $subj, $body, $plain_body, $attachment_ar);

		if (!$this->global_use_queue)
		{
			return $this->send($id);
		}

		return $id;
	}

	public function process_incuts(&$r)
	{
		$r->body = stripslashes($r->body);

		$incut_ar = $r->incut_ids ? explode(",", $r->incut_ids) : array();
		if ($incut_ar)
		{
			foreach ($incut_ar as $incut_id)
			{
				$token = self::incut_token($incut_id);

				if (!isset($this->incuts_ar[$token]))
				{
					$incut_r = $this->getDb()->r("mail_incuts", "WHERE id='$incut_id'");

					if ($incut_r)
						$this->incuts_ar[$token] = $incut_r->content;
				}
			}

			$r->body = str_replace(array_keys($this->incuts_ar), array_values($this->incuts_ar), $r->body);
		}
	}

	public function getAttachment($r)
	{
		if (!empty($r->news_id))
		{
			if (!isset($GLOBALS["___NEWS_ATTACHMENT"]))
			{
				$at_r = $this->getDb()->r("news_attaches", "WHERE news_id='$r->news_id'");

				$GLOBALS["___NEWS_ATTACHMENT"] = unserialize($at_r->attachment);
			}

			return $GLOBALS["___NEWS_ATTACHMENT"];
		}
		else
		{
			return unserialize($r->attachment);
		}
	}

	// by default the first message is being sent
	public function send($id = 0)
	{
		$q = $id ? " and id='$id'" : "";
		$r = $this->getDb()->r($this->table, "WHERE visible='1' and sent='0'$q");

		if ($r)
		{
			$this->getDb()->update($this->table, array("visible" => 0), $r->id);

			$attachment = $this->getAttachment($r);
			$this->process_incuts($r);

			$body = $r->plain_body ? $r->body : "";
			$body_html = $r->plain_body ? "" : $r->body;

			$result = $this->sendWorker($r->sender, $r->recipient, $r->subject, $body, $body_html, $attachment);

			if ($result)
			{
				$this->set_message_sent($r->id);

				return true;
			}
		}

		return false;
	}

	public function sendWorker($from, $to, $subject, $message, $body_html, $attachment_ar = [], $options = [])
	{
		if (!is_array($to))
		{
			$to = [$to];
		}

		$res = true;

		foreach ($to as $singleTo)
		{
			if (!static::sendEmail($from, $singleTo, $subject, $message, $body_html, $attachment_ar, $options))
			{
				$res = false;
			}
		}

		return $res;
	}

	public static function sendEmail($from, $to, $subject, $message, $body_html, $attachment_ar = [], $options = [])
	{
		return diEmail::fastSend($from, $to, $subject, $message, $body_html, $attachment_ar, $options);
	}

	public function send_all($limit = 0)
	{
		$rs = $this->getDb()->rs($this->table, "WHERE visible='1' and sent='0' ORDER BY id ASC");
		$counter = 0;

		while ($r = $this->getDb()->fetch($rs))
		{
			$this->getDb()->update($this->table, array("visible" => 0), $r->id);

			$attachment = $this->getAttachment($r);
			$this->process_incuts($r);

			$body = $r->plain_body ? $r->body : "";
			$body_html = $r->plain_body ? "" : $r->body;

			$rez = $this->sendWorker($r->sender, $r->recipient, $r->subject, $body, $body_html, $attachment);

			if ($rez)
			{
				$this->set_message_sent($r->id);
			}

			if ($limit && ++$counter > $limit)
			{
				break;
			}
		}
	}

	private function set_message_sent($id)
	{
		$this->getDb()->delete($this->table, $id);
	}

	public function send_all_safe($limit = 0)
	{
		$i = -1;
		do {$i++; if ($limit && $i > $limit) break; } while ($this->send());

		return $i;
	}

	public static function incut_token($id)
	{
		return "{{{-MAIL-INCUT-{$id}-}}}";
	}

	public function setVisible()
	{
		$this->getDb()->update($this->getTable(), [
			"visible" => 1,
		], "WHERE visible='0'");
	}
}