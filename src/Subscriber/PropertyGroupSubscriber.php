<?php

declare(strict_types=1);

namespace CobbyPlugin\Subscriber;

use CobbyPlugin\CobbyPlugin;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;

/**
 * Property Group and Property Group Option event subscriber (Metadata-Only).
 *
 * SUBSCRIBED EVENTS (4 total):
 * - property_group.written / property_group.deleted
 * - property_group_option.written / property_group_option.deleted
 *
 * KEY FEATURES:
 * 1. Minimal Implementation: Only extracts entity ID, no data loading
 * 2. Metadata-Only: Stores entity_type + entity_id in queue
 * 3. Lightweight: No repository queries, no associations
 */
class PropertyGroupSubscriber extends AbstractWebhookSubscriber
{
    public static function getSubscribedEvents(): array
    {
        return [
            'property_group.written' => 'onPropertyGroupWritten',
            'property_group.deleted' => 'onPropertyGroupDeleted',
            'property_group_option.written' => 'onPropertyGroupOptionWritten',
            'property_group_option.deleted' => 'onPropertyGroupOptionDeleted',
        ];
    }

    public function onPropertyGroupWritten(EntityWrittenEvent $event): void
    {
        $this->handleSimpleWrittenEvent($event, 'property_group');
    }

    public function onPropertyGroupDeleted(EntityDeletedEvent $event): void
    {
        $this->handleSimpleDeletedEvent($event, 'property_group');
    }

    public function onPropertyGroupOptionWritten(EntityWrittenEvent $event): void
    {
        $this->handleSimpleWrittenEvent($event, 'property_group_option');
    }

    public function onPropertyGroupOptionDeleted(EntityDeletedEvent $event): void
    {
        $this->handleSimpleDeletedEvent($event, 'property_group_option');
    }

    protected function getConfigKey(): string
    {
        return CobbyPlugin::CONFIG_PREFIX . 'enablePropertyGroupEvents';
    }
}
