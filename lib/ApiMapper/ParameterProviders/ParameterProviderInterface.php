<?php

namespace ApiMapper\ParameterProviders;

interface ParameterProviderInterface
{
    public function lookup($route);
}