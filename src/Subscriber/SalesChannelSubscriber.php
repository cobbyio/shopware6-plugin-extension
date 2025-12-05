<?php declare(strict_types=1);

namespace CobbyPlugin\Subscriber;

use CobbyPlugin\CobbyPlugin;

class SalesChannelSubscriber extends SimpleEntitySubscriber
{
    protected function getEntityType(): string
    {
        return 'sales_channel';
    }

    protected function getConfigKey(): string
    {
        return CobbyPlugin::CONFIG_PREFIX . 'enableSalesChannelEvents';
    }
}
