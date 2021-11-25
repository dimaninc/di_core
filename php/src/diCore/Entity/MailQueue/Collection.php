<?php
/**
 * Created by \diModelsManager
 * Date: 20.03.2017
 * Time: 21:00
 */

namespace diCore\Entity\MailQueue;

use diCore\Data\Types;

/**
 * Class Collection
 * Methods list for IDE
 *
 * @method $this filterById($value, $operator = null)
 * @method $this filterByDate($value, $operator = null)
 * @method $this filterBySender($value, $operator = null)
 * @method $this filterByRecipient($value, $operator = null)
 * @method $this filterByRecipientId($value, $operator = null)
 * @method $this filterByReplyTo($value, $operator = null)
 * @method $this filterBySubject($value, $operator = null)
 * @method $this filterByBody($value, $operator = null)
 * @method $this filterByPlainBody($value, $operator = null)
 * @method $this filterByAttachment($value, $operator = null)
 * @method $this filterByIncutIds($value, $operator = null)
 * @method $this filterByVisible($value, $operator = null)
 * @method $this filterBySent($value, $operator = null)
 * @method $this filterByNewsId($value, $operator = null)
 * @method $this filterBySettings($value, $operator = null)
 *
 * @method $this orderById($direction = null)
 * @method $this orderByDate($direction = null)
 * @method $this orderBySender($direction = null)
 * @method $this orderByRecipient($direction = null)
 * @method $this orderByRecipientId($direction = null)
 * @method $this orderByReplyTo($direction = null)
 * @method $this orderBySubject($direction = null)
 * @method $this orderByBody($direction = null)
 * @method $this orderByPlainBody($direction = null)
 * @method $this orderByAttachment($direction = null)
 * @method $this orderByIncutIds($direction = null)
 * @method $this orderByVisible($direction = null)
 * @method $this orderBySent($direction = null)
 * @method $this orderByNewsId($direction = null)
 * @method $this orderBySettings($direction = null)
 *
 * @method $this selectId()
 * @method $this selectDate()
 * @method $this selectSender()
 * @method $this selectRecipient()
 * @method $this selectRecipientId()
 * @method $this selectReplyTo()
 * @method $this selectSubject()
 * @method $this selectBody()
 * @method $this selectPlainBody()
 * @method $this selectAttachment()
 * @method $this selectIncutIds()
 * @method $this selectVisible()
 * @method $this selectSent()
 * @method $this selectNewsId()
 * @method $this selectSettings()
 */
class Collection extends \diCollection
{
	const type = Types::mail_queue;
	protected $table = 'mail_queue';
	protected $modelType = 'mail_queue';

	/**
	 * @return $this
	 * @throws \Exception
	 */
	public static function createActual()
	{
		return static::create()
			->filterByVisible(1)
			->filterBySent(0);
	}
}