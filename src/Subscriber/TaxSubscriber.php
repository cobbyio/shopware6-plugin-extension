<?php declare(strict_types=1);

namespace CobbyShopware6Extension\Subscriber;

use CobbyShopware6Extension\CobbyShopware6Extension;

class TaxSubscriber extends SimpleEntitySubscriber
{
    protected function getEntityType(): string
    {
        return 'tax';
    }

    protected function getConfigKey(): string
    {
        return CobbyShopware6Extension::CONFIG_PREFIX . 'enableTaxEvents';
    }
}
