<?php

declare(strict_types=1);

namespace CobbyPlugin\Subscriber;

use CobbyPlugin\CobbyPlugin;

class CurrencySubscriber extends SimpleEntitySubscriber
{
    protected function getEntityType(): string
    {
        return 'currency';
    }

    protected function getConfigKey(): string
    {
        return CobbyPlugin::CONFIG_PREFIX.'enableCurrencyEvents';
    }
}
