<?php

declare(strict_types=1);

namespace CobbyPlugin\Subscriber;

use CobbyPlugin\CobbyPlugin;

/**
 * Tag event subscriber for tag tracking (Metadata-Only).
 *
 * SUBSCRIBED EVENTS (2 total):
 * - tag.written / tag.deleted: Tag changes
 *
 * KEY FEATURES:
 * 1. Minimal Implementation: Only extracts entity ID, no data loading
 * 2. Metadata-Only: Stores entity_type + entity_id in queue (external service loads data via API)
 * 3. Lightweight: No repository queries, no associations, no field extraction
 */
class TagSubscriber extends SimpleEntitySubscriber
{
    protected function getEntityType(): string
    {
        return 'tag';
    }

    protected function getConfigKey(): string
    {
        return CobbyPlugin::CONFIG_PREFIX . 'enableTagEvents';
    }
}
