<?php
/**
 * Created by diModelsManager
 * Date: 11.09.2015
 * Time: 11:50
 */
/**
 * Class diDiMigrationsLogModel
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
 * @method diDiMigrationsLogModel setAdminId($value)
 * @method diDiMigrationsLogModel setIdx($value)
 * @method diDiMigrationsLogModel setName($value)
 * @method diDiMigrationsLogModel setDirection($value)
 * @method diDiMigrationsLogModel setDate($value)
 */
class diDiMigrationsLogModel extends diModel
{
	protected $table = "di_migrations_log";
}