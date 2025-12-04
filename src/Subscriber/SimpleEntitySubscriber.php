<?php declare(strict_types=1);

namespace CobbyShopware6Extension\Subscriber;

use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;

/**
 * Base class for simple entity subscribers with standard written/deleted events.
 *
 * This class eliminates boilerplate code for subscribers that only need to handle
 * basic entity.written and entity.deleted events with no custom logic.
 *
 * Child classes only need to:
 * 1. Implement getEntityType() - return the entity type string (e.g., 'tax', 'currency')
 * 2. Implement getConfigKey() - return the config key for enable/disable
 *
 * Example:
 * ```php
 * class TaxSubscriber extends SimpleEntitySubscriber
 * {
 *     protected function getEntityType(): string { return 'tax'; }
 *     protected function getConfigKey(): string {
 *         return CobbyShopware6Extension::CONFIG_PREFIX . 'enableTaxEvents';
 *     }
 * }
 * ```
 */
abstract class SimpleEntitySubscriber extends AbstractWebhookSubscriber
{
    /**
     * Get the entity type for this subscriber.
     * Must be overridden by child classes.
     *
     * @return string Entity type (e.g., 'tax', 'currency', 'sales_channel')
     */
    abstract protected function getEntityType(): string;

    /**
     * Automatically subscribe to entity.written and entity.deleted events.
     *
     * @return array<string, string> Event name => handler method mapping
     */
    public static function getSubscribedEvents(): array
    {
        // Note: Can't use $this in static context, but can use static late binding
        // This requires child class to be instantiated, which Symfony does automatically
        return [
            static::getEntityTypeStatic() . '.written' => 'onEntityWritten',
            static::getEntityTypeStatic() . '.deleted' => 'onEntityDeleted',
        ];
    }

    /**
     * Static wrapper for getEntityType() to use in getSubscribedEvents().
     * Child classes can override this if needed.
     *
     * @return string Entity type
     */
    protected static function getEntityTypeStatic(): string
    {
        // This is a workaround since we can't call instance methods from static context
        // We extract entity type from class name (e.g., TaxSubscriber -> tax)
        $className = static::class;
        $shortName = substr($className, strrpos($className, '\\') + 1);
        $entityName = str_replace('Subscriber', '', $shortName);

        // Convert CamelCase to snake_case
        $snakeCase = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $entityName));

        return $snakeCase;
    }

    /**
     * Handle entity.written events generically.
     *
     * @param EntityWrittenEvent $event The written event
     */
    public function onEntityWritten(EntityWrittenEvent $event): void
    {
        $this->handleSimpleWrittenEvent($event, $this->getEntityType());
    }

    /**
     * Handle entity.deleted events generically.
     *
     * @param EntityDeletedEvent $event The deleted event
     */
    public function onEntityDeleted(EntityDeletedEvent $event): void
    {
        $this->handleSimpleDeletedEvent($event, $this->getEntityType());
    }
}
