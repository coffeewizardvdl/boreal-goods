<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://borealgoods.ca');
header('Cache-Control: public, max-age=300'); // Cache for 5 minutes

$api_key   = 'p1bpg9ii2d72ozzca1otww8m';
$shop_id   = 'borealgoodsyeg';

$endpoint  = "https://api.etsy.com/v3/application/shops/{$shop_id}/listings/active?limit=25&includes=Images,MainImage";

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $endpoint,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        "x-api-key: {$api_key}",
        "Accept: application/json",
    ],
    CURLOPT_TIMEOUT        => 10,
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false || $http_code !== 200) {
    http_response_code(502);
    echo json_encode(['error' => 'Failed to fetch listings', 'code' => $http_code]);
    exit;
}

echo $response;
