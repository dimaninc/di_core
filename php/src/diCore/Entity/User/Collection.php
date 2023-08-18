<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 27.12.2017
 * Time: 13:07
 */

namespace diCore\Entity\User;

use diCore\Data\Types;

class Collection extends \diCollection
{
    const type = Types::user;
    protected $table = 'users';
    protected $modelType = 'user';
}
