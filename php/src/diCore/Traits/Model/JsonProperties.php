<?php

namespace diCore\Traits\Model;

/**
 * @method array    getProperties
 *
 * @method bool hasProperties
 *
 * @method $this setProperties($value)
 */
trait JsonProperties
{
    public function getProperty(array|string $path)
    {
        return $this->getJsonData('properties', $path);
    }

    public function setProperty(array|string $path, $value = null)
    {
        return $this->updateJsonData('properties', $path, $value);
    }

    public function incProperty(array|string $path)
    {
        return $this->setProperty($path, $this->getProperty($path) + 1);
    }
}
