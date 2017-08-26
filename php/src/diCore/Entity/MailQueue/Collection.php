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
 * @method Collection filterById($value, $operator = null)
 * @method Collection filterByDate($value, $operator = null)
 * @method Collection filterBySender($value, $operator = null)
 * @method Collection filterByRecipient($value, $operator = null)
 * @method Collection filterByRecipientId($value, $operator = null)
 * @method Collection filterBySubject($value, $operator = null)
 * @method Collection filterByBody($value, $operator = null)
 * @method Collection filterByPlainBody($value, $operator = null)
 * @method Collection filterByAttachment($value, $operator = null)
 * @method Collection filterByIncutIds($value, $operator = null)
 * @method Collection filterByVisible($value, $operator = null)
 * @method Collection filterBySent($value, $operator = null)
 * @method Collection filterByNewsId($value, $operator = null)
 *
 * @method Collection orderById($direction = null)
 * @method Collection orderByDate($direction = null)
 * @method Collection orderBySender($direction = null)
 * @method Collection orderByRecipient($direction = null)
 * @method Collection orderByRecipientId($direction = null)
 * @method Collection orderBySubject($direction = null)
 * @method Collection orderByBody($direction = null)
 * @method Collection orderByPlainBody($direction = null)
 * @method Collection orderByAttachment($direction = null)
 * @method Collection orderByIncutIds($direction = null)
 * @method Collection orderByVisible($direction = null)
 * @method Collection orderBySent($direction = null)
 * @method Collection orderByNewsId($direction = null)
 *
 * @method Collection selectId()
 * @method Collection selectDate()
 * @method Collection selectSender()
 * @method Collection selectRecipient()
 * @method Collection selectRecipientId()
 * @method Collection selectSubject()
 * @method Collection selectBody()
 * @method Collection selectPlainBody()
 * @method Collection selectAttachment()
 * @method Collection selectIncutIds()
 * @method Collection selectVisible()
 * @method Collection selectSent()
 * @method Collection selectNewsId()
 */
class Collection extends \diCollection
{
	const type = Types::mail_queue;
	protected $table = 'mail_queue';
	protected $modelType = 'mail_queue';

	/**
	 * @return Collection
	 * @throws \Exception
	 */
	public static function createActual()
	{
		/** @var Collection $col */
		$col = \diCollection::create(Types::mail_queue);
		$col
			->filterByVisible(1)
			->filterBySent(0);

		return $col;
	}
}