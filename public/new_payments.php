<?php
session_start();

// Check if the user is logged in
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    // If not logged in, redirect to the login page
    header('Location: login.php');
    exit();
}


// Supabase Configuration
$supabaseUrl = 'https://tihodezxfrpjtpratrez.supabase.co';
$supabaseAnonKey = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InRpaG9kZXp4ZnJwanRwcmF0cmV6Iiwicm9sZSI6ImFub24iLCJpYXQiOjE3NDcyOTkyODksImV4cCI6MjA2Mjg3NTI4OX0.JU2jbQYhjjq90wrDi35LNr9AWKpqdvJtaO_JDgVR_JM';
$supabaseTable = 'students_account';
$paymentsTable = 'payments'; // Assuming you have a 'payments' table

$students = [];
$selectedStudent = null;

// Get filter values from GET request
$givenNameFilter = $_GET['given_name_filter'] ?? ''; // New filter for given name
$courseFilter = $_GET['course_filter'] ?? '';
$yearLevelFilter = $_GET['year_level_filter'] ?? '';
// Removed: $schoolYearFilter = $_GET['school_year_filter'] ?? '';
// Removed: $semesterFilter = $_GET['semester_filter'] ?? '';
$studentId = $_GET['student_id'] ?? null; // For selecting a specific student from results

// Handle form submission for adding a payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_payment'])) {
    $student_id_for_payment = $_POST['student_id'];
    $payment_amount = filter_var($_POST['payment_amount'], FILTER_VALIDATE_FLOAT);
    $payment_date = $_POST['payment_date'];
    $payment_description = htmlspecialchars($_POST['payment_description']);

    if ($payment_amount === false || $payment_amount <= 0) {
        $_SESSION['message'] = "Invalid payment amount. Please enter a positive number.";
        $_SESSION['message_type'] = 'error';
    } else {
        // First, fetch the current balance of the student
        $studentApiUrl = $supabaseUrl . '/rest/v1/' . $supabaseTable . '?id=eq.' . $student_id_for_payment . '&select=current_balance';
        $ch = curl_init($studentApiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'apikey: ' . $supabaseAnonKey,
            'Authorization: Bearer ' . $supabaseAnonKey,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_CAINFO, 'C:\Program Files\Ampps\php82\extras\ssl\cacert.pem'); // Ensure this path is correct

        $studentResponse = curl_exec($ch);
        $studentHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $currentBalance = null;
        if ($studentHttpCode >= 200 && $studentHttpCode < 300) {
            $studentData = json_decode($studentResponse, true);
            if (!empty($studentData) && isset($studentData[0]['current_balance'])) {
                $currentBalance = $studentData[0]['current_balance'];
            }
        }

        if ($currentBalance !== null) {
            $newBalance = $currentBalance - $payment_amount;
            $accountStatus = ($newBalance <= 0) ? 'Fully Paid' : 'With Balance';

            // Insert new payment record
            $paymentData = [
                'student_id' => (int)$student_id_for_payment,
                'payment_amount' => $payment_amount,
                'payment_date' => $payment_date,
                'description' => $payment_description,
                'balance_before_payment' => $currentBalance,
                'balance_after_payment' => $newBalance
            ];

            $ch = curl_init($supabaseUrl . '/rest/v1/' . $paymentsTable);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($paymentData));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'apikey: ' . $supabaseAnonKey,
                'Authorization: Bearer ' . $supabaseAnonKey,
                'Content-Type: application/json',
                'Prefer: return=representation'
            ]);
            curl_setopt($ch, CURLOPT_CAINFO, 'C:\Program Files\Ampps\php82\extras\ssl\cacert.pem');

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($httpCode >= 200 && $httpCode < 300) {
                // Update student's current balance and account status
                $updateData = [
                    'current_balance' => $newBalance,
                    'account_status' => $accountStatus
                ];

                $ch = curl_init($supabaseUrl . '/rest/v1/' . $supabaseTable . '?id=eq.' . $student_id_for_payment);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH'); // Use PATCH for updates
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($updateData));
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'apikey: ' . $supabaseAnonKey,
                    'Authorization: Bearer ' . $supabaseAnonKey,
                    'Content-Type: application/json',
                    'Prefer: return=representation'
                ]);
                curl_setopt($ch, CURLOPT_CAINFO, 'C:\Program Files\Ampps\php82\extras\ssl\cacert.pem');

                $updateResponse = curl_exec($ch);
                $updateHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $updateError = curl_error($ch);
                curl_close($ch);

                if ($updateHttpCode >= 200 && $updateHttpCode < 300) {
                    $_SESSION['message'] = "Payment added and student balance updated successfully!";
                    $_SESSION['message_type'] = 'success';
                    // Redirect to prevent form resubmission
                    header("Location: add_payment.php?student_id=" . $student_id_for_payment); // Changed to add_payment.php
                    exit();
                } else {
                    $_SESSION['message'] = "Payment added, but failed to update student balance: HTTP " . $updateHttpCode . " - " . ($updateError ?: $updateResponse);
                    $_SESSION['message_type'] = 'error';
                }
            } else {
                $_SESSION['message'] = "Error adding payment to Supabase: HTTP " . $httpCode . " - " . ($error ?: $response);
                $_SESSION['message_type'] = 'error';
            }
        } else {
            $_SESSION['message'] = "Error: Could not retrieve current balance for student.";
            $_SESSION['message_type'] = 'error';
        }
    }
}

// Fetch student data based on filters or student_id
// Build the API URL with filters
$apiUrl = $supabaseUrl . '/rest/v1/' . $supabaseTable . '?select=*';

// Apply filters for given_name and last_name using 'or'
if (!empty($givenNameFilter)) {
    // To search across both given_name and last_name, use Supabase's 'or' operator
    // The format is: or=(column1.ilike.value,column2.ilike.value)
    $encodedFilter = urlencode('%' . $givenNameFilter . '%');
    $apiUrl .= '&or=(given_name.ilike.' . $encodedFilter . ',last_name.ilike.' . $encodedFilter . ')';
}

if (!empty($courseFilter)) {
    $apiUrl .= '&course=eq.' . urlencode($courseFilter);
}
if (!empty($yearLevelFilter)) {
    $apiUrl .= '&year_level=eq.' . urlencode($yearLevelFilter);
}

// Order by last_name and given_name
$apiUrl .= '&order=last_name.asc,given_name.asc';

// If a specific student_id is provided, prioritize fetching that student
if (!empty($studentId)) {
    $apiUrl = $supabaseUrl . '/rest/v1/' . $supabaseTable . '?id=eq.' . urlencode($studentId) . '&select=*';
}

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'apikey: ' . $supabaseAnonKey,
    'Authorization: Bearer ' . $supabaseAnonKey,
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_CAINFO, 'C:\Program Files\Ampps\php82\extras\ssl\cacert.pem'); // Ensure this path is correct

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($httpCode >= 200 && $httpCode < 300) {
    $fetchedData = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $_SESSION['message'] = "Error decoding JSON from Supabase: " . json_last_error_msg();
        $_SESSION['message_type'] = 'error';
        $students = [];
    } else {
        if (!empty($studentId) && !empty($fetchedData)) {
            $selectedStudent = $fetchedData[0]; // Set the selected student if ID was provided
        } else {
            $students = $fetchedData; // Set all fetched students if no ID was provided (for filter results)
        }
    }
} else {
    $_SESSION['message'] = "Error fetching students from Supabase: HTTP " . $httpCode . " - " . ($error ?: $response);
    $_SESSION['message_type'] = 'error';
}

// Fetch all unique values for dropdowns (Course, Year Level)
$allStudentsForFilters = [];
$apiUrlAll = $supabaseUrl . '/rest/v1/' . $supabaseTable . '?select=course,year_level'; // Only fetch necessary columns
$chAll = curl_init($apiUrlAll);
curl_setopt($chAll, CURLOPT_RETURNTRANSFER, true);
curl_setopt($chAll, CURLOPT_HTTPHEADER, [
    'apikey: ' . $supabaseAnonKey,
    'Authorization: Bearer ' . $supabaseAnonKey,
    'Content-Type: application/json'
]);
curl_setopt($chAll, CURLOPT_CAINFO, 'C:\Program Files\Ampps\php82\extras\ssl\cacert.pem');
$responseAll = curl_exec($chAll);
curl_close($chAll);

$uniqueCourses = [];
$uniqueYearLevels = [];
// Removed: $uniqueSchoolYears = [];
// Removed: $uniqueSemesters = [];

if (curl_getinfo($chAll, CURLINFO_HTTP_CODE) >= 200 && curl_getinfo($chAll, CURLINFO_HTTP_CODE) < 300) {
    $allStudentsForFilters = json_decode($responseAll, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        foreach ($allStudentsForFilters as $student) {
            if (!empty($student['course'])) $uniqueCourses[htmlspecialchars($student['course'])] = htmlspecialchars($student['course']);
            if (isset($student['year_level'])) $uniqueYearLevels[(int)$student['year_level']] = (int)$student['year_level'];
        }
        ksort($uniqueYearLevels);
        sort($uniqueCourses);
    }
}

$current_page = basename($_SERVER['PHP_SELF']);
$is_payments_active = in_array($current_page, ['add_payment.php', 'payments.php', 'view_payment_history.php', 'new_payments.php']); // Changed to add_payment.php
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SAMS - Student Account Management System</title>
    <link rel="icon" href="../logo/logo.png" type="image/png">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <link rel="stylesheet" href="Admin LTE/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="Admin LTE/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="sweetalert2/sweetalert2.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flowbite@1.4.0/dist/flowbite.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<style>
    /* Custom styles similar to view_students.php */
    .action-link {
        color: #3B82F6; /* Tailwind blue-500 for example */
        text-decoration: none;
        margin-right: 8px; /* Space between links */
    }
    .action-link:hover {
        text-decoration: underline;
    }

    #studentSearchResultsTable.dataTable thead th,
    #studentSearchResultsTable.dataTable tbody td {
        padding-top: 12px !important;
        padding-bottom: 12px !important;
        border: 1px solid #e2e8f0 !important;
    }

    #studentSearchResultsTable.dataTable {
        border-collapse: collapse !important;
        width: 100% !important;
    }

    .dataTables_wrapper .dataTables_length,
    .dataTables_wrapper .dataTables_filter {
        padding: 8px 16px !important;
        margin-bottom: 0px !important;
    }

    .dataTables_wrapper .dataTables_scrollBody,
    .dataTables_wrapper .dataTables_info {
        margin-top: 16px !important;
    }

    .dataTables_wrapper .dataTables_filter input,
    .dataTables_wrapper .dataTables_length select {
        padding: 6px 10px !important;
        border: 1px solid #d1d5db !important;
        border-radius: 0.25rem !important;
    }

    .dataTables_wrapper .dataTables_paginate .paginate_button {
        padding: 6px 12px !important;
        margin-left: 5px !important;
        border: 1px solid #d1d5db !important;
        border-radius: 0.25rem !important;
        background-color: #f9fafb !important;
    }

    .dataTables_wrapper .dataTables_paginate .paginate_button.current,
    .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
        background-color:rgb(52, 99, 16) !important;
        color: white !important;
        border-color:rgb(20, 85, 12) !important;
    }
            body {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc; /* Lighter background for a cleaner feel */
            color: #334155; /* Darker text for readability */
        }
    .content-header h1 {
        margin: 0;
        font-size: 20px;
    }
    .no-underline-hover:hover {
        text-decoration: none !important;
    }
</style>
<body class="bg-gray-100 font-inter">
    <div class="flex h-screen">

                <button data-drawer-target="default-sidebar" data-drawer-toggle="default-sidebar" aria-controls="default-sidebar" type="button" class="inline-flex items-center p-2 mt-2 ms-3 text-sm text-gray-500 rounded-lg sm:hidden hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-gray-200 dark:text-gray-400 dark:hover:bg-gray-700 dark:focus:ring-gray-600">
            <span class="sr-only">Open sidebar</span>
            <svg class="w-6 h-6" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                <path clip-rule="evenodd" fill-rule="evenodd" d="M2 4.75A.75.75 0 012.75 4h14.5a.75.75 0 010 1.5H2.75A.75.75 0 012 4.75zm0 10.5a.75.75 0 01.75-.75h7.5a.75.75 0 010 1.5h-7.5a.75.75 0 01-.75-.75zM2 10a.75.75 0 01.75-.75h14.5a.75.75 0 010 1.5H2.75A.75.75 0 012 10z"></path>
            </svg>
        </button>

        <aside id="default-sidebar" class="fixed top-0 left-0 z-40 w-64 h-screen transition-transform -translate-x-full sm:translate-x-0" aria-label="Sidebar">
            <div class="flex items-center justify-center mb-2 bg-green-900 p-4 rounded-b-xl shadow-md">
                <img src="../logo/logo.png" alt="CPSU Logo" class="h-16 w-16 object-contain mr-3">
                <div class="text-center">
                    <h1 class="text-2xl font-extrabold text-white">SAMS</h1>
                    <p class="text-xs text-green-100">Student Account Management System</p>
                </div>
            </div>
            <div class="h-full px-3 py-4 overflow-y-auto bg-white shadow-lg rounded-tr-lg">
                <ul class="space-y-2 font-medium">
                    <li>
                        <a href="../public/index.php" class="<?php echo ($current_page == 'index.php') ? 'text-green-700 bg-green-50 font-semibold shadow-sm' : 'text-gray-700 hover:bg-green-50 hover:text-green-700'; ?> flex items-center p-2 rounded-lg group transition duration-200">
                            <i class="<?php echo ($current_page == 'index.php') ? 'text-green-600' : 'text-gray-500 group-hover:text-green-700'; ?> fa-solid fa-gauge-high shrink-0 w-5 h-5 transition duration-75"></i>
                            <span class="ms-3">Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="view_students.php" class="<?php echo ($current_page == 'view_students.php') ? 'text-green-700 bg-green-50 font-semibold shadow-sm' : 'text-gray-700 hover:bg-green-50 hover:text-green-700'; ?> flex items-center p-2 rounded-lg group transition duration-200">
                            <i class="<?php echo ($current_page == 'view_students.php') ? 'text-green-600' : 'text-gray-500 group-hover:text-green-700'; ?> fa-solid fa-users shrink-0 w-5 h-5 transition duration-75"></i>
                            <span class="flex-1 ms-3 whitespace-nowrap">View Students</span>
                        </a>
                    </li>
                    <li>
                        <button type="button" class="flex items-center w-full p-2 text-base text-gray-700 transition duration-200 rounded-lg group <?php echo $is_payments_active ? 'text-green-700 bg-green-50 font-semibold shadow-sm' : 'hover:bg-green-50 hover:text-green-700'; ?>" aria-controls="dropdown-payments" data-collapse-toggle="dropdown-payments" <?php echo $is_payments_active ? 'aria-expanded="true"' : ''; ?>>
                            <i class="fa-solid fa-money-check-dollar shrink-0 w-5 h-5 transition duration-75 <?php echo $is_payments_active ? 'text-green-600' : 'text-gray-500 group-hover:text-green-700'; ?>"></i>
                            <span class="flex-1 ms-3 text-left rtl:text-right whitespace-nowrap">Payments</span>
                            <svg class="w-3 h-3 ms-2" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 10 6">
                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 4 4 4-4"/>
                            </svg>
                        </button>
                        <ul id="dropdown-payments" class="py-2 space-y-2 <?php echo $is_payments_active ? '' : 'hidden'; ?>">
                            <li>
                                <a href="new_payments.php" class="<?php echo ($current_page == 'new_payments.php') ? 'text-green-700 bg-green-50 font-semibold shadow-sm' : 'text-gray-700 hover:bg-green-50 hover:text-green-700'; ?> flex items-center w-full p-2 transition duration-200 rounded-lg pl-11 group">
                                    <i class="<?php echo ($current_page == 'new_payments.php') ? 'text-green-600' : 'text-gray-500 group-hover:text-green-700'; ?> fa-solid fa-plus-circle shrink-0 w-5 h-5 transition duration-75"></i>
                                    <span class="ms-3">Add Payment</span>
                                </a>
                            </li>
                            <li>
                                <a href="payments.php" class="<?php echo ($current_page == 'payments.php') ? 'text-green-700 bg-green-50 font-semibold shadow-sm' : 'text-gray-700 hover:bg-green-50 hover:text-green-700'; ?> flex items-center w-full p-2 transition duration-200 rounded-lg pl-11 group">
                                    <i class="<?php echo ($current_page == 'payments.php') ? 'text-green-600' : 'text-gray-500 group-hover:text-green-700'; ?> fa-solid fa-receipt shrink-0 w-5 h-5 transition duration-75"></i>
                                    <span class="ms-3">Payment List</span>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li>
                        <div class="flex items-center w-full p-2 text-xs font-bold text-gray-500 uppercase mt-4">
                            FORMS
                        </div>
                    </li>
                    <li>
                        <a href="addnew.php" class="<?php echo ($current_page == 'addnew.php') ? 'text-green-700 bg-green-50 font-semibold shadow-sm' : 'text-gray-700 hover:bg-green-50 hover:text-green-700'; ?> flex items-center p-2 rounded-lg group transition duration-200">
                            <i class="<?php echo ($current_page == 'addnew.php') ? 'text-green-600' : 'text-gray-500 group-hover:text-green-700'; ?> fa-solid fa-upload shrink-0 w-4 h-4 transition duration-75"></i>
                            <span class="flex-1 ms-3 whitespace-nowrap">Import Data</span>
                        </a>
                    </li>
                    <li>
                        <a href="logout.php" class="flex items-center p-2 rounded-lg text-gray-700 hover:bg-red-50 hover:text-red-700 group transition duration-200">
                            <i class="fas fa-sign-out-alt shrink-0 w-5 h-5 transition duration-75 text-gray-500 group-hover:text-red-700"></i>
                            <span class="ms-3">Logout</span>
                        </a>
                    </li>
                </ul>
            </div>
        </aside>

        <div class="p-1 sm:ml-64 flex-1 overflow-y-auto">
            <main class="flex-1 p-1 overflow-y-auto">
                <section class="content-header bg-green-900 p-8 rounded-t-lg mb-3">
                    <div class="row">
                        <div class="col-md-6 text-white font-semibold">
                            <h1>Add New Payment</h1>
                        </div>
                        <div class="col-sm-6">
                            <ol class="breadcrumb float-sm-right">
                                <li class="breadcrumb-item active text-white"></li>
                            </ol>
                        </div>
                    </div>
                </section>
                <section class="mt-3 p-3">

                    <?php
                    if (isset($_SESSION['message'])) {
                        $messageClass = $_SESSION['message_type'] ?? 'info';
                        echo '<div class="message ' . htmlspecialchars($messageClass) . ' p-3 mb-3 rounded-md text-sm ';
                        if ($messageClass == 'success') {
                            echo 'bg-green-100 text-green-800';
                        } elseif ($messageClass == 'error') {
                            echo 'bg-red-100 text-red-800';
                        } else {
                            echo 'bg-blue-100 text-blue-800';
                        }
                        echo '">' . htmlspecialchars($_SESSION['message']) . '</div>';
                        unset($_SESSION['message']);
                        unset($_SESSION['message_type']);
                    }
                    ?>

                    <div class="flex flex-col md:flex-row gap-6">
                        <div class="w-full md:w-1/4 bg-white p-6 rounded-lg shadow-md h-fit">
                            <h2 class="text-lg font-semibold text-gray-800 mb-4">Search Student</h2>
                            <form action="" method="GET" class="space-y-4">
                                <div>
                                    <label for="given_name_filter" class="block text-sm font-medium text-gray-700">Name</label>
                                    <input type="text" name="given_name_filter" id="given_name_filter" value="<?php echo htmlspecialchars($givenNameFilter); ?>" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500" placeholder="Enter given name">
                                </div>

                                <div>
                                    <label for="course_filter" class="block text-sm font-medium text-gray-700">Course</label>
                                    <select name="course_filter" id="course_filter" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500">
                                        <option value="">---Select---</option>
                                        <?php foreach ($uniqueCourses as $course): ?>
                                            <option value="<?php echo htmlspecialchars($course); ?>" <?php echo ($courseFilter == $course) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($course); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div>
                                    <label for="year_level_filter" class="block text-sm font-medium text-gray-700">Year Level</label>
                                    <select name="year_level_filter" id="year_level_filter" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500">
                                        <option value="">---Select---</option>
                                        <?php
                                        // Map numeric year level to descriptive text
                                        $yearLevelMap = [
                                            1 => 'First Year',
                                            2 => 'Second Year',
                                            3 => 'Third Year',
                                            4 => 'Fourth Year'
                                        ];
                                        foreach ($uniqueYearLevels as $yearLevelValue):
                                            $yearLevelLabel = $yearLevelMap[$yearLevelValue] ?? $yearLevelValue;
                                        ?>
                                            <option value="<?php echo htmlspecialchars($yearLevelValue); ?>" <?php echo ($yearLevelFilter == $yearLevelValue) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($yearLevelLabel); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <button type="submit" class="w-full bg-green-700 hover:bg-green-800 text-white font-bold py-2 px-4 rounded-md flex items-center justify-center">
                                    <i class="fa-solid fa-search mr-2"></i> Search
                                </button>
                                <?php if (!empty($givenNameFilter) || !empty($courseFilter) || !empty($yearLevelFilter)): // Updated clear filter condition ?>
                                    <a href="new_payments.php" class="block w-full text-center bg-yellow-400 text-white hover:bg-yellow-400 text-white font-bold py-2 px-4 rounded-md no-underline-hover mt-2">Clear Filters</a>
                                <?php endif; ?>
                            </form>
                        </div>

                        <div class="w-full md:w-3/4 bg-white p-6 rounded-lg shadow-md">
                            <?php if ($selectedStudent): ?>
                                <h2 class="text-lg font-semibold text-gray-800 mb-4">Add Payment for: <span class="text-green-700"><?php echo htmlspecialchars($selectedStudent['given_name'] . ' ' . $selectedStudent['middle_initial'] . ' ' . $selectedStudent['last_name']); ?></span></h2>
                                <p class="mb-4 text-gray-600">Current Balance: <span class="font-bold <?php echo ($selectedStudent['current_balance'] ?? 0) == 0 ? 'text-green-600' : 'text-red-600'; ?>">₱<?php echo number_format($selectedStudent['current_balance'] ?? 0, 2); ?></span></p>

                                <form action="" method="POST" class="space-y-4">
                                    <input type="hidden" name="student_id" value="<?php echo htmlspecialchars($selectedStudent['id']); ?>">

                                    <div>
                                        <label for="payment_amount" class="block text-sm font-medium text-gray-700">Payment Amount (₱)</label>
                                        <input type="number" step="0.01" name="payment_amount" id="payment_amount" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500" required>
                                    </div>

                                    <div>
                                        <label for="payment_date" class="block text-sm font-medium text-gray-700">Payment Date</label>
                                        <input type="date" name="payment_date" id="payment_date" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500" value="<?php echo date('Y-m-d'); ?>" required>
                                    </div>

                                    <div>
                                        <label for="payment_description" class="block text-sm font-medium text-gray-700">Description (Optional)</label>
                                        <input type="text" name="payment_description" id="payment_description" class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500">
                                    </div>

                                    <div class="flex items-center justify-end">
                                        <button type="submit" name="add_payment" class="bg-green-700 hover:bg-green-800 text-white font-bold py-2 px-4 rounded-md flex items-center">
                                            <i class="fa-solid fa-money-bill-transfer mr-2"></i> Record Payment
                                        </button>
                                    </div>
                                </form>
                            <?php elseif (!empty($students) && !$selectedStudent): ?>
                                <div class="overflow-x-auto">
                                    <table id="studentSearchResultsTable" class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Name</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Given Name</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Course</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Year Level</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Current Balance</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <?php foreach ($students as $student): ?>
                                                <tr>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-600">
                                                        <?php echo htmlspecialchars($student['last_name'] ?? ''); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-600">
                                                        <?php echo htmlspecialchars($student['given_name'] ?? ''); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-600">
                                                        <?php echo htmlspecialchars($student['course'] ?? ''); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-600">
                                                        <?php
                                                            $yearLevel = $student['year_level'] ?? '';
                                                            switch ($yearLevel) {
                                                                case 1: echo 'First Year'; break;
                                                                case 2: echo 'Second Year'; break;
                                                                case 3: echo 'Third Year'; break;
                                                                case 4: echo 'Fourth Year'; break;
                                                                default: echo htmlspecialchars($yearLevel); break;
                                                            }
                                                        ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold <?php echo ($student['current_balance'] ?? 0) == 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                                        ₱<?php echo number_format($student['current_balance'] ?? 0, 2); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                        <a href="add_payment.php?student_id=<?php echo htmlspecialchars($student['id']); ?>" class="bg-green-700 hover:bg-green-800 text-white font-bold py-2 px-4 rounded no-underline-hover">
                                                            Select
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-gray-600">Apply filters on the left to search for students.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>
            </main>
        </div>
    </div>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://unpkg.com/flowbite@1.6.0/dist/flowbite.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.3.0/flowbite.min.js"></script>

<script>
    $(document).ready(function() {
        // Initialize DataTables only if the search results table exists
        if ($('#studentSearchResultsTable').length) {
            $('#studentSearchResultsTable').DataTable({
                "paging": true,
                "ordering": true,
                "info": true,
                "searching": false, // Disable DataTables built-in search for this table
                "pageLength": 5, // Limits the table to 5 rows
                "lengthChange": false // This line excludes the "Show X entries" dropdown
            });
        }
    });
</script>
</body>
</html>