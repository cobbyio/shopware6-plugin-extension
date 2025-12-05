<?php declare(strict_types=1);

namespace CobbyShopware6Extension\Subscriber;

use CobbyShopware6Extension\CobbyPlugin;

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
