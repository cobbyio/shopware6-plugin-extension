<?php declare(strict_types=1);

namespace CobbyShopware6Extension\Subscriber;

use CobbyShopware6Extension\CobbyShopware6Extension;

class SalesChannelSubscriber extends SimpleEntitySubscriber
{
    protected function getEntityType(): string
    {
        return 'sales_channel';
    }

    protected function getConfigKey(): string
    {
        return CobbyShopware6Extension::CONFIG_PREFIX . 'enableSalesChannelEvents';
    }
}
