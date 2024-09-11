<?php

namespace diCore\Traits\Model;

use diCore\Entity\AdditionalVariable\Collection as Variables;
use diCore\Entity\AdditionalVariable\Model as Variable;
use diCore\Entity\AdditionalVariableValue\Collection as Values;
use diCore\Entity\AdditionalVariableValue\Model as Value;

/**
 * @method int modelType
 * @method int getId
 */
trait AdditionalVariable
{
    /**
     * @var Variables
     */
    private $additionalVariables;
    /**
     * @var array key => value
     */
    private $additionalVariableValues;
    private $populated = false;

    public function avPopulate($force = false)
    {
        if ($this->populated && !$force) {
            return $this;
        }

        $this->additionalVariables = Variables::createReadOnly()->filterByTargetType(
            $this->modelType()
        );

        $values = Values::createReadOnly()
            ->filterByAdditionalVariableId($this->additionalVariables->map('id'))
            ->filterByTargetId($this->getId());

        /** @var Value $value */
        foreach ($values as $value) {
            /** @var Variable $var */
            $var = $this->additionalVariables->getById(
                $value->getAdditionalVariableId()
            );

            if ($var->exists()) {
                $this->additionalVariableValues[
                    $var->getName()
                ] = $value->getValue();
            }
        }

        $this->populated = true;

        return $this;
    }

    /**
     * @param string $name
     * @return \diModel|Variable
     */
    private function getVariableByName(string $name)
    {
        $this->avPopulate();

        return $this->additionalVariables
            ->filtered(fn(Variable $v) => $v->getName() === $name)
            ->getFirstItem();
    }

    public function avSet($name, $value = null)
    {
        $this->avPopulate();

        if (is_array($name) && $value === null) {
            $this->additionalVariables = extend($this->additionalVariables, $name);

            return $this;
        }

        $this->additionalVariables[$name] = $value;

        return $this;
    }

    public function avGet($name = null)
    {
        $this->avPopulate();

        if ($name === null) {
            return $this->additionalVariables;
        }

        if (isset($this->additionalVariables[$name])) {
            return $this->additionalVariables[$name];
        }

        return $this->getVariableByName($name)->getDefaultValue();
    }

    public function avHas($name)
    {
        return !!$this->avGet($name);
    }
}
