<?php

namespace ApiMapper\Providers;

class GenericProvider implements ProviderInterface
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