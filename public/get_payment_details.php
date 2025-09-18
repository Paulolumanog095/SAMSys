<?php
session_start();

// Check if the user is logged in
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    // If not logged in, redirect to the login page
    header('Location: login.php');
    exit();
}


header('Content-Type: application/json');

// Supabase Configuration
$supabaseUrl = 'https://tihodezxfrpjtpratrez.supabase.co';
$supabaseAnonKey = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InRpaG9kZXp4ZnJwanRwcmF0cmV6Iiwicm9sZSI6ImFub24iLCJpYXQiOjE3NDcyOTkyODksImV4cCI6MjA2Mjg3NTI4OX0.JU2jbQYhjjq90wrDi35LNr9AWKpqdvJtaO_JDgVR_JM';
$supabasePaymentsTable = 'payments';

$paymentId = $_GET['payment_id'] ?? null;

if (!$paymentId) {
    echo json_encode(['error' => 'No payment ID provided.']);
    exit();
}

$fetchPaymentApiUrl = $supabaseUrl . '/rest/v1/' . $supabasePaymentsTable . '?select=*&id=eq.' . urlencode($paymentId);

$ch = curl_init($fetchPaymentApiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'apikey: ' . $supabaseAnonKey,
    'Authorization: Bearer ' . $supabaseAnonKey
]);
curl_setopt($ch, CURLOPT_CAINFO, 'C:\Program Files\Ampps\php82\extras\ssl\cacert.pem'); // Ensure this path is correct
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($httpCode >= 200 && $httpCode < 300) {
    $data = json_decode($response, true);
    if (json_last_error() === JSON_ERROR_NONE && !empty($data)) {
        echo json_encode($data[0]); // Return the first (and only) payment object
    } else {
        echo json_encode(['error' => 'Payment not found or JSON error.']);
    }
} else {
    echo json_encode(['error' => 'Error fetching payment: HTTP ' . $httpCode . ' - ' . ($error ?: $response)]);
}
?>