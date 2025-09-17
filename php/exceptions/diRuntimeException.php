<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 02.07.2015
 * Time: 15:54
 */

class diRuntimeException extends \RuntimeException implements diException
{
    protected array $metadata = [];

    /**
     * Add error metadata
     */
    public function addMetadata(array $metadata): static
    {
        $this->metadata = extend($this->metadata, $metadata);

        return $this;
    }

    /**
     * Reset error metadata
     */
    public function resetMetadata(): static
    {
        $this->metadata = [];

        return $this;
    }

    /**
     * Get error metadata
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }
}
