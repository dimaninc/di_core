<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 28.10.15
 * Time: 12:15
 */
class diCartItemCollection extends diCollection
{
	protected $table = "cart_items";
	protected $modelType = "cart_item";
}