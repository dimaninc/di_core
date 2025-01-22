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
    public function hasProp(array|string $path)
    {
        return $this->hasJsonData('properties', $path);
    }

    public function prop(array|string $path)
    {
        return $this->getJsonData('properties', $path);
    }

    public function setProp(array|string $path, $value = null)
    {
        return $this->updateJsonData('properties', $path, $value);
    }

    /** @deprecated  */
    public function updateProp(array|string $path, $value = null)
    {
        return $this->setProp($path, $value);
    }

    public function incProp(array|string $path)
    {
        return $this->setProp($path, $this->prop($path) + 1);
    }

    public function decProp(array|string $path)
    {
        return $this->setProp($path, $this->prop($path) - 1);
    }

    public function killProp(string $path)
    {
        return $this->killJsonData('properties', $path);
    }

    /** @deprecated  */
    public function hasProperty(array|string $path)
    {
        return $this->hasProp($path);
    }

    /** @deprecated  */
    public function getProperty(array|string $path)
    {
        return $this->prop($path);
    }

    /** @deprecated  */
    public function setProperty(array|string $path, $value = null)
    {
        return $this->setProp($path, $value);
    }

    /** @deprecated  */
    public function incProperty(array|string $path)
    {
        return $this->incProp($path);
    }
}
