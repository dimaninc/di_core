<?php
/**
 * Created by \diModelsManager
 * Date: 11.12.2017
 * Time: 15:59
 */

namespace diCore\Entity\MailPlan;

/**
 * Class Collection
 * Methods list for IDE
 *
 * @method Collection filterById($value, $operator = null)
 * @method Collection filterByTargetType($value, $operator = null)
 * @method Collection filterByTargetId($value, $operator = null)
 * @method Collection filterByMode($value, $operator = null)
 * @method Collection filterByConditions($value, $operator = null)
 * @method Collection filterByCreatedAt($value, $operator = null)
 * @method Collection filterByStartedAt($value, $operator = null)
 * @method Collection filterByProcessedAt($value, $operator = null)
 *
 * @method Collection orderById($direction = null)
 * @method Collection orderByTargetType($direction = null)
 * @method Collection orderByTargetId($direction = null)
 * @method Collection orderByMode($direction = null)
 * @method Collection orderByConditions($direction = null)
 * @method Collection orderByCreatedAt($direction = null)
 * @method Collection orderByStartedAt($direction = null)
 * @method Collection orderByProcessedAt($direction = null)
 *
 * @method Collection selectId()
 * @method Collection selectTargetType()
 * @method Collection selectTargetId()
 * @method Collection selectMode()
 * @method Collection selectConditions()
 * @method Collection selectCreatedAt()
 * @method Collection selectStartedAt()
 * @method Collection selectProcessedAt()
 */
class Collection extends \diCollection
{
	const type = \diTypes::mail_plan;
	protected $table = 'mail_plans';
	protected $modelType = 'mail_plan';
}