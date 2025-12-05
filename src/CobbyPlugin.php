<?php declare(strict_types=1);

namespace CobbyShopware6Extension;

use CobbyShopware6Extension\Service\NotificationService;
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

class CobbyPlugin extends Plugin
{
    public const string PLUGIN_VERSION = '1.0.49';
    public const string CONFIG_PREFIX = 'cobby.config.';

    public function getMigrationNamespace(): string
    {
        return 'CobbyShopware6Extension\\Migration';
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
}