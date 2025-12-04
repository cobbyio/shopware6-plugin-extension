<?php declare(strict_types=1);

namespace CobbyShopware6Extension\Exception;

/**
 * Exception thrown when webhook operations fail.
 *
 * Provides static factory methods for common webhook error scenarios.
 */
class WebhookException extends \RuntimeException
{
    /**
     * Create exception for failed webhook send operation.
     */
    public static function sendFailed(string $url, \Throwable $previous): self
    {
        return new self(
            "Failed to send webhook to: {$url} - " . $previous->getMessage(),
            0,
            $previous
        );
    }

    /**
     * Create exception for invalid webhook response.
     */
    public static function invalidResponse(string $url, int $statusCode, string $body = ''): self
    {
        $message = "Webhook returned invalid status {$statusCode} from: {$url}";
        if ($body) {
            $message .= " - Response: " . substr($body, 0, 200);
        }
        return new self($message);
    }

    /**
     * Create exception for webhook timeout.
     */
    public static function timeout(string $url, int $timeoutSeconds): self
    {
        return new self("Webhook request timed out after {$timeoutSeconds}s: {$url}");
    }

    /**
     * Create exception for invalid webhook configuration.
     */
    public static function invalidConfiguration(string $reason): self
    {
        return new self("Invalid webhook configuration: {$reason}");
    }

    /**
     * Create exception for missing webhook URL.
     */
    public static function missingUrl(): self
    {
        return new self('Webhook URL is not configured');
    }
}
