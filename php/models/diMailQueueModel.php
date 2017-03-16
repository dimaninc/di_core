<?php
/**
 * Created by diModelsManager
 * Date: 11.09.2015
 * Time: 11:48
 */
/**
 * Class diMailQueueModel
 * Methods list for IDE
 *
 * @method string	getSender
 * @method string	getRecipient
 * @method integer	getRecipientId
 * @method string	getSubject
 * @method string	getBody
 * @method integer	getPlainBody
 * @method string	getAttachment
 * @method string	getIncutIds
 * @method integer	getVisible
 * @method integer	getSent
 * @method integer	getNewsId
 * @method string	getDate
 *
 * @method bool hasSender
 * @method bool hasRecipient
 * @method bool hasRecipientId
 * @method bool hasSubject
 * @method bool hasBody
 * @method bool hasPlainBody
 * @method bool hasAttachment
 * @method bool hasIncutIds
 * @method bool hasVisible
 * @method bool hasSent
 * @method bool hasNewsId
 * @method bool hasDate
 *
 * @method diMailQueueModel setSender($value)
 * @method diMailQueueModel setRecipient($value)
 * @method diMailQueueModel setRecipientId($value)
 * @method diMailQueueModel setSubject($value)
 * @method diMailQueueModel setBody($value)
 * @method diMailQueueModel setPlainBody($value)
 * @method diMailQueueModel setAttachment($value)
 * @method diMailQueueModel setIncutIds($value)
 * @method diMailQueueModel setVisible($value)
 * @method diMailQueueModel setSent($value)
 * @method diMailQueueModel setNewsId($value)
 * @method diMailQueueModel setDate($value)
 */
class diMailQueueModel extends diModel
{
	const type = diTypes::mail_queue;
	protected $table = "mail_queue";
}