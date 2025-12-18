# CobbyShopware6Extension - Developer Documentation

## 🏗️ Architecture Overview

### Component Hierarchy (v1.0.50)
```
CobbyShopware6Extension (Main Plugin Class)
    ├── Service/
    │   ├── NotificationService (Lightweight HTTP notifications)
    │   └── QueueTableService (Optimized metadata-only queue: entity_type + entity_id)
    └── Subscriber/
        ├── AbstractWebhookSubscriber (Base Class - DRY with PHP 8 Constructor Promotion)
        │   └── SimpleEntitySubscriber (Template Method Pattern for simple entities)
        │       ├── CategorySubscriber (2 events)
        │       ├── TaxSubscriber (2 events)
        │       └── ... 7 more simple subscribers
        ├── PropertyGroupSubscriber (extends AbstractWebhookSubscriber) - 4 events
        └── ProductSubscriber (extends AbstractWebhookSubscriber) - 8 events (complex)
```

### Data Flow
1. **Event Trigger**: Shopware fires DAL event (e.g., product.written)
2. **Subscriber**: Generic handler (handleWrittenEvent) checks if enabled via `isEnabled()`
3. **Entity Fetch**: Entity fetched for validation (not stored in queue)
4. **Data Processing**: Changed fields detected, system fields filtered
5. **Queue Storage**: Metadata only (entity_type + entity_id) stored in cobby_queue table
6. **Notification**: Lightweight webhook sent with queue_id via NotificationService
7. **External Service**: Loads entity data on-demand via Shopware API (always current)
8. **Error Handling**: All errors caught and logged, never thrown

## 🎯 Design Patterns & Best Practices

### 1. AbstractWebhookSubscriber Pattern (DRY Principle)

**Problem Solved**: All subscribers had duplicate code for:
- Primary key extraction
- Config checking
- Entity fetching with fallback
- Queue storage with full data
- Webhook notification sending

**Solution**: Base class with generic handler methods.

```php
abstract class AbstractWebhookSubscriber implements EventSubscriberInterface
{
    // Must be implemented by child classes
    abstract protected function getConfigKey(): string;
    abstract protected function extractEntityData($entity): array;
    abstract protected function buildFallbackData(string $id, array $payload): array;

    // Generic handler methods (DRY principle)
    protected function handleWrittenEvent($event, string $entityType, array $associations, EntityRepository $repository): void {...}
    protected function handleDeletedEvent($event, string $entityType): void {...}
    protected function handleParentEntityEvent($event, string $childIdKey, string $parentEntityType, array $associations, EntityRepository $repository): void {...}

    // Shared functionality
    protected function extractPrimaryKey($primaryKey): ?string {...}
    protected function isEnabled(): bool {...}
    protected function fetchEntityWithFallback(...): array {...}
    protected function enqueueWithFullData(...): void {...}
    // Note: Despite the name, this now stores metadata only (entity_type + entity_id)
    // The entityData parameter is kept for backwards compatibility but is ignored
}
```

**Benefits**:
- ~200 lines of code removed through DRY
- Consistent error handling across all subscribers
- Easy to add new subscribers
- Single source of truth for common logic

### 2. Lazy Configuration Loading

**Problem**: Configuration loaded in constructor → changes required cache clear.

**Old Approach (v1.0.0)**:
```php
public function __construct(...) {
    $this->webhookUrl = $configService->get('...');  // ❌ Fixed at instantiation
}
```

**Current Approach (v1.0.0)**:
```php
private function getWebhookUrl(): string {
    return $this->systemConfigService->get('...');  // ✅ Fresh on each call
}
```

**Benefits**:
- Configuration changes take effect immediately
- No cache clear needed
- Better testability

### 3. System Field Filtering

**Problem**: Shopware auto-updates many fields on every save (versionId, updatedAt, etc.) making `changedFields` noisy.

**Example**: User changes product name, but `changedFields` shows:
```
["name", "versionId", "updatedAt", "availableStock", "productMediaVersionId", ...]
```

**Solution**: Filter out 16 system-generated fields.

```php
private const SYSTEM_FIELDS = [
    'versionId', 'parentVersionId', 'productManufacturerVersionId',
    'productMediaVersionId', 'cmsPageVersionId', 'canonicalProductVersionId',
    'updatedAt', 'createdAt', 'autoIncrement', 'availableStock',
    'available', 'childCount', 'ratingAverage', 'sales', 'translated',
    '_uniqueIdentifier'
];
```

**Result**: Only user-modified fields shown: `["name"]`

### 4. JSON Error Handling

**Problem**: `json_encode()` can fail silently and return `false`.

**Solution**: Use `JSON_THROW_ON_ERROR` flag.

```php
try {
    $jsonData = json_encode($data, JSON_THROW_ON_ERROR);
} catch (\JsonException $e) {
    $this->logger->error('JSON encoding failed', ['error' => $e->getMessage()]);
    return ['success' => false, 'error' => 'JSON encoding failed'];
}
```

### 5. PSR-3 Compliant Logging

**Old Approach (v1.0.0)**:
```php
// ❌ Hardcoded file path
file_put_contents('/var/www/html/var/log/cobby_webhook.log', ...);
```

**Current Approach (v1.0.0)**:
```php
// ✅ PSR-3 logger with context
$this->logger->info('Webhook sent successfully', [
    'event' => $eventName,
    'http_status' => $httpCode,
    'success' => true
]);
```

**Benefits**:
- Works with Shopware's log system
- Configurable log handlers
- Structured logging
- Log levels (info, warning, error)

## ⚙️ Critical Implementation Details

### 1. Why curl Instead of GuzzleHttp

**Issue**: During development, GuzzleHttp requests would sometimes hang indefinitely in Docker containers.

**Symptoms**:
- Webhook requests never completing
- Timeouts not being respected
- Container becoming unresponsive

**Solution**: Native PHP curl with specific options.

```php
curl_setopt($ch, CURLOPT_NOSIGNAL, 1);  // Critical: Prevents signal handling issues
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);  // 2 seconds to connect
curl_setopt($ch, CURLOPT_TIMEOUT, 5);  // 5 seconds total
```

**Location**: `NotificationService::sendWebhook()`

**Important**: Do NOT reintroduce GuzzleHttp without extensive testing in Docker environments.

### 2. Changed Fields Tracking

**Implementation** (ProductSubscriber):
```php
if ($operation === 'update') {
    $changedFields = array_keys($payload);  // Get changed fields from DAL
    $relevantFields = $this->getRelevantChangedFields($changedFields);  // Filter system fields
    if (!empty($relevantFields)) {
        $entityData['changedFields'] = $relevantFields;
    }
}
```

**Key Points**:
- Only added on UPDATE operations (not INSERT)
- System fields filtered out
- Empty array not included in payload
- Translated fields (name, description) handled correctly

### 3. Version Check Removal

**Why NO Version Checks**: Shopware creates draft versions when editing in admin. Filtering by `Defaults::LIVE_VERSION` would block all admin edits from triggering webhooks.

**Old (Wrong)**:
```php
if ($payload['versionId'] !== Defaults::LIVE_VERSION) {
    return;  // ❌ Blocks admin edits!
}
```

**New (Correct)**:
```php
// ✅ No version check - all changes trigger webhooks
```

## 🧪 Testing Guide

### Manual Testing

```bash
# Test Property Group
docker exec shopware-container bash -c "
  php bin/console dal:create property_group '{\"name\": \"Test Group\"}'
"

# Test Product
docker exec shopware-container bash -c "
  php bin/console product:create --name='Test Product' --stock=100
"

# Watch logs
docker exec shopware-container bash -c "
  tail -f /var/www/html/var/log/dev.log | grep Webhook
"
```

### Webhook Endpoint Testing

Your test endpoint should:
1. **Accept POST** with JSON body
2. **Return 200 OK** within 5 seconds
3. **Be idempotent** (same webhook ID = same action)

### Unit Testing (Future)

```php
// Example test structure
class ProductSubscriberTest extends TestCase
{
    public function testSystemFieldsAreFiltered()
    {
        $subscriber = new ProductSubscriber(...);
        $fields = ['name', 'versionId', 'updatedAt', 'price'];
        $result = $subscriber->getRelevantChangedFields($fields);

        $this->assertEquals(['name', 'price'], $result);
        $this->assertNotContains('versionId', $result);
        $this->assertNotContains('updatedAt', $result);
    }
}
```

## 🔧 Troubleshooting Guide

### Webhooks Not Sending

**1. Check Plugin Status**
```bash
bin/console plugin:list | grep CobbyWebhook
# Should show: Cobby | installed, active
```

**2. Check Configuration**
```bash
bin/console system:config:get Cobby.config
```

Verify:
- `webhookUrl` is set
- `enablePropertyGroupEvents` or `enableProductEvents` is true

**3. Check Logs**
```bash
tail -f /var/www/html/var/log/dev.log | grep -i webhook
```

Look for:
- "Preparing webhook" - Webhook about to be sent
- "Webhook sent successfully" - Success
- "Webhook failed" - Failure with error

**4. Test Endpoint Manually**
```bash
curl -X POST https://your-endpoint.com/webhook \
  -H "Content-Type: application/json" \
  -H "X-Shopware-Event: test.event" \
  -d '{"test": "data"}'
```

### Changed Fields Not Showing

**Symptoms**: `changedFields` array is empty or missing.

**Causes**:
1. **Insert operation**: Changed fields only for updates, not inserts
2. **Only system fields changed**: All fields filtered out
3. **Config disabled**: Check if product events are enabled

**Debug**:
```php
// Add to ProductSubscriber::onProductWritten()
$this->logger->info('Raw changed fields', [
    'changed' => array_keys($payload),
    'operation' => $operation
]);
```

### Performance Issues

**Symptoms**: Webhooks slowing down Shopware operations.

**Solutions**:

1. **Increase Timeout** (if endpoint is slow):
```php
// NotificationService.php
private const DEFAULT_TIMEOUT = 10;  // Increase from 5 to 10
```

2. **Reduce Associations** (if not needed):
```php
// ProductSubscriber.php
private const PRODUCT_ASSOCIATIONS = [
    'manufacturer',  // Keep only needed
    'categories',
    // Remove: 'media', 'cover', 'prices', etc.
];
```

3. **Disable Debug Logging**:
```php
// config.xml or Admin
enableDebugLogging = false
```

## 📋 Deployment Checklist

### Before Release

- [ ] Disable debug logging
- [ ] Test all event types (property groups, products)
- [ ] Test endpoint responds within 5 seconds
- [ ] Check log file permissions
- [ ] Clear test data
- [ ] Update version in `composer.json`

### Production Configuration

```yaml
# Recommended settings
webhookUrl: "https://your-production-endpoint.com/webhook"
enablePropertyGroupEvents: true
enableProductEvents: true
enableDebugLogging: false  # MUST be false in production
```

### Security Checklist

- [ ] HTTPS endpoint (not HTTP)
- [ ] Endpoint rate limits requests
- [ ] Endpoint logs suspicious activity
- [ ] Firewall rules allow Shopware server IP

## 🚀 Adding New Subscribers

### Step-by-Step Guide

**1. Create Subscriber Class** (using generic methods)
```php
// src/Subscriber/CustomSubscriber.php
namespace CobbyShopware6Extension\Subscriber;

class CustomSubscriber extends AbstractWebhookSubscriber
{
    private EntityRepository $customRepository;

    public function __construct(
        QueueTableService $queueService,
        NotificationService $notificationService,
        LoggerInterface $logger,
        EntityRepository $customRepository,
        SystemConfigService $systemConfigService
    ) {
        parent::__construct($queueService, $notificationService, $logger, $systemConfigService);
        $this->customRepository = $customRepository;
    }

    public static function getSubscribedEvents(): array {
        return [
            'custom.written' => 'onCustomWritten',
            'custom.deleted' => 'onCustomDeleted',
        ];
    }

    protected function getConfigKey(): string {
        return 'Cobby.config.enableCustomEvents';
    }

    protected function extractEntityData($entity): array {
        return [
            'id' => $entity->getId(),
            'name' => $entity->getName(),
            'customField1' => $entity->getCustomField1(),
            // Add more fields...
        ];
    }

    protected function buildFallbackData(string $id, array $payload): array {
        return [
            'id' => $id,
            'name' => $payload['name'] ?? null,
        ];
    }

    // Use generic handler methods (simple, clean!)
    public function onCustomWritten(EntityWrittenEvent $event): void {
        $this->handleWrittenEvent($event, 'custom', ['translations'], $this->customRepository);
    }

    public function onCustomDeleted(EntityDeletedEvent $event): void {
        $this->handleDeletedEvent($event, 'custom');
    }
}
```

**Benefits of using generic methods**:
- Only 2 lines per event handler (vs. ~30 lines with old approach)
- Consistent error handling (built into base class)
- Automatic queue storage + notification
- No boilerplate code

**2. Register in services.xml**
```xml
<service id="CobbyShopware6Extension\Subscriber\CustomSubscriber" public="true">
    <argument type="service" id="CobbyShopware6Extension\Service\QueueTableService"/>
    <argument type="service" id="CobbyShopware6Extension\Service\NotificationService"/>
    <argument type="service" id="monolog.logger"/>
    <argument type="service" id="custom.repository"/>
    <argument type="service" id="Shopware\Core\System\SystemConfig\SystemConfigService"/>
    <tag name="kernel.event_subscriber"/>
</service>
```

**3. Add Config Option**
```xml
<!-- config.xml -->
<input-field type="bool">
    <name>enableCustomEvents</name>
    <label>Enable Custom Events</label>
    <defaultValue>true</defaultValue>
</input-field>
```

## 📚 Code Conventions

### Naming
- **Services**: Singular (NotificationService, QueueTableService - not NotificationServices)
- **Subscribers**: EntityName + Subscriber (ProductSubscriber)
- **Events**: snake_case.with.dots (product.written, property_group.deleted)

### Logging
```php
// Use appropriate log levels
$this->logger->info('Normal operation');
$this->logger->warning('Recoverable issue');
$this->logger->error('Critical failure');
```

### Error Handling
```php
// ALWAYS catch in subscribers (generic methods do this automatically)
try {
    // Webhook logic
} catch (\Throwable $e) {
    $this->logger->error('Error message', [
        'error' => $e->getMessage()
    ]);
    // NEVER re-throw - would break Shopware
}
```

**Note**: Generic handler methods (handleWrittenEvent, handleDeletedEvent, handleParentEntityEvent) include automatic error handling, so you don't need to write try-catch blocks manually.

## 🔄 Migration Path

### From v0.6.0-beta to v1.0.0

**Major Changes**:
- ✅ Added 12 new entity types (14 total, 44 events)
- ✅ Metadata-Only architecture with lightweight metadata storage (entity_type + entity_id only)
- ✅ Generic handler methods (handleWrittenEvent, handleDeletedEvent, handleParentEntityEvent)
- ✅ Simplified services: NotificationService + QueueTableService (removed ProductHashService, RegistryService, RateLimiterService)
- ✅ Lightweight notification payloads (~200 bytes) with queue_id reference
- ✅ External service loads entity data on-demand via Shopware API (always current, never stale)
- ✅ New method: truncateQueue() in QueueTableService
- ✅ New API endpoint: POST /api/_action/cobby/queue/truncate
- ⚠️ Database schema changed: only cobby_queue table (NO entity_data, status, retry_count, error_message columns)

**Migration Steps**:
1. **Backup** current queue data (if any)
2. **Update** plugin files
3. **Run migrations**: `bin/console database:migrate --all CobbyShopware6Extension`
   - Only Migration1737648000CreateQueueTable.php will run
   - Removed migrations: Migration1737655000, Migration1737649000, Migration1737650000, Migration1737651000
4. **Clear cache**: `bin/console cache:clear`
5. **Configure** new entity toggles in Admin UI
6. **Update** external service workflow:
   - Receive webhook notification with queue_id
   - Poll queue: GET /api/_action/cobby/queue?minQueueId=X
   - Load entity data on-demand: GET /api/product/{entity_id}
   - Process entity data (always current, loaded live from Shopware)
   - Mark processed: DELETE /api/_action/cobby/queue?maxQueueId=Y

## 📞 Support & Contributing

### Reporting Issues

Include:
1. Shopware version (`bin/console --version`)
2. Plugin version (check `composer.json`)
3. Error messages from logs
4. Webhook payload example
5. Steps to reproduce

### Code Style

- PSR-12 coding standards
- Strict types: `declare(strict_types=1);`
- Type hints on all methods
- PHPDoc blocks for public methods

---

*Last updated: January 23, 2025*
*Plugin version: 1.0.50*
*Shopware compatibility: 6.4+*
