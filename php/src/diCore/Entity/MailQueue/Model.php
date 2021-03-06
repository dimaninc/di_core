<?php
/**
 * Created by diModelsManager
 * Date: 11.09.2015
 * Time: 11:48
 */

namespace diCore\Entity\MailQueue;
use diCore\Data\Types;

/**
 * Class Model
 * Methods list for IDE
 *
 * @method string	getSender
 * @method string	getRecipient
 * @method integer	getRecipientId
 * @method string	getReplyTo
 * @method string	getSubject
 * @method string	getBody
 * @method integer	getPlainBody
 * @method string	getAttachment
 * @method string	getIncutIds
 * @method integer	getVisible
 * @method integer	getSent
 * @method integer	getNewsId
 * @method string	getDate
 * @method string	getSettings
 *
 * @method bool hasSender
 * @method bool hasRecipient
 * @method bool hasRecipientId
 * @method bool hasReplyTo
 * @method bool hasSubject
 * @method bool hasBody
 * @method bool hasPlainBody
 * @method bool hasAttachment
 * @method bool hasIncutIds
 * @method bool hasVisible
 * @method bool hasSent
 * @method bool hasNewsId
 * @method bool hasDate
 * @method bool hasSettings
 *
 * @method Model setRecipientId($value)
 * @method Model setSubject($value)
 * @method Model setBody($value)
 * @method Model setPlainBody($value)
 * @method Model setAttachment($value)
 * @method Model setIncutIds($value)
 * @method Model setVisible($value)
 * @method Model setSent($value)
 * @method Model setNewsId($value)
 * @method Model setDate($value)
 * @method Model setSettings($value)
 */
class Model extends \diModel
{
	const type = Types::mail_queue;
	protected $table = 'mail_queue';

	const BODY_TYPE_HTML = 0;
	const BODY_TYPE_PLAIN_TEXT = 1;

	public static $bodyTypes = [
		self::BODY_TYPE_HTML => 'HTML',
		self::BODY_TYPE_PLAIN_TEXT => 'Просто текст',
	];

	const DIRECTION_INCOMING = 0;
	const DIRECTION_OUTGOING = 1;

	public static $email_incoming_ar = [
		self::DIRECTION_INCOMING => 'Исходящее',
		self::DIRECTION_OUTGOING => 'Входящее',
	];

	/**
	 * @param string $field
	 * @param string|array $email
	 * @param string|null $name
	 * @return Model
	 */
	protected function setEmailNameField($field, $email, $name = null)
	{
		if ($name === null)
		{
			if (is_array($email))
			{
				if (!empty($email['name']))
				{
					$sender = sprintf('%s <%s>', $email['name'], $email['email']);
				}
				else
				{
					$sender = $email['email'];
				}
			}
			else
			{
				$sender = $email;
			}
		}
		else
		{
			$sender = sprintf('%s <%s>', $name, $email);
		}

		return $this->set($field, $sender);
	}

	public function setSender($email, $name = null)
	{
		return $this->setEmailNameField('sender', $email, $name);
	}

	public function setRecipient($email, $name = null)
	{
		return $this->setEmailNameField('recipient', $email, $name);
	}

	public function setReplyTo($email, $name = null)
	{
		return $this->setEmailNameField('reply_to', $email, $name);
	}
}