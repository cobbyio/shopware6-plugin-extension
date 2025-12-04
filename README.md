# CobbyShopware6Extension - Extended Webhook Events for Shopware 6

## Overview
CobbyShopware6Extension extends Shopware 6 with additional webhook events that are not available through standard Shopware Apps. It provides comprehensive webhook support for **12 entity types**, covering the complete e-commerce catalog management:

**Supported Entities:**
- Products (8 events)
- Property Groups (4 events)
- Categories (2 events)
- Manufacturers (2 events)
- Tax Rates (2 events)
- Currencies (2 events)
- Sales Channels (2 events)
- Rules (2 events)
- Units (2 events)
- Delivery Times (2 events)
- Tags (2 events)

**Total: 30 webhook events** tracking all critical e-commerce entities.

## Features

### Complete Event Coverage (30 Events Across 12 Entities)

**Product Events (8 events)**
- `product.written` / `product.deleted` - Product catalog changes
- `product_price.written` / `product_price.deleted` - Pricing updates
- `product_media.written` / `product_media.deleted` - Product images
- `product_category.written` / `product_category.deleted` - Category assignments

**Property Group Events (4 events)**
- `property_group.written` / `property_group.deleted` - Variant groups (Size, Color)
- `property_group_option.written` / `property_group_option.deleted` - Variant options

**Category Events (2 events)**
- `category.written` / `category.deleted` - Category tree changes

**Manufacturer Events (2 events)**
- `product_manufacturer.written` / `product_manufacturer.deleted` - Brand changes

**Tax Events (2 events)**
- `tax.written` / `tax.deleted` - Tax rate configuration

**Currency Events (2 events)**
- `currency.written` / `currency.deleted` - Currency configuration

**Sales Channel Events (2 events)**
- `sales_channel.written` / `sales_channel.deleted` - Sales channel configuration

**Rule Events (2 events)**
- `rule.written` / `rule.deleted` - Business rule changes (promotion rules, payment rules, etc.)

**Unit Events (2 events)**
- `unit.written` / `unit.deleted` - Measurement unit changes (kg, liter, piece, etc.)

**Delivery Time Events (2 events)**
- `delivery_time.written` / `delivery_time.deleted` - Delivery time configuration (2-3 days, 1-2 weeks, etc.)

**Tag Events (2 events)**
- `tag.written` / `tag.deleted` - Product tag and label changes

### Enhanced Entity Data
The plugin fetches comprehensive data for all entities with deep associations:
- **Products**: 50+ fields including SEO, packaging, variants, manufacturer, categories, media, prices
- **Properties**: Groups and options with translations, filtering, display settings
- **Categories**: Tree structure, media, SEO, breadcrumbs
- **Manufacturers**: Brand info, media, links, translations
- **Tax/Currency**: Rates, symbols, ISO codes, translations
- **Sales Channels**: Multi-channel configuration, languages, currencies, domains
- **Rules**: Business rules with conditions, priorities, validation status
- **Units**: Measurement units with translations (kg, liter, piece, etc.)
- **Delivery Times**: Shipping timeframes with min/max values and translations
- **Tags**: Product tags and labels with custom fields

### Advanced Features
- **Changed fields detection**: Only shows fields that actually changed (system fields filtered out)
- **Metadata tracking**: Queue stores only entity_type + entity_id (data loaded on-demand by external service)
- **Lazy configuration**: Changes take effect immediately without cache clear
- **Queue system**: Reliable change tracking with database-backed queue (metadata-only architecture)
- **Flexible toggles**: Enable/disable events per entity type

## Installation

### Via Docker Compose (Development)
```yaml
volumes:
  - ./shopware6-extension/CobbyShopware6Extension:/var/www/html/custom/plugins/CobbyShopware6Extension
```

### Manual Installation
```bash
# Copy plugin to Shopware
cp -r CobbyShopware6Extension /var/www/html/custom/plugins/

# Install and activate
bin/console plugin:refresh
bin/console plugin:install CobbyShopware6Extension --activate
bin/console cache:clear
```

## Configuration

Configure the plugin in **Shopware Admin → Settings → System → Plugins → CobbyShopware6Extension**:

**General Settings:**
- **Webhook URL**: Target endpoint (default: `https://automate.cobby.io/webhook/shopware/plugin-event`)
- **Shop ID**: Unique shop identifier (UUID v4, auto-generated)
- **Enable Debug Logging**: Detailed logging via PSR-3 logger (default: false)

**Entity Event Toggles** (all default: true):
- **Enable Property Group Events**: Product variants and attributes
- **Enable Product Events**: Product catalog changes
- **Enable Category Events**: Category tree modifications
- **Enable Tax Events**: Tax rate changes
- **Enable Currency Events**: Currency configuration
- **Enable Manufacturer Events**: Brand/manufacturer changes
- **Enable Sales Channel Events**: Sales channel configuration
- **Enable Rule Events**: Business rule changes
- **Enable Unit Events**: Measurement unit changes
- **Enable Delivery Time Events**: Delivery time configuration
- **Enable Tag Events**: Product tag and label changes

## Webhook Payload Structure

### Property Group Event
```json
{
  "event": "property_group.written",
  "operation": "insert|update",
  "entity": "property_group",
  "data": {
    "id": "group-id",
    "name": "Size",
    "displayType": "text",
    "sortingType": "alphanumeric",
    "filterable": true,
    "visibleOnProductDetailPage": true,
    "position": 1,
    "customFields": null
  },
  "timestamp": 1234567890,
  "shop": {
    "shopUrl": "localhost",
    "shopwareVersion": "6.5.0.0"
  }
}
```

### Property Group Option Event
```json
{
  "event": "property_group_option.written",
  "operation": "insert|update",
  "entity": "property_group_option",
  "data": {
    "id": "option-id",
    "groupId": "group-id",
    "name": "Large",
    "position": 1,
    "colorHexCode": "#FF5733",
    "mediaId": null,
    "customFields": null,
    "groupName": "Size"
  },
  "timestamp": 1234567890,
  "shop": {
    "shopUrl": "localhost",
    "shopwareVersion": "6.5.0.0"
  }
}
```

### Product Event (with Changed Fields)
```json
{
  "event": "product.written",
  "operation": "update",
  "entity": "product",
  "data": {
    "id": "product-id",
    "productNumber": "SW10001",
    "name": "Example Product",
    "description": "Product description...",
    "active": true,
    "stock": 100,
    "price": {...},
    "metaTitle": "SEO Title",
    "metaDescription": "SEO Description",
    "manufacturer": {
      "id": "manufacturer-id",
      "name": "Brand Name"
    },
    "categories": [...],
    "media": [...],
    "tax": {...},
    "changedFields": ["name", "price", "description"]
  },
  "timestamp": 1234567890,
  "shop": {
    "shopUrl": "localhost",
    "shopwareVersion": "6.5.0.0"
  }
}
```

**Note:** The `changedFields` array only appears on update operations and contains only user-modified fields (system-generated fields like `versionId`, `updatedAt` are filtered out).

### Manufacturer Event
```json
{
  "event": "product_manufacturer.written",
  "operation": "insert|update",
  "entity": "product_manufacturer",
  "data": {
    "id": "manufacturer-id",
    "name": "Example Brand",
    "link": "https://example-brand.com",
    "description": "Premium brand description",
    "media": {
      "id": "media-id",
      "url": "https://shop.example/media/brand-logo.png"
    },
    "translations": [
      {
        "languageId": "lang-id",
        "name": "Example Brand",
        "description": "Premium brand description"
      }
    ],
    "customFields": null
  },
  "timestamp": 1234567890,
  "shop": {
    "shopUrl": "localhost",
    "shopwareVersion": "6.5.0.0"
  }
}
```

### Category Event
```json
{
  "event": "category.written",
  "operation": "insert|update",
  "entity": "category",
  "data": {
    "id": "category-id",
    "name": "Electronics",
    "active": true,
    "visible": true,
    "type": "page",
    "displayNestedProducts": true,
    "level": 2,
    "path": "|root-id|parent-id|",
    "parentId": "parent-id",
    "childCount": 5,
    "metaTitle": "Electronics - Shop",
    "metaDescription": "Browse our electronics catalog",
    "keywords": "electronics, technology",
    "media": {...},
    "breadcrumb": ["Home", "Categories", "Electronics"]
  },
  "timestamp": 1234567890,
  "shop": {
    "shopUrl": "localhost",
    "shopwareVersion": "6.5.0.0"
  }
}
```

### Deleted Event (All Entities)
```json
{
  "event": "product.deleted",
  "operation": "delete",
  "entity": "product",
  "data": {
    "id": "deleted-entity-id"
  },
  "timestamp": 1234567890,
  "shop": {
    "shopUrl": "localhost",
    "shopwareVersion": "6.5.0.0"
  }
}
```

**Note:** Deleted events contain only the entity ID. All other entity types follow similar structures with their respective fields.

## Webhook Headers
- `Content-Type`: application/json
- `X-Shopware-Event`: Event name (e.g., "product.written")

## Logging

The plugin uses PSR-3 compliant logging:
- **Info level**: Successful webhook sends
- **Warning level**: Failed webhook attempts
- **Error level**: Critical errors
- **Debug**: Detailed payload logging (when enabled)

Check Shopware logs:
```bash
tail -f /var/www/html/var/log/dev.log
```

## Performance

- **Non-blocking**: 5-second timeout prevents blocking Shopware operations
- **Lazy config loading**: Configuration changes take effect immediately
- **Optimized queries**: Efficient database queries with proper associations
- **Error handling**: Graceful degradation on failures

## Requirements

- Shopware 6.4 or higher
- PHP 7.4 or higher
- Configured webhook endpoint that responds within 5 seconds

## Troubleshooting

### Webhooks not being sent

1. **Check plugin status**
   ```bash
   bin/console plugin:list | grep CobbyWebhook
   ```

2. **Verify configuration**
   - Admin → Settings → System → Plugins → CobbyShopware6Extension
   - Ensure events are enabled
   - Verify webhook URL is correct

3. **Check logs**
   ```bash
   tail -f /var/www/html/var/log/dev.log | grep Webhook
   ```

4. **Test endpoint**
   - Ensure endpoint is reachable from Shopware server
   - Endpoint must respond with 200 OK within 5 seconds

### Changed fields not showing

- Changed fields only appear on **update** operations (not insert)
- Only user-modified fields are shown (system fields are filtered)
- If no relevant fields changed, array will be empty

### Missing entity data

**Note**: In Metadata-Only architecture v1.0.0, the queue does NOT store entity data. External services must load entity data on-demand via Shopware API. Schema optimized with VARCHAR field sizes for ~60% storage reduction per row.

- Ensure the specific entity event is enabled in configuration
- Check that entity exists in database when external service loads it
- External service should use GET /api/{entity-type}/{id} to load current data
- Check logs for any API errors when loading entity data

### Events not triggering for specific entities

1. Verify the entity type toggle is enabled (e.g., enableProductEvents, enablePropertyGroupEvents)
2. Check that changes are actually being saved in Shopware
3. Review logs for subscriber errors
4. Ensure queue table is being populated

## Architecture

The plugin uses a clean, maintainable Metadata-Only architecture:

**Core Services:**
- **NotificationService**: HTTP communication
- **QueueTableService**: Metadata-only change tracking (stores entity_type + entity_id, no full data)

**Event Subscribers (12 Total):**
- **AbstractWebhookSubscriber**: Base class implementing DRY principle with generic handlers
- **ProductSubscriber**: Product catalog (8 events)
- **PropertyGroupSubscriber**: Variant attributes (4 events)
- **CategorySubscriber**: Category tree (2 events)
- **ManufacturerSubscriber**: Brands (2 events)
- **TaxSubscriber**: Tax rates (2 events)
- **CurrencySubscriber**: Currencies (2 events)
- **SalesChannelSubscriber**: Sales channels (2 events)
- **RuleSubscriber**: Business rules (2 events)
- **UnitSubscriber**: Measurement units (2 events)
- **DeliveryTimeSubscriber**: Delivery times (2 events)
- **TagSubscriber**: Product tags (2 events)

**Database:**
- `cobby_queue`: Metadata-only queue (entity_type + entity_id only, no entity_data column)

**External Service Integration:**
The plugin uses a Metadata-Only architecture where:
1. Queue stores only metadata (entity_type + entity_id)
2. External service receives lightweight webhook notifications
3. External service loads entity data on-demand via Shopware API:
   - GET /api/cobby-queue?minQueueId=X (get queue metadata)
   - GET /api/product/{id} (load current entity data)
4. Benefits: Always current data (never stale), minimal database footprint

**API Endpoints:**
- GET /api/cobby-queue?minQueueId=X&pageSize=Y - Get queue entries (metadata only)
- GET /api/cobby-queue/max - Get maximum queue ID
- DELETE /api/cobby-queue - Truncate queue (deletes all entries, resets IDs)

See `DEVELOPMENT.md` for detailed architecture documentation.

## Complementary App

This plugin works alongside the **Cobby Automate** app which handles standard Shopware webhook events. Together they provide comprehensive webhook coverage for all Shopware entities.

## License

MIT License - © 2025 Cobby

## Support

For issues or questions:
1. Check `DEVELOPMENT.md` for developer documentation
2. Review logs in `/var/www/html/var/log/`
3. Create an issue with:
   - Shopware version
   - Plugin version
   - Error messages
   - Webhook payload example
