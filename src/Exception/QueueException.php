<?php

declare(strict_types=1);

namespace CobbyPlugin\Exception;

/**
 * Exception thrown when queue operations fail.
 *
 * Provides static factory methods for common queue error scenarios.
 */
class QueueException extends \RuntimeException
{
    /**
     * Create exception for empty queue scenario.
     */
    public static function emptyQueue(): self
    {
        return new self('Queue is empty, no entries to process');
    }

    /**
     * Create exception for failed truncate operation.
     */
    public static function truncateFailed(\Throwable $previous): self
    {
        return new self('Failed to truncate queue table: ' . $previous->getMessage(), 0, $previous);
    }

    /**
     * Create exception for failed enqueue operation.
     */
    public static function enqueueFailed(string $entityType, string $entityId, \Throwable $previous): self
    {
        return new self(
            "Failed to enqueue {$entityType} with ID {$entityId}: " . $previous->getMessage(),
            0,
            $previous
        );
    }

    /**
     * Create exception for invalid queue parameters.
     */
    public static function invalidParameters(string $reason): self
    {
        return new self("Invalid queue parameters: {$reason}");
    }
}
