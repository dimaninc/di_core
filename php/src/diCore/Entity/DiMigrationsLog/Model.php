<?php
/**
 * Created by \diModelsManager
 * Date: 30.01.2018
 * Time: 17:27
 */

namespace diCore\Entity\DiMigrationsLog;

/**
 * Class Model
 * Methods list for IDE
 *
 * @method integer	getAdminId
 * @method string	getIdx
 * @method string	getName
 * @method integer	getDirection
 * @method string	getDate
 *
 * @method bool hasAdminId
 * @method bool hasIdx
 * @method bool hasName
 * @method bool hasDirection
 * @method bool hasDate
 *
 * @method $this setAdminId($value)
 * @method $this setIdx($value)
 * @method $this setName($value)
 * @method $this setDirection($value)
 * @method $this setDate($value)
 */
class Model extends \diModel
{
    const type = \diTypes::di_migrations_log;
    const table = 'di_migrations_log';
    protected $table = 'di_migrations_log';
}
