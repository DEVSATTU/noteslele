<?php
// Set CORS and JSON headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ==========================================
// 1. CASHFREE CREDENTIALS CONFIGURATION
// ==========================================
$app_id = 'YOUR_CASHFREE_APP_ID';
$secret_key = 'YOUR_CASHFREE_SECRET_KEY';

// Change to 'production' when going live
$environment = 'sandbox'; 

// ==========================================
// 2. ENDPOINT SETUP
// ==========================================
$base_url = ($environment === 'sandbox') 
    ? "https://sandbox.cashfree.com/pg/orders" 
    : "https://api.cashfree.com/pg/orders";

// Read the incoming JSON payload from frontend
$raw_input = file_get_contents('php://input');
$data = json_decode($raw_input, true);

// ==========================================
// 3. PROCESS THE CREATE ORDER ACTION
// ==========================================
if (isset($data['action']) && $data['action'] === 'create_order') {
    
    // Extract variables passed from JS
    $noteId = $data['noteId'] ?? 'Unknown_Note';
    $price = (float)($data['price'] ?? 0);
    $userId = $data['userId'] ?? 'Guest';
    $customerName = $data['customerName'] ?? 'Student';
    $customerEmail = $data['customerEmail'] ?? 'student@example.com';
    $customerPhone = $data['customerPhone'] ?? '9999999999'; // Cashfree requires a valid 10 digit phone number

    // Validate Price
    if ($price <= 0) {
        echo json_encode(["error" => "Invalid price amount"]);
        exit();
    }

    // Generate a Unique Order ID (Prefix + NoteID + Timestamp)
    $order_id = "ORDER_" . substr(preg_replace('/[^a-zA-Z0-9]/', '', $noteId), 0, 10) . "_" . time();

    // Prepare Cashfree API payload
    $order_payload = [
        "order_id" => $order_id,
        "order_amount" => round($price, 2),
        "order_currency" => "INR",
        "customer_details" => [
            "customer_id" => substr(preg_replace('/[^a-zA-Z0-9_-]/', '', $userId), 0, 50),
            "customer_name" => $customerName,
            "customer_email" => $customerEmail,
            "customer_phone" => $customerPhone
        ],
        "order_meta" => [
            // Return URL is required by API but the JS SDK handles the UI response directly
            "return_url" => "https://yourwebsite.com/return?order_id={order_id}" 
        ],
        "order_note" => "Payment for Notes ID: " . $noteId
    ];

    // Setup cURL request to Cashfree
    $ch = curl_init($base_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($order_payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "x-client-id: " . $app_id,
        "x-client-secret: " . $secret_key,
        "x-api-version: 2023-08-01" // Current Cashfree API Version
    ]);

    // Execute API Call
    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
        echo json_encode(["error" => "cURL Error: " . $err]);
        exit();
    }

    // Decode Cashfree Response
    $result = json_decode($response, true);

    // If successful, Cashfree returns a payment_session_id
    if ($httpcode == 200 && isset($result['payment_session_id'])) {
        echo json_encode([
            "payment_session_id" => $result['payment_session_id'],
            "order_id" => $order_id
        ]);
    } else {
        // Return detailed error if creation fails
        echo json_encode([
            "error" => "Cashfree Order Creation Failed",
            "details" => $result,
            "http_code" => $httpcode
        ]);
    }

} else {
    echo json_encode(["error" => "Invalid Action Request"]);
}
?>
