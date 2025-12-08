<?php
$data = ["event" => "test", "timestamp" => time()];
$json = json_encode($data);
$ch = curl_init("https://automate.cobby.io/webhook/shopware/plugin-event");
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
echo "Response: " . $response . " (HTTP " . $httpCode . ")\n";
curl_close($ch);