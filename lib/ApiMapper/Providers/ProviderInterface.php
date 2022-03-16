<?php

namespace ApiMapper\Providers;

interface ProviderInterface
{
    public function lookup($route);
}
