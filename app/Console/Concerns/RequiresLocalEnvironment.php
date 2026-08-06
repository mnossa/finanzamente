<?php

namespace App\Console\Concerns;

use App\Support\LocalEnvironmentGuard;

trait RequiresLocalEnvironment
{
    protected function blockUnlessLocalEnvironment(): bool
    {
        if (LocalEnvironmentGuard::isLocalDevelopment()) {
            return true;
        }

        $this->error('Questo comando è disponibile solo in ambiente local/development/testing.');

        return false;
    }
}
