<?php

declare(strict_types=1);

use CobbyPlugin\Controller\QueueTableController;
use CobbyPlugin\Service\NotificationService;
use CobbyPlugin\Service\QueueTableService;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return function (ContainerConfigurator $configurator): void {
    $services = $configurator->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    // Queue Service (Metadata-Only Architecture)
    $services->set(QueueTableService::class)
        ->args([
            service(Connection::class),
            service('monolog.logger'),
            service(SystemConfigService::class),
        ]);

    // Notification Service (Webhook delivery) - public for Plugin lifecycle access
    $services->set(NotificationService::class)
        ->public()
        ->args([
            service('monolog.logger'),
            service(SystemConfigService::class),
        ]);

    // Queue Controller (API endpoints for queue management)
    $services->set(QueueTableController::class)
        ->public()
        ->args([
            service(QueueTableService::class),
        ])
        ->call('setContainer', [service('service_container')])
        ->tag('controller.service_arguments');

    // Event subscribers (auto-registered via prototype)
    $services->load('CobbyPlugin\\Subscriber\\', __DIR__ . '/../../src/Subscriber/*Subscriber.php')
        ->tag('kernel.event_subscriber');
};
