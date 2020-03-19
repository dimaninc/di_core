<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 29.08.2019
 * Time: 09:50
 */

namespace diCore\Interfaces;

interface CartOrder
{
    public function getQuantityOfItem($item, $options);
    public function getCostOfItem($item, $options);
    public function getAdditionalCostOfItem($item, $options);
    public function getRowCountOfItem($item, $options);
}