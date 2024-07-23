<?php

namespace PhpServer\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER | Attribute::TARGET_PROPERTY)]
class InterfaceIterator
{
    /**
     * Constructs a new instance of the InterfaceIterator class.
     *
     * @param string $variableName The name of the variable.
     * @param string $interface The name of the interface.
     */
    public function __construct(private readonly string $variableName, private readonly string $interface)
    {
    }

    /**
     * Get the name of the variable.
     *
     * @return string The name of the variable.
     */
    public function getVariableName(): string
    {
        return $this->variableName;
    }

    /**
     * Get the name of the interface.
     *
     * @return string The name of the interface.
     */
    public function getInterface(): string
    {
        return $this->interface;
    }
}
