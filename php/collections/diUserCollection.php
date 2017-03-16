<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 16.10.15
 * Time: 18:02
 */
class diUserCollection extends diCollection
{
	const type = diTypes::user;
	protected $table = "users";
	protected $modelType = "user";
}