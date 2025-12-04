<?php declare(strict_types=1);

namespace CobbyShopware6Extension\Subscriber;

use CobbyShopware6Extension\CobbyShopware6Extension;

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
        return CobbyShopware6Extension::CONFIG_PREFIX . 'enableManufacturerEvents';
    }
}
