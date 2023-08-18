<?php

namespace diCore\Module;

/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 10.09.15
 * Time: 19:40
 */

class User extends \diModule
{
    public function render()
    {
        if ($this->getTwig()->exists('text')) {
            $this->getTwig()->renderPage('text');
        } else {
            $this->getTpl()->define('user', ['page']);
        }
    }
}
