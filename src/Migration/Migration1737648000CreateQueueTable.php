<?php declare(strict_types=1);

namespace CobbyShopware6Extension\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * Migration: Create Queue Table (Metadata-Only Architecture)
 *
 * Creates the cobby_queue table for tracking entity changes.
 * Stores only metadata (entity_type, entity_id, operation).
 * Entity data is loaded on-demand via Shopware API.
 *
 * Metadata-Only features:
 * - Minimal storage (only IDs, not full data)
 * - Small database footprint
 * - Always current data (loaded live from Shopware)
 *
 *  Cobby\Migration
 */
class Migration1737648000CreateQueueTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1737648000; // 2025-01-23
    }

    public function update(Connection $connection): void
    {
        // Create queue table for Metadata-Only architecture
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `cobby_queue` (
                `queue_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `entity_type` VARCHAR(30) NOT NULL COMMENT "Entity type (product, category, etc.)",
                `entity_id` VARCHAR(36) NOT NULL COMMENT "Entity ID (single UUID per queue entry)",
                `operation` VARCHAR(10) NOT NULL COMMENT "Operation type (insert, update, delete)",
                `user_name` VARCHAR(255) NULL COMMENT "Admin user name or System",
                `context` VARCHAR(20) NOT NULL DEFAULT "backend" COMMENT "Context (backend, api, system, cobby)",
                `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) COMMENT "Automatically set by database",
                PRIMARY KEY (`queue_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT="Metadata-Only architecture: stores only entity metadata, data loaded on-demand";
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
        // No destructive changes needed
    }
}
