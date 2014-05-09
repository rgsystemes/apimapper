<?php

namespace ApiMapper\Providers;

class XForwardedForHeaderProvider implements ProviderInterface
{
    public function lookup($route)
    {
        return "X-Forwarded-For: " . $_SERVER['REMOTE_ADDR'];
    }
}