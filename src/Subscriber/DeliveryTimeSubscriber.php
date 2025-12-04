<?php declare(strict_types=1);

namespace CobbyShopware6Extension\Subscriber;

use CobbyShopware6Extension\CobbyShopware6Extension;

/**
 * Delivery Time event subscriber for shipping timeframe tracking (Metadata-Only).
 *
 * SUBSCRIBED EVENTS (2 total):
 * - delivery_time.written / delivery_time.deleted
 */
class DeliveryTimeSubscriber extends SimpleEntitySubscriber
{
    protected function getEntityType(): string
    {
        return 'delivery_time';
    }

    protected function getConfigKey(): string
    {
        return CobbyShopware6Extension::CONFIG_PREFIX . 'enableDeliveryTimeEvents';
    }
}
