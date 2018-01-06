<?php
/**
 * Created by \diModelsManager
 * Date: 06.01.2018
 * Time: 15:33
 */

namespace diCore\Entity\Order;

/**
 * Class Collection
 * Methods list for IDE
 *

 *

 *

 */
class Collection extends \diCollection
{
	const type = \diTypes::order;
	protected $table = 'order';
	protected $modelType = 'order';
}