<?php

declare(strict_types=1);

namespace CobbyPlugin\Subscriber;

use CobbyPlugin\CobbyPlugin;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;

/**
 * Product event subscriber for product change tracking (Metadata-Only).
 *
 * SUBSCRIBED EVENTS (8 total):
 * - product.written / product.deleted: Main product entity changes
 * - product_price.written / product_price.deleted: Price changes
 * - product_media.written / product_media.deleted: Media/image changes
 * - product_category.written / product_category.deleted: Category assignments
 *
 * KEY FEATURES:
 * 1. Minimal Implementation: Only extracts entity ID, no data loading
 * 2. Metadata-Only: Stores entity_type + entity_id in queue
 * 3. Lightweight: No repository queries, no associations
 * 4. Parent Tracking: Child events (price, media, category) trigger parent product updates
 */
class ProductSubscriber extends AbstractWebhookSubscriber
{
    public static function getSubscribedEvents(): array
    {
        return [
            'product.written' => 'onProductWritten',
            'product.deleted' => 'onProductDeleted',
            'product_price.written' => 'onProductPriceWritten',
            'product_price.deleted' => 'onProductPriceDeleted',
            'product_media.written' => 'onProductMediaWritten',
            'product_media.deleted' => 'onProductMediaDeleted',
            'product_category.written' => 'onProductCategoryWritten',
            'product_category.deleted' => 'onProductCategoryDeleted',
        ];
    }

    public function onProductWritten(EntityWrittenEvent $event): void
    {
        $this->handleSimpleWrittenEvent($event, 'product');
    }

    public function onProductDeleted(EntityDeletedEvent $event): void
    {
        $this->handleSimpleDeletedEvent($event, 'product');
    }

    public function onProductPriceWritten(EntityWrittenEvent $event): void
    {
        $this->handleParentUpdateEvent($event, 'product', 'productId');
    }

    public function onProductPriceDeleted(EntityDeletedEvent $event): void
    {
        $this->handleParentUpdateEvent($event, 'product', 'productId');
    }

    public function onProductMediaWritten(EntityWrittenEvent $event): void
    {
        $this->handleProductMediaEvent($event);
    }

    public function onProductMediaDeleted(EntityDeletedEvent $event): void
    {
        $this->handleProductMediaEvent($event);
    }

    public function onProductCategoryWritten(EntityWrittenEvent $event): void
    {
        $this->handleParentUpdateEvent($event, 'product', 'productId');
    }

    public function onProductCategoryDeleted(EntityDeletedEvent $event): void
    {
        $this->handleParentUpdateEvent($event, 'product', 'productId');
    }

    protected function getConfigKey(): string
    {
        return CobbyPlugin::CONFIG_PREFIX . 'enableProductEvents';
    }

    /**
     * Handle product_media events with DB query fallback.
     * Shopware's write result payload for product_media often doesn't contain productId,
     * especially for cascaded writes and delete events.
     */
    private function handleProductMediaEvent(EntityWrittenEvent|EntityDeletedEvent $event): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        try {
            $contextType = $this->detectContextType($event->getContext());
            $parentIds = [];

            foreach ($event->getWriteResults() as $writeResult) {
                $payload = $writeResult->getPayload();

                // Try payload first (available for direct API writes)
                if (isset($payload['productId'])) {
                    $parentIds[] = $payload['productId'];

                    continue;
                }

                // Fallback: Query DB for productId using product_media ID
                $productMediaId = $this->extractPrimaryKey($writeResult->getPrimaryKey());
                if ($productMediaId) {
                    $productId = $this->queueService->getProductIdByProductMediaId($productMediaId);
                    if ($productId) {
                        $parentIds[] = $productId;
                    }
                }
            }

            foreach (array_unique($parentIds) as $parentId) {
                $this->enqueueMetadataOnly('product', $parentId, 'update', $contextType, $event->getContext());
            }
        } catch (\Throwable $e) {
            $this->logger->error('Error in product media event', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
