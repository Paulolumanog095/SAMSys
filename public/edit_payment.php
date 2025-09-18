<?php
session_start();

// Check if the user is logged in
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    // If not logged in, redirect to the login page
    header('Location: login.php');
    exit();
}

error_log("edit_payment.php: Script started."); // Log start

// Supabase Configuration
$supabaseUrl = 'https://tihodezxfrpjtpratrez.supabase.co';
$supabaseAnonKey = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InRpaG9kZXp4ZnJwanRwcmF0cmV6Iiwicm9sZSI6ImFub24iLCJpYXQiOjE3NDcyOTkyODksImV4cCI6MjA2Mjg3NTI4OX0.JU2jbQYhjjq90wrDi35LNr9AWKpqdvJtaO_JDgVR_JM';
$supabaseStudentsTable = 'students_account';
$supabasePaymentsTable = 'payments';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("edit_payment.php: POST request received.");

    $paymentId = $_POST['payment_id'] ?? null;
    $studentId = $_POST['student_id'] ?? null;
    $newPaymentDate = $_POST['payment_date'] ?? null;
    $newORNumber = $_POST['or_number'] ?? null;
    $newPaymentAmount = $_POST['payment_amount'] ?? null;

    error_log("edit_payment.php: Received data - paymentId: {$paymentId}, studentId: {$studentId}, newPaymentAmount: {$newPaymentAmount}");

    // Validate inputs - basic checks
    if (empty($paymentId) || empty($studentId) || empty($newPaymentDate) || empty($newORNumber) || !isset($newPaymentAmount)) {
        http_response_code(400); // Bad Request
        echo json_encode(['success' => false, 'message' => 'Missing required payment data.']);
        error_log("edit_payment.php: Error - Missing required data.");
        exit();
    }

    $newPaymentAmount = floatval($newPaymentAmount);
    // Ensure the new payment amount is rounded to 2 decimal places for consistency
    $newPaymentAmount = round($newPaymentAmount, 2);
    error_log("edit_payment.php: newPaymentAmount (float, rounded): {$newPaymentAmount}");

    if ($newPaymentAmount < 0) {
        http_response_code(400); // Bad Request
        echo json_encode(['success' => false, 'message' => 'Payment amount cannot be negative.']);
        error_log("edit_payment.php: Error - Negative payment amount.");
        exit();
    }

    // --- STEP 1: Fetch the original payment record's amount to calculate the difference ---
    $fetchPaymentApiUrl = $supabaseUrl . '/rest/v1/' . $supabasePaymentsTable . '?select=payment_amount&id=eq.' . urlencode($paymentId);
    error_log("edit_payment.php: Fetching original payment from: " . $fetchPaymentApiUrl);
    $chPayment = curl_init($fetchPaymentApiUrl);
    curl_setopt($chPayment, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($chPayment, CURLOPT_HTTPHEADER, [
        'apikey: ' . $supabaseAnonKey,
        'Authorization: Bearer ' . $supabaseAnonKey
    ]);
    curl_setopt($chPayment, CURLOPT_CAINFO, 'C:\Program Files\Ampps\php82\extras\ssl\cacert.pem');
    $paymentResponse = curl_exec($chPayment);
    $paymentHttpCode = curl_getinfo($chPayment, CURLINFO_HTTP_CODE);
    $paymentError = curl_error($chPayment);
    curl_close($chPayment);

    error_log("edit_payment.php: Original Payment Fetch Response - HTTP: {$paymentHttpCode}, Error: {$paymentError}, Data: " . ($paymentResponse ? substr($paymentResponse, 0, 200) : "N/A"));

    if ($paymentHttpCode >= 200 && $paymentHttpCode < 300) {
        $paymentData = json_decode($paymentResponse, true);
        if (json_last_error() !== JSON_ERROR_NONE || empty($paymentData)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Payment not found or error decoding original payment data.']);
            error_log("edit_payment.php: Error - Payment not found or JSON decode error for original payment.");
            exit();
        }
        $actualOriginalPaymentAmount = floatval($paymentData[0]['payment_amount'] ?? 0);
        // Ensure original amount is also rounded for consistent calculations
        $actualOriginalPaymentAmount = round($actualOriginalPaymentAmount, 2);
        error_log("edit_payment.php: actualOriginalPaymentAmount (rounded): {$actualOriginalPaymentAmount}");
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => "Error fetching original payment details: HTTP " . $paymentHttpCode . " - " . ($paymentError ?: $paymentResponse)]);
        error_log("edit_payment.php: Error fetching original payment: " . ($paymentError ?: $paymentResponse));
        exit();
    }

    // --- STEP 2: Fetch current student details to get the current_balance for validation ---
    $fetchStudentApiUrl = $supabaseUrl . '/rest/v1/' . $supabaseStudentsTable . '?select=current_balance&id=eq.' . urlencode($studentId);
    error_log("edit_payment.php: Fetching student balance from: " . $fetchStudentApiUrl);
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

    error_log("edit_payment.php: Student Balance Fetch Response - HTTP: {$studentHttpCode}, Error: {$studentError}, Data: " . ($studentResponse ? substr($studentResponse, 0, 200) : "N/A"));

    if ($studentHttpCode >= 200 && $studentHttpCode < 300) {
        $studentData = json_decode($studentResponse, true);
        if (json_last_error() !== JSON_ERROR_NONE || empty($studentData)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Student not found or error decoding student data.']);
            error_log("edit_payment.php: Error - Student not found or JSON decode error for student balance.");
            exit();
        }
        $currentBalance = floatval($studentData[0]['current_balance'] ?? 0);
        // Ensure current balance is also rounded for consistent calculations
        $currentBalance = round($currentBalance, 2);
        error_log("edit_payment.php: currentBalance (rounded): {$currentBalance}");
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => "Error fetching student current balance: HTTP " . $studentHttpCode . " - " . ($studentError ?: $studentResponse)]);
        error_log("edit_payment.php: Error fetching student balance: " . ($studentError ?: $studentResponse));
        exit();
    }

    // --- STEP 3: Implement the "payment exceeds remaining" validation logic ---
    // paymentAmountDifference is the change in the payment amount
    // If newPaymentAmount is 120 and original was 130, diff = -10
    // If newPaymentAmount is 140 and original was 130, diff = 10
    $paymentAmountDifference = $newPaymentAmount - $actualOriginalPaymentAmount;

    // projectedNewBalance is the currentBalance adjusted by the *difference*
    // If payment amount increased (diff is positive), balance should decrease.
    // If payment amount decreased (diff is negative), balance should increase.
    $projectedNewBalance = $currentBalance - $paymentAmountDifference;

    // IMPORTANT: Round the projected new balance immediately
    $projectedNewBalance = round($projectedNewBalance, 2);

    error_log("edit_payment.php: newPaymentAmount: {$newPaymentAmount}, actualOriginalPaymentAmount: {$actualOriginalPaymentAmount}, paymentAmountDifference: {$paymentAmountDifference}, currentBalance: {$currentBalance}, projectedNewBalance: {$projectedNewBalance}");

    // Use a small epsilon for floating-point comparison, e.g., 0.01 for currency.
    $epsilon = 0.01;

    // Validation for overpayment (projected new balance becomes negative)
    if ($projectedNewBalance < -$epsilon) {
        http_response_code(400); // Bad Request / Unprocessable Entity
        echo json_encode([
            'success' => false,
            'message' => 'Payment amount (₱' . number_format($newPaymentAmount, 2) . ') would result in an overpayment. Projected new balance: ₱' . number_format($projectedNewBalance, 2) . '.'
        ]);
        error_log("edit_payment.php: Validation failed - Payment exceeds remaining balance. Projected: {$projectedNewBalance}");
        exit();
    }
    error_log("edit_payment.php: Validation passed. Proceeding with update.");

    // --- STEP 4: Update the payment record in the 'payments' table (only if validation passes) ---
    $updatePaymentApiUrl = $supabaseUrl . '/rest/v1/' . $supabasePaymentsTable . '?id=eq.' . urlencode($paymentId);
    $paymentPayload = json_encode([
        'payment_date' => $newPaymentDate,
        'or_number' => $newORNumber,
        'payment_amount' => $newPaymentAmount,
        'payment_time' => date('H:i:s')
    ]);

    error_log("edit_payment.php: Updating payment record: " . $updatePaymentApiUrl . ", Payload: " . $paymentPayload);
    $chUpdatePayment = curl_init($updatePaymentApiUrl);
    curl_setopt($chUpdatePayment, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($chUpdatePayment, CURLOPT_CUSTOMREQUEST, 'PATCH');
    curl_setopt($chUpdatePayment, CURLOPT_POSTFIELDS, $paymentPayload);
    curl_setopt($chUpdatePayment, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'apikey: ' . $supabaseAnonKey,
        'Authorization: Bearer ' . $supabaseAnonKey,
        'Prefer: return=representation'
    ]);
    curl_setopt($chUpdatePayment, CURLOPT_CAINFO, 'C:\Program Files\Ampps\php82\extras\ssl\cacert.pem');
    $updatePaymentResponse = curl_exec($chUpdatePayment);
    $updatePaymentHttpCode = curl_getinfo($chUpdatePayment, CURLINFO_HTTP_CODE);
    $updatePaymentError = curl_error($chUpdatePayment);
    curl_close($chUpdatePayment);

    error_log("edit_payment.php: Payment Update Response - HTTP: {$updatePaymentHttpCode}, Error: {$updatePaymentError}, Data: " . ($updatePaymentResponse ? substr($updatePaymentResponse, 0, 200) : "N/A"));

    if ($updatePaymentHttpCode >= 200 && $updatePaymentHttpCode < 300) {
        // Payment updated successfully. Now update student balance.

        // Determine account_status based on the *rounded* projected new balance
        $accountStatus = ($projectedNewBalance <= $epsilon && $projectedNewBalance >= -$epsilon) ? 'Fully Paid' : 'With Balance';

        // Add a log to see the determined status before update
        error_log("edit_payment.php: Determined Account Status: {$accountStatus} for projectedNewBalance: {$projectedNewBalance}");

        // --- STEP 5: Update the student's account balance and status ---
        $updateStudentApiUrl = $supabaseUrl . '/rest/v1/' . $supabaseStudentsTable . '?id=eq.' . urlencode($studentId);
        $studentPayload = json_encode([
            'current_balance' => $projectedNewBalance, // Use the already rounded projected balance
            'account_status' => $accountStatus
        ]);

        error_log("edit_payment.php: Updating student balance: " . $updateStudentApiUrl . ", Payload: " . $studentPayload);
        $chUpdateStudent = curl_init($updateStudentApiUrl);
        curl_setopt($chUpdateStudent, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($chUpdateStudent, CURLOPT_CUSTOMREQUEST, 'PATCH');
        curl_setopt($chUpdateStudent, CURLOPT_POSTFIELDS, $studentPayload);
        curl_setopt($chUpdateStudent, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'apikey: ' . $supabaseAnonKey,
            'Authorization: Bearer ' . $supabaseAnonKey
        ]);
        curl_setopt($chUpdateStudent, CURLOPT_CAINFO, 'C:\Program Files\Ampps\php82\extras\ssl\cacert.pem');
        $updateStudentResponse = curl_exec($chUpdateStudent);
        $updateStudentHttpCode = curl_getinfo($chUpdateStudent, CURLINFO_HTTP_CODE);
        $updateStudentError = curl_error($chUpdateStudent);
        curl_close($chUpdateStudent);

        error_log("edit_payment.php: Student Update Response - HTTP: {$updateStudentHttpCode}, Error: {$updateStudentError}, Data: " . ($updateStudentResponse ? substr($updateStudentResponse, 0, 200) : "N/A"));

        if ($updateStudentHttpCode >= 200 && $updateStudentHttpCode < 300) {
            echo json_encode(['success' => true, 'message' => 'Payment updated successfully.']);
            error_log("edit_payment.php: Success - Payment and student balance updated.");
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => "Error updating student balance after payment update: HTTP " . $updateStudentHttpCode . " - " . ($updateStudentError ?: $updateStudentResponse)]);
            error_log("edit_payment.php: Error - Student balance update failed: " . ($updateStudentError ?: $updateStudentResponse));
        }
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => "Error updating payment record: HTTP " . $updatePaymentHttpCode . " - " . ($updatePaymentError ?: $updatePaymentResponse)]);
        error_log("edit_payment.php: Error - Payment record update failed: " . ($updatePaymentError ?: $updatePaymentResponse));
    }

} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    error_log("edit_payment.php: Error - Invalid request method.");
}
?>