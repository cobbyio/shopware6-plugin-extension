<?php
// Script to reset the in-memory cache of the OrderSubscriber

echo "Resetting OrderSubscriber cache...\n";

// Since the cache is static in PHP memory, we need to restart PHP workers
// The easiest way is to clear Shopware cache which will reset everything

exec('cd /var/www/html && bin/console cache:clear', $output, $returnCode);

if ($returnCode === 0) {
    echo "Cache cleared successfully!\n";
    echo "The next order with a new ID will be treated as 'order.created'\n";
} else {
    echo "Failed to clear cache\n";
    echo implode("\n", $output);
}