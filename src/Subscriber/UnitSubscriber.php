<?php declare(strict_types=1);

namespace CobbyPlugin\Subscriber;

use CobbyPlugin\CobbyPlugin;

class UnitSubscriber extends SimpleEntitySubscriber
{
    protected function getEntityType(): string
    {
        return 'unit';
    }

    protected function getConfigKey(): string
    {
        return CobbyPlugin::CONFIG_PREFIX . 'enableUnitEvents';
    }
}
