<?php
session_start();

// Check if the user is logged in
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    // If not logged in, redirect to the login page
    header('Location: login.php');
    exit();
}

header('Content-Type: application/json');


$supabaseUrl = 'https://tihodezxfrpjtpratrez.supabase.co';
$supabaseAnonKey = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InRpaG9kZXp4ZnJwanRwcmF0cmV6Iiwicm9sZSI6ImFub24iLCJpYXQiOjE3NDcyOTkyODksImV4cCI6MjA2Mjg3NTI4OX0.JU2jbQYhjjq90wrDi35LNr9AWKpqdvJtaO_JDgVR_JM';
$supabaseTable = 'students_account';

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $student_id = filter_input(INPUT_POST, 'id', FILTER_SANITIZE_STRING);
    $last_name = filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_STRING);
    $given_name = filter_input(INPUT_POST, 'given_name', FILTER_SANITIZE_STRING);
    $middle_initial = filter_input(INPUT_POST, 'middle_initial', FILTER_SANITIZE_STRING);
    $course = filter_input(INPUT_POST, 'course', FILTER_SANITIZE_STRING);
    
    $year_level = filter_input(INPUT_POST, 'year_level', FILTER_VALIDATE_INT);
    $semester = filter_input(INPUT_POST, 'semester', FILTER_VALIDATE_INT);
    $school_year = filter_input(INPUT_POST, 'school_year', FILTER_SANITIZE_STRING);

    if (empty($student_id)) {
        $response['message'] = 'Student ID is empty.';
        echo json_encode($response);
        exit;
    }

    
    $checkDuplicateApiUrl = $supabaseUrl . '/rest/v1/' . $supabaseTable .
                            '?last_name=eq.' . urlencode($last_name) .
                            '&given_name=eq.' . urlencode($given_name) .
                            '&middle_initial=eq.' . urlencode($middle_initial) .
                            '&id=neq.' . urlencode($student_id); 

    $ch_check = curl_init($checkDuplicateApiUrl);
    curl_setopt($ch_check, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch_check, CURLOPT_HTTPHEADER, [
        'apikey: ' . $supabaseAnonKey,
        'Authorization: Bearer ' . $supabaseAnonKey,
        'Content-Type: application/json',
        'Prefer: return=minimal'
    ]);
    curl_setopt($ch_check, CURLOPT_CAINFO, 'C:\Program Files\Ampps\php82\extras\ssl\cacert.pem');

    $checkResponse = curl_exec($ch_check);
    $checkHttpCode = curl_getinfo($ch_check, CURLINFO_HTTP_CODE);
    curl_close($ch_check);

    
    $duplicateStudents = json_decode($checkResponse, true);

    if ($checkHttpCode >= 200 && $checkHttpCode < 300 && !empty($duplicateStudents)) {
        
        $response['message'] = 'A student with this exact full name already exists. Please use a unique name.';
        echo json_encode($response);
        exit; 
    }
    


    
    $updateData = [];
    if ($last_name !== null) $updateData['last_name'] = $last_name;
    if ($given_name !== null) $updateData['given_name'] = $given_name;
    if ($middle_initial !== null) $updateData['middle_initial'] = $middle_initial;
    if ($course !== null) $updateData['course'] = $course;
    
    if ($year_level !== false && $year_level !== null) $updateData['year_level'] = $year_level;
    if ($semester !== false && $semester !== null) $updateData['semester'] = $semester;
    if ($school_year !== null) $updateData['school_year'] = $school_year;

    if (empty($updateData)) {
        $response['message'] = 'No data provided for update.';
        echo json_encode($response);
        exit;
    }

    
    $apiUrl = $supabaseUrl . '/rest/v1/' . $supabaseTable . '?id=eq.' . $student_id; 

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH'); 
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($updateData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . $supabaseAnonKey,
        'Authorization: Bearer ' . $supabaseAnonKey,
        'Content-Type: application/json',
        'Prefer: return=minimal' 
    ]);
    curl_setopt($ch, CURLOPT_CAINFO, 'C:\Program Files\Ampps\php82\extras\ssl\cacert.pem'); 

    $apiResponse = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($httpCode >= 200 && $httpCode < 300) {
        $response['success'] = true;
        $response['message'] = 'Updated successfully.';
    } else {
        $response['message'] = "Error updating student: HTTP " . $httpCode . " - " . ($error ?: $apiResponse);
    }
} else {
    $response['message'] = 'Invalid request method.';
}

echo json_encode($response);
?>