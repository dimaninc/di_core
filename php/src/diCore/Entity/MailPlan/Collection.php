<?php
/**
 * Created by \diModelsManager
 * Date: 11.12.2017
 * Time: 15:59
 */

namespace diCore\Entity\MailPlan;

use diCore\Traits\Collection\TargetInside;

/**
 * Class Collection
 * Methods list for IDE
 *
 * @method $this filterById($value, $operator = null)
 * @method $this filterByMode($value, $operator = null)
 * @method $this filterByConditions($value, $operator = null)
 * @method $this filterByCreatedAt($value, $operator = null)
 * @method $this filterByStartedAt($value, $operator = null)
 * @method $this filterByProcessedAt($value, $operator = null)
 *
 * @method $this orderById($direction = null)
 * @method $this orderByMode($direction = null)
 * @method $this orderByConditions($direction = null)
 * @method $this orderByCreatedAt($direction = null)
 * @method $this orderByStartedAt($direction = null)
 * @method $this orderByProcessedAt($direction = null)
 *
 * @method $this selectId()
 * @method $this selectMode()
 * @method $this selectConditions()
 * @method $this selectCreatedAt()
 * @method $this selectStartedAt()
 * @method $this selectProcessedAt()
 */
class Collection extends \diCollection
{
    use TargetInside;

	const type = \diTypes::mail_plan;
	protected $table = 'mail_plans';
	protected $modelType = 'mail_plan';
}