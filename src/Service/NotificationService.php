<?php declare(strict_types=1);

namespace CobbyShopware6Extension\Service;

use CobbyShopware6Extension\CobbyShopware6Extension;
use CobbyShopware6Extension\Exception\WebhookException;
use CobbyShopware6Extension\Util\SecurityTrait;
use Psr\Log\LoggerInterface;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * Core webhook delivery service using native PHP curl for maximum reliability.
 *
 * WHY CURL INSTEAD OF GUZZLEHTTP:
 * During development, GuzzleHttp requests would sometimes hang indefinitely in
 * Docker containers, blocking order saves and causing "Order could not be saved"
 * errors. Native curl with CURLOPT_NOSIGNAL=1 and proper timeouts proved more
 * reliable in containerized environments.
 *
 * CONFIGURATION:
 * - webhookUrl: Target endpoint (default: https://automate.cobby.io/webhook/shopware/plugin-event)
 * - enableDebugLogging: Detailed logging
 */
class NotificationService
{
    use SecurityTrait;

    private const DEFAULT_TIMEOUT = 5;
    private const CONNECT_TIMEOUT = 2;

    private LoggerInterface $logger;
    private SystemConfigService $systemConfigService;

    public function __construct(
        LoggerInterface $logger,
        SystemConfigService $systemConfigService
    ) {
        $this->logger = $logger;
        $this->systemConfigService = $systemConfigService;
    }

    private function getWebhookUrl(): string
    {
        return $this->systemConfigService->get(CobbyShopware6Extension::CONFIG_PREFIX . 'webhookUrl');
    }

    private function isDebugLoggingEnabled(): bool
    {
        return (bool) $this->systemConfigService->get(CobbyShopware6Extension::CONFIG_PREFIX . 'enableDebugLogging');
    }

    /**
     * Sends a webhook with full response details (for testing and debugging).
     *
     * @param string $eventName Event identifier (e.g., "product.written")
     * @param array $data Webhook payload data
     * @param string|null $url Override webhook URL (for testing)
     * @param int|null $timeout Override timeout in seconds (default: 5)
     * @return array ['success' => bool, 'http_status' => int|null, 'response' => string|null, 'error' => string|null]
     */
    public function sendWebhookWithResponse(
        string $eventName,
        array $data,
        string $url = null,
        int $timeout = null
    ): array {
        $webhookUrl = $url ?: $this->getWebhookUrl();
        $webhookTimeout = $timeout ?: self::DEFAULT_TIMEOUT;

        if (empty($webhookUrl)) {
            return [
                'success' => false,
                'error' => 'No webhook URL configured',
                'http_status' => null,
                'response' => null
            ];
        }

        // Add shop information if not already present
        if (!isset($data['shop'])) {
            $data['shop'] = [
                'shopUrl' => $this->getSafeHttpHost(),
                'shopwareVersion' => $this->getShopwareVersion(),
            ];
        }

        // Encode JSON with error handling
        try {
            $jsonData = json_encode($data, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $this->logger->error('Failed to encode webhook data as JSON', [
                'error' => $e->getMessage(),
                'event' => $eventName
            ]);
            return [
                'success' => false,
                'error' => 'JSON encoding failed: ' . $e->getMessage(),
                'http_status' => null,
                'response' => null
            ];
        }

        // Log webhook preparation
        $logContext = [
            'event' => $eventName,
            'url' => $webhookUrl
        ];

        if ($this->isDebugLoggingEnabled()) {
            $logContext['payload'] = $jsonData;
        }

        $this->logger->info('Preparing webhook', $logContext);

        // Use curl for more control
        $ch = curl_init($webhookUrl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-Shopware-Event: ' . $eventName,
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $webhookTimeout);
        curl_setopt($ch, CURLOPT_NOSIGNAL, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, self::CONNECT_TIMEOUT);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        $success = $httpCode >= 200 && $httpCode < 300 && !$error;

        // Log result
        $resultContext = [
            'event' => $eventName,
            'http_status' => $httpCode,
            'success' => $success
        ];

        if ($this->isDebugLoggingEnabled()) {
            $resultContext['response'] = $response;
            if ($error) {
                $resultContext['error'] = $error;
            }
        }

        if ($success) {
            $this->logger->info('Webhook sent successfully', $resultContext);
        } else {
            $this->logger->warning('Webhook failed', array_merge($resultContext, ['error' => $error]));
        }

        return [
            'success' => $success,
            'http_status' => $httpCode ?: null,
            'response' => $response ?: null,
            'error' => $error ?: null
        ];
    }

    /**
     * Sends a webhook without waiting for detailed response (fire-and-forget).
     *
     * @param string $eventName Event identifier (e.g., "product.written")
     * @param array $data Webhook payload data
     * @return void
     */
    public function sendWebhook(string $eventName, array $data): void
    {
        $result = $this->sendWebhookWithResponse($eventName, $data);

        if (!$result['success']) {
            $this->logger->error('Failed to send webhook', [
                'event' => $eventName,
                'error' => $result['error'],
                'url' => $this->getWebhookUrl(),
            ]);
        }
    }
}
