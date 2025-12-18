# CobbyShopware6Extension - Architecture Documentation

## Table of Contents
1. [Overview](#overview)
2. [Architecture Diagram](#architecture-diagram)
3. [Components](#components)
4. [Data Flow](#data-flow)
5. [Design Patterns](#design-patterns)
6. [Critical Design Decisions](#critical-design-decisions)
7. [Code Examples](#code-examples)
8. [Performance](#performance)
9. [Security](#security)
10. [Testing Strategy](#testing-strategy)
11. [Future Enhancements](#future-enhancements)

---

## Overview

CobbyShopware6Extension extends Shopware 6's webhook functionality to support comprehensive e-commerce entity tracking across **12 entity types** with **30 webhook events**:

**Supported Entities:**
- **Products** (8 events) - Catalog, prices, media, categories
- **Property Groups** (4 events) - Variant attributes and options
- **Categories** (2 events) - Category tree changes
- **Manufacturers** (2 events) - Brand/manufacturer changes
- **Tax Rates** (2 events) - Tax configuration
- **Currencies** (2 events) - Currency configuration
- **Sales Channels** (2 events) - Sales channel configuration
- **Rules** (2 events) - Business rule changes
- **Units** (2 events) - Measurement unit changes
- **Delivery Times** (2 events) - Delivery time configuration
- **Tags** (2 events) - Product tags and labels

### Key Features
- **Optimized Database Schema** (~60% storage reduction with VARCHAR optimizations)
- **SimpleEntitySubscriber Pattern** (Template Method, ~50% code reduction for simple entities)
- **PHP 8 Modern Features** (Attributes for routes, Constructor Property Promotion)
- **Lazy Configuration Loading** (no cache clear needed)
- **DRY Architecture** with AbstractWebhookSubscriber + SimpleEntitySubscriber
- **PSR-3 Compliant Logging**
- **JSON Error Handling** with proper exceptions
- **Deep Entity Data** with comprehensive associations
- **Database Queue** for reliable change tracking (metadata-only: stores entity_type + entity_id)
- **Lightweight Notifications**

### What's NOT Supported
- ❌ Retry logic (fire-and-forget webhook delivery)
- ❌ Webhook batching (each event triggers separate webhook)
- ❌ Webhook filtering (all changes sent, filtering on receiver side)

---

## Architecture Diagram

```
┌──────────────────────────────────────────────────────────────────────────────┐
│                           Shopware 6 Core                                    │
│          (Entity Manager, Event Dispatcher, DI Container)                    │
└───────────────────────────┬──────────────────────────────────────────────────┘
                            │
                            │ Fires DAL Events (EntityWrittenEvent, EntityDeletedEvent)
                            ▼
┌──────────────────────────────────────────────────────────────────────────────┐
│                   Event Subscribers (12 Total, 30 Events)                    │
│                                                                              │
│  ┌────────────────────────────────────────────────────────────────────────┐  │
│  │          AbstractWebhookSubscriber (Base Class - DRY)                  │  │
│  │  • extractPrimaryKey() • isEnabled() • buildWebhookPayload()           │  │
│  │  • fetchEntityWithFallback() • sendWebhookSafely() • enqueueAndNotify()│  │
│  └──────┬──────────────┬──────────────┬─────────────┬──────────────┬──────┘  │
│         │              │              │             │              │         │
│   ┌─────▼────┐  ┌──────▼─────┐  ┌─────▼────┐  ┌─────▼─────┐   ┌────▼─────┐   │
│   │Product   │  │PropertyGrp │  │Category  │  │Manufactur │   │Tax       │   │
│   │(8 events)│  │(4 events)  │  │(2 events)│  │(2 events) │   │(2 events)│   │
│   └─────┬────┘  └──────┬─────┘  └───────┬──┘  └───────┬───┘   └────┬─────┘   │
│         │              │                │             │            │         │
│   ┌─────▼────────┐  ┌──▼──────────┐  ┌──▼─────────┐ ┌─▼─────────┐ ┌▼───────┐ │
│   │Currency      │  │SalesChannel │  │Rule        │ │Unit       │ │Delivery│ │
│   │(2 events)    │  │(2 events)   │  │(2 events)  │ │(2 events) │ │Time    │ │
│   └──────┬───────┘  └─────┬───────┘  └──────┬─────┘ └──────┬────┘ │(2 evs) │ │
│          │                │                 │              │      └────┬───┘ │
│          │                │                 │              │           │     │
│          │          ┌─────▼─────────┐       │              │           │     │
│          │          │Tag (2 events) │       │              │           │     │
│          │          └───────┬───────┘       │              │           │     │
│          └──────────────────┴───────────────┴──────────────┴───────────┘     │
│                                             │                                │
└─────────────────────────────────────────────┼────────────────────────────────┘
                                              │
                    ┌─────────────────────────┼────────────────┐
                    ▼                         ▼                ▼
        ┌───────────────────────┐  ┌────────────────────────────────┐
        │  NotificationService  │  │       QueueTableService        │
        │                       │  │                                │
        │• curl HTTP POST       │  │• Metadata-only storage         │
        │• Lazy config          │  │• Stores entity_type + entity_id│
        │• Lightweight payload  │  │• No entity_data column         │
        │                       │  │                                │
        └─────┬─────────────────┘  └───────┬────────────────────────┘
              │                            │
              │                            ▼
              │              ┌──────────────────────────────────────────┐
              │              │         Database (MySQL/MariaDB)         │
              │              │                                          │
              │              │  ┌────────────────────────────────────┐  │
              │              │  │  cobby_queue (Metadata-Only)       │  │
              │              │  │  • entity_type, entity_ids (TEXT)  │  │
              │              │  │  • operation (insert/update/delete)│  │
              │              │  │  • user_name, context, created_at  │  │
              │              │  │  • transaction_id (grouping)       │  │
              │              │  │  • NO entity_data column           │  │
              │              │  └────────────────────────────────────┘  │
              │              └──────────────────────────────────────────┘
              │
              │ curl_exec() with CURLOPT_NOSIGNAL
              ▼
┌────────────────────────────────────────────────────────────────┐
│         External Webhook Endpoint                              │
│         https://automate.cobby.io/webhook/shopware/plugin-event│
│                                                                │
│  Receives: Content-Type: application/json                      │
│           X-Shopware-Event: product.written                    │
└────────────────────────────────────────────────────────────────┘
```

---

## Components

The plugin consists of **12 event subscribers**, **2 core services**, **1 database table**, and various support classes. This section documents the architecture and implementation details.

**Event Subscribers** (all extend AbstractWebhookSubscriber):
1. **ProductSubscriber** - 8 events (product, product_price, product_media, product_category)
2. **PropertyGroupSubscriber** - 4 events (property_group, property_group_option)
3. **CategorySubscriber** - 2 events (category.written, category.deleted)
4. **ManufacturerSubscriber** - 2 events (product_manufacturer.written/deleted)
5. **TaxSubscriber** - 2 events (tax.written/deleted)
6. **CurrencySubscriber** - 2 events (currency.written/deleted)
7. **SalesChannelSubscriber** - 2 events (sales_channel.written/deleted)
8. **RuleSubscriber** - 2 events (rule.written/deleted)
9. **UnitSubscriber** - 2 events (unit.written/deleted)
10. **DeliveryTimeSubscriber** - 2 events (delivery_time.written/deleted)
11. **TagSubscriber** - 2 events (tag.written/deleted)

**Core Services**:
- **NotificationService** - HTTP communication
- **QueueTableService** - Database-backed queue with metadata-only storage (entity_type + entity_id)

**Note**: Detailed documentation below focuses on PropertyGroupSubscriber and ProductSubscriber as reference implementations. All other subscribers (CategorySubscriber, ManufacturerSubscriber, TaxSubscriber, CurrencySubscriber, SalesChannelSubscriber, RuleSubscriber, UnitSubscriber, DeliveryTimeSubscriber, TagSubscriber) follow the same pattern with entity-specific data extraction logic.

### 1. Plugin Main Class
**File**: `src/CobbyShopware6Extension.php`

**Responsibilities**:
- Plugin lifecycle management (install, activate, deactivate, uninstall)
- Service container configuration
- Default configuration initialization

**Key Methods**:
- `build(ContainerBuilder $container)`: Loads services.xml into DI container
- `install(InstallContext $context)`: Initializes default configuration
- `activate(ActivateContext $context)`: Validates configuration
- `uninstall(UninstallContext $context)`: Optionally removes configuration

**Configuration**:
```php
private const DEFAULT_WEBHOOK_URL = 'https://automate.cobby.io/webhook/shopware/plugin-event';

$defaults = [
    'webhookUrl' => self::DEFAULT_WEBHOOK_URL,
    'enablePropertyGroupEvents' => true,
    'enableProductEvents' => true,
    'enableDebugLogging' => false,
];
```

### 2. Services

#### 2.1 NotificationService
**File**: `src/Service/NotificationService.php`

**Purpose**: Lightweight HTTP notification delivery

**Constructor Dependencies**:
```php
public function __construct(
    LoggerInterface $logger,
    SystemConfigService $systemConfigService
)
```

**Key Design**: Lazy Configuration Loading
```php
// ❌ OLD: Config loaded in constructor
public function __construct(...) {
    $this->webhookUrl = $configService->get('...');  // Fixed at instantiation
}

// ✅ NEW (v0.4.0-beta): Config loaded on-demand
private function getWebhookUrl(): string {
    return $this->systemConfigService->get('Cobby.config.webhookUrl')
        ?? self::DEFAULT_WEBHOOK_URL;
}
```

**Benefits**:
- Configuration changes take effect immediately (no cache clear)
- Better testability
- Lower memory footprint

**Key Method**: `sendWebhookWithResponse(array $data): array`

1. **Add Shop Metadata**:
   ```php
   $data['shop'] = [
       'shopUrl' => $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost',
       'shopwareVersion' => $this->getShopwareVersion(),
   ];
   ```

2. **JSON Encode with Error Handling**:
   ```php
   try {
       $jsonData = json_encode($data, JSON_THROW_ON_ERROR);
   } catch (\JsonException $e) {
       $this->logger->error('JSON encoding failed', ['error' => $e->getMessage()]);
       return ['success' => false, 'error' => 'JSON encoding failed'];
   }
   ```

3. **Send with curl** (why curl? See [Critical Design Decisions](#critical-design-decisions)):
   ```php
   curl_setopt($ch, CURLOPT_NOSIGNAL, 1);        // Prevents signal issues
   curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);  // 2s to connect
   curl_setopt($ch, CURLOPT_TIMEOUT, 5);         // 5s total
   curl_setopt($ch, CURLOPT_HTTPHEADER, [
       'Content-Type: application/json',
       'X-Shopware-Event: ' . $data['event'],
   ]);
   ```

5. **PSR-3 Logging**:
   ```php
   // ❌ OLD: Hardcoded file path
   file_put_contents('/var/www/html/var/log/cobby_webhook.log', ...);

   // ✅ NEW: PSR-3 logger
   $this->logger->info('Webhook sent successfully', [
       'event' => $eventName,
       'http_status' => $httpCode,
       'response_time' => $duration,
   ]);
   ```

### 3. Database Layer

The plugin uses **1 database table** for reliable change tracking with metadata-only storage (Metadata-Only Architecture).

#### 3.1 cobby_queue Table

**Purpose**: Tracks all entity changes using metadata only (entity_type + entity_id). External services load actual entity data on-demand via Shopware API.

**Schema** (Optimized):
```sql
CREATE TABLE IF NOT EXISTS `cobby_queue` (
    `queue_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `entity_type` VARCHAR(30) NOT NULL COMMENT 'Entity type (product, category, etc.)',
    `entity_id` VARCHAR(36) NOT NULL COMMENT 'Entity ID (single UUID per queue entry)',
    `operation` VARCHAR(10) NOT NULL COMMENT 'Operation type (insert, update, delete)',
    `user_name` VARCHAR(255) NULL COMMENT 'Admin user name or System',
    `context` VARCHAR(20) NOT NULL DEFAULT 'backend' COMMENT 'Context (backend, api, system, cobby)',
    `created_at` DATETIME(3) NOT NULL,
    PRIMARY KEY (`queue_id`),
    KEY `idx.cobby_queue.entity_type` (`entity_type`),
    KEY `idx.cobby_queue.created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Metadata-Only architecture: stores only entity metadata, data loaded on-demand';
```

**Optimizations from v0.4.0**:
- `entity_type`: VARCHAR(100) → **VARCHAR(30)** (longest: "product_manufacturer" = 20 chars)
- `entity_id`: TEXT → **VARCHAR(36)** (UUID-optimized, ~99% storage reduction)
- `operation`: VARCHAR(20) → **VARCHAR(10)** (max: "delete" = 6 chars)
- `context`: VARCHAR(50) → **VARCHAR(20)** (max: "admin-backend" = 13 chars)
- **Removed `transaction_id`** field and index (never used, no grouping functionality)
- **Storage savings**: ~156 bytes per row

**Usage Pattern**:
```php
// QueueTableService::enqueueWithData()
// Note: $entityData parameter ignored - metadata-only storage
$this->connection->insert('cobby_queue', [
    'entity_type' => 'product',
    'entity_id' => 'product-id-123',  // Single UUID (VARCHAR(36))
    'operation' => 'update',
    'user_name' => 'System',
    'context' => 'backend',
    'created_at' => (new \DateTime())->format('Y-m-d H:i:s.v')
]);
// No entity_data stored - loaded on-demand by external service
```

**Key Fields**:
- `queue_id`: BIGINT UNSIGNED auto-increment primary key
- `entity_type`: One of: product, property_group, property_group_option, category, manufacturer, tax, currency, sales_channel, rule, unit, delivery_time, tag (VARCHAR(30))
- `entity_id`: VARCHAR(36) containing single entity UUID
- `operation`: insert, update, or delete (VARCHAR(10))
- `user_name`: Admin username or 'System' (VARCHAR(255))
- `context`: backend (admin), api (REST API), system (CLI), or cobby (integration) (VARCHAR(20))
- **NO entity_data column** - data loaded on-demand
- **NO transaction_id** - removed in v0.5.0 (never used)

**Consumer Workflow**:
1. External system receives lightweight webhook notification with queue_id
2. External system polls queue via GET /api/_action/cobby/queue?minQueueId=X
3. For each queue entry, loads entity data on-demand: GET /api/product/{entity_ids}
4. Processes entity data (always current, never stale)
5. Marks processed: DELETE /api/_action/cobby/queue?maxQueueId=Y

### 4. Subscriber Base Class

#### 4.1 AbstractWebhookSubscriber
**File**: `src/Subscriber/AbstractWebhookSubscriber.php`

**Purpose**: Implements DRY principles - eliminates code duplication across all subscribers

**Abstract Methods** (must be implemented by child classes):
```php
abstract protected function getConfigKey(): string;
// Returns: 'Cobby.config.enablePropertyGroupEvents'

abstract protected function extractEntityData($entity): array;
// Converts entity object to array for webhook payload
```

**Protected Helper Methods**:

1. **extractPrimaryKey($primaryKey): ?string**
   - Handles both string IDs and composite keys
   - Returns null if extraction fails

2. **isEnabled(): bool**
   - Checks if event type is enabled in config
   - Uses lazy-loaded configuration

3. **fetchEntityWithFallback(...): array**
   ```php
   protected function fetchEntityWithFallback(
       string $id,
       Context $context,
       array $associations,
       array $fallbackData,
       EntityRepository $repository
   ): array
   ```
   - Tries to fetch full entity with associations
   - Falls back to payload data if fetch fails
   - Ensures webhook is always sent

4. **buildWebhookPayload(...): array**
   ```php
   protected function buildWebhookPayload(
       string $eventName,
       string $operation,
       string $entityType,
       array $entityData
   ): array
   ```
   - Returns standardized payload structure:
   ```json
   {
     "event": "property_group.written",
     "operation": "insert|update",
     "entity": "property_group",
     "data": {...},
     "timestamp": 1234567890
   }
   ```

5. **enqueueWithFullData(...): void**
   - Enqueues entity metadata only (entity_type + entity_id) to queue
   - entityData parameter kept for backwards compatibility but ignored
   - Sends lightweight webhook notification via NotificationService
   - Prevents exceptions from breaking Shopware

**Benefits of this pattern**:
- ~200 lines of code removed through DRY
- Consistent error handling across all subscribers
- Easy to add new subscribers (extend base, implement 2 methods)
- Single source of truth for common logic

### 5. Event Subscribers

**Note**: The plugin includes 12 subscribers (30 events total). This section documents PropertyGroupSubscriber and ProductSubscriber as reference implementations. All other subscribers (CategorySubscriber, ManufacturerSubscriber, TaxSubscriber, CurrencySubscriber, SalesChannelSubscriber, RuleSubscriber, UnitSubscriber, DeliveryTimeSubscriber, TagSubscriber) follow the same architectural pattern with entity-specific data extraction logic.

#### 5.1 PropertyGroupSubscriber
**File**: `src/Subscriber/PropertyGroupSubscriber.php`

**Extends**: `AbstractWebhookSubscriber`

**Constructor**:
```php
public function __construct(
    QueueTableService $queueService,
    NotificationService $notificationService,
    LoggerInterface $logger,
    EntityRepository $propertyGroupRepository,
    EntityRepository $propertyGroupOptionRepository,
    SystemConfigService $systemConfigService
)
```

**Subscribed Events** (4 total):
```php
public static function getSubscribedEvents(): array {
    return [
        'property_group.written' => 'onPropertyGroupWritten',
        'property_group.deleted' => 'onPropertyGroupDeleted',
        'property_group_option.written' => 'onPropertyGroupOptionWritten',
        'property_group_option.deleted' => 'onPropertyGroupOptionDeleted',
    ];
}
```

**Config Key**:
```php
protected function getConfigKey(): string {
    return 'Cobby.config.enablePropertyGroupEvents';
}
```

**Entity Data Extraction**:
```php
protected function extractEntityData($entity): array {
    // Detects type by method existence
    if (method_exists($entity, 'getGroupId')) {
        // PropertyGroupOption
        return [
            'id' => $entity->getId(),
            'groupId' => $entity->getGroupId(),
            'name' => $entity->getName(),
            'position' => $entity->getPosition(),
            'colorHexCode' => $entity->getColorHexCode(),
            'mediaId' => $entity->getMediaId(),
            'customFields' => $entity->getCustomFields(),
            'groupName' => $entity->getGroup()?->getName(),
        ];
    }

    // PropertyGroup
    return [
        'id' => $entity->getId(),
        'name' => $entity->getName(),
        'displayType' => $entity->getDisplayType(),
        'sortingType' => $entity->getSortingType(),
        'filterable' => $entity->getFilterable(),
        'visibleOnProductDetailPage' => $entity->getVisibleOnProductDetailPage(),
        'position' => $entity->getPosition(),
        'customFields' => $entity->getCustomFields(),
    ];
}
```

**Event Handler Pattern** (using generic method):
```php
public function onPropertyGroupWritten(EntityWrittenEvent $event): void {
    // Uses generic handleWrittenEvent() from AbstractWebhookSubscriber
    $this->handleWrittenEvent($event, 'property_group', ['translations'], $this->propertyGroupRepository);
}
```

**What handleWrittenEvent() does**:
1. Checks if events are enabled (`isEnabled()`)
2. Extracts primary key from event
3. Fetches entity with associations
4. Enqueues to database with full entity data
5. Sends lightweight webhook notification
6. Wraps everything in try-catch (never throws)

#### 5.2 ProductSubscriber
**File**: `src/Subscriber/ProductSubscriber.php`

**Extends**: `AbstractWebhookSubscriber`

**Constructor**:
```php
public function __construct(
    QueueTableService $queueService,
    NotificationService $notificationService,
    LoggerInterface $logger,
    EntityRepository $productRepository,
    SystemConfigService $systemConfigService
)
```

**Subscribed Events** (8 total):
```php
public static function getSubscribedEvents(): array {
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
```

**Key Feature: System Field Filtering**

**Problem**: Shopware auto-updates many fields on every save:
```php
// Raw changedFields from Shopware:
["name", "versionId", "parentVersionId", "updatedAt", "availableStock", "available", ...]

// User only changed name, but Shopware updated 10+ fields automatically!
```

**Solution**: Filter out system-generated fields
```php
private const SYSTEM_FIELDS = [
    'versionId', 'parentVersionId', 'productManufacturerVersionId',
    'productMediaVersionId', 'cmsPageVersionId', 'canonicalProductVersionId',
    'updatedAt', 'createdAt', 'autoIncrement', 'availableStock',
    'available', 'childCount', 'ratingAverage', 'sales', 'translated',
    '_uniqueIdentifier'
];

private function getRelevantChangedFields(array $changedFields): array {
    return array_values(array_filter($changedFields, function($field) {
        return !in_array($field, self::SYSTEM_FIELDS);
    }));
}
```

**Result**:
```php
// Filtered changedFields sent to webhook:
["name"]  // Only actual business changes!
```

**Changed Fields Tracking**:
```php
if ($operation === 'update') {
    $changedFields = array_keys($payload);  // Get changed fields from DAL
    $relevantFields = $this->getRelevantChangedFields($changedFields);
    if (!empty($relevantFields)) {
        $entityData['changedFields'] = $relevantFields;
    }
}
```

**Important**: `changedFields` only added on UPDATE operations, not INSERT

**Product Data Extraction** (50+ fields):
```php
protected function extractEntityData($entity): array {
    $data = [
        // Basic
        'id' => $entity->getId(),
        'productNumber' => $entity->getProductNumber(),
        'name' => $entity->getName(),
        'description' => $entity->getDescription(),
        'active' => $entity->getActive(),
        'stock' => $entity->getStock(),

        // Pricing
        'price' => $entity->getPrice(),
        'purchasePrices' => $entity->getPurchasePrices(),
        '
' => $entity->getTaxId(),

        // SEO
        'metaTitle' => $entity->getMetaTitle(),
        'metaDescription' => $entity->getMetaDescription(),
        'keywords' => $entity->getKeywords(),

        // Packaging
        'packUnit' => $entity->getPackUnit(),
        'purchaseUnit' => $entity->getPurchaseUnit(),
        'referenceUnit' => $entity->getReferenceUnit(),

        // Variants
        'parentId' => $entity->getParentId(),
        'childCount' => $entity->getChildCount(),
        'options' => $this->extractOptions($entity),

        // Associations
        'manufacturer' => $this->extractManufacturer($entity),
        'categories' => $this->extractCategories($entity),
        'media' => $this->extractMedia($entity),
        'cover' => $this->extractCover($entity),
        'tax' => $this->extractTax($entity),
        'unit' => $this->extractUnit($entity),
        'deliveryTime' => $this->extractDeliveryTime($entity),

        // ... 30+ more fields
    ];

    return $data;
}
```

**Associations Loaded**:
```php
private const PRODUCT_ASSOCIATIONS = [
    'manufacturer',
    'categories',
    'media',
    'cover',
    'options.group',
    'prices',
    'tax',
    'unit',
    'deliveryTime',
    'properties.group',
    'visibilities',
    'translations',
];
```

---

## Data Flow

### Example 1: Property Group Created

```
1. Admin creates property group "Size" in Shopware backend
   ↓
2. Shopware DAL persists entity to database
   ↓
3. EntityWrittenEvent fired
   event: property_group.written
   operation: insert
   primaryKey: "abc123..."
   payload: ["name" => "Size", "displayType" => "text", ...]
   ↓
4. PropertyGroupSubscriber::onPropertyGroupWritten() invoked
   │
   ├─ handleWrittenEvent() (generic method from AbstractWebhookSubscriber)
   │  ├─ isEnabled()? → Check: enablePropertyGroupEvents === true
   │  ├─ extractPrimaryKey() → "abc123..."
   │  ├─ fetchEntityWithFallback() → Load from property_group.repository
   │  │  └─ Criteria with associations: ['translations']
   │  ├─ enqueueWithFullData() → Store in cobby_queue (metadata only: entity_type + entity_id)
   │  └─ NotificationService::sendWebhook() → Send lightweight notification
   ↓
5. NotificationService::sendWebhook()
   │
   ├─ getWebhookUrl() → "https://automate.cobby.io/..."
   ├─ Add shop metadata → shopUrl, shopwareVersion
   ├─ json_encode($data, JSON_THROW_ON_ERROR)
   ├─ curl POST with headers:
   │  - Content-Type: application/json
   │  - X-Shopware-Event: property_group.written
   └─ logger->info() → Log success/failure
   ↓
6. External endpoint receives lightweight notification:
   POST https://automate.cobby.io/webhook/shopware/plugin-event
   Headers:
     X-Shopware-Event: property_group.written
   Body:
   {
     "event": "property_group.written",
     "shop_id": "550e8400-e29b-41d4-a716-446655440000",
     "shop_url": "example.com",
     "entity_type": "property_group",
     "entity_id": "abc123...",
     "operation": "insert",
     "queue_id": 12345,  // Reference to metadata in cobby_queue
     "timestamp": 1234567890,
     "plugin_version": "0.4.0-beta"
   }

7. External system fetches metadata from queue, then loads entity data on-demand:
   GET /api/_action/cobby/queue?minQueueId=12344
   Response: {"queue_id": 12345, "entity_type": "property_group", "entity_ids": "abc123...", ...}

   GET /api/property-group/abc123...
   Response: Complete property group data with all associations (loaded live from Shopware)
```

### Example 2: Product Name Changed (with System Field Filtering)

```
1. Admin changes product name: "T-Shirt Blue" → "T-Shirt Navy"
   ↓
2. Shopware DAL updates:
   - product_translation.name = "T-Shirt Navy"
   - product.updatedAt = NOW()
   - product.versionId = <new-version-id>
   - (Shopware auto-updates many fields)
   ↓
3. EntityWrittenEvent fired
   operation: update
   payload: {
     "name": "T-Shirt Navy",
     "versionId": "...",
     "updatedAt": "2025-01-13 10:30:00",
     "availableStock": 100,
     "available": true,
     // ... 10+ more auto-updated fields
   }
   ↓
4. ProductSubscriber::onProductWritten()
   │
   ├─ isEnabled()? → true
   ├─ extractPrimaryKey() → "product-id-123"
   ├─ operation === 'update'? → YES
   │  │
   │  ├─ array_keys($payload) → ["name", "versionId", "updatedAt", "availableStock", ...]
   │  └─ getRelevantChangedFields() → ["name"]  ✅ System fields filtered!
   │
   ├─ fetchEntityWithFallback()
   │  └─ Load full product with 12 associations
   │
   ├─ Add changedFields to entityData → ["name"]  ✅ Only actual change!
   │
   ├─ enqueueWithFullData()
   │  └─ Store metadata only in cobby_queue (queue_id: 12346, entity_type: product, entity_ids: product-id-123)
   │
   └─ NotificationService::sendWebhook()
   ↓
5. Lightweight webhook notification sent:
   {
     "event": "product.written",
     "shop_id": "550e8400-e29b-41d4-a716-446655440000",
     "entity_type": "product",
     "entity_id": "product-id-123",
     "operation": "update",
     "queue_id": 12346,  // Reference to metadata in cobby_queue
     "timestamp": 1234567890
   }

6. External system fetches metadata from queue, then loads entity data on-demand:
   GET /api/_action/cobby/queue?minQueueId=12345
   Response:
   {
     "queue_id": 12346,
     "entity_type": "product",
     "entity_ids": "product-id-123",
     "operation": "update",
     "created_at": "2025-01-23 10:30:00.123"
   }

   GET /api/product/product-id-123
   Response:
   {
     "id": "product-id-123",
     "name": "T-Shirt Navy",  ✅ Current value (loaded live)
     // ... 50+ other fields with all associations (always up-to-date)
   }

   Note: changedFields no longer available in Metadata-Only architecture
```

**Without filtering**, webhook would show:
```json
"changedFields": ["name", "versionId", "updatedAt", "availableStock", "available", ...]
```
❌ Noise! Downstream systems can't tell what actually changed.

**With filtering**, webhook shows:
```json
"changedFields": ["name"]
```
✅ Clear signal: User changed the name!

---

## Design Patterns

### 1. Abstract Base Class Pattern (DRY)

**Before**: Each subscriber had ~200 lines of duplicate code
```php
// PropertyGroupSubscriber.php - 245 lines
private function extractPrimaryKey($pk) { /* duplicate */ }
private function sendWebhook($data) { /* duplicate */ }
// ... duplicate error handling, config check, etc.

// ProductSubscriber.php - 474 lines
private function extractPrimaryKey($pk) { /* duplicate */ }
private function sendWebhook($data) { /* duplicate */ }
// ... same duplicate code!
```

**After (v0.4.0-beta)**: AbstractWebhookSubscriber eliminates duplication
```php
// AbstractWebhookSubscriber.php - 165 lines (shared)
protected function extractPrimaryKey($pk) { /* single implementation */ }
protected function sendWebhookSafely($data) { /* single implementation */ }

// PropertyGroupSubscriber.php - 245 lines (no duplication)
class PropertyGroupSubscriber extends AbstractWebhookSubscriber {
    // Only entity-specific logic
}

// ProductSubscriber.php - 474 lines (no duplication)
class ProductSubscriber extends AbstractWebhookSubscriber {
    // Only entity-specific logic
}
```

**Result**: ~200 lines removed, easier maintenance, consistent behavior

### 2. Template Method Pattern

AbstractWebhookSubscriber defines the algorithm, child classes fill in specific steps:

```php
// AbstractWebhookSubscriber.php (template)
public function handleEvent(EntityWrittenEvent $event) {
    if (!$this->isEnabled()) return;  // Step 1: Config check

    foreach ($event->getWriteResults() as $result) {
        $id = $this->extractPrimaryKey($result->getPrimaryKey());  // Step 2: Extract ID
        if (!$id) continue;

        $data = $this->fetchEntityWithFallback(...);  // Step 3: Fetch entity
        $payload = $this->buildWebhookPayload(...);   // Step 4: Build payload
        $this->sendWebhookSafely(...);                // Step 5: Send webhook
    }
}

// Child classes implement:
abstract protected function getConfigKey(): string;         // Config key
abstract protected function extractEntityData($entity): array;  // Entity → array
```

### 3. Lazy Loading Pattern

**Old Approach**: Eager loading in constructor
```php
class WebhookService {
    private string $webhookUrl;

    public function __construct(SystemConfigService $config) {
        // ❌ Config loaded once at service instantiation
        $this->webhookUrl = $config->get('Cobby.config.webhookUrl');
        // Problem: Changes require cache clear!
    }
}
```

**New Approach (v0.4.0-beta)**: Lazy loading via getter methods
```php
class WebhookService {
    private SystemConfigService $systemConfigService;

    public function __construct(SystemConfigService $config) {
        $this->systemConfigService = $config;  // Store service, not value
    }

    private function getWebhookUrl(): string {
        // ✅ Config loaded fresh on each call
        return $this->systemConfigService->get('Cobby.config.webhookUrl')
            ?? self::DEFAULT_WEBHOOK_URL;
    }
}
```

**Benefits**:
- Configuration changes take effect immediately
- No cache clear needed
- Better testability (can mock config service)
- Lower memory usage (config not stored)

### 4. Blacklist Filtering Pattern

System field filtering uses blacklist approach:

```php
// Define known system fields
private const SYSTEM_FIELDS = [
    'versionId', 'parentVersionId', 'updatedAt', 'createdAt', ...
];

// Filter them out
private function getRelevantChangedFields(array $fields): array {
    return array_filter($fields, fn($f) => !in_array($f, self::SYSTEM_FIELDS));
}
```

**Why blacklist over whitelist?**
- ✅ New custom fields automatically included
- ✅ Plugin works with Shopware extensions
- ✅ Future-proof (new business fields pass through)
- ❌ New system fields need to be added to blacklist

**Alternative (whitelist)**: Would require maintaining list of all valid business fields
- ❌ Would break with custom fields
- ❌ Would need updates for every Shopware version

### 5. Fail-Safe Pattern

All subscriber methods wrapped in try-catch, never re-throw:

```php
public function onProductWritten(EntityWrittenEvent $event): void {
    try {
        // Webhook logic
    } catch (\Throwable $e) {
        $this->logger->error('Error in ProductSubscriber', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        // ✅ Log and return
        // ❌ NEVER re-throw - would break Shopware!
    }
}
```

**Why?** Throwing exceptions in event subscribers can:
- Break the admin interface
- Prevent entity saves
- Crash the application

**Philosophy**: Webhooks are nice-to-have, not critical. Never block Shopware operations.

---

## Critical Design Decisions

### 1. Why curl Instead of GuzzleHttp?

**Decision**: Use native PHP `curl_*()` functions

**Reasoning**:
During development, GuzzleHttp caused hanging requests in Dockware/Docker environments:
- Requests would never complete
- Timeouts not respected
- Container became unresponsive

**Solution**: Native curl with specific options
```php
curl_setopt($ch, CURLOPT_NOSIGNAL, 1);  // ⚠️ CRITICAL!
// Prevents curl from using signals for timeouts
// Signal handling can fail in Docker/FPM environments

curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);  // 2s to establish connection
curl_setopt($ch, CURLOPT_TIMEOUT, 5);          // 5s total request time
```

**Benefits**:
- ✅ Reliable timeouts in all environments
- ✅ No dependency on external HTTP library
- ✅ Better performance (no abstraction overhead)
- ✅ Works in all PHP installations

**Trade-offs**:
- ❌ More verbose than GuzzleHttp
- ❌ Manual header management

**Verdict**: Reliability > convenience. Do NOT reintroduce GuzzleHttp without extensive Docker testing.

### 2. Why Lazy Configuration Loading?

**Decision**: Load configuration on-demand via getter methods

**Reasoning**:
**User Experience Problem**:
1. Admin changes webhook URL in settings
2. Clicks "Save"
3. Tests webhook → Still uses old URL! 😕
4. Must run `bin/console cache:clear`
5. Now works 🎉

**Root Cause**: Services instantiated once, config loaded in constructor, cached

**Solution** (v0.4.0-beta): Lazy loading
```php
// Called on every webhook send
private function getWebhookUrl(): string {
    return $this->systemConfigService->get('...');  // Fresh value!
}
```

**Impact**:
1. Admin changes webhook URL
2. Clicks "Save"
3. Tests webhook → Uses new URL immediately! 🎉
4. No cache clear needed

**Benefits**:
- ✅ Better UX (changes take effect immediately)
- ✅ Easier testing (no need to clear cache)
- ✅ Less support overhead (no "did you clear cache?" questions)

**Cost**: Negligible performance impact (config reads are cached by Shopware)

### 3. Why System Field Filtering?

**Decision**: Filter out auto-generated fields from `changedFields` array

**Problem**:
Shopware auto-updates many fields on every save:
```php
// User changes product name
// But Shopware updates 10+ fields automatically

$payload = [
    'name' => 'New Name',               // ✅ User change
    'versionId' => '...',               // ❌ Auto-updated
    'parentVersionId' => '...',         // ❌ Auto-updated
    'updatedAt' => '2025-01-13 ...',    // ❌ Auto-updated
    'availableStock' => 100,            // ❌ Auto-calculated
    'available' => true,                // ❌ Auto-calculated
    'childCount' => 0,                  // ❌ Auto-calculated
    // ... 10+ more
];
```

**Without filtering**:
```json
"changedFields": ["name", "versionId", "updatedAt", "availableStock", "available", ...]
```
❌ Downstream systems can't tell what actually changed!

**With filtering**:
```json
"changedFields": ["name"]
```
✅ Clear signal: User changed the name!

**Impact on Downstream Systems**:
- ✅ Can skip processing if `changedFields` is empty
- ✅ Can make intelligent decisions (e.g., re-index only changed fields)
- ✅ Reduced noise in logs and analytics

**Alternative Considered**: Send all fields
- ❌ Too noisy
- ❌ Misleading (implies business changes)
- ❌ Wastes bandwidth

### 4. Why No Version Checks?

**Decision**: Do NOT filter by `Defaults::LIVE_VERSION`

**Reasoning**:
Shopware creates draft versions when editing in admin:
1. Admin opens product for editing
2. Shopware creates draft version (not live version)
3. Admin makes changes
4. Admin clicks "Save"
5. Draft version merged to live version

**If we filtered by live version**:
```php
if ($payload['versionId'] !== Defaults::LIVE_VERSION) {
    return;  // ❌ Would skip all admin edits!
}
```

**Result**: All admin edits would be ignored! Only API/storefront changes would trigger webhooks.

**Solution**: Don't check version, send webhooks for ALL changes

**Trade-off**:
- ❌ Webhook fired for draft version, then again for live version (2 webhooks)
- ✅ All changes captured (admin, API, storefront)

**Verdict**: Better to send 2 webhooks than miss admin edits

### 5. Why JSON_THROW_ON_ERROR?

**Decision**: Use `JSON_THROW_ON_ERROR` flag in json_encode()

**Problem**:
```php
$json = json_encode($data);  // Returns false on error!
curl_setopt($ch, CURLOPT_POSTFIELDS, $json);  // Sends "false" 😱
```

**Solution** (v0.4.0-beta):
```php
try {
    $json = json_encode($data, JSON_THROW_ON_ERROR);
} catch (\JsonException $e) {
    $this->logger->error('JSON encoding failed', ['error' => $e->getMessage()]);
    return ['success' => false, 'error' => 'JSON encoding failed'];
}
```

**Benefits**:
- ✅ Errors detected immediately
- ✅ Proper error logging
- ✅ No invalid data sent to endpoint

### 6. Why PSR-3 Logging?

**Decision**: Use `LoggerInterface` instead of hardcoded file paths

**Old Approach**:
```php
file_put_contents('/var/www/html/var/log/cobby_webhook.log', ...);
```

**Problems**:
- ❌ Hardcoded path (breaks in non-standard setups)
- ❌ No log rotation
- ❌ No log level control
- ❌ Not integrated with Shopware's logging

**New Approach** (v0.4.0-beta):
```php
$this->logger->info('Webhook sent successfully', [
    'event' => $eventName,
    'http_status' => $httpCode,
]);
```

**Benefits**:
- ✅ Works with Shopware's log system
- ✅ Automatic log rotation
- ✅ Configurable log handlers (file, email, Sentry, etc.)
- ✅ Log levels (debug, info, warning, error)
- ✅ Structured logging (context arrays)

---

## Code Examples

### Adding a New Subscriber

**Scenario**: Add webhook support for "Customer" entity

**Step 1**: Create Subscriber Class
```php
// src/Subscriber/CustomerSubscriber.php
namespace CobbyShopware6Extension\Subscriber;

use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;

class CustomerSubscriber extends AbstractWebhookSubscriber
{
    public static function getSubscribedEvents(): array {
        return [
            'customer.written' => 'onCustomerWritten',
            'customer.deleted' => 'onCustomerDeleted',
        ];
    }

    protected function getConfigKey(): string {
        return 'Cobby.config.enableCustomerEvents';
    }

    protected function extractEntityData($entity): array {
        return [
            'id' => $entity->getId(),
            'customerNumber' => $entity->getCustomerNumber(),
            'email' => $entity->getEmail(),
            'firstName' => $entity->getFirstName(),
            'lastName' => $entity->getLastName(),
            'active' => $entity->getActive(),
            'customerId' => $entity->getDefaultBillingAddress()?->getId(),
        ];
    }

    // Use generic handler methods
    public function onCustomerWritten(EntityWrittenEvent $event): void {
        $this->handleWrittenEvent(
            $event,
            'customer',
            ['defaultBillingAddress', 'defaultShippingAddress', 'group', 'salesChannel'],
            $this->customerRepository
        );
    }

    public function onCustomerDeleted(EntityDeletedEvent $event): void {
        $this->handleDeletedEvent($event, 'customer');
    }

    protected function buildFallbackData(string $id, array $payload): array {
        return [
            'id' => $id,
            'customerNumber' => $payload['customerNumber'] ?? null,
            'email' => $payload['email'] ?? null,
            'firstName' => $payload['firstName'] ?? null,
            'lastName' => $payload['lastName'] ?? null,
        ];
    }
}
```

**Step 2**: Register in services.xml
```xml
<service id="CobbyShopware6Extension\Subscriber\CustomerSubscriber" public="true">
    <argument type="service" id="CobbyShopware6Extension\Service\QueueTableService"/>
    <argument type="service" id="CobbyShopware6Extension\Service\NotificationService"/>
    <argument type="service" id="monolog.logger"/>
    <argument type="service" id="customer.repository"/>
    <argument type="service" id="Shopware\Core\System\SystemConfig\SystemConfigService"/>
    <tag name="kernel.event_subscriber"/>
</service>
```

**Step 3**: Add Config Option
```xml
<!-- Resources/config/config.xml -->
<input-field type="bool">
    <name>enableCustomerEvents</name>
    <label>Enable Customer Events</label>
    <defaultValue>true</defaultValue>
</input-field>
```

**Done!** Only ~50 lines of code needed, rest is handled by base class.

### Webhook Processing (Receiver Side)

**Node.js Example**:
```javascript
const express = require('express');

const app = express();
app.use(express.json());

app.post('/webhook', (req, res) => {
    const { event, operation, data } = req.body;

    if (event === 'product.written') {
        if (operation === 'insert') {
            // Product created
        } else if (operation === 'update') {
            // Product updated
            const changedFields = data.changedFields || [];
            if (changedFields.includes('name')) {
                // Product name changed
            }
        }
    }

    res.status(200).send('OK');
});
```

**PHP Example**:
```php
// Receiver endpoint
$rawBody = file_get_contents('php://input');
$data = json_decode($rawBody, true);

// Process webhook
switch ($data['event']) {
    case 'product.written':
        if ($data['operation'] === 'insert') {
            // Product created
        } else {
            // Product updated
            $changedFields = $data['data']['changedFields'] ?? [];
        }
        break;
}

http_response_code(200);
echo 'OK';
```

---

## Performance

### Database Queries

**Per Webhook**:
- PropertyGroup: 1 SELECT with 1 JOIN (translations)
- Product: 1 SELECT with 12 JOINs (all associations)

**Optimization**:
- Shopware's entity cache used
- Eager loading via `Criteria::addAssociation()`
- No N+1 query problems

**Impact**:
- PropertyGroup webhook: ~5-10ms DB time
- Product webhook: ~20-50ms DB time (more associations)

### HTTP Requests

**Timeout Configuration**:
```php
CURLOPT_CONNECTTIMEOUT = 2  // 2 seconds to connect
CURLOPT_TIMEOUT = 5          // 5 seconds total
```

**Worst Case**: 5 seconds per webhook

**Best Case**: ~50-200ms for fast endpoints

**Impact on Shopware**:
- Property Group events: Synchronous, adds latency to admin save
- Product events: Synchronous, adds latency to admin save

**Recommendation**: Webhook endpoint should respond in <500ms
- Use async processing (queue)
- Return 200 OK immediately
- Process webhook in background

### Memory Usage

**Lazy Loading Benefits**:
- Config not stored in service properties
- Loaded fresh on each call
- SystemConfigService handles caching

**Typical Memory per Webhook**:
- PropertyGroup: ~1 KB
- Product (with 50 fields): ~5-10 KB

**Total Plugin Memory Footprint**: <1 MB (services + subscribers)

---

## Security

### HTTPS Enforcement

**Plugin doesn't enforce HTTPS** (to allow local testing)

**Production Checklist**:
- ✅ Webhook URL uses `https://` (not `http://`)
- ✅ Valid SSL certificate
- ✅ TLS 1.2 or higher
- ✅ No self-signed certificates

### Rate Limiting

**Plugin doesn't rate limit** (Shopware's responsibility)

**Receiver Endpoint Should**:
- Implement rate limiting (e.g., 100 req/min per shop)
- Return 429 Too Many Requests if exceeded
- Log suspicious patterns (same webhook ID, high frequency)

### Logging Sensitive Data

**Plugin NEVER logs**:
- Webhook secret
- Customer passwords
- Payment data
- API keys

**Debug Logging** (when enabled):
- Logs full webhook payload
- May contain customer emails, names, addresses
- ⚠️ **Disable in production!**

---

## Testing Strategy

### Unit Tests

**Test AbstractWebhookSubscriber**:
```php
class AbstractWebhookSubscriberTest extends TestCase
{
    public function testExtractPrimaryKeyWithString() {
        $subscriber = $this->getMockForAbstractClass(AbstractWebhookSubscriber::class);
        $result = $subscriber->extractPrimaryKey('abc123');
        $this->assertEquals('abc123', $result);
    }

    public function testSystemFieldFiltering() {
        $subscriber = new ProductSubscriber(...);
        $fields = ['name', 'price', 'versionId', 'updatedAt'];
        $relevant = $subscriber->getRelevantChangedFields($fields);

        $this->assertContains('name', $relevant);
        $this->assertContains('price', $relevant);
        $this->assertNotContains('versionId', $relevant);
        $this->assertNotContains('updatedAt', $relevant);
    }
}
```

### Integration Tests

**Test Full Webhook Flow**:
```php
class WebhookIntegrationTest extends IntegrationTestBehaviour
{
    public function testPropertyGroupWebhook() {
        // 1. Set up test endpoint
        $endpoint = $this->mockWebhookEndpoint();

        // 2. Create property group
        $this->getContainer()->get('property_group.repository')->create([
            ['name' => 'Test Group']
        ], Context::createDefaultContext());

        // 3. Assert webhook received
        $this->assertWebhookReceived($endpoint, 'property_group.written');
        $this->assertWebhookSignatureValid($endpoint);
    }
}
```

### Manual Testing

**Test Scripts**:
```bash
# Test property group webhook
bin/console dal:create property_group '{"name": "Test Group"}'

# Test product webhook
bin/console product:create --name="Test Product" --stock=100

# Watch logs
tail -f var/log/dev.log | grep -i webhook
```

**Webhook Testing Tools**:
- https://webhook.site/ (inspect webhooks)
- https://requestbin.com/ (capture requests)
- ngrok (expose local server)

### Load Testing

**Simulate High Load**:
```bash
# Create 100 products simultaneously
for i in {1..100}; do
    bin/console product:create --name="Product $i" --stock=100 &
done
wait

# Check logs for errors
grep -i error var/log/dev.log
```

**Expected Behavior**:
- All 100 webhooks sent
- No errors in logs
- Shopware remains responsive

---

## Future Enhancements

### Considered Improvements

1. **Queue-Based Processing** (High Priority)
   - **Why**: Currently webhooks block entity saves (5s max)
   - **Solution**: Symfony Messenger integration
   - **Benefits**: No blocking, automatic retries, dead letter queue
   - **Effort**: Medium (3-5 days)

2. **Retry Logic** (Medium Priority)
   - **Why**: Webhook endpoints can be temporarily down
   - **Solution**: Exponential backoff with max 3 retries
   - **Benefits**: Higher delivery success rate
   - **Effort**: Low (1 day)

3. **Admin UI - Webhook Log Viewer** (Medium Priority)
   - **Why**: Debugging is currently log-file based
   - **Solution**: Admin panel showing recent webhooks, status, retry button
   - **Benefits**: Better UX, easier troubleshooting
   - **Effort**: Medium (3-5 days)

4. **Batch Webhooks** (Low Priority)
   - **Why**: Reduce HTTP overhead for bulk operations
   - **Solution**: Collect events for 1 second, send as batch
   - **Benefits**: Fewer requests, lower latency for bulk imports
   - **Effort**: Medium (2-3 days)

5. **Webhook Filtering** (Low Priority)
   - **Why**: Users may not want all product changes
   - **Solution**: Admin config: "Send webhook only if price/stock changed"
   - **Benefits**: Reduced noise, lower bandwidth
   - **Effort**: Medium (2-3 days)

### Not Recommended

1. ❌ **Multi-Server Lock Coordination**
   - Not needed (orders not supported anymore)

2. ❌ **GraphQL Webhooks**
   - Too complex for current requirements

3. ❌ **Webhook Response Processing**
   - Plugin should be fire-and-forget
   - Receiver should use separate API for responses

---

## Troubleshooting

### No Webhooks Being Sent

**Checklist**:
1. **Plugin Activated?**
   ```bash
   bin/console plugin:list | grep CobbyWebhook
   # Should show: installed, active
   ```

2. **Event Type Enabled?**
   - Admin → Extensions → CobbyShopware6Extension
   - Check: enableProductEvents / enablePropertyGroupEvents

3. **Webhook URL Configured?**
   - Admin → Extensions → CobbyShopware6Extension
   - Should not be empty

4. **Check Logs**:
   ```bash
   tail -f var/log/dev.log | grep -i webhook
   ```
   Look for: "Preparing webhook", "Webhook sent successfully"

5. **Test Endpoint Manually**:
   ```bash
   curl -X POST https://your-endpoint.com/webhook \
     -H "Content-Type: application/json" \
     -d '{"test": "data"}'
   ```

### changedFields is Empty

**Explanation**: This is often expected behavior!

**Reasons**:
1. **Insert Operation**: changedFields only for updates, not inserts
2. **Only System Fields Changed**: All fields filtered out
3. **Translation Change**: Product name is in product_translation table

**Debug**:
```php
// Add to ProductSubscriber::onProductWritten()
$this->logger->info('Raw changed fields', [
    'all_fields' => array_keys($payload),
    'operation' => $writeResult->getOperation()
]);
```

**Expected Output**:
```
Raw changed fields: ["name", "versionId", "updatedAt"]
Operation: update
Filtered: ["name"]  // Only name is relevant
```

### Webhook Timeouts

**Symptoms**: Webhooks take >5 seconds, admin saves are slow

**Fixes**:

1. **Optimize Receiver Endpoint**:
   - Return 200 OK immediately
   - Process webhook asynchronously (queue)
   - Add database indexes
   - Use caching

2. **Increase Timeout** (temporary fix):
   ```php
   // WebhookService.php
   private const DEFAULT_TIMEOUT = 10;  // Changed from 5
   ```

3. **Move to Queue** (recommended):
   - Implement Symfony Messenger integration
   - Webhooks processed in background
   - Admin saves never blocked

---

## Version History

### v0.4.0-beta (Current - 2025-01-23)
**Metadata-Only Architecture**: Simplified, lightweight notification system storing only entity metadata (entity_type + entity_id)

**Supported Entities** (12 total, 30 events):
- ✅ Product events (8 events) - product, product_price, product_media, product_category
- ✅ Property Group events (4 events) - property_group, property_group_option
- ✅ Category events (2 events)
- ✅ Manufacturer events (2 events)
- ✅ Tax events (2 events)
- ✅ Currency events (2 events)
- ✅ Sales Channel events (2 events)
- ✅ Rule events (2 events)
- ✅ Unit events (2 events)
- ✅ Delivery Time events (2 events)
- ✅ Tag events (2 events)

**Core Features:**
- ✅ Metadata-Only architecture with lightweight storage in `cobby_queue` table (entity_type + entity_id only)
- ✅ NotificationService for lightweight webhook notifications
- ✅ QueueTableService for database-backed queue management (metadata-only: stores entity_type + entity_id)
- ✅ New API endpoint: POST /api/_action/cobby/queue/truncate (reset queue and IDs)
- ✅ New method: truncateQueue() in QueueTableService
- ✅ External service loads entity data on-demand via Shopware API (always current, never stale)
- ✅ AbstractWebhookSubscriber base class with generic handler methods (DRY)
- ✅ Generic methods: handleWrittenEvent(), handleDeletedEvent(), handleParentEntityEvent()
- ✅ System field filtering for changed fields
- ✅ Lazy configuration loading (no cache clear needed)
- ✅ JSON error handling with JSON_THROW_ON_ERROR
- ✅ PSR-3 compliant logging
- ✅ UUID v4 shop identification (RFC 4122)
- ✅ Auto-generated secure webhook secrets
- ✅ HMAC-SHA256 signature validation

**Architecture Improvements:**
- Simplified from 2 database tables to 1 (cobby_queue only)
- Removed complexity: ProductHashService, RegistryService, RateLimiterService
- All 12 subscribers follow consistent AbstractWebhookSubscriber pattern
- Lightweight notification payload (~200 bytes) with queue_id reference
- Database schema changes: NO entity_data, status, retry_count, error_message columns
- Migration simplified: Only Migration1737648000CreateQueueTable.php needed
- Removed migrations: Migration1737655000, Migration1737649000, Migration1737650000, Migration1737651000
- Entity data loaded on-demand by external service (not stored in queue)
- Comprehensive entity data with deep associations (loaded live from Shopware)
- Enhanced security (HTTP_HOST validation, HMAC signing)

### Initial Release
- Property Group webhooks (4 events)
- Product webhooks (8 events)
- Basic HMAC signature validation
- File-based logging
- 2 entities, 12 events total

---

## References

- **README.md**: Plugin overview and installation guide
- **DEVELOPMENT.md**: Development workflow, testing, troubleshooting
- **CHANGELOG.md**: Version history and migration guide
- **CLAUDE.md**: High-level architecture and design guidelines
- **Shopware 6 Docs**: https://developer.shopware.com/

---

**Last Updated**: 2025-01-23
**Plugin Version**: 0.4.0-beta
**Shopware Compatibility**: 6.4+
**Maintainer**: CobbyShopware6Extension Team
