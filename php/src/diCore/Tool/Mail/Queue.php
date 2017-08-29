<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 26.08.2017
 * Time: 12:08
 */

namespace diCore\Tool\Mail;

use diCore\Data\Types;
use diCore\Entity\MailIncut\Model as IncutModel;
use diCore\Entity\MailIncut\Collection as IncutCollection;
use diCore\Entity\MailIncut\Type;
use diCore\Entity\MailQueue\Collection;
use diCore\Entity\MailQueue\Model;
use diCore\Tool\Logger;
use diCore\Traits\BasicCreate;

class Queue
{
	use BasicCreate;

	const INSTANT_SEND = false;
	const SAFE_SEND_ERRORS_ALLOWED = 5;

	private $incuts = [];

	private $lastError = Error::NONE;

	public function add($from, $to, $subject, $body, $plainBody = false, $attachments = [], $incutIds = '')
	{
		/** @var Model $model */
		$model = \diModel::create(Types::mail_queue);
		$model
			->setSender($from)
			->setRecipient($to)
			->setSubject($subject)
			->setBody($body)
			->setPlainBody($plainBody)
			->setIncutIds($incutIds)
			->setSent(0);

		if (!is_array($attachments) && $attachments)
		{
			$model
				->setAttachment('')
				->setNewsId((int)$attachments);
		}
		else
		{
			$model
				->setAttachment(serialize($attachments));
		}

		$model->save();

		return $model->getId();
	}

	public function addAndSend($from, $to, $subject, $body, $plainBody = false, $attachments = [])
	{
		$id = $this->add($from, $to, $subject, $body, $plainBody, $attachments);

		$this->send($id);

		return $this;
	}

	public function addAndMayBeSend($from, $to, $subject, $body, $plainBody = false, $attachments = [])
	{
		$id = $this->add($from, $to, $subject, $body, $plainBody, $attachments);

		if (static::INSTANT_SEND)
		{
			$this->send($id);
		}

		return $this;
	}

	public function processIncuts(Model $model)
	{
		$incutIds = $model->hasIncutIds() ? explode(',', $model->getIncutIds()) : [];

		if ($incutIds)
		{
			foreach ($incutIds as $incutId)
			{
				$incutId = (int)$incutId;
				$token = static::incutToken($incutId);

				if ($incutId && !isset($this->incuts[$token]))
				{
					/** @var IncutModel $incut */
					$incut = \diModel::create(Types::mail_incut, $incutId);

					if ($incut->exists())
					{
						$this->incuts[$token] = $incut->getContent();
					}
				}
			}

			$model->setBody(str_replace(array_keys($this->incuts), array_values($this->incuts), $model->getBody()));
		}

		return $this;
	}

	public function getAttachments(Model $model)
	{
		if ($model->hasNewsId())
		{
			/** @var IncutCollection $col */
			$col = \diCollection::create(Types::mail_incut);
			$col
				->filterByTargetType(Types::news)
				->filterByTargetId($model->getNewsId())
				->filterByType(Type::binary_attachment);

			/** @var \diCore\Entity\MailIncut\Model $incut */
			$incut = $col->getFirstItem();

			return $incut->exists() && $incut->hasContent()
				? unserialize($incut->getContent())
				: [];
		}
		else
		{
			return unserialize($model->getAttachment());
		}
	}

	// by default the first message is being sent
	public function send($id = 0)
	{
		/** @var Collection $messages */
		$messages = Collection::createActual();

		if ($id)
		{
			$messages
				->filterById($id);
		}

		/** @var Model $message */
		$message = $messages->getFirstItem();

		if ($message->exists())
		{
			return $this->sendMessage($message);
		}

		return false;
	}

	public function sendWorker($from, $to, $subject, $bodyPlain, $bodyHtml, $attachments = [], $options = [])
	{
		if (!is_array($to))
		{
			$to = [$to];
		}

		$sender = Sender::basicCreate();

		$res = true;

		foreach ($to as $singleTo)
		{
			try {
				if (!$sender->send($from, $singleTo, $subject, $bodyPlain, $bodyHtml, $attachments, $options))
				{
					$res = false;

					$this->setLastError(Error::UNKNOWN_FATAL);
				}
			} catch (\Exception $e) {
				Logger::getInstance()->log($e->getMessage(), 'Queue::sendWorker');
				$res = false;
			}
		}

		return $res;
	}

	public function sendAll($limit = 0)
	{
		/** @var Collection $messages */
		$messages = Collection::createActual();

		$counter = 0;

		/** @var Model $message */
		foreach ($messages as $message)
		{
			if ($this->sendMessage($message))
			{
				$counter++;
			}

			if ($limit && $counter > $limit)
			{
				break;
			}
		}

		return $this;
	}

	public function sendAllSafe($limit = 0)
	{
		$i = 0;
		//$errorsAllowed = static::SAFE_SEND_ERRORS_ALLOWED;

		do {
			if ($limit && $i >= $limit)
			{
				break;
			}

			$this
				->setLastError();

			$sent = $this->send();

			if ($sent)
			{
				$i++;
			}
			else
			{
				if ($this->isLastErrorFatal())
				{
					break;
					//$errorsAllowed--;
				}
			}
		} while ($sent); // || $errorsAllowed)

		return $i;
	}

	protected function sendMessage(Model $message)
	{
		$message
			->setVisible(0)
			->save();

		$attachments = $this->getAttachments($message);
		$this->processIncuts($message);

		$bodyPlain = $message->hasPlainBody() ? $message->getBody() : '';
		$bodyHtml = $message->hasPlainBody() ? '' : $message->getBody();

		$result = $this->sendWorker($message->getSender(), $message->getRecipient(), $message->getSubject(),
			$bodyPlain, $bodyHtml, $attachments);

		if ($result)
		{
			$this->setMessageSent($message);
		}

		return $result;
	}

	private function setMessageSent(Model $message)
	{
		$message
			->hardDestroy();

		return $this;
	}

	/**
	 * @return int
	 */
	public function getLastError()
	{
		return $this->lastError;
	}

	/**
	 * @param int $lastError
	 * @return $this
	 */
	protected function setLastError($lastError = Error::NONE)
	{
		$this->lastError = $lastError;

		return $this;
	}

	protected function isLastErrorFatal()
	{
		return !$this->isLastErrorLite();
	}

	protected function isLastErrorLite()
	{
		return in_array($this->getLastError(), [
			Error::NONE,
			Error::QUEUE_IS_EMPTY,
			Error::NO_CREDENTIALS,
		]);
	}

	public static function incutToken($id)
	{
		return IncutModel::token($id);
	}

	public function setVisible()
	{
		/** @var Collection $col */
		$col = \diCollection::create(Types::mail_queue);
		$col
			->filterByVisible(0)
			->update([
				'visible' => 1,
			]);

		return $this;
	}
}