<?php declare(strict_types=1);

namespace CobbyShopware6Extension\Subscriber;

use CobbyShopware6Extension\CobbyPlugin;

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
