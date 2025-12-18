# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

This is a Shopware 6 plugin (`CobbyShopware6Extension`) that extends Shopware's webhook functionality to support **12 entity types** with **30 webhook events**: Products (8), Property Groups (4), Categories (2), Manufacturers (2), Tax (2), Currency (2), Sales Channels (2), Rules (2), Units (2), Delivery Times (2), and Tags (2). These webhook events provide comprehensive e-commerce entity tracking beyond standard Shopware Apps.

## Development Commands

### Plugin Management
```bash
# Install and activate the plugin
bin/console plugin:install Cobby --activate

# Refresh plugin list
bin/console plugin:refresh

# Clear cache after changes
bin/console cache:clear
```

### Testing in Docker/Dockware
```bash
# Access container
docker exec -it shopware-container bash

# Inside container, run commands above
```

## Architecture

### Component Overview
```
CobbyShopware6Extension (Main Plugin Class)
    └── Subscribers (12 Total)
        ├── AbstractWebhookSubscriber (Base Class - DRY, PHP 8 Constructor Promotion)
        │   └── SimpleEntitySubscriber (Template Method Pattern for simple entities)
        │       ├── CategorySubscriber (2 events)
        │       ├── TaxSubscriber (2 events)
        │       ├── CurrencySubscriber (2 events)
        │       ├── ManufacturerSubscriber (2 events)
        │       ├── SalesChannelSubscriber (2 events)
        │       ├── RuleSubscriber (2 events)
        │       ├── UnitSubscriber (2 events)
        │       ├── DeliveryTimeSubscriber (2 events)
        │       └── TagSubscriber (2 events)
        ├── ProductSubscriber (8 events - complex, tracks child entities)
        └── PropertyGroupSubscriber (4 events)
    └── Services
        ├── NotificationService (HTTP Communication)
        └── QueueTableService (Database-backed queue)
    └── Database Tables
        └── cobby_queue (Metadata-Only: entity_type VARCHAR(30) + entity_id VARCHAR(36) only)
```

### Key Design Patterns

**1. AbstractWebhookSubscriber Pattern (DRY + PHP 8)**
- Base class for all subscribers
- Common functionality: primary key extraction, config checking, webhook sending
- PHP 8 Constructor Property Promotion (reduced from ~15 lines to 6 lines)
- Child classes: SimpleEntitySubscriber, ProductSubscriber, PropertyGroupSubscriber

**2. SimpleEntitySubscriber Pattern (Template Method)**
- Extends AbstractWebhookSubscriber
- Auto-generates event subscriptions for simple entities
- Reduces subscriber code from ~35 lines to ~18 lines
- Used by: 9 simple entities (Category, Tax, Currency, Manufacturer, SalesChannel, Rule, Unit, DeliveryTime, Tag)
- Only requires: `getEntityType()` and `getConfigKey()` implementation

**3. Service-Subscriber Pattern**
- Subscribers listen to Shopware DAL events
- NotificationService handles HTTP communication
- Dependency injection via services.xml

**4. Lazy Configuration Loading**
- Configuration is loaded on-demand (not in constructor)
- Changes take effect immediately without cache clear
- Methods: `getWebhookUrl()`, `isDebugLoggingEnabled()`

### Event Flow
1. **Shopware triggers event** (e.g., product.written)
2. **Subscriber catches event** (ProductSubscriber::onProductWritten)
3. **Config check** (`isEnabled()` from AbstractWebhookSubscriber)
4. **Entity fetched** with associations
5. **Webhook sent** via NotificationService

## Critical Implementation Details

### 1. Why curl Instead of GuzzleHttp
**Problem**: GuzzleHttp would sometimes hang indefinitely in Docker containers.
**Solution**: Native PHP curl with `CURLOPT_NOSIGNAL=1` proved more reliable.
**Location**: `NotificationService::sendWebhookWithResponse()`

### 2. System Field Filtering
**Problem**: Shopware auto-updates many fields (versionId, updatedAt) even when user only changed one field.
**Solution**: Filter out system-generated fields to show only user-modified fields.
**Location**: `ProductSubscriber::getRelevantChangedFields()`

**System Fields Filtered**:
- versionId, parentVersionId, *VersionId
- updatedAt, createdAt
- availableStock, available, childCount, ratingAverage, sales
- translated, _uniqueIdentifier

### 3. Changed Fields Detection
**Feature**: Track which fields actually changed (only on updates).
**Implementation**: Extract `array_keys($payload)` and filter system fields.
**Location**: `ProductSubscriber::onProductWritten()` line ~113

### 4. Config Lazy Loading
**Why**: Allows configuration changes without cache clear.
**Implementation**: Private methods in NotificationService fetch config on each request.
**Methods**: `getWebhookUrl()`, `isDebugLoggingEnabled()`

### 5. JSON Error Handling
**Problem**: `json_encode()` can return false silently.
**Solution**: Use `JSON_THROW_ON_ERROR` flag to catch encoding errors.
**Location**: `NotificationService::sendWebhookWithResponse()` line ~114

## Code Structure

```
src/
├── CobbyShopware6Extension.php          # Main plugin class (lifecycle)
├── Controller/
│   └── QueueTableController.php         # API endpoints for queue management
├── Service/
│   ├── NotificationService.php          # HTTP communication
│   └── QueueTableService.php            # Database queue management
└── Subscriber/
    ├── AbstractWebhookSubscriber.php    # Base class with generic handlers
    ├── PropertyGroupSubscriber.php      # Property group events
    ├── ProductSubscriber.php            # Product events (8 types)
    └── ... (8 more subscribers)

Resources/config/
├── config.xml                           # Plugin configuration
└── services.xml                         # Dependency injection
```

## Important Notes

### What This Plugin Does NOT Support
- ❌ Webhook retry logic (fire-and-forget delivery)
- ❌ Webhook batching (each event = separate webhook)
- ❌ Webhook response processing (one-way communication)
- ❌ Event filtering by field (all changes sent, filter on receiver side)

### Key Features
- ✅ 12 entity types with 30 events: Product (8), PropertyGroup (4), Category (2), Manufacturer (2), Tax (2), Currency (2), SalesChannel (2), Rule (2), Unit (2), DeliveryTime (2), Tag (2)
- ✅ Database queue system (cobby_queue) with metadata-only storage (entity_type + entity_id)
- ✅ Lazy configuration loading (changes take effect immediately)
- ✅ Deep entity associations with comprehensive data
- ✅ UUID v4 shop identification
- ✅ Consolidated NotificationService for all webhook delivery

## Best Practices When Modifying

### Adding New Subscribers
1. Extend `AbstractWebhookSubscriber`
2. Implement `getConfigKey()` - return config key for enable/disable
3. Implement `extractEntityData($entity)` - extract data from entity object
4. Implement `buildFallbackData($id, $payload)` - build fallback data for failed fetches
5. Use generic methods for event handlers

Example (Simple Entity):
```php
class CustomSubscriber extends AbstractWebhookSubscriber
{
    private const CUSTOM_ASSOCIATIONS = ['translations', 'media'];

    protected function getConfigKey(): string {
        return 'Cobby.config.enableCustomEvents';
    }

    protected function extractEntityData($entity): array {
        return [
            'id' => $entity->getId(),
            'name' => $entity->getName(),
            'active' => $entity->getActive(),
        ];
    }

    protected function buildFallbackData(string $id, array $payload): array {
        return [
            'id' => $id,
            'name' => $payload['name'] ?? null,
            'active' => $payload['active'] ?? true,
        ];
    }

    // Use generic methods (DRY principle)
    public function onCustomWritten(EntityWrittenEvent $event): void {
        $this->handleWrittenEvent($event, 'custom', self::CUSTOM_ASSOCIATIONS, $this->customRepository);
    }

    public function onCustomDeleted(EntityDeletedEvent $event): void {
        $this->handleDeletedEvent($event, 'custom');
    }
}
```

Example (Parent-Child Relationship):
```php
// For child entities that should update parent (e.g., product_price should update product)
public function onChildEntityWritten(EntityWrittenEvent $event): void {
    $this->handleParentEntityEvent(
        $event,
        'parentId',  // Key in child payload containing parent ID
        'parent',    // Parent entity type
        self::PARENT_ASSOCIATIONS,
        $this->parentRepository
    );
}
```

### Generic Methods Available in AbstractWebhookSubscriber

**handleWrittenEvent($event, $entityType, $associations, $repository)**
- For standard entity.written events
- Automatically fetches entity with associations
- Builds fallback data if fetch fails
- Enqueues with full data and sends webhook

**handleDeletedEvent($event, $entityType)**
- For standard entity.deleted events
- Only stores entity ID (no fetch needed)
- Enqueues and sends webhook

**handleParentEntityEvent($event, $childIdKey, $parentEntityType, $associations, $repository)**
- For child entity changes that should trigger parent updates
- Example: product_price.written should update product
- Extracts parent IDs from child payloads
- Fetches and enqueues parent entities

### Error Handling
- **ALWAYS** wrap event handlers in try-catch
- **NEVER** throw exceptions in subscribers (breaks Shopware)
- **ALWAYS** log errors with context

### Testing
```bash
# Create test product
docker exec shopware-container bash -c "
  php bin/console product:create --name='Test Product'
"

# Watch webhook logs
docker exec shopware-container bash -c "
  tail -f /var/www/html/var/log/dev.log | grep Webhook
"
```

## Documentation

- `README.md` - User documentation
- `DEVELOPMENT.md` - Developer documentation (architecture, troubleshooting)
- `ARCHITECTURE.md` - Detailed architecture documentation
- `CHANGELOG.md` - Version history

## Common Tasks

### Adding a New Product Field
1. Add field to `ProductSubscriber::extractEntityData()` (line ~327)
2. Test with product update
3. Verify field appears in webhook payload

### Changing Webhook Timeout
1. Edit `NotificationService::DEFAULT_TIMEOUT` constant (line ~35)
2. Clear cache: `bin/console cache:clear`

### Debugging Webhooks
1. Enable debug logging in plugin config
2. Check logs: `tail -f /var/www/html/var/log/dev.log`
3. Look for "Preparing webhook" and "Webhook sent" messages

## Version Information

- **Shopware Compatibility**: 6.4+
- **PHP Version**: 8.0+ (uses PHP 8 features: Attributes, Constructor Promotion)

## Architecture
**Metadata-Only Architecture:**
- 12 entity types tracked via database queue (30 events total)
- Single database table: `cobby_queue` (stores metadata only: entity_type + entity_id, NO entity_data column)
- 2 core services: QueueTableService, NotificationService
- 1 controller: QueueTableController (API endpoints for queue management)
- All subscribers extend AbstractWebhookSubscriber (DRY)
- Lightweight webhook notifications
- External service loads entity data on-demand via Shopware API
- Migration: Only Migration1737648000CreateQueueTable.php (removed 4 other migrations)

**API Endpoints:**
- GET /api/cobby-queue?minQueueId=X&pageSize=Y - Get queue entries (metadata only)
- GET /api/cobby-queue/max - Get maximum queue ID
- DELETE /api/cobby-queue - Truncate queue (deletes all entries, resets IDs)

See `CHANGELOG.md` for version history.
