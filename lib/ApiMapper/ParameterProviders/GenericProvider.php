<?php

namespace ApiMapper\ParameterProviders;

class GenericProvider implements ParameterProviderInterface
{
    private $value;

    public function __construct($value = null)
    {
        $this->value = $value;
    }

    public function lookup($route)
    {
        return $this->value;
    }
}