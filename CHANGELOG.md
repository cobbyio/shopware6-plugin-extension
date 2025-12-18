# Changelog

All notable changes to the CobbyShopware6Extension will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.51] - 2025-12-18 (CURRENT)

**Documentation Cleanup**

### Changed
- Removed hardcoded version numbers from documentation files (CLAUDE.md, ARCHITECTURE.md, DEVELOPMENT.md, README.md)
- Version is now only tracked in composer.json, CobbyPlugin.php, and CHANGELOG.md

---

## [1.0.50] - 2025-12-18

**Minor Updates & Bug Fixes**

### Changed
- Updated version references in documentation (CLAUDE.md, ARCHITECTURE.md, DEVELOPMENT.md)

---

## [1.0.0] - 2025-01-24

**Shopware Best Practices & Code Quality Release**

### Added
- **Custom Exception Classes** for better error handling:
  - `QueueException` - Queue operation failures (truncate, enqueue, etc.)
  - `WebhookException` - Webhook delivery failures (send failed, timeout, etc.)
  - Located in `src/Exception/` directory
  - Static factory methods for common error scenarios

### Changed
- **BREAKING**: Plugin main class renamed: `CobbyPlugin` → `CobbyShopware6Extension` (naming consistency)
  - All references updated across codebase (Services, Subscribers, Controllers, Documentation)
  - CONFIG_PREFIX remains: `CobbyShopware6Extension.config.`
- **services.xml Optimization** (~60% reduction):
  - Added `<defaults autowire="true" autoconfigure="true"/>`
  - Replaced 12 individual subscriber definitions with `<prototype>` pattern
  - Removed unnecessary `public="true"` flags from services (kept only for Controllers)
  - Reduced from 138 lines to 81 lines (~41% less boilerplate)
- DELETE API endpoint simplified: Always truncates entire queue (no more `maxQueueId` parameter)

### Removed
- `deleteQueue()` method from QueueTableService (redundant with `truncateQueue()`)
- `markAsProcessed()` method from QueueTableService (unused, redundant with `deleteQueue()`)

### Optimizations
- **DI Container**: Autowiring eliminates manual service wiring
- **Prototype Pattern**: Auto-registers all subscribers with single XML block
- **Code reduction**: ~60 lines removed from services.xml (~41% less configuration)

### Architecture
- **Exception Hierarchy**: Proper exception classes following Shopware standards
- **Service Registration**: Modern Symfony autowiring + autoconfiguration
- **Naming Consistency**: Plugin class name matches package name

### Migration from v0.5.0-beta
- No database changes required
- Plugin must be reinstalled to register new class name
- External services using DELETE endpoint: Remove `maxQueueId` parameter (now truncates entire queue)

---

## [0.5.0-beta] - 2025-01-23

**Code Optimization & Database Efficiency Release**

### Changed
- **BREAKING**: Database schema optimized for efficiency (~60% storage reduction per row)
  - `entity_id`: TEXT → VARCHAR(36) (UUID-optimized)
  - `entity_type`: VARCHAR(100) → VARCHAR(30) (max: "product_manufacturer" = 20 chars)
  - `operation`: VARCHAR(20) → VARCHAR(10) (only: "insert", "update", "delete")
  - `context`: VARCHAR(50) → VARCHAR(20) (max: "admin-backend" = 13 chars)
  - **Removed `transaction_id` completely** (unused, no grouping functionality)
- Plugin main class renamed: `CobbyWebhookPlugin` → `CobbyPlugin` (cleaner, no redundancy)
- Routes: Migrated from DocBlock Annotations to PHP 8 Attributes
- AbstractWebhookSubscriber: PHP 8 Constructor Property Promotion (~10 lines reduced)
- Method renamed: `enqueueData()` → `enqueueMetadataOnly()` (more descriptive)

### Added
- **SimpleEntitySubscriber** base class for simple entities (reduces subscriber code ~50%)
  - CategorySubscriber, TaxSubscriber, CurrencySubscriber, RuleSubscriber, UnitSubscriber
  - DeliveryTimeSubscriber, TagSubscriber, ManufacturerSubscriber, SalesChannelSubscriber
  - All use Template Method Pattern, auto-generate event subscriptions
- `activate()` lifecycle method in CobbyPlugin for proper plugin activation
- Full documentation updates (CHANGELOG, CLAUDE, ARCHITECTURE, README, DEVELOPMENT)

### Removed
- `transaction_id` field and index from `cobby_queue` table (never used, no functionality)
- `transaction_id` parameter from `QueueTableService::enqueueWithData()`
- `build()` and `configureRoutes()` temporarily removed, then **restored** (critical for route loading)
- `webhookUrl` and `webhookSecret` from DB initialization (uses NotificationService fallbacks)

### Fixed
- **CRITICAL**: WorkspaceController route not loading (missing `configureRoutes()` method)
  - Added back `build()` and `configureRoutes()` methods to CobbyPlugin
  - API endpoint `POST /api/_action/cobby/workspace` now works correctly
  - `workspaceId` can now be saved and retrieved for webhook payloads
- `entity_id` naming mismatch: `entity_ids` → `entity_id` in QueueTableService (SQL error fixed)
- Migration comments updated to reflect VARCHAR(36) instead of TEXT

### Optimizations
- **Storage savings**: ~156 bytes per queue entry (entity_type:70 + operation:10 + context:30 + transaction_id:46)
- **Code reduction**: ~20 lines in AbstractWebhookSubscriber, ~500 lines across 9 subscribers
- **Performance**: VARCHAR fields faster to index and query than TEXT
- **Database efficiency**: Removed unused index on transaction_id

### Architecture
- **12 entity types with 30 events**: Product (8), PropertyGroup (4), Category (2), Manufacturer (2), Tax (2), Currency (2), SalesChannel (2), Rule (2), Unit (2), DeliveryTime (2), Tag (2)
- **2 core services**: QueueTableService, NotificationService
- **1 database table**: cobby_queue (Metadata-Only: entity_type + entity_id, optimized field sizes)
- **1 migration**: Migration1737648000CreateQueueTable.php (updated schema)
- **CobbyPlugin class**: 82 lines (down from 113, ~27% reduction)

## [0.4.0-beta] - 2025-11-17

**BETA RELEASE**: Metadata-Only Architecture with consolidated services

### Changed
- **BREAKING**: Version reset to 0.4.0-beta (beta release)
- **BREAKING**: Database schema changed to Metadata-Only architecture
  - cobby_queue table NO LONGER has: entity_data, status, retry_count, error_message columns
  - cobby_queue table NOW has: queue_id (BIGINT), entity_ids (TEXT), user_name, transaction_id
  - External service must load entity data on-demand via Shopware API
- **BREAKING**: Removed 7 subscribers (Order, Customer, Media, ShippingMethod, PaymentMethod, CustomerGroup, Promotion)
- **BREAKING**: Added 4 new subscribers (Rule, Unit, DeliveryTime, Tag)
- Consolidated NotificationService (removed separate WebhookService)
- Refactored all 12 subscribers to use base class event handlers
- Reduced codebase by ~1000 lines through deduplication

### Added
- New method in QueueTableService: truncateQueue()
- New API endpoint: POST /api/_action/cobby/queue/truncate (reset queue and auto-increment IDs)
- Metadata-Only architecture: stores only entity_type + entity_id (no entity_data JSON)
- External service integration: loads entity data on-demand via Shopware API

### Removed
- Deprecated methods: `QueueTableService::enqueue()`
- Database columns: entity_data, status, retry_count, error_message (Metadata-Only architecture)
- Migrations: Migration1737655000, Migration1737649000, Migration1737650000, Migration1737651000
- Unused configuration: `enablePushWebhooks`, `notificationUrl`
- Debug error_log statements in favor of PSR-3 logging
- Stack trace logging from subscribers (Shopware handles this automatically)
- References to removed services: ProductHashService, RegistryService, RateLimiterService
- Product hash table (`cobby_product_hash`) - simplified to metadata-only architecture

### Fixed
- Missing `splitIntoBatches()` method reference in deprecated code
- Outdated documentation references to v2.0.0 architecture
- Inconsistent error handling across subscribers

### Architecture
- **12 entity types with 30 events**: Product (8), PropertyGroup (4), Category (2), Manufacturer (2), Tax (2), Currency (2), SalesChannel (2), Rule (2), Unit (2), DeliveryTime (2), Tag (2)
- **2 core services**: QueueTableService, NotificationService
- **1 database table**: cobby_queue (Metadata-Only: stores entity_type + entity_id only, NO entity_data column)
- **1 migration**: Migration1737648000CreateQueueTable.php (simplified, removed 4 other migrations)
- **External service workflow**: Loads entity data on-demand via Shopware API (always current, never stale)
- **New API endpoints**:
  - GET /api/_action/cobby/queue (get queue metadata)
  - DELETE /api/_action/cobby/queue?maxQueueId=X (delete processed entries)
  - POST /api/_action/cobby/queue/truncate (reset queue and IDs)

### Migration to v0.4.0-beta (Metadata-Only Architecture)
**BREAKING CHANGES**:
1. **Database schema changed**: cobby_queue table no longer stores entity_data
2. **External service must adapt**: Load entity data on-demand via Shopware API
3. **Migrations simplified**: Only Migration1737648000CreateQueueTable.php needed

**External Service Migration Steps**:
1. Update polling logic to use GET /api/_action/cobby/queue?minQueueId=X
2. For each queue entry, load entity data: GET /api/{entity-type}/{entity_ids}
3. Process entity data (always current, loaded live from Shopware)
4. Mark processed: DELETE /api/_action/cobby/queue?maxQueueId=Y
5. Optional: Use POST /api/_action/cobby/queue/truncate to reset after full sync

**Benefits of Metadata-Only Architecture**:
- Always current data (loaded live from Shopware, never stale JSON)
- Minimal database footprint (~95% smaller queue table)
- Simplified migrations (only 1 migration file)
- Cleaner architecture (queue is just a change log, not a data store)

---

## [2.0.0] - 2025-01-14 (DEPRECATED)

**MAJOR EXPANSION**: From 2 entities (12 events) to 14 entities (44 events)

### Added - New Entity Support (12 entity types, 32 events)
- **OrderSubscriber**: Complete order lifecycle tracking (8 events)
  - `order.written` / `order.deleted`
  - `order_line_item.written` / `order_line_item.deleted`
  - `order_delivery.written` / `order_delivery.deleted`
  - `order_transaction.written` / `order_transaction.deleted`
- **CustomerSubscriber**: Customer and address management (4 events)
  - `customer.written` / `customer.deleted`
  - `customer_address.written` / `customer_address.deleted`
- **CategorySubscriber**: Category tree tracking (2 events)
- **ManufacturerSubscriber**: Brand/manufacturer changes (2 events)
- **MediaSubscriber**: Standalone media file tracking (2 events)
- **ShippingMethodSubscriber**: Shipping configuration tracking (2 events)
- **PaymentMethodSubscriber**: Payment configuration tracking (2 events)
- **TaxSubscriber**: Tax rate configuration (2 events)
- **CurrencySubscriber**: Currency configuration (2 events)
- **CustomerGroupSubscriber**: Customer group configuration (2 events)
- **SalesChannelSubscriber**: Sales channel configuration (2 events)
- **PromotionSubscriber**: Promotion/discount tracking (2 events)

### Added - Database & Services (Magento 2 Pattern)
- **Database Queue System** (`cobby_queue` table)
  - Reliable change tracking with entity_type, entity_ids, operation, source
  - Enables external systems to poll or receive notifications
- **Product Hash System** (`cobby_product_hash` table)
  - SHA256 hash-based change detection following Magento 2 connector pattern
  - Detects real business changes vs. system-generated updates
- **QueueTableService**: Database-backed queue management
- **ProductHashService**: Hash calculation and comparison
- **RegistryService**: Duplicate event prevention
- **RateLimiterService**: Webhook flood protection

### Added - Configuration & Security
- **11 Entity Event Toggles**: Individual enable/disable for each entity type
- **UUID v4 Shop Identification**: RFC 4122 compliant, auto-generated
- **Auto-Generated Webhook Secrets**: Secure 64-character secrets on first install
- **HTTP_HOST Validation**: Prevents header injection attacks
- **Enhanced HMAC Signing**: Improved security with validated shop metadata

### Added - API Endpoints
- **Import APIs**: Product, Stock, Category, Property, Media import endpoints
- **Export APIs**: Config, Product, Category, Property, CustomerGroup, SalesChannel, SeoUrl, PriceRule, CrossSelling exports
- **Admin APIs**: Queue statistics, product hash statistics, queue clearing

### Added - Core Features
- **AbstractWebhookSubscriber**: DRY base class for all subscribers
- **Changed Fields Tracking**: Shows which fields actually changed (on updates)
- **System Field Filtering**: Removes auto-generated fields (versionId, updatedAt, etc.)
- **Deep Entity Associations**: Comprehensive data for all entity types
- **Config Lazy Loading**: Changes take effect immediately (no cache clear needed)
- **JSON Error Handling**: Proper encoding with JSON_THROW_ON_ERROR
- **PSR-3 Logging**: Shopware-integrated logging

### Changed
- **Database Structure**: Migrated from 5 tables to 2 tables (Magento pattern)
  - Removed: cobby_webhook_log, cobby_webhook_route, cobby_webhook_processed_order
  - Kept: cobby_queue (improved), cobby_product_hash (new, Magento pattern)
- **All Subscribers**: Now extend AbstractWebhookSubscriber for consistency
- **WebhookService**: Lazy config loading, improved error handling
- **Configuration**: From 5 options to 15 options (11 entity toggles + 4 general)
- **Documentation**: Comprehensive updates across all 5 documentation files

### Fixed
- **Config Changes**: Take effect immediately without cache clear
- **JSON Encoding**: Proper error handling prevents silent failures
- **Memory Usage**: Lazy loading reduces memory footprint
- **Code Duplication**: DRY architecture reduces maintenance

### Migration Guide from 1.0.0
1. **Backup database**: `mysqldump shopware > backup.sql`
2. **Update plugin files** to v2.0.0
3. **Refresh plugin**: `bin/console plugin:refresh`
4. **Run migrations**: Database tables will be created/updated automatically
5. **Clear cache**: `bin/console cache:clear`
6. **Review configuration**:
   - New entity toggles (all enabled by default)
   - New shopId (auto-generated UUID v4)
   - New webhookSecret (auto-generated if empty)
7. **Initialize product hashes** (optional but recommended):
   - Use Admin API: `POST /api/_action/cobby/admin/initialize-product-hashes`
   - Or run via command line (if implemented)
8. **Test webhooks**: Verify all entity types are sending correctly

### Notes
- **Backward Compatible**: Existing PropertyGroup and Product webhooks continue working
- **Database Tables**: Old tables removed, new queue system added
- **Configuration Preserved**: Existing webhookUrl and webhookSecret retained
- **No Data Loss**: Queue table tracks all changes going forward

## [1.0.0] - 2025-01-20

### Added
- Initial release of CobbyShopware6Extension
- Property Group webhook events (written, deleted)
- Property Group Option webhook events (written, deleted)
- Order webhook events with granular distinction (created, updated)
- Order status change webhooks (order, delivery, transaction)
- File-based locking mechanism for order duplicate prevention
- Database tables for webhook management:
  - `cobby_webhook_route` - Route configurations
  - `cobby_webhook_log` - Webhook history
  - `cobby_webhook_processed_order` - Order tracking
- WebhookManager service for orchestration
- WebhookRouteRegistry for route management
- Standardized webhook payload structure v2.0
- Retry logic with exponential backoff
- HMAC-SHA256 signature validation
- Debug logging capability
- Configuration options in plugin settings

### Changed
- Replaced GuzzleHttp with curl for better reliability
- Moved from synchronous to asynchronous webhook sending
- Rebranded from N8nWebhookPlugin to CobbyShopware6Extension

### Fixed
- Order save failures when webhooks were slow
- Duplicate order.created events for the same order
- Webhook hanging issues with GuzzleHttp
- Race conditions in multi-process environments

### Security
- HMAC-SHA256 signature for all webhooks
- Configurable webhook secret
- Non-blocking file locks to prevent DoS

## [0.9.0] - 2025-01-19 (Pre-release)

### Added
- Basic order webhook functionality
- Property group event handling
- Initial webhook service implementation

### Known Issues
- Orders triggering multiple created events
- Webhooks blocking order saves
- Insufficient webhook payload data

## [0.8.0] - 2025-01-18 (Development)

### Added
- Initial plugin structure
- Basic event subscribers
- N8n integration (later rebranded)

### Notes
- Development version, not production ready
- Testing with Dockware environment

---

## Migration Notes

### Upgrading from Pre-1.0 versions

1. **Database Migration Required**
   ```bash
   bin/console database:migrate Cobby --all
   ```

2. **Configuration Changes**
   - Webhook URL default changed to `https://automate.cobby.io/webhook/shopware/plugin-event`
   - New configuration options added (see README)

3. **Breaking Changes**
   - Plugin renamed from N8nWebhookPlugin to CobbyShopware6Extension
   - Webhook payload structure changed to v2.0 format
   - Lock files moved to `/tmp/order_lock_*.lock`

4. **Action Required**
   - Update webhook endpoints to handle new payload structure
   - Update HMAC validation to use new secret key
   - Clear cache after update: `bin/console cache:clear`

### Rollback Instructions

If you need to rollback to a previous version:

1. Deactivate plugin: `bin/console plugin:deactivate CobbyShopware6Extension`
2. Replace plugin files with previous version
3. Run: `bin/console plugin:refresh`
4. Activate plugin: `bin/console plugin:activate CobbyShopware6Extension`
5. Clear cache: `bin/console cache:clear`

Note: Database tables will remain but won't be used by older versions.