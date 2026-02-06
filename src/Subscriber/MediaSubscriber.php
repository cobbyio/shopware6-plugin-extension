<?php

declare(strict_types=1);

namespace CobbyPlugin\Subscriber;

use CobbyPlugin\CobbyPlugin;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;

class MediaSubscriber extends AbstractWebhookSubscriber
{
    public static function getSubscribedEvents(): array
    {
        return [
            'media.written' => 'onMediaWritten',
            'media.deleted' => 'onMediaDeleted',
        ];
    }

    public function onMediaWritten(EntityWrittenEvent $event): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        try {
            $contextType = $this->detectContextType($event->getContext());

            foreach ($event->getWriteResults() as $writeResult) {
                $mediaId = $this->extractPrimaryKey($writeResult->getPrimaryKey());
                if (!$mediaId) {
                    continue;
                }

                // Always enqueue media event, even if no products are linked yet
                $this->enqueueMetadataOnly('media', $mediaId, $writeResult->getOperation(), $contextType, $event->getContext());

                $productIds = $this->queueService->getProductIdsByMediaId($mediaId);

                foreach ($productIds as $productId) {
                    $this->enqueueMetadataOnly('product', $productId, 'update', $contextType, $event->getContext());
                }
            }
        } catch (\Throwable $e) {
            $this->logger->error('Error in media written event', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function onMediaDeleted(EntityDeletedEvent $event): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        try {
            $contextType = $this->detectContextType($event->getContext());

            foreach ($event->getWriteResults() as $writeResult) {
                $mediaId = $this->extractPrimaryKey($writeResult->getPrimaryKey());
                if (!$mediaId) {
                    continue;
                }

                // Always enqueue media event, even if no products are linked yet
                $this->enqueueMetadataOnly('media', $mediaId, 'delete', $contextType, $event->getContext());

                $productIds = $this->queueService->getProductIdsByMediaId($mediaId);

                foreach ($productIds as $productId) {
                    $this->enqueueMetadataOnly('product', $productId, 'update', $contextType, $event->getContext());
                }
            }
        } catch (\Throwable $e) {
            $this->logger->error('Error in media deleted event', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function getConfigKey(): string
    {
        return CobbyPlugin::CONFIG_PREFIX . 'enableMediaEvents';
    }
}
