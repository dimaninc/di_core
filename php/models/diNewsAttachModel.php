<?php
/**
 * Created by diModelsManager
 * Date: 08.03.2016
 * Time: 15:21
 */

/**
 * Class diNewsAttachModel
 * Methods list for IDE
 *
 * @method integer	getNewsId
 * @method string	getAttachment
 *
 * @method bool hasNewsId
 * @method bool hasAttachment
 *
 * @method diNewsAttachModel setNewsId($value)
 * @method diNewsAttachModel setAttachment($value)
 */
class diNewsAttachModel extends diModel
{
	const type = diTypes::news_attach;
	protected $table = "news_attaches";
}