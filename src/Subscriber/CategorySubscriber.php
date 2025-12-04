<?php declare(strict_types=1);

namespace CobbyShopware6Extension\Subscriber;

use CobbyShopware6Extension\CobbyShopware6Extension;

/**
 * Category event subscriber for category tracking (Metadata-Only).
 *
 * SUBSCRIBED EVENTS (2 total):
 * - category.written / category.deleted
 */
class CategorySubscriber extends SimpleEntitySubscriber
{
    protected function getEntityType(): string
    {
        return 'category';
    }

    protected function getConfigKey(): string
    {
        return CobbyShopware6Extension::CONFIG_PREFIX . 'enableCategoryEvents';
    }
}
