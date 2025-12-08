<?php
// Test script to create a property group via API
$baseUrl = 'http://localhost:8080';
$username = 'admin';
$password = 'shopware';

// Get OAuth token
$ch = curl_init($baseUrl . '/api/oauth/token');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'client_id' => 'administration',
    'grant_type' => 'password',
    'scopes' => 'write',
    'username' => $username,
    'password' => $password
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$token = json_decode($response, true)['access_token'] ?? null;
curl_close($ch);

if (!$token) {
    die("Failed to get token\n");
}

echo "Got token: " . substr($token, 0, 20) . "...\n";

// Create property group
$propertyData = [
    'name' => 'Test Property ' . time(),
    'displayType' => 'text',
    'sortingType' => 'alphanumeric',
    'options' => [
        [
            'name' => 'Option 1'
        ],
        [
            'name' => 'Option 2'
        ]
    ]
];

$ch = curl_init($baseUrl . '/api/property-group');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($propertyData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $token
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Create property group response (HTTP $httpCode): " . $response . "\n";

// Now check the webhook log
sleep(2);
$log = file_get_contents('/var/www/html/var/log/n8n_webhook_test.log');
$lines = explode("\n", $log);
$recent = array_slice($lines, -10);
echo "\nRecent webhook log entries:\n";
foreach ($recent as $line) {
    if (!empty($line)) {
        echo $line . "\n";
    }
}