<?php
session_start();

// Check if the user is logged in
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    // If not logged in, redirect to the login page
    header('Location: login.php');
    exit();
}


// Supabase Configuration - Make sure these match your view_students.php
$supabaseUrl = 'https://tihodezxfrpjtpratrez.supabase.co';
$supabaseAnonKey = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InRpaG9kZXp4ZnJwanRwcmF0cmV6Iiwicm9sZSI6ImFub24iLCJpYXQiOjE3NDcyOTkyODksImV4cCI6MjA2Mjg3NTI4OX0.JU2jbQYhjjq90wrDi35LNr9AWKpqdvJtaO_JDgVR_JM';
$supabaseTable = 'students_account';

header('Content-Type: application/json'); // Set header for JSON response

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect and sanitize input
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $lastName = filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_STRING);
    $givenName = filter_input(INPUT_POST, 'given_name', FILTER_SANITIZE_STRING);
    $middleInitial = filter_input(INPUT_POST, 'middle_initial', FILTER_SANITIZE_STRING);
    $course = filter_input(INPUT_POST, 'course', FILTER_SANITIZE_STRING);
    $yearLevel = filter_input(INPUT_POST, 'year_level', FILTER_VALIDATE_INT);
    $semester = filter_input(INPUT_POST, 'semester', FILTER_VALIDATE_INT);
    $schoolYear = filter_input(INPUT_POST, 'school_year', FILTER_SANITIZE_STRING);
    $initialBalance = filter_input(INPUT_POST, 'initial_account_balance', FILTER_VALIDATE_FLOAT);

    // Basic validation
    if (!$id || !$lastName || !$givenName || !$course || !$yearLevel || !$semester || !$schoolYear || $initialBalance === false) {
        echo json_encode(['success' => false, 'message' => 'Invalid input data.']);
        exit();
    }

    // Prepare data for Supabase update
    $dataToUpdate = [
        'last_name' => $lastName,
        'given_name' => $givenName,
        'middle_initial' => $middleInitial,
        'course' => $course,
        'year_level' => $yearLevel,
        'semester' => $semester,
        'school_year' => $schoolYear,
        'initial_account_balance' => $initialBalance
    ];

    // Check if initial balance changed, and if so, update current_balance and account_status
    // This is a simplified logic. You might need more complex logic based on your payment system.
    $fetchCurrentStudentApiUrl = $supabaseUrl . '/rest/v1/' . $supabaseTable . '?select=initial_account_balance,current_balance&id=eq.' . $id;

    $chFetch = curl_init($fetchCurrentStudentApiUrl);
    curl_setopt($chFetch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($chFetch, CURLOPT_HTTPHEADER, [
        'apikey: ' . $supabaseAnonKey,
        'Authorization: Bearer ' . $supabaseAnonKey,
        'Content-Type: application/json'
    ]);
    curl_setopt($chFetch, CURLOPT_CAINFO, 'C:\Program Files\Ampps\php82\extras\ssl\cacert.pem');
    $responseFetch = curl_exec($chFetch);
    $httpCodeFetch = curl_getinfo($chFetch, CURLINFO_HTTP_CODE);
    curl_close($chFetch);

    $currentStudentData = [];
    if ($httpCodeFetch >= 200 && $httpCodeFetch < 300) {
        $currentStudentData = json_decode($responseFetch, true);
        if (!empty($currentStudentData)) {
            $currentStudentData = $currentStudentData[0]; // Assuming it returns an array with one object
        }
    }

    if (!empty($currentStudentData)) {
        $oldInitialBalance = $currentStudentData['initial_account_balance'];
        $oldCurrentBalance = $currentStudentData['current_balance'];

        // If the initial balance is changed, recalculate current balance relative to the new initial balance
        // and update account status. This assumes no payments have been made *against* the new initial balance yet.
        // This logic needs to be robust for your specific payment processing.
        if ($initialBalance != $oldInitialBalance) {
            // Option 1: Reset current balance to new initial balance (if no payments recorded)
            // $dataToUpdate['current_balance'] = $initialBalance;

            // Option 2: Adjust current balance based on the difference
            $balanceDifference = $initialBalance - $oldInitialBalance;
            $newCurrentBalance = $oldCurrentBalance + $balanceDifference;
            $dataToUpdate['current_balance'] = $newCurrentBalance;

            if ($newCurrentBalance <= 0) {
                $dataToUpdate['account_status'] = 'Fully Paid';
                $dataToUpdate['current_balance'] = 0; // Ensure it's not negative
            } else {
                $dataToUpdate['account_status'] = 'With Balance';
            }
        }
    } else {
        // If we can't fetch current data, set initial balance as current if no payments are considered
        $dataToUpdate['current_balance'] = $initialBalance;
        $dataToUpdate['account_status'] = ($initialBalance <= 0) ? 'Fully Paid' : 'With Balance';
    }


    $apiUrl = $supabaseUrl . '/rest/v1/' . $supabaseTable . '?id=eq.' . $id;

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH'); // Use PATCH for partial updates
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dataToUpdate));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . $supabaseAnonKey,
        'Authorization: Bearer ' . $supabaseAnonKey,
        'Content-Type: application/json',
        'Prefer: return=representation' // To get the updated record back in the response
    ]);
    curl_setopt($ch, CURLOPT_CAINFO, 'C:\Program Files\Ampps\php82\extras\ssl\cacert.pem'); // Ensure this path is correct

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($httpCode >= 200 && $httpCode < 300) {
        // Successfully updated
        echo json_encode(['success' => true, 'message' => 'Student updated successfully!']);
    } else {
        // Error handling
        $errorMessage = "Failed to update student: HTTP " . $httpCode;
        if ($error) {
            $errorMessage .= " - cURL Error: " . $error;
        } else {
            $responseArray = json_decode($response, true);
            $errorMessage .= " - Supabase Error: " . json_encode($responseArray);
        }
        echo json_encode(['success' => false, 'message' => $errorMessage]);
    }
} else {
    // Not a POST request
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>