<?php
// Add error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Format the number correctly with country code
// For Bangladesh numbers (+880), remove leading 0
$phoneNumber = "01712923446";
if (substr($phoneNumber, 0, 1) === "0") {
    $phoneNumber = "880" . substr($phoneNumber, 1);
}

$data = [
    "number" => $phoneNumber,
    "message" => "Hello from PHP!"
];

$jsonData = json_encode($data);
echo "Sending data: " . $jsonData . "\n";

$options = [
    "http" => [
        "header" => "Content-Type: application/json",
        "method" => "POST",
        "content" => $jsonData,
        "ignore_errors" => true  // This allows us to see error responses
    ],
];

$context = stream_context_create($options);
$response = file_get_contents("http://localhost:3000/send-message", false, $context);

// Get HTTP response code
$statusLine = $http_response_header[0];
preg_match('{HTTP\/\S*\s(\d{3})}', $statusLine, $match);
$statusCode = $match[1];

echo "Status code: " . $statusCode . "\n";
echo "Response: " . $response . "\n";
?>