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
$supabaseStudentsTable = 'students_account'; // Renamed for clarity
$supabasePaymentsTable = 'payments'; // New table for payments

$student = null;
$searchQuery = $_GET['search'] ?? '';
$studentId = $_GET['student_id'] ?? null; // For direct link from view_students.php

// Handle search for student
if (!empty($searchQuery) || !empty($studentId)) {
    $apiUrl = $supabaseUrl . '/rest/v1/' . $supabaseStudentsTable . '?select=*';

    if (!empty($studentId)) {
        // Search by ID (direct link)
        $apiUrl .= '&id=eq.' . urlencode($studentId);
    } else {
        // Search by name/course (manual search)
        $searchQueryEncoded = urlencode('%' . $searchQuery . '%');
        $apiUrl .= '&or=(last_name.ilike.' . $searchQueryEncoded . ',given_name.ilike.' . $searchQueryEncoded . ',middle_initial.ilike.' . $searchQueryEncoded . ',course.ilike.' . $searchQueryEncoded . ')';
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
        $foundStudents = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $_SESSION['message'] = "Error decoding JSON from Supabase: " . json_last_error_msg();
            $_SESSION['message_type'] = 'error';
        } else if (!empty($foundStudents)) {
            $student = $foundStudents[0]; // Get the first matching student
        } else {
            $_SESSION['message'] = "No student found with that ID or matching your search criteria.";
            $_SESSION['message_type'] = 'info';
        }
    } else {
        $_SESSION['message'] = "Error searching for student: HTTP " . $httpCode . " - " . ($error ?: $response);
        $_SESSION['message_type'] = 'error';
    }
}

// Handle payment submission
if (isset($_POST['submit_payment']) && isset($_POST['student_id']) && isset($_POST['payment_amount']) && isset($_POST['or_number']) && isset($_POST['payment_date']) && isset($_POST['payment_time'])) {
    $paymentStudentId = $_POST['student_id'];
    $orNumber = trim($_POST['or_number']);
    $paymentDate = trim($_POST['payment_date']);
    $paymentTime = trim($_POST['payment_time']);
    $paymentAmount = filter_var($_POST['payment_amount'], FILTER_VALIDATE_FLOAT);

    // Basic validation for payment details
    if (empty($orNumber)) {
        $_SESSION['message'] = "OR Number cannot be empty.";
        $_SESSION['message_type'] = 'error';
    } elseif (empty($paymentDate)) {
        $_SESSION['message'] = "Payment Date cannot be empty.";
        $_SESSION['message_type'] = 'error';
    } elseif (empty($paymentTime)) {
        $_SESSION['message'] = "Payment Time cannot be empty.";
        $_SESSION['message_type'] = 'error';
    } elseif ($paymentAmount === false || $paymentAmount <= 0) {
        $_SESSION['message'] = "Invalid payment amount. Please enter a positive number.";
        $_SESSION['message_type'] = 'error';
    } else {
        // 1. Check if the OR number already exists
        $checkOrApiUrl = $supabaseUrl . '/rest/v1/' . $supabasePaymentsTable . '?select=or_number&or_number=eq.' . urlencode($orNumber);
        $chCheckOr = curl_init($checkOrApiUrl);
        curl_setopt($chCheckOr, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($chCheckOr, CURLOPT_HTTPHEADER, [
            'apikey: ' . $supabaseAnonKey,
            'Authorization: Bearer ' . $supabaseAnonKey,
            'Content-Type: application/json'
        ]);
        curl_setopt($chCheckOr, CURLOPT_CAINFO, 'C:\Program Files\Ampps\php82\extras\ssl\cacert.pem');
        $checkOrResponse = curl_exec($chCheckOr);
        $checkOrHttpCode = curl_getinfo($chCheckOr, CURLINFO_HTTP_CODE);
        $checkOrError = curl_error($chCheckOr);
        curl_close($chCheckOr);

        if ($checkOrHttpCode >= 200 && $checkOrHttpCode < 300) {
            $existingOr = json_decode($checkOrResponse, true);
            if (json_last_error() === JSON_ERROR_NONE && !empty($existingOr)) {
                // OR number already exists
                $_SESSION['message'] = "Payment with this OR number already exists. Please check.";
                $_SESSION['message_type'] = 'warning';
            } else {

                // 2. Fetch current student data to get current_balance and account_status
                $fetchStudentApiUrl = $supabaseUrl . '/rest/v1/' . $supabaseStudentsTable . '?select=current_balance,account_status&id=eq.' . urlencode($paymentStudentId);
                $chFetch = curl_init($fetchStudentApiUrl);
                curl_setopt($chFetch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($chFetch, CURLOPT_HTTPHEADER, [
                    'apikey: ' . $supabaseAnonKey,
                    'Authorization: Bearer ' . $supabaseAnonKey
                ]);
                curl_setopt($chFetch, CURLOPT_CAINFO, 'C:\Program Files\Ampps\php82\extras\ssl\cacert.pem');
                $fetchResponse = curl_exec($chFetch);
                $fetchHttpCode = curl_getinfo($chFetch, CURLINFO_HTTP_CODE);
                $fetchError = curl_error($chFetch);
                curl_close($chFetch);

                if ($fetchHttpCode >= 200 && $fetchHttpCode < 300) {
                    $fetchedStudentData = json_decode($fetchResponse, true);
                    if (json_last_error() === JSON_ERROR_NONE && !empty($fetchedStudentData)) {
                        $currentBalance = $fetchedStudentData[0]['current_balance'] ?? 0;
                        $accountStatus = $fetchedStudentData[0]['account_status'] ?? '';

                        // Check if the account is already 'Fully Paid'
                        if ($accountStatus === 'Fully Paid') {
                            $_SESSION['message'] = "This student's account is already Fully Paid. No further payments are needed.";
                            $_SESSION['message_type'] = 'info';
                            header('Location: add_payment.php?student_id=' . urlencode($paymentStudentId));
                            exit();
                        }

                        // Validate if payment amount exceeds current balance (unless balance is already zero)
                        if ($currentBalance > 0 && $paymentAmount > $currentBalance) {
                            $_SESSION['message'] = "Payment amount (₱" . number_format($paymentAmount, 2) . ") exceeds the remaining balance (₱" . number_format($currentBalance, 2) . "). Please enter an amount less than or equal to the current balance.";
                            $_SESSION['message_type'] = 'warning';
                            // Redirect immediately as validation failed
                            header('Location: add_payment.php?student_id=' . urlencode($paymentStudentId));
                            exit();
                        }

                        $newBalance = $currentBalance - $paymentAmount;

                        // Update account status based on new balance
                        $newAccountStatus = ($newBalance <= 0) ? 'Fully Paid' : 'With Balance';

                        // --- TRANSACTION START (Conceptual: Supabase doesn't have explicit server-side transactions for two separate inserts/updates) ---
                        // We'll proceed with two separate API calls. If the first fails, the second won't run.
                        // If the second fails, the balance might be updated but payment record not added.
                        // For a highly critical system, you'd need a more robust transaction management (e.g., Supabase Functions/RPC).

                        // 3. Update the student's current_balance in Supabase
                        $updateStudentData = [
                            'current_balance' => $newBalance,
                            'account_status' => $newAccountStatus,
                            'updated_at' => date('Y-m-d H:i:sP') // Update timestamp
                        ];

                        $updateStudentApiUrl = $supabaseUrl . '/rest/v1/' . $supabaseStudentsTable . '?id=eq.' . urlencode($paymentStudentId);
                        $chUpdateStudent = curl_init($updateStudentApiUrl);
                        curl_setopt($chUpdateStudent, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($chUpdateStudent, CURLOPT_CUSTOMREQUEST, 'PATCH'); // Use PATCH for updates
                        curl_setopt($chUpdateStudent, CURLOPT_HTTPHEADER, [
                            'apikey: ' . $supabaseAnonKey,
                            'Authorization: Bearer ' . $supabaseAnonKey,
                            'Content-Type: application/json',
                            'Prefer: return=representation'
                        ]);
                        curl_setopt($chUpdateStudent, CURLOPT_CAINFO, 'C:\Program Files\Ampps\php82\extras\ssl\cacert.pem');
                        curl_setopt($chUpdateStudent, CURLOPT_POSTFIELDS, json_encode($updateStudentData));

                        $updateStudentResponse = curl_exec($chUpdateStudent);
                        $updateStudentHttpCode = curl_getinfo($chUpdateStudent, CURLINFO_HTTP_CODE);
                        $updateStudentError = curl_error($chUpdateStudent);
                        curl_close($chUpdateStudent);

                        if ($updateStudentHttpCode >= 200 && $updateStudentHttpCode < 300) {
                            // Student balance updated successfully. Now, record the payment.

                            // 4. Insert the payment record into the 'payments' table
                            $paymentRecordData = [
                                'student_id' => $paymentStudentId,
                                'or_number' => $orNumber,
                                'payment_amount' => $paymentAmount,
                                'payment_date' => $paymentDate,
                                'payment_time' => $paymentTime,
                                // 'created_at' and 'updated_at' will be handled by Supabase defaults
                            ];

                            $insertPaymentApiUrl = $supabaseUrl . '/rest/v1/' . $supabasePaymentsTable;
                            $chInsertPayment = curl_init($insertPaymentApiUrl);
                            curl_setopt($chInsertPayment, CURLOPT_RETURNTRANSFER, true);
                            curl_setopt($chInsertPayment, CURLOPT_CUSTOMREQUEST, 'POST');
                            curl_setopt($chInsertPayment, CURLOPT_HTTPHEADER, [
                                'apikey: ' . $supabaseAnonKey,
                                'Authorization: Bearer ' . $supabaseAnonKey,
                                'Content-Type: application/json',
                                'Prefer: return=representation'
                            ]);
                            curl_setopt($chInsertPayment, CURLOPT_CAINFO, 'C:\Program Files\Ampps\php82\extras\ssl\cacert.pem');
                            curl_setopt($chInsertPayment, CURLOPT_POSTFIELDS, json_encode($paymentRecordData));

                            $insertPaymentResponse = curl_exec($chInsertPayment);
                            $insertPaymentHttpCode = curl_getinfo($chInsertPayment, CURLINFO_HTTP_CODE);
                            $insertPaymentError = curl_error($chInsertPayment);
                            curl_close($chInsertPayment);

                            if ($insertPaymentHttpCode >= 200 && $insertPaymentHttpCode < 300) {
                                $_SESSION['message'] = "Payment of ₱" . number_format($paymentAmount, 2) . " (OR: " . htmlspecialchars($orNumber) . ") successfully recorded. New balance: ₱" . number_format($newBalance, 2) . ".";
                                $_SESSION['message_type'] = 'success';
                                // Add this line to redirect with student ID
                                header('Location: view_payments_history.php?student_id=' . urlencode($paymentStudentId));
                                exit(); // Ensure no further code is executed after the redirect
                            } else {
                                // Payment record failed but student balance might be updated.
                                $_SESSION['message'] = "Payment failed to record (OR: " . htmlspecialchars($orNumber) . "). Student balance updated, but payment details not saved. Error: HTTP " . $insertPaymentHttpCode . " - " . ($insertPaymentError ?: $insertPaymentResponse);
                                $_SESSION['message_type'] = 'warning'; // Use warning if balance updated
                            }

                        } else {
                            $_SESSION['message'] = "Error updating student balance: HTTP " . $updateStudentHttpCode . " - " . ($updateStudentError ?: $updateStudentResponse);
                            $_SESSION['message_type'] = 'error';
                        }

                    } else {
                        $_SESSION['message'] = "Could not fetch student's current balance for update.";
                        $_SESSION['message_type'] = 'error';
                    }
                } else {
                    $_SESSION['message'] = "Error fetching student for balance update: HTTP " . $fetchHttpCode . " - " . ($fetchError ?: $fetchResponse);
                    $_SESSION['message_type'] = 'error';
                }
            }
        } else {
            $_SESSION['message'] = "Error checking for existing OR number: HTTP " . $checkOrHttpCode . " - " . ($checkOrError ?: $checkOrResponse);
            $_SESSION['message_type'] = 'error';
        }

    }
    // Redirect back to add_payment.php, possibly with the student_id to show updated info
    header('Location: add_payment.php?student_id=' . urlencode($paymentStudentId));
    exit();
}
$current_page = basename($_SERVER['PHP_SELF']);
$is_payments_active = in_array($current_page, ['new_payments.php', 'payments.php', 'view_payment_history.php', 'add_payment.php']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SAMS - Student Account Management System</title>
    <link rel="icon" href="../logo/logo.png" type="image/png">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <link rel="stylesheet" href="Admin LTE/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="Admin LTE/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="Admin LTE/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
    <link rel="stylesheet" href="Admin LTE/plugins/datatables-buttons/css/buttons.bootstrap4.min.css">
    <link rel="stylesheet" href="Admin LTE/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="sweetalert2/sweetalert2.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.3.0/flowbite.min.css" rel="stylesheet" />
    <script src="https://cdn.tailwindcss.com"></script>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Student Account Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flowbite@1.4.0/dist/flowbite.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.js"></script>
    <style>
        /* Custom styles for messages */
        .message {
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 0.375rem; /* Equivalent to rounded-md */
        }

        .message.info {
            background-color: #d1e7dd; /* Light green */
            color: #155724;
            border: 1px solid #f5c6cb;
        }
        .message.warning {
            background-color: #fff3cd; /* Light yellow */
            color: #664d03; /* Darker yellow */
            border: 1px solid #ffecb5;
        }
        .message.success {
            background-color: #d4edda; /* Green for success */
            color: #155724; /* Dark green for success */
            border: 1px solid #c3e6cb;
        }
        .content-header h1 {
            margin: 0;
            font-size: 20px;
        }
                body {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc; /* Lighter background for a cleaner feel */
            color: #334155; /* Darker text for readability */
        }
    </style>
</head>
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
                            <h1>Payments</h1>
                        </div>
                        <div class="col-sm-6">
                            <ol class="breadcrumb float-sm-right">
                                <li class="breadcrumb-item active text-white"></li>
                            </ol>
                        </div>
                    </div>
                </section>
                <main class="container mx-auto p-4">
                    <h1 class="text-xl font-semibold mb-4 text-gray-700">Add Payment</h1>

                    <?php
                    if (isset($_SESSION['message'])) {
                        $messageClass = $_SESSION['message_type'] ?? 'info';
                        echo '<div class="message ' . htmlspecialchars($messageClass) . '">' . htmlspecialchars($_SESSION['message']) . '</div>';
                        unset($_SESSION['message']);
                        unset($_SESSION['message_type']);
                    }
                    ?>

                    <?php if ($student): ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="student-details bg-white p-6 rounded-lg shadow-md border border-gray-200">

    <dl class="divide-y divide-gray-200">
        <div class="py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-0">
            <dd class="mt-1 text-2xl sm:col-span-2 sm:mt-0 font-bold text-gray-600"><?php echo htmlspecialchars($student['last_name'] ?? '') . ', ' . htmlspecialchars($student['given_name'] ?? '') . ' ' . htmlspecialchars($student['middle_initial'] ?? ''); ?></dd>
        </div>
        <div class="py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-0">
            <dt class="text-sm font-medium text-gray-600">Course</dt>
            <dd class="mt-1 text-base font-semibold text-gray-500 sm:col-span-2 sm:mt-0"><?php echo htmlspecialchars($student['course'] ?? ''); ?></dd>
        </div>
        <div class="py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-0">
            <dt class="text-sm font-medium text-gray-600">Year Level</dt>
            <dd class="mt-1 text-base font-semibold text-gray-500 sm:col-span-2 sm:mt-0">
        <?php
        $yearLevel = $student['year_level'] ?? '';
        switch ($yearLevel) {
            case 1:
                echo 'First Year';
                break;
            case 2:
                echo 'Second Year';
                break;
            case 3:
                echo 'Third Year';
                break;
            case 4:
                echo 'Fourth Year';
                break;
            default:
                echo htmlspecialchars($yearLevel); // Fallback for other values or if not set
                break;
        }
        ?>
    </dd>
        </div>
        <div class="py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-0">
            <dt class="text-sm font-medium text-gray-600">Current Balance</dt>
            <dd class="mt-1 text-xl font-bold sm:col-span-2 sm:mt-0 text-red-600">₱<?php echo number_format($student['current_balance'] ?? 0, 2); ?></dd>
        </div>
        <div class="py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-0">
            <dt class="text-sm font-medium text-gray-600">Account Status</dt>
            <dd class="uppercase mt-1 text-base font-semibold sm:col-span-2 sm:mt-0 <?php echo ($student['account_status'] === 'Fully Paid') ? 'text-green-600' : 'text-orange-500'; ?>"><?php echo htmlspecialchars($student['account_status'] ?? ''); ?></dd>
        </div>
    </dl>
</div>

                            <div class="payment-form bg-white p-6 rounded-lg shadow-md">
                                <h3 class="text-xl font-semibold mb-4 text-gray-700">Record Payment</h3>
                                <?php
                                $isFullyPaid = ($student['account_status'] ?? '') === 'Fully Paid';
                                if ($isFullyPaid) {
                                    echo '<div class="bg-red-500 border border-red-400 text-white px-4 py-3 rounded relative mb-4" role="alert">';
                                    echo '<i class="fa-solid fa-triangle-exclamation"></i>';
                                    echo '<span class="block sm:inline"> This student\'s account is fully paid. No further payments are needed.</span>';
                                    echo '</div>';
                                }
                                ?>
                                <form action="" method="post" class="space-y-4">
                                    <input type="hidden" name="student_id" value="<?php echo htmlspecialchars($student['id']); ?>">

                                    <div>
                                        <label for="or_number" class="block mb-2 text-sm font-medium text-gray-900">OR Number:</label>
                                        <input type="text" id="or_number" maxlength="8" name="or_number" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" <?php echo $isFullyPaid ? 'disabled' : 'required'; ?>>
                                    </div>

                                    <div>
                                        <label for="payment_date" class="block mb-2 text-sm font-medium text-gray-900">Date:</label>
                                        <input type="date" id="payment_date" name="payment_date" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" value="<?php echo date('Y-m-d'); ?>" <?php echo $isFullyPaid ? 'disabled' : 'required'; ?>>
                                    </div>

                                    <input type="hidden" id="payment_time" name="payment_time" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" required>


                                    <div>
                                        <label for="payment_amount" class="block mb-2 text-sm font-medium text-gray-900">Amount (₱):</label>
                                        <input type="number" id="payment_amount" name="payment_amount" step="0.01" min="0.01" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5" <?php echo $isFullyPaid ? 'disabled' : 'required'; ?>>
                                    </div>

                                    <button type="submit" name="submit_payment" class="font-semibold text-white bg-green-700 hover:bg-green-800 focus:ring-4 focus:outline-none focus:ring-green-300 font-medium rounded-lg text-sm w-full px-5 py-2.5 text-center <?php echo $isFullyPaid ? 'opacity-50 cursor-not-allowed' : ''; ?>" <?php echo $isFullyPaid ? 'disabled' : ''; ?>>Add Payment</button>
                                </form>
                            </div>
                        </div>
                    <?php elseif (!empty($searchQuery)): ?>
                        <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded relative" role="alert">
                            <strong class="font-bold">Info!</strong>
                            <span class="block sm:inline">No student found matching "<?php echo htmlspecialchars($searchQuery); ?>".</span>
                        </div>
                    <?php endif; ?>
                </main>
            </main>
        </div>
            <script src="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.js"></script>
            <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
            <script src="sweetalert2/sweetalert2.all.min.js"></script>
            <script src="jsQR-master/dist/jsQR.js"></script>
            <script src="sweetalert2/sweetalert2.min.js"></script>
            <script src="Admin LTE/plugins/jquery/jquery.min.js"></script>
            <script src="Admin LTE/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
            <script src="Admin LTE/dist/js/adminlte.min.js"></script>
            <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js">
            </script><script src="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.3.0/flowbite.min.js"></script>
            <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Set current date
            const today = new Date();
            const year = today.getFullYear();
            const month = String(today.getMonth() + 1).padStart(2, '0'); // Month is 0-indexed
            const day = String(today.getDate()).padStart(2, '0');
            document.getElementById('payment_date').value = `${year}-${month}-${day}`;

            // Set current time
            const hours = String(today.getHours()).padStart(2, '0');
            const minutes = String(today.getMinutes()).padStart(2, '0');
            document.getElementById('payment_time').value = `${hours}:${minutes}`;
        });
        // Set current time for payment_time hidden input
    document.addEventListener('DOMContentLoaded', function() {
        const paymentTimeInput = document.getElementById('payment_time');
        const now = new Date();
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const seconds = String(now.getSeconds()).padStart(2, '0');
        paymentTimeInput.value = `${hours}:${minutes}:${seconds}`;
    });
    </script>
</body>
</html>