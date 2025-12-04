<?php declare(strict_types=1);

namespace CobbyShopware6Extension\Subscriber;

use CobbyShopware6Extension\CobbyShopware6Extension;
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

    protected function getConfigKey(): string
    {
        return CobbyShopware6Extension::CONFIG_PREFIX . 'enableProductEvents';
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
        $this->handleParentUpdateEvent($event, 'product', 'productId');
    }

    public function onProductMediaDeleted(EntityDeletedEvent $event): void
    {
        $this->handleParentUpdateEvent($event, 'product', 'productId');
    }

    public function onProductCategoryWritten(EntityWrittenEvent $event): void
    {
        $this->handleParentUpdateEvent($event, 'product', 'productId');
    }

    public function onProductCategoryDeleted(EntityDeletedEvent $event): void
    {
        $this->handleParentUpdateEvent($event, 'product', 'productId');
    }

}
