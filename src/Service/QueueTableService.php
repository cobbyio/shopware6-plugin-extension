<?php declare(strict_types=1);

namespace CobbyShopware6Extension\Service;

use CobbyShopware6Extension\CobbyPlugin;
use CobbyShopware6Extension\Exception\QueueException;
use CobbyShopware6Extension\Util\SecurityTrait;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * Queue Service for Metadata-Only Architecture
 *
 * Manages the cobby_queue table which tracks entity changes.
 * Stores only metadata (entity_type, entity_id, operation).
 * Entity data is loaded on-demand by external service via Shopware API.
 *
 * Metadata-Only Architecture (v0.4.0-beta):
 * - Stores only entity_type + entity_id (no full data)
 * - Minimal database footprint
 * - External service loads data on-demand
 * - Always current data (not stale JSON)
 *
 *  Cobby\Service
 */
class QueueTableService
{
    use SecurityTrait;

    private const CONFIG_WORKSPACE_ID = CobbyPlugin::CONFIG_PREFIX . 'workspaceId';

    public function __construct(
        private readonly Connection $connection,
        private readonly LoggerInterface $logger,
        private readonly SystemConfigService $systemConfigService
    ) {
    }

    /**
     * Enqueue entity change to the queue table (Metadata-Only).
     * Stores only metadata (entity_type, entity_id, operation).
     *
     * The $entityData parameter is kept for backwards compatibility but is ignored.
     * External service loads entity data on-demand via Shopware API.
     *
     * @param string $entityType Entity type (e.g., 'product', 'category', 'order')
     * @param string $entityId Single entity ID
     * @param string $operation Operation type ('insert', 'update', 'delete')
     * @param array $entityData IGNORED - kept for backwards compatibility
     * @param string $context Context where change occurred ('backend', 'api', 'frontend')
     * @param Context|null $shopwareContext Shopware context to extract admin user (optional)
     * @return int|null Created queue ID, or null on failure
     */
    public function enqueueWithData(
        string $entityType,
        string $entityId,
        string $operation,
        array $entityData, // IGNORED - for backwards compatibility only
        string $context = 'backend',
        ?Context $shopwareContext = null
    ): ?int {
        // Extract admin user or integration from context if available
        $userName = 'System';
        if ($shopwareContext && $shopwareContext->getSource() instanceof AdminApiSource) {
            $source = $shopwareContext->getSource();

            // Check integration first (priority over user)
            if ($source->getIntegrationId()) {
                $cobbyIntegrationId = $this->getCobbyIntegrationId();  // Auto-load & cache

                if ($cobbyIntegrationId && $source->getIntegrationId() === $cobbyIntegrationId) {
                    $userName = 'cobby';  // Known cobby integration
                } else {
                    $userName = $source->getIntegrationId();  // Other integration (UUID)
                }
            }
            // Then check user
            else if ($source->getUserId()) {
                $userName = $source->getUserId();  // Admin user ID
            }
        }

        try {
            // Metadata-Only: Store only entity_type, entity_id, operation (no entity_data)
            // Note: created_at is automatically set by database (DEFAULT CURRENT_TIMESTAMP(3))
            $this->connection->insert('cobby_queue', [
                'entity_type' => $entityType,
                'entity_id' => $entityId, // Single ID (no batching)
                'operation' => $operation,
                'user_name' => $userName, // Admin user ID or 'System'
                'context' => $context,
            ]);

            $queueId = (int) $this->connection->lastInsertId();

            $this->logger->info('Queue entry created (metadata-only)', [
                'queue_id' => $queueId,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'operation' => $operation,
            ]);

            return $queueId;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to enqueue change', [
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'operation' => $operation,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get the cobby integration ID, loading from database and caching in config.
     *
     * Workflow:
     * 1. Check if already cached in config
     * 2. If not: Load from integration table (WHERE label='cobby')
     * 3. Cache the result for future requests
     * 4. Log warning if not found
     *
     * @return string|null Cobby integration UUID or null if not found
     */
    public function getCobbyIntegrationId(): ?string
    {
        // Check cache first
        $cached = $this->systemConfigService->get(CobbyPlugin::CONFIG_PREFIX . 'cobbyIntegrationId');
        if ($cached) {
            return $cached;
        }

        // Load from database
        try {
            $result = $this->connection->fetchOne(
                'SELECT LOWER(HEX(id)) FROM integration WHERE label = :label LIMIT 1',
                ['label' => 'cobby']
            );

            if ($result) {
                // Cache for future requests
                $this->systemConfigService->set(CobbyPlugin::CONFIG_PREFIX . 'cobbyIntegrationId', $result);
                $this->logger->info('Cobby integration ID loaded and cached', ['integration_id' => $result]);
                return $result;
            }

            // Not found - log warning once
            $this->logger->warning('Cobby integration not found in database', [
                'hint' => 'Create integration with label "cobby" in Settings > System > Integrations'
            ]);
            return null;

        } catch (\Throwable $e) {
            $this->logger->error('Failed to load cobby integration ID', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get queue entries starting from a minimum queue ID.
     * Used by external system to poll for changes.
     *
     * Returns only metadata (entity_type, entity_id, operation).
     * External service loads entity data on-demand via Shopware API.
     *
     * @param int $minQueueId Minimum queue ID to retrieve (exclusive)
     * @param int $pageSize Maximum number of entries to return (default: 100, max: 1000)
     * @return array Array of queue entries (metadata only)
     */
    public function getQueue(int $minQueueId = 0, int $pageSize = 100): array
    {
        // Limit page size to prevent memory issues
        $pageSize = min($pageSize, 1000);

        try {
            // Metadata-Only: Return only entity_type, entity_id, operation
            $sql = '
                SELECT
                    queue_id,
                    entity_type,
                    entity_id,
                    operation,
                    user_name,
                    context,
                    created_at
                FROM cobby_queue
                WHERE queue_id > :minQueueId
                ORDER BY queue_id ASC
                LIMIT :pageSize
            ';

            $result = $this->connection->fetchAllAssociative($sql, [
                'minQueueId' => $minQueueId,
                'pageSize' => $pageSize,
            ], [
                'minQueueId' => \PDO::PARAM_INT,
                'pageSize' => \PDO::PARAM_INT,
            ]);

            return $result;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to retrieve queue', [
                'minQueueId' => $minQueueId,
                'pageSize' => $pageSize,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Get the maximum queue ID currently in the queue.
     * Used to determine where to start polling.
     *
     * @return int Maximum queue ID, or 0 if queue is empty
     */
    public function getMaxQueueId(): int
    {
        try {
            $sql = 'SELECT MAX(queue_id) as max_id FROM cobby_queue';
            $result = $this->connection->fetchOne($sql);

            return (int) ($result ?? 0);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to get max queue ID', [
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * Truncate the queue table.
     * Deletes all entries and resets auto-increment IDs to 1.
     *
     * This is useful for:
     * - Resetting the queue after full synchronization
     * - Cleaning up the database after testing
     * - Starting fresh with ID 1
     *
     * WARNING: This deletes ALL queue entries!
     *
     * @return void
     */
    public function truncateQueue(): void
    {
        try {
            $this->connection->executeStatement('TRUNCATE TABLE cobby_queue');
            $this->logger->info('Queue table truncated (all entries deleted, auto-increment IDs reset to 1)');
        } catch (\Throwable $e) {
            $this->logger->error('Failed to truncate queue table', [
                'error' => $e->getMessage(),
            ]);
            throw QueueException::truncateFailed($e);
        }
    }

}
