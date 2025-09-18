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
$supabaseAnonKey = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InRpaG9kZXp4ZnJwanRwcmF0cmV6Iiwicm9sZSI6ImFub24iLCJpYXQiOjE3NDcyOTkyODksImexdCI6MjA2Mjg3NTI4OX0.JU2jbQYhjjq90wrDi35LNr9AWKpqdvJtaO_JDgVR_JM'; // Use a service role key if updates are not allowed by anon
$supabaseStudentsTable = 'students_account';
$supabasePaymentsTable = 'payments';

// Check if it's a POST request and content type is JSON
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

$paymentId = $input['payment_id'] ?? null;
$studentId = $input['student_id'] ?? null;
$newPaymentAmount = filter_var($input['payment_amount'] ?? 0, FILTER_VALIDATE_FLOAT);
$newORNumber = filter_var($input['or_number'] ?? '', FILTER_SANITIZE_STRING);

if (empty($paymentId) || empty($studentId) || $newPaymentAmount === false || $newPaymentAmount <= 0 || empty($newORNumber)) {
    echo json_encode(['success' => false, 'message' => 'Missing or invalid data provided.']);
    exit();
}

// 1. Update the payment record in Supabase
$updatePaymentApiUrl = $supabaseUrl . '/rest/v1/' . $supabasePaymentsTable . '?id=eq.' . urlencode($paymentId);
$chUpdate = curl_init($updatePaymentApiUrl);
curl_setopt($chUpdate, CURLOPT_RETURNTRANSFER, true);
curl_setopt($chUpdate, CURLOPT_CUSTOMREQUEST, 'PATCH'); // Use PATCH for partial updates
curl_setopt($chUpdate, CURLOPT_POSTFIELDS, json_encode([
    'payment_amount' => $newPaymentAmount,
    'or_number' => $newORNumber,
    'payment_date' => date('Y-m-d'), // Update date to current date of edit
    'payment_time' => date('H:i:s')  // Update time to current time of edit
]));
curl_setopt($chUpdate, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'apikey: ' . $supabaseAnonKey, // Make sure your RLS allows updates with anon key or use a service key
    'Authorization: Bearer ' . $supabaseAnonKey,
    'Prefer: return=representation'
]);
curl_setopt($chUpdate, CURLOPT_CAINFO, 'C:\Program Files\Ampps\php82\extras\ssl\cacert.pem');
$updateResponse = curl_exec($chUpdate);
$updateHttpCode = curl_getinfo($chUpdate, CURLINFO_HTTP_CODE);
$updateError = curl_error($chUpdate);
curl_close($chUpdate);

if (!($updateHttpCode >= 200 && $updateHttpCode < 300)) {
    echo json_encode(['success' => false, 'message' => 'Failed to update payment: HTTP ' . $updateHttpCode . ' - ' . ($updateError ?: $updateResponse)]);
    exit();
}

// 2. Fetch student's initial_account_balance
$fetchStudentApiUrl = $supabaseUrl . '/rest/v1/' . $supabaseStudentsTable . '?select=initial_account_balance&id=eq.' . urlencode($studentId);
$chStudent = curl_init($fetchStudentApiUrl);
curl_setopt($chStudent, CURLOPT_RETURNTRANSFER, true);
curl_setopt($chStudent, CURLOPT_HTTPHEADER, [
    'apikey: ' . $supabaseAnonKey,
    'Authorization: Bearer ' . $supabaseAnonKey
]);
curl_setopt($chStudent, CURLOPT_CAINFO, 'C:\Program Files\Ampps\php82\extras\ssl\cacert.pem');
$studentResponse = curl_exec($chStudent);
$studentHttpCode = curl_getinfo($chStudent, CURLINFO_HTTP_CODE);
$studentError = curl_error($chStudent);
curl_close($chStudent);

$initialAccountBalance = 0;
if ($studentHttpCode >= 200 && $studentHttpCode < 300) {
    $studentData = json_decode($studentResponse, true);
    if (json_last_error() === JSON_ERROR_NONE && !empty($studentData)) {
        $initialAccountBalance = floatval($studentData[0]['initial_account_balance'] ?? 0);
    } else {
        error_log("Student not found or initial_account_balance missing for student ID: " . $studentId);
        echo json_encode(['success' => false, 'message' => 'Student not found or initial balance missing for recalculation.']);
        exit();
    }
} else {
    error_log("Error fetching student initial balance: HTTP " . $studentHttpCode . " - " . ($studentError ?: $studentResponse));
    echo json_encode(['success' => false, 'message' => 'Error fetching student data for balance recalculation.']);
    exit();
}

// 3. Fetch ALL payments for the student to recalculate total paid
$fetchAllPaymentsApiUrl = $supabaseUrl . '/rest/v1/' . $supabasePaymentsTable . '?select=payment_amount&student_id=eq.' . urlencode($studentId);
$chAllPayments = curl_init($fetchAllPaymentsApiUrl);
curl_setopt($chAllPayments, CURLOPT_RETURNTRANSFER, true);
curl_setopt($chAllPayments, CURLOPT_HTTPHEADER, [
    'apikey: ' . $supabaseAnonKey,
    'Authorization: Bearer ' . $supabaseAnonKey
]);
curl_setopt($chAllPayments, CURLOPT_CAINFO, 'C:\Program Files\Ampps\php82\extras\ssl\cacert.pem');
$allPaymentsResponse = curl_exec($chAllPayments);
$allPaymentsHttpCode = curl_getinfo($chAllPayments, CURLINFO_HTTP_CODE);
$allPaymentsError = curl_error($chAllPayments);
curl_close($chAllPayments);

$totalPaymentsMade = 0;
if ($allPaymentsHttpCode >= 200 && $allPaymentsHttpCode < 300) {
    $allStudentPayments = json_decode($allPaymentsResponse, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($allStudentPayments)) {
        foreach ($allStudentPayments as $payment) {
            $totalPaymentsMade += floatval($payment['payment_amount'] ?? 0);
        }
    } else {
        error_log("Error decoding all payments for student ID: " . $studentId . " - " . json_last_error_msg());
        // Continue, current_balance will be based only on initial_account_balance
    }
} else {
    error_log("Error fetching all payments for total calculation: HTTP " . $allPaymentsHttpCode . " - " . ($allPaymentsError ?: $allPaymentsResponse));
    // Continue, current_balance will be based only on initial_account_balance
}

// 4. Calculate new current balance and account status
$newCurrentBalance = $initialAccountBalance - $totalPaymentsMade;
$newAccountStatus = ($newCurrentBalance <= 0.001) ? 'Fully Paid' : 'Partial Payment'; // Use a small epsilon for floating point comparison

// 5. Update student's current_balance and account_status in Supabase
$updateStudentData = [
    'current_balance' => $newCurrentBalance,
    'account_status' => $newAccountStatus
];

$updateStudentApiUrl = $supabaseUrl . '/rest/v1/' . $supabaseStudentsTable . '?id=eq.' . urlencode($studentId);
$chUpdateStudent = curl_init($updateStudentApiUrl);
curl_setopt($chUpdateStudent, CURLOPT_RETURNTRANSFER, true);
curl_setopt($chUpdateStudent, CURLOPT_CUSTOMREQUEST, 'PATCH');
curl_setopt($chUpdateStudent, CURLOPT_POSTFIELDS, json_encode($updateStudentData));
curl_setopt($chUpdateStudent, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'apikey: ' . $supabaseAnonKey, // Make sure your RLS allows updates with anon key or use a service key
    'Authorization: Bearer ' . $supabaseAnonKey
]);
curl_setopt($chUpdateStudent, CURLOPT_CAINFO, 'C:\Program Files\Ampps\php82\extras\ssl\cacert.pem');
$updateStudentResponse = curl_exec($chUpdateStudent);
$updateStudentHttpCode = curl_getinfo($chUpdateStudent, CURLINFO_HTTP_CODE);
$updateStudentError = curl_error($chUpdateStudent);
curl_close($chUpdateStudent);

if ($updateStudentHttpCode >= 200 && $updateStudentHttpCode < 300) {
    echo json_encode(['success' => true, 'message' => 'Payment and student balance updated successfully!']);
} else {
    error_log("Failed to update student balance/status: HTTP " . $updateStudentHttpCode . " - " . ($updateStudentError ?: $updateStudentResponse));
    echo json_encode(['success' => false, 'message' => 'Payment updated, but failed to update student balance/status.']);
}
?>