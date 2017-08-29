<?php
/**
 * Created by \diModelsManager
 * Date: 29.08.2017
 * Time: 18:25
 */

namespace diCore\Entity\MailIncut;

/**
 * Class Model
 * Methods list for IDE
 *
 * @method integer	getTargetType
 * @method integer	getTargetId
 * @method integer	getType
 * @method string	getContent
 *
 * @method bool hasTargetType
 * @method bool hasTargetId
 * @method bool hasType
 * @method bool hasContent
 *
 * @method Model setTargetType($value)
 * @method Model setTargetId($value)
 * @method Model setType($value)
 * @method Model setContent($value)
 */
class Model extends \diModel
{
	const type = \diTypes::mail_incut;
	protected $table = 'mail_incuts';

	const TOKEN = '{{{-MAIL-INCUT-%d-}}}';

	/**
	 * @param string $content
	 * @param int|null $targetType
	 * @param int|null $targetId
	 * @return Model
	 * @throws \Exception
	 */
	public static function createText($content, $targetType = null, $targetId = null)
	{
		/** @var Model $m */
		$m = static::create(static::type);
		$m
			->setType(Type::text)
			->setEssentials($content, $targetType, $targetId);

		return $m;
	}

	/**
	 * @param string $content
	 * @param int|null $targetType
	 * @param int|null $targetId
	 * @return Model
	 * @throws \Exception
	 */
	public static function createBinaryAttachment($content, $targetType = null, $targetId = null)
	{
		/** @var Model $m */
		$m = static::create(static::type);
		$m
			->setType(Type::binary_attachment)
			->setEssentials($content, $targetType, $targetId);

		return $m;
	}

	public function setEssentials($content, $targetType = null, $targetId = null)
	{
		$this
			->setContent($content)
			->setTargetType($targetType)
			->setTargetId($targetId);

		return $this;
	}

	public function getToken()
	{
		return $this->hasId()
			? static::token($this->getId())
			: null;
	}

	public static function token($id)
	{
		return sprintf(static::TOKEN, $id);
	}
}