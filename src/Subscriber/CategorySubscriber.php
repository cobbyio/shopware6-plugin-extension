<?php

declare(strict_types=1);

namespace CobbyPlugin\Subscriber;

use CobbyPlugin\CobbyPlugin;

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
        return CobbyPlugin::CONFIG_PREFIX.'enableCategoryEvents';
    }
}
