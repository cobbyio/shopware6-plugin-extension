<?php

declare(strict_types=1);

namespace CobbyPlugin\Subscriber;

use CobbyPlugin\CobbyPlugin;
use CobbyPlugin\Service\NotificationService;
use CobbyPlugin\Service\QueueTableService;
use CobbyPlugin\Util\SecurityTrait;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Abstract base class for Queue-Only subscribers.
 *
 * Queue-based webhook architecture:
 * - No webhooks (only queue)
 * - No import mode (no bidirectional sync)
 * - Direct enqueue with full entity data
 * - Lightweight notification service
 *
 * Provides common functionality for:
 * - Primary key extraction
 * - Config checking (enable/disable events)
 * - Entity fetching with payload fallback
 * - Queue management with full entity data
 * - Notification service integration
 *
 *  Cobby\Subscriber
 */
abstract class AbstractWebhookSubscriber implements EventSubscriberInterface
{
    use SecurityTrait;

    protected QueueTableService $queueService;

    protected NotificationService $notificationService;

    protected LoggerInterface $logger;

    protected SystemConfigService $systemConfigService;

    public function __construct(
        QueueTableService $queueService,
        NotificationService $notificationService,
        LoggerInterface $logger,
        SystemConfigService $systemConfigService,
    ) {
        $this->queueService = $queueService;
        $this->notificationService = $notificationService;
        $this->logger = $logger;
        $this->systemConfigService = $systemConfigService;
    }

    /**
     * Get the config key for enabling/disabling this subscriber's events.
     * Example: 'Cobby.config.enableProductEvents'.
     */
    abstract protected function getConfigKey(): string;

    /**
     * Extract the primary key from WriteResult.
     * Handles both array and string formats.
     */
    protected function extractPrimaryKey($primaryKey): ?string
    {
        if (\is_string($primaryKey)) {
            return $primaryKey;
        }

        if (\is_array($primaryKey)) {
            return $primaryKey['id'] ?? $primaryKey[0] ?? null;
        }

        return null;
    }

    /**
     * Check if events for this subscriber are enabled in the config.
     */
    protected function isEnabled(): bool
    {
        $enabled = $this->systemConfigService->get($this->getConfigKey());

        return $enabled !== false;
    }

    /**
     * Detect the context type from Shopware's Context Source.
     *
     * Maps Shopware's ContextSource types to meaningful context values:
     * - AdminApiSource with cobby integrationId → 'cobby' (configured cobby integration)
     * - AdminApiSource with other integrationId → {integrationId UUID} (other integration)
     * - AdminApiSource with userId → 'admin-backend' (admin panel, admin user)
     * - SalesChannelApiSource → 'api' (store API, storefront)
     * - SystemSource → 'system' (CLI commands, scheduled tasks)
     *
     * @param Context $shopwareContext The Shopware context
     *
     * @return string Context type ('admin-backend', 'cobby', {integrationId}, 'api', 'system')
     */
    protected function detectContextType(Context $shopwareContext): string
    {
        $source = $shopwareContext->getSource();

        if ($source instanceof AdminApiSource) {
            // Check for integration
            if ($source->getIntegrationId()) {
                $cobbyIntegrationId = $this->queueService->getCobbyIntegrationId();  // Auto-load & cache

                if ($cobbyIntegrationId && $source->getIntegrationId() === $cobbyIntegrationId) {
                    return 'cobby';  // Known cobby integration
                }

                return $source->getIntegrationId();  // Other integration (UUID)
            }

            return 'admin-backend';  // Admin user call
        }

        if ($source instanceof SalesChannelApiSource) {
            return 'api';
        }

        if ($source instanceof SystemSource) {
            return 'system';
        }

        // Unknown source type - log warning and fallback
        $this->logger->warning('Unknown ContextSource type encountered', [
            'source_type' => $source::class,
            'entity_context' => 'webhook_subscriber',
        ]);

        return 'backend';
    }

    /**
     * Enqueue entity change with metadata only (simplified v0.5.0-beta approach).
     *
     * Metadata-Only architecture:
     * 1. Queue stores: entity_type + entity_id + operation (no entity_data)
     * 2. Lightweight notification webhook sent with queue_id reference
     * 3. External service fetches entity data via Shopware API on-demand
     *
     * This is the RECOMMENDED method for all subscribers in v0.5.0-beta.
     *
     * @param string $entityType Entity type (e.g., 'product', 'category', 'tag')
     * @param string $entityId Single entity ID
     * @param string $operation Operation type (insert, update, delete)
     * @param string $context Context where change occurred (backend, api, frontend)
     * @param Context|null $shopwareContext Shopware context to extract admin user (optional)
     */
    protected function enqueueMetadataOnly(
        string $entityType,
        string $entityId,
        string $operation,
        string $context = 'backend',
        ?Context $shopwareContext = null,
    ): void {
        try {
            // 1. Enqueue metadata only (no entity data loading)
            $queueId = $this->queueService->enqueueWithData(
                $entityType,
                $entityId,
                $operation,
                [],  // Empty array - enqueueWithData ignores this anyway (metadata-only)
                $context,
                $shopwareContext // Pass context for user extraction
            );

            // 2. Send lightweight webhook notification if queue entry was created
            //    (Skip notification for Cobby integration - they pull data themselves)
            if ($queueId !== null && $context !== 'cobby') {
                $eventSuffix = ($operation === 'delete') ? 'deleted' : 'written';
                $eventName = $entityType . '.' . $eventSuffix;

                // Build lightweight notification payload
                $payload = [
                    'event' => $eventName,
                    'shopUrl' => $this->getSafeHttpHost(),
                    'entityType' => $entityType,
                    'entityId' => $entityId,
                    'operation' => $operation,
                    'queueId' => $queueId,
                    'timestamp' => time(),
                    'pluginVersion' => CobbyPlugin::PLUGIN_VERSION,
                ];

                $this->notificationService->sendWebhook($eventName, $payload);
            }
        } catch (\Throwable $e) {
            $this->logger->error('Error enqueueing entity metadata', [
                'entityType' => $entityType,
                'entityId' => $entityId,
                'operation' => $operation,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Generic handler for simple entity.written events.
     *
     * @param EntityWrittenEvent $event The written event
     * @param string $entityType The entity type (e.g., 'category', 'tax')
     */
    protected function handleSimpleWrittenEvent(EntityWrittenEvent $event, string $entityType): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        try {
            $contextType = $this->detectContextType($event->getContext());
            foreach ($event->getWriteResults() as $writeResult) {
                $id = $this->extractPrimaryKey($writeResult->getPrimaryKey());
                if ($id) {
                    $this->enqueueMetadataOnly($entityType, $id, $writeResult->getOperation(), $contextType, $event->getContext());
                }
            }
        } catch (\Throwable $e) {
            $this->logger->error("Error in {$entityType} written event", [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Generic handler for simple entity.deleted events.
     *
     * @param EntityDeletedEvent $event The deleted event
     * @param string $entityType The entity type (e.g., 'category', 'tax')
     */
    protected function handleSimpleDeletedEvent(EntityDeletedEvent $event, string $entityType): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        try {
            $contextType = $this->detectContextType($event->getContext());
            foreach ($event->getWriteResults() as $writeResult) {
                $id = $this->extractPrimaryKey($writeResult->getPrimaryKey());
                if ($id) {
                    $this->enqueueMetadataOnly($entityType, $id, 'delete', $contextType, $event->getContext());
                }
            }
        } catch (\Throwable $e) {
            $this->logger->error("Error in {$entityType} deleted event", [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Generic handler for parent entity updates triggered by child entity changes.
     * Example: product_price.written should trigger product update.
     *
     * @param EntityWrittenEvent|EntityDeletedEvent $event The event
     * @param string $parentEntityType Parent entity type (e.g., 'product')
     * @param string $parentIdField Field name in payload containing parent ID (e.g., 'productId')
     */
    protected function handleParentUpdateEvent($event, string $parentEntityType, string $parentIdField): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        try {
            $contextType = $this->detectContextType($event->getContext());

            // Extract parent IDs from child entity changes
            $parentIds = [];
            foreach ($event->getWriteResults() as $writeResult) {
                $payload = $writeResult->getPayload();
                if (isset($payload[$parentIdField])) {
                    $parentIds[] = $payload[$parentIdField];
                }
            }

            // Enqueue parent entities as updated
            foreach (array_unique($parentIds) as $parentId) {
                $this->enqueueMetadataOnly($parentEntityType, $parentId, 'update', $contextType, $event->getContext());
            }
        } catch (\Throwable $e) {
            $this->logger->error('Error in parent entity update event', [
                'parentEntity' => $parentEntityType,
                'parentIdField' => $parentIdField,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
