<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 29.07.2015
 * Time: 13:09
 */

class diRuntimeErrorsException extends diRuntimeException
{
    protected $errors = [];

    /**
     * Set runtime error messages
     *
     * @param array $errors
     */
    public function setErrors(array $errors)
    {
        $this->errors = $errors;
    }

    /**
     * Get runtime errors
     * @return array
     */
    public function getErrors()
    {
        return $this->errors;
    }
}
