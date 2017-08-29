<?php
/**
 * Created by \diModelsManager
 * Date: 29.08.2017
 * Time: 18:25
 */

namespace diCore\Entity\MailIncut;

/**
 * Class Collection
 * Methods list for IDE
 *
 * @method Collection filterById($value, $operator = null)
 * @method Collection filterByTargetType($value, $operator = null)
 * @method Collection filterByTargetId($value, $operator = null)
 * @method Collection filterByType($value, $operator = null)
 * @method Collection filterByContent($value, $operator = null)
 *
 * @method Collection orderById($direction = null)
 * @method Collection orderByTargetType($direction = null)
 * @method Collection orderByTargetId($direction = null)
 * @method Collection orderByType($direction = null)
 * @method Collection orderByContent($direction = null)
 *
 * @method Collection selectId()
 * @method Collection selectTargetType()
 * @method Collection selectTargetId()
 * @method Collection selectType()
 * @method Collection selectContent()
 */
class Collection extends \diCollection
{
	const type = \diTypes::mail_incut;
	protected $table = 'mail_incuts';
	protected $modelType = 'mail_incut';

	/**
	 * @return Collection
	 * @throws \Exception
	 */
	public static function createText()
	{
		/** @var Collection $col */
		$col = \diCollection::create(static::type);
		$col
			->filterByType(Type::text);

		return $col;
	}

	/**
	 * @return Collection
	 * @throws \Exception
	 */
	public static function createBinaryAttachment()
	{
		/** @var Collection $col */
		$col = \diCollection::create(static::type);
		$col
			->filterByType(Type::binary_attachment);

		return $col;
	}
}