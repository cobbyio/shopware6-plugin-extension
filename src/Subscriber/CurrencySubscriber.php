<?php declare(strict_types=1);

namespace CobbyShopware6Extension\Subscriber;

use CobbyShopware6Extension\CobbyPlugin;

class CurrencySubscriber extends SimpleEntitySubscriber
{
    protected function getEntityType(): string
    {
        return 'currency';
    }

    protected function getConfigKey(): string
    {
        return CobbyPlugin::CONFIG_PREFIX . 'enableCurrencyEvents';
    }
}
