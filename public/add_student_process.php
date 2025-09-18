<?php
session_start();

// Check if the user is logged in
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    // If not logged in, redirect to the login page
    header('Location: login.php');
    exit();
}

// Set the content type to JSON for the response
header('Content-Type: application/json');

// --- Supabase Configuration (IMPORTANT: Replace with your actual details) ---
$supabaseUrl = 'https://tihodezxfrpjtpratrez.supabase.co'; // e.g., 'https://abcdefg.supabase.co'
$supabaseAnonKey = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InRpaG9kZXp4ZnJwanRwcmF0cmV6Iiwicm9sZSI6ImFub24iLCJpYXQiOjE3NDcyOTkyODksImV4cCI6MjA2Mjg3NTI4OX0.JU2jbQYhjjq90wrDi35LNr9AWKpqdvJtaO_JDgVR_JM'; // Your 'anon' key from Supabase Project Settings -> API

// The name of your table in Supabase
$supabaseTable = 'students_account'; // <--- Ensure this matches your Supabase table

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Sanitize and Validate Input from the form
    $last_name = filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $given_name = filter_input(INPUT_POST, 'given_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $middle_initial = filter_input(INPUT_POST, 'middle_initial', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $course = filter_input(INPUT_POST, 'course', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $year_level = filter_input(INPUT_POST, 'year_level', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $semester = filter_input(INPUT_POST, 'semester', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $school_year = filter_input(INPUT_POST, 'school_year', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $initial_account_balance_str = filter_input(INPUT_POST, 'initial_account_balance', FILTER_SANITIZE_FULL_SPECIAL_CHARS); // Get as string first

    // Validate required fields
    if (empty($last_name) || empty($given_name) || empty($course) || empty($year_level) || empty($semester) || empty($school_year)) {
        $response['message'] = 'Please fill in all required student details.';
        echo json_encode($response);
        exit();
    }

    // Sanitize and validate initial_account_balance
    $initial_account_balance_cleaned = str_replace(',', '', $initial_account_balance_str);
    $initial_account_balance = filter_var($initial_account_balance_cleaned, FILTER_VALIDATE_FLOAT);

    if ($initial_account_balance === false) {
        $initial_account_balance = 0.00; // Default to 0 if invalid or empty
    }

    // Determine account status
    $account_status = ($initial_account_balance <= 0) ? 'Fully Paid' : 'With Balance';

    // Prepare data for Supabase
    $dataToInsert = [
        'last_name' => $last_name,
        'given_name' => $given_name,
        'middle_initial' => ($middle_initial === '') ? null : $middle_initial,
        'course' => ($course === '') ? null : $course,
        'year_level' => ($year_level === '') ? null : $year_level,
        'semester' => ($semester === '') ? null : $semester,
        'school_year' => ($school_year === '') ? null : $school_year,
        'initial_account_balance' => $initial_account_balance,
        'current_balance' => $initial_account_balance, // Current balance starts as initial
        'account_status' => $account_status,
        // No 'created_at', 'updated_at' needed if Supabase handles timestamps
    ];

    // Supabase API URL for insertion with conflict handling (upsert)
    // Assuming you have a UNIQUE constraint on (last_name, given_name, middle_initial) in Supabase
    $apiUrl = $supabaseUrl . '/rest/v1/' . $supabaseTable . '?on_conflict=last_name,given_name,middle_initial';

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . $supabaseAnonKey,
        'Authorization: Bearer ' . $supabaseAnonKey,
        'Content-Type: application/json',
        'Prefer: return=representation' // To get the inserted/updated record back
    ]);
    // Set CA info for SSL verification (CRUCIAL FOR WINDOWS, adjust path if needed)
    curl_setopt($ch, CURLOPT_CAINFO, 'C:\Program Files\Ampps\php82\extras\ssl\cacert.pem');

    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dataToInsert));

    $supabaseResponse = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        error_log("cURL Error in add_student_process.php: " . $curlError);
        $response['message'] = 'Server communication error: ' . $curlError;
    } else if ($httpCode >= 200 && $httpCode < 300) {
        $responseData = json_decode($supabaseResponse, true);
        if (!empty($responseData)) {
            $response['success'] = true;
            $response['message'] = 'Student added successfully!';
            // You can also return the newly added student's ID or full data if needed
            // $response['student_data'] = $responseData[0];
        } else {
            // This happens if Supabase returns 2xx but no data, likely due to on_conflict matching
            $response['success'] = false; // Still a "success" from the user's perspective, as the data exists
            $response['message'] = 'Student with this name already exists and was not duplicated.';
        }
    } else if ($httpCode == 409) { // HTTP 409 Conflict indicates a duplicate entry
        $response['success'] = false; // Treat as success from user's perspective (no error, just skipped)
        $response['message'] = 'Student with this name already exists.';
        // Optionally, you might want to fetch and return the existing record or offer an update option
    }
    else {
        $response['message'] = 'Failed to add student. Supabase responded with HTTP ' . $httpCode . '. Response: ' . $supabaseResponse;
        error_log("Supabase Error in add_student_process.php: HTTP " . $httpCode . ", Response: " . $supabaseResponse);
    }

} else {
    $response['message'] = 'Invalid request method. Only POST is allowed.';
}

echo json_encode($response);
exit();
?>