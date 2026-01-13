<?php

declare(strict_types=1);

namespace CobbyPlugin\Subscriber;

use CobbyPlugin\CobbyPlugin;

class ManufacturerSubscriber extends SimpleEntitySubscriber
{
    protected static function getEntityTypeStatic(): string
    {
        return 'product_manufacturer';
    }

    protected function getEntityType(): string
    {
        return 'product_manufacturer';
    }

    protected function getConfigKey(): string
    {
        return CobbyPlugin::CONFIG_PREFIX . 'enableManufacturerEvents';
    }
}
