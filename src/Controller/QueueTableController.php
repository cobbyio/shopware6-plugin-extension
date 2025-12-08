<?php declare(strict_types=1);

namespace CobbyPlugin\Controller;

use CobbyPlugin\Service\QueueTableService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * QueueTable API Controller for Cobby Connector (Metadata-Only Architecture)
 *
 * Provides REST endpoints for queue management:
 * - Get queue entries (metadata only: entity_type, entity_id, operation)
 * - Get max queue ID
 * - Delete processed queue entries
 * - Truncate queue (reset IDs)
 *
 * Metadata-Only features:
 * - Returns only metadata (no entity_data)
 * - External service loads entity data on-demand via Shopware API
 * - Minimal database footprint
 */
class QueueTableController extends AbstractController
{
    public function __construct(
        private readonly QueueTableService $queueService
    ) {
    }

    /**
     * Get queue entries starting from a minimum queue ID.
     *
     * Metadata-Only Architecture: Returns only metadata (entity_type, entity_id, operation).
     * External service must load entity data via Shopware API (e.g., GET /api/product/{id}).
     *
     * Query parameters:
     * - minQueueId (int): Minimum queue ID to retrieve (default: 0)
     * - pageSize (int): Maximum number of entries to return (default: 100, max: 1000)
     *
     * Response format:
     * {
     *   "success": true,
     *   "data": [
     *     {
     *       "queue_id": 1,
     *       "entity_type": "product",
     *       "entity_ids": "abc123",
     *       "operation": "update",
     *       "user_name": "System",
     *       "context": "backend",
     *       "transaction_id": "xyz789",
     *       "created_at": "2025-01-23 10:30:00.123"
     *     },
     *     ...
     *   ],
     *   "count": 10
     * }
     *
     * External service workflow:
     * 1. Call GET /api/_action/cobby/queue?minQueueId=0
     * 2. For each entry, load entity data: GET /api/product/{entity_ids}
     * 3. Process entity data
     * 4. After full sync: Call DELETE /api/_action/cobby/queue (truncates entire queue)
     */
    #[Route(path: '/api/cobby-queue', name: 'api.action.cobby.queue.get', methods: ['GET'], defaults: ['_routeScope' => ['api']])]
    public function list(Request $request): JsonResponse
    {
        $minQueueId = (int) $request->query->get('minQueueId', 0);
        $pageSize = (int) $request->query->get('pageSize', 100);

        // Limit page size to prevent performance issues
        if ($pageSize > 1000) {
            $pageSize = 1000;
        }

        if ($pageSize < 1) {
            $pageSize = 1;
        }

        $queue = $this->queueService->getQueue($minQueueId, $pageSize);

        return new JsonResponse([
            'success' => true,
            'data' => $queue,
            'count' => count($queue),
        ]);
    }

    /**
     * Get the maximum queue ID currently in the queue.
     * Used to determine where to start polling from.
     *
     * Response format:
     * {
     *   "success": true,
     *   "maxQueueId": 12345
     * }
     */
    #[Route(path: '/api/cobby-queue/max', name: 'api.action.cobby.queue.max', methods: ['GET'], defaults: ['_routeScope' => ['api']])]
    public function getMaxQueueId(): JsonResponse
    {
        $maxQueueId = $this->queueService->getMaxQueueId();

        return new JsonResponse([
            'success' => true,
            'maxQueueId' => $maxQueueId,
        ]);
    }

    /**
     * Delete all queue entries and reset auto-increment IDs.
     * Called by external system after full synchronization.
     *
     * WARNING: This deletes ALL queue entries!
     *
     * Response format:
     * {
     *   "success": true,
     *   "message": "Queue truncated successfully (all entries deleted, IDs reset to 1)"
     * }
     */
    #[Route(path: '/api/cobby-queue', name: 'api.action.cobby.queue.delete', methods: ['DELETE'], defaults: ['_routeScope' => ['api']])]
    public function resetQueue(Request $request): JsonResponse
    {
        try {
            $this->queueService->truncateQueue();

            return new JsonResponse([
                'success' => true,
                'message' => 'Queue truncated successfully (all entries deleted, IDs reset to 1)',
            ]);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Failed to truncate queue: ' . $e->getMessage(),
            ], 500);
        }
    }
}
