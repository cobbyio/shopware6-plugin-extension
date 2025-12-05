<?php declare(strict_types=1);

namespace CobbyPlugin\Subscriber;

use CobbyPlugin\CobbyPlugin;

class TaxSubscriber extends SimpleEntitySubscriber
{
    protected function getEntityType(): string
    {
        return 'tax';
    }

    protected function getConfigKey(): string
    {
        return CobbyPlugin::CONFIG_PREFIX . 'enableTaxEvents';
    }
}
