<?php declare(strict_types=1);

namespace CobbyPlugin;

use CobbyPlugin\Service\NotificationService;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Uuid\Uuid;

class CobbyPlugin extends Plugin
{
    public const string PLUGIN_VERSION = '1.0.50';
    public const string CONFIG_PREFIX = 'cobby.config.';
    private const string COBBY_ROLE = 'cobby_role';

    public function getMigrationNamespace(): string
    {
        return 'CobbyPlugin\\Migration';
    }

    public function getMigrationPath(): string
    {
        return __DIR__ . '/Migration';
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.xml');
    }

    public function configureRoutes(RoutingConfigurator $routes, string $environment): void
    {
        parent::configureRoutes($routes, $environment);

        $routes->import(__DIR__ . '/../Resources/config/routes.xml');
    }

    public function install(InstallContext $context): void
    {
        parent::install($context);
        $this->initializeDefaultConfiguration();
        $this->createCobbyAclRole();
        $this->sendLifecycleNotification('installed');
    }

    public function uninstall(UninstallContext $context): void
    {
        // Send notification BEFORE uninstall - use direct instantiation
        // because plugin services are not available after deactivation
        $this->sendUninstallNotification();

        parent::uninstall($context);

        if ($context->keepUserData()) {
            return;
        }

        $this->removeConfiguration();
        $this->removeCobbyAclRole();
        $this->dropQueueTable();
    }

    /**
     * Send uninstall notification using direct service instantiation.
     * This is needed because plugin services are not available after deactivation.
     */
    private function sendUninstallNotification(): void
    {
        try {
            $systemConfig = $this->container->get(SystemConfigService::class);

            // Use NullLogger since monolog.logger is not public during uninstall
            $logger = new \Psr\Log\NullLogger();

            // Create NotificationService directly
            $notificationService = new NotificationService($logger, $systemConfig);
            $notificationService->sendStatusNotification('uninstalled');
        } catch (\Throwable $e) {
            // Silently ignore - lifecycle should not fail due to notification errors
        }
    }

    public function activate(ActivateContext $context): void
    {
        parent::activate($context);
        $this->sendLifecycleNotification('activated');
    }

    public function deactivate(DeactivateContext $context): void
    {
        $this->sendLifecycleNotification('deactivated');
        parent::deactivate($context);
    }

    private function initializeDefaultConfiguration(): void
    {
        $systemConfig = $this->container->get(SystemConfigService::class);
        $domain = self::CONFIG_PREFIX;

        // Set default values if not already set (synced with config.xml)
        $defaults = [
            'enablePropertyGroupEvents' => true,
            'enableProductEvents' => true,
            'enableCategoryEvents' => true,
            'enableTaxEvents' => true,
            'enableCurrencyEvents' => true,
            'enableManufacturerEvents' => true,
            'enableSalesChannelEvents' => true,
            'enableRuleEvents' => true,
            'enableUnitEvents' => true,
            'enableDeliveryTimeEvents' => true,
            'enableTagEvents' => true,
            'enableDebugLogging' => false,
        ];

        foreach ($defaults as $key => $value) {
            if ($systemConfig->get($domain . $key) === null) {
                $systemConfig->set($domain . $key, $value);
            }
        }
    }

    private function removeConfiguration(): void
    {
        $domain = rtrim(self::CONFIG_PREFIX, '.');

        // Remove all plugin configuration
        $connection = $this->container->get(Connection::class);
        $connection->executeStatement(
            'DELETE FROM system_config WHERE configuration_key LIKE :key',
            ['key' => $domain . '%']
        );
    }

    private function dropQueueTable(): void
    {
        $connection = $this->container->get(Connection::class);
        $connection->executeStatement('DROP TABLE IF EXISTS `cobby_queue`');
    }

    private function sendLifecycleNotification(string $status): void
    {
        try {
            $notificationService = $this->container->get(NotificationService::class);
            $notificationService->sendStatusNotification($status);
        } catch (\Throwable $e) {
            // Log error for debugging - lifecycle should not fail due to notification errors
            error_log('Cobby lifecycle notification failed: ' . $e->getMessage());
        }
    }

    /**
     * Create the cobby ACL role with read and write permissions for all tracked entities.
     * Admin creates the integration manually in Shopware Admin and assigns this role.
     */
    private function createCobbyAclRole(): void
    {
        try {
            $connection = $this->container->get(Connection::class);

            // Check if ACL role already exists
            $existing = $connection->fetchOne(
                'SELECT id FROM acl_role WHERE name = :name',
                ['name' => self::COBBY_ROLE]
            );

            // Read and write permissions for all tracked entities
            $privileges = [
                "category:create",
                "category:read",
                "category:update",
                "currency:read",
                "custom_field:read",
                "delivery_time:read",
                "media:create",
                "media:read",
                "media:update",
                "media_default_folder:read",
                "media_folder:read",
                "product:create",
                "product:read",
                "product:update",
                "product_configurator_setting:create",
                "product_configurator_setting:delete",
                "product_configurator_setting:read",
                "product_manufacturer:create",
                "product_manufacturer:read",
                "product_manufacturer:update",
                "product_media:create",
                "product_media:delete",
                "product_media:read",
                "product_media:update",
                "product_option:create",
                "product_option:delete",
                "product_option:read",
                "product_price:read",
                "product_price:update",
                "product_property:create",
                "product_property:delete",
                "product_property:read",
                "product_tag:create",
                "product_tag:delete",
                "product_tag:read",
                "product_tag:update",
                "product_visibility:create",
                "product_visibility:delete",
                "product_visibility:read",
                "product_visibility:update",
                "property_group:read",
                "property_group:update",
                "property_group_option:create",
                "property_group_option:read",
                "property_group_option:update",
                "property_group_option_translation:create",
                "property_group_option_translation:read",
                "property_group_option_translation:update",
                "property_group_translation:read",
                "property_group_translation:update",
                "rule:read",
                "sales_channel:read",
                "system_config:create",
                "system_config:read",
                "system_config:update",
                "tag:create",
                "tag:read",
                "tag:update",
                "tax:read",
                "unit:read"
            ];

            if ($existing) {
                // Update existing role with current privileges
                $connection->executeStatement(
                    'UPDATE acl_role SET privileges = :privileges, updated_at = :updated_at WHERE name = :name',
                    [
                        'name' => self::COBBY_ROLE,
                        'privileges' => json_encode($privileges),
                        'updated_at' => (new \DateTime())->format('Y-m-d H:i:s.v'),
                    ]
                );
            } else {
                // Create new ACL role
                $connection->insert('acl_role', [
                    'id' => Uuid::randomBytes(),
                    'name' => self::COBBY_ROLE,
                    'privileges' => json_encode($privileges),
                    'created_at' => (new \DateTime())->format('Y-m-d H:i:s.v'),
                ]);
            }

        } catch (\Throwable $e) {
            error_log('Cobby ACL role creation failed: ' . $e->getMessage());
        }
    }

    /**
     * Remove the cobby ACL role.
     */
    private function removeCobbyAclRole(): void
    {
        try {
            $connection = $this->container->get(Connection::class);

            // Delete ACL role (integration_role entries cascade automatically)
            $connection->executeStatement(
                'DELETE FROM acl_role WHERE name = :name',
                ['name' => self::COBBY_ROLE]
            );

        } catch (\Throwable $e) {
            error_log('Cobby ACL role removal failed: ' . $e->getMessage());
        }
    }
}