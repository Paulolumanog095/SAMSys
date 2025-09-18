<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
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
$supabaseTableStudents = 'students_account'; // Renamed for clarity
$supabaseTablePayments = 'payments'; // New table for payments

$totalStudents = 0;
$studentsWithBalance = 0;
$fullyPaidStudents = 0;
$totalBalanceDue = 0;

$courseSummary = []; // Total students per course
$fullyPaidCourseSummary = []; // Fully paid students per course
$balanceCourseSummary = []; // Students with balance per course
$totalPaymentsByCourse = []; // Total payments received per course
$overallTotalPayments = 0; // Overall total payments from all courses

// --- Function to fetch data from Supabase with pagination ---
// Modified to always return an array (empty on error/no data)
function fetchSupabaseData($url, $apiKey, $limit, $offset) {
    $apiUrl = $url . '?select=*&limit=' . $limit . '&offset=' . $offset;
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . $apiKey,
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json'
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($httpCode >= 200 && $httpCode < 300) {
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $_SESSION['message'] = "Error decoding JSON from Supabase: " . json_last_error_msg();
            $_SESSION['message_type'] = 'error';
            return []; // Return empty array on JSON decode error
        }
        return $data;
    } else {
        $_SESSION['message'] = "Error fetching data from Supabase: HTTP " . $httpCode . " - " . ($error ?: $response);
        $_SESSION['message_type'] = 'error';
        return []; // Return empty array on HTTP error
    }
}

// --- Fetch All Students ---
$limit = 1000;
$offset = 0;
$allStudents = []; // Initialize to an empty array
do {
    $fetchedStudents = fetchSupabaseData($supabaseUrl . '/rest/v1/' . $supabaseTableStudents, $supabaseAnonKey, $limit, $offset);
    // fetchedStudents will now always be an array, even if empty on error
    $allStudents = array_merge($allStudents, $fetchedStudents);
    $offset += count($fetchedStudents);
    // Continue loop only if we fetched 'limit' number of records, indicating more might exist
} while (count($fetchedStudents) === $limit);

// --- Fetch All Payments ---
$offset = 0;
$allPayments = []; // Initialize to an empty array
do {
    $fetchedPayments = fetchSupabaseData($supabaseUrl . '/rest/v1/' . $supabaseTablePayments, $supabaseAnonKey, $limit, $offset);
    // fetchedPayments will now always be an array, even if empty on error
    $allPayments = array_merge($allPayments, $fetchedPayments);
    $offset += count($fetchedPayments);
    // Continue loop only if we fetched 'limit' number of records, indicating more might exist
} while (count($fetchedPayments) === $limit);


// --- Process All Fetched Students and Payments ---
// Create a map of student_id to course for payments processing
$studentCourseMap = [];
foreach ($allStudents as $student) { // Line 12 will now work correctly
    // It's good practice to check if 'student_id' exists here too
    if (isset($student['student_id'])) {
        $studentCourseMap[$student['student_id']] = $student['course'] ?? 'Unknown';
    }
}

foreach ($allStudents as $student) { // Line 19 will now work correctly
    $totalStudents++;
    $currentBalance = $student['current_balance'] ?? 0;
    $course = $student['course'] ?? 'Unknown';

    // Aggregate total students per course (for overall enrollment chart)
    if (!isset($courseSummary[$course])) {
        $courseSummary[$course] = 0;
    }
    $courseSummary[$course]++;

    $totalBalanceDue += $currentBalance;

    if ($currentBalance > 0) {
        $studentsWithBalance++;
        // Aggregate students with balance per course
        if (!isset($balanceCourseSummary[$course])) {
            $balanceCourseSummary[$course] = 0;
        }
        $balanceCourseSummary[$course]++;
    } else {
        $fullyPaidStudents++;
        // Aggregate fully paid students per course
        if (!isset($fullyPaidCourseSummary[$course])) {
            $fullyPaidCourseSummary[$course] = 0;
        }
        $fullyPaidCourseSummary[$course]++;
    }
}

// Process payments to get total payments per course
foreach ($allPayments as $payment) { // Line 50 will now work correctly
    // This was the original location of the "Undefined array key 'student_id'" warning
    // We add the isset check here to robustly handle it.
    if (isset($payment['student_id'])) {
        $studentId = $payment['student_id'];
    } else {
        $studentId = null; // Assign null or handle as an error
        error_log("Warning: Payment record missing 'student_id' key in JSON: " . json_encode($payment));
        // If you want to skip payments without a student_id altogether:
        // continue;
    }

    $amountPaid = $payment['amount_paid'] ?? 0;

    $overallTotalPayments += $amountPaid;

    if ($studentId && isset($studentCourseMap[$studentId])) {
        $course = $studentCourseMap[$studentId];
        if (!isset($totalPaymentsByCourse[$course])) {
            $totalPaymentsByCourse[$course] = 0;
        }
        $totalPaymentsByCourse[$course] += $amountPaid;
    } else {
        // Handle payments for unknown students or no course associated
        if (!isset($totalPaymentsByCourse['Unknown Course'])) {
            $totalPaymentsByCourse['Unknown Course'] = 0;
        }
        $totalPaymentsByCourse['Unknown Course'] += $amountPaid;
    }
}

// The summary arrays (courseSummary, etc.) will now be properly initialized as arrays
// because the foreach loops will either run or run over an empty array.
// Line 85 (and others using array_keys/array_values) will now work.
$chartLabels = json_encode(array_keys($courseSummary));
$chartData = json_encode(array_values($courseSummary));

$fullyPaidChartLabels = json_encode(array_keys($fullyPaidCourseSummary));
$fullyPaidChartData = json_encode(array_values($fullyPaidCourseSummary));

$balanceChartLabels = json_encode(array_keys($balanceCourseSummary));
$balanceChartData = json_encode(array_values($balanceCourseSummary));

$paymentsByCourseLabels = json_encode(array_keys($totalPaymentsByCourse));
$paymentsByCourseData = json_encode(array_values($totalPaymentsByCourse));

// The rest of your HTML/display code goes here
$current_page = basename($_SERVER['PHP_SELF']);
$is_payments_active = in_array($current_page, ['new_payments.php', 'payments.php', 'view_payment_history.php']);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SAMS - Student Account Management System</title>
    <link rel="icon" href="../logo/logo.png" type="image/png">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.3.0/flowbite.min.css" rel="stylesheet" />
    <script src="https://cdn.tailwindcss.com"></script>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc; /* Lighter background for a cleaner feel */
            color: #334155; /* Darker text for readability */
        }
        /* Custom styles for messages */
        .message {
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 0.5rem;
            font-weight: 500;
        }
        .message.info {
            background-color: #e0f2fe; /* light blue */
            color: #0c4a6e; /* dark blue */
            border: 1px solid #90cdf4;
        }
        .message.success {
            background-color: #d1fae5; /* light green */
            color: #065f46; /* dark green */
            border: 1px solid #6ee7b7;
        }
        .message.error {
            background-color: #fee2e2; /* light red */
            color: #991b1b; /* dark red */
            border: 1px solid #fca5a5;
        }
    </style>
</head>
<body>
    <div class="flex h-screen bg-gray-100">

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
                            <h1 class="font-semibold text-xl">Dashboard</h1>
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
                // Display session messages using Flowbite alerts
                if (isset($_SESSION['message'])) {
                    $messageClass = $_SESSION['message_type'] ?? 'info';
                    $alertColor = '';
                    $icon = '';
                    switch ($messageClass) {
                        case 'success':
                            $alertColor = 'green';
                            $icon = '<svg class="flex-shrink-0 inline w-4 h-4 me-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20"><path d="M10 .5a9.5 9.5 0 1 0 9.5 9.5A9.51 9.51 0 0 0 10 .5ZM9.03 16.59l-4.75-4.75 1.41-1.41L9.03 13.77l6.36-6.36 1.41 1.41-7.77 7.77Z"/></svg>';
                            break;
                        case 'error':
                            $alertColor = 'red';
                            $icon = '<svg class="flex-shrink-0 inline w-4 h-4 me-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20"><path d="M10 .5a9.5 9.5 0 1 0 9.5 9.5A9.51 9.51 0 0 0 10 .5ZM10 15a1 1 0 1 1 0-2 1 1 0 0 1 0 2Zm1-4a1 1 0 1 1-2 0V6a1 1 0 1 1 2 0v5Z"/></svg>';
                            break;
                        default: // info
                            $alertColor = 'blue';
                            $icon = '<svg class="flex-shrink-0 inline w-4 h-4 me-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20"><path d="M10 .5a9.5 9.5 0 1 0 9.5 9.5A9.51 9.51 0 0 0 10 .5ZM9.03 16.59l-4.75-4.75 1.41-1.41L9.03 13.77l6.36-6.36 1.41 1.41-7.77 7.77Z"/></svg>';
                            break;
                    }
                    echo '<div id="alert-border-1" class="flex items-center p-4 mb-4 text-' . $alertColor . '-800 border-t-4 border-' . $alertColor . '-300 bg-' . $alertColor . '-50 rounded-lg" role="alert">';
                    echo $icon;
                    echo '<div class="text-sm font-medium">' . htmlspecialchars($_SESSION['message']) . '</div>';
                    echo '<button type="button" class="ms-auto -mx-1.5 -my-1.5 bg-' . $alertColor . '-50 text-' . $alertColor . '-500 rounded-lg focus:ring-2 focus:ring-' . $alertColor . '-400 p-1.5 hover:bg-' . $alertColor . '-200 inline-flex items-center justify-center h-8 w-8" data-dismiss-target="#alert-border-1" aria-label="Close">';
                    echo '<span class="sr-only">Dismiss</span>';
                    echo '<svg class="w-3 h-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6"/></svg>';
                    echo '</button>';
                    echo '</div>';
                    unset($_SESSION['message']);
                    unset($_SESSION['message_type']);
                }
                ?>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                        <div class="bg-white p-6 rounded-xl shadow-lg flex items-center justify-between transition-transform transform hover:-translate-y-1 hover:shadow-xl duration-300">
                            <div>
                                <h3 class="text-sm font-medium text-gray-500 uppercase mb-2">Total Students</h3>
                                <p class="text-4xl font-extrabold text-blue-600"><?php echo $totalStudents; ?></p>
                            </div>
                            <div class="p-3 bg-white rounded-full">
                                <i class="fas fa-users text-4xl text-blue-600"></i>
                            </div>
                        </div>
                    <div class="bg-yellow-50 p-6 rounded-xl shadow-lg flex items-center justify-between transition-transform transform hover:-translate-y-1 hover:shadow-xl duration-300">
                        <div>
                            <h3 class="text-sm font-medium text-gray-700 uppercase mb-2">With Balance</h3>
                            <p class="text-4xl font-extrabold text-yellow-600"><?php echo $studentsWithBalance; ?></p>
                        </div>
                        <div class="p-3 bg-yellow-50 rounded-full">
                            <i class="fas fa-exclamation-triangle text-4xl text-yellow-600"></i>
                        </div>
                    </div>
                    <div class="bg-green-100 p-6 rounded-xl shadow-lg flex items-center justify-between transition-transform transform hover:-translate-y-1 hover:shadow-xl duration-300">
                        <div>
                            <h3 class="text-sm font-medium text-gray-700 uppercase mb-2">Fully Paid</h3>
                            <p class="text-4xl font-extrabold text-green-600"><?php echo $fullyPaidStudents; ?></p>
                        </div>
                        <div class="p-3 bg-green-100 rounded-full">
                            <i class="fas fa-check-circle text-4xl text-green-600"></i>
                        </div>
                    </div>
                    <div class="bg-red-100 p-6 rounded-xl shadow-lg flex items-center justify-between transition-transform transform hover:-translate-y-1 hover:shadow-xl duration-300">
                        <div>
                            <h3 class="text-sm font-medium text-gray-500 uppercase mb-2">Total Balance Due</h3>
                            <p class="text-2xl font-extrabold text-red-700">₱<?php echo number_format($totalBalanceDue, 2); ?></p>
                        </div>
                        <div class="p-3 bg-red-100 rounded-full">
                            <i class="fas fa-wallet text-4xl text-red-600"></i>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                    <div class="bg-white p-6 rounded-xl shadow-lg">
                        <h2 class="text-xl font-semibold text-gray-500 mb-4">Fully Paid Students</h2>
                        <div class="chart-container h-96 w-full flex items-center justify-center">
                            <?php if (empty($fullyPaidCourseSummary)): ?>
                                <p class="text-gray-500 text-center">No fully paid student data available to display chart.</p>
                            <?php else: ?>
                                <canvas id="fullyPaidCourseChart"></canvas>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="bg-white p-6 rounded-xl shadow-lg">
                        <h2 class="text-xl font-semibold text-gray-500 mb-4">Students With Balance</h2>
                        <div class="chart-container h-96 w-full flex items-center justify-center">
                            <?php if (empty($balanceCourseSummary)): ?>
                                <p class="text-gray-500 text-center">No student balance data available to display chart.</p>
                            <?php else: ?>
                                <canvas id="balanceCourseChart"></canvas>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                    <div class="bg-white p-6 rounded-xl shadow-lg">
                        <h2 class="text-xl font-semibold text-gray-500 mb-4">Total Payments by Course</h2>
                        <div class="chart-container h-96 w-full flex items-center justify-center">
                            <?php if (empty($totalPaymentsByCourse)): ?>
                                <p class="text-gray-500 text-center">No payment data available to display chart.</p>
                            <?php else: ?>
                                <canvas id="paymentsByCourseChart"></canvas>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="bg-white p-6 rounded-xl shadow-lg">
                        <h2 class="text-xl font-semibold text-gray-500 mb-4">Student Enrollment by Course</h2>
                        <div class="chart-container" style="height: 400px;">
                            <?php if (empty($courseSummary)): ?>
                                <p class="text-gray-500 text-center">No overall student enrollment data available to display chart.</p>
                            <?php else: ?>
                                <canvas id="courseBarChart"></canvas>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                </main>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.3.0/flowbite.min.js"></script>
    <script>
        // Data for Chart.js
        const courseLabels = <?php echo $chartLabels; ?>; // All students per course (for bar chart)
        const courseData = <?php echo $chartData; ?>;

        const fullyPaidChartLabels = <?php echo $fullyPaidChartLabels; ?>;
        const fullyPaidChartData = <?php echo $fullyPaidChartData; ?>;

        const balanceChartLabels = <?php echo $balanceChartLabels; ?>;
        const balanceChartData = <?php echo $balanceChartData; ?>;

        const paymentsByCourseLabels = <?php echo $paymentsByCourseLabels; ?>;
        const paymentsByCourseData = <?php echo $paymentsByCourseData; ?>;


        // Define your custom color mapping for courses (you can reuse these or modify them)
        const customCourseColors = {
            'BSIT': '#8A2BE2',      // Violet (Blue Violet)
            'BEED': '#0000FF',      // Blue
            'BSED-BIO. SCI': '#87CEEB', // Sky Blue
            'BSED-MATH': '#87CEEB',   // Sky Blue
            'BSCRIM': '#FF6347',     // Light Red (Tomato)
            'BSAB': '#008000',       // Green
            'BSSW': '#7CFC00',       // Green (for BSSW if you have it)
            'BHM': '#FF69B4',        // Pink (Hot Pink)
            'BSHM': '#FF69B4',      // Pink (Hot Pink)
            'HRM': '#FFFF00',        // Yellow (Pure Yellow)
            'BSHRM': '#FFFF00',      // Yellow (Pure Yellow)
            'BSA': '#008000',        // Green
            'BSACT': '#FFFF00',      // Yellow
            'BAS': '#008000',        // Green
            'UNKNOWN COURSE': '#9CA3AF' // Gray for unknown course
        };

        // Fallback colors for courses not explicitly defined in customCourseColors
        const fallbackColors = [
            '#60A5FA', // Blue-400
            '#34D399', // Green-400
            '#FCD34D', // Yellow-300
            '#F87171', // Red-400
            '#A78BFA', // Purple-400
            '#FDA47E', // Orange-300
            '#9CA3AF', // Gray-400
            '#BEF264', // Lime-300
            '#818CF8', // Indigo-400
            '#F472B6', // Pink-400
            '#2DD4BF', // Teal-400
            '#FB923C', // Amber-400
            '#E879F9', // Fuchsia-400
            '#D946EF'  // Violet-500
        ];

        // Function to get colors for charts, normalizing labels
        function getChartColors(labelsArray) {
            return labelsArray.map((label, index) => {
                const normalizedLabel = label.toUpperCase().trim();

                // Check for direct matches or includes
                if (customCourseColors[normalizedLabel]) {
                    return customCourseColors[normalizedLabel];
                }
                if (normalizedLabel.includes('BSIT')) return customCourseColors['BSIT'];
                if (normalizedLabel.includes('BEED')) return customCourseColors['BEED'];
                if (normalizedLabel.includes('BSED (BIO SCI)') || normalizedLabel.includes('BSED-BIO. SCI')) return customCourseColors['BSED-BIO. SCI'];
                if (normalizedLabel.includes('BSED (MATH)') || normalizedLabel.includes('BSED-MATH')) return customCourseColors['BSED-MATH'];
                if (normalizedLabel.includes('BSCRIM')) return customCourseColors['BSCRIM'];
                if (normalizedLabel.includes('BSAB')) return customCourseColors['BSAB'];
                if (normalizedLabel.includes('BSHM') || normalizedLabel === 'HM') return customCourseColors['BSHM'];
                if (normalizedLabel.includes('BSHRM') || normalizedLabel === 'HRM') return customCourseColors['BSHRM'];
                if (normalizedLabel.includes('BSA')) return customCourseColors['BSA'];
                if (normalizedLabel.includes('BSACT')) return customCourseColors['BSACT'];
                if (normalizedLabel.includes('BAS')) return customCourseColors['BAS'];
                if (normalizedLabel.includes('UNKNOWN COURSE')) return customCourseColors['UNKNOWN COURSE'];

                return fallbackColors[index % fallbackColors.length];
            });
        }

        const commonChartOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                    labels: {
                        font: { size: 14, family: 'Inter', weight: '500' },
                        usePointStyle: true,
                        padding: 20
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.label || '';
                            if (label) { label += ': '; }
                            if (context.parsed !== null) {
                                const total = context.dataset.data.reduce((acc, current) => acc + current, 0);
                                const percentage = ((context.parsed / total) * 100).toFixed(1) + '%';
                                label += context.parsed + ' students (' + percentage + ')';
                            }
                            return label;
                        }
                    },
                    backgroundColor: 'rgba(51, 65, 85, 0.9)',
                    titleFont: { size: 14, weight: '600' },
                    bodyFont: { size: 12, weight: '400' },
                    padding: 12,
                    cornerRadius: 6
                }
            },
            animation: {
                animateScale: true,
                animateRotate: true
            }
        };

        // --- Fully Paid Students per Course Chart ---
        const ctxFullyPaid = document.getElementById('fullyPaidCourseChart');
        if (ctxFullyPaid && fullyPaidChartLabels.length > 0 && fullyPaidChartData.length > 0) {
            new Chart(ctxFullyPaid.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: fullyPaidChartLabels,
                    datasets: [{
                        data: fullyPaidChartData,
                        backgroundColor: getChartColors(fullyPaidChartLabels),
                        borderColor: '#ffffff',
                        borderWidth: 2,
                        hoverOffset: 12
                    }]
                },
                options: {
                    ...commonChartOptions,
                    plugins: {
                        ...commonChartOptions.plugins,
                        tooltip: {
                            ...commonChartOptions.plugins.tooltip,
                            callbacks: {
                                label: function(context) {
                                    let label = context.label || '';
                                    if (label) { label += ': '; }
                                    if (context.parsed !== null) {
                                        const total = context.dataset.data.reduce((acc, current) => acc + current, 0);
                                        const percentage = ((context.parsed / total) * 100).toFixed(1) + '%';
                                        label += context.parsed + ' student(s) (' + percentage + ')';
                                    }
                                    return label;
                                }
                            }
                        }
                    }
                }
            });
        }

        // --- Students With Balance per Course Chart ---
        const ctxBalance = document.getElementById('balanceCourseChart');
        if (ctxBalance && balanceChartLabels.length > 0 && balanceChartData.length > 0) {
            new Chart(ctxBalance.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: balanceChartLabels,
                    datasets: [{
                        data: balanceChartData,
                        backgroundColor: getChartColors(balanceChartLabels),
                        borderColor: '#ffffff',
                        borderWidth: 2,
                        hoverOffset: 12
                    }]
                },
                options: {
                    ...commonChartOptions,
                    plugins: {
                        ...commonChartOptions.plugins,
                        tooltip: {
                            ...commonChartOptions.plugins.tooltip,
                            callbacks: {
                                label: function(context) {
                                    let label = context.label || '';
                                    if (label) { label += ': '; }
                                    if (context.parsed !== null) {
                                        const total = context.dataset.data.reduce((acc, current) => acc + current, 0);
                                        const percentage = ((context.parsed / total) * 100).toFixed(1) + '%';
                                        label += context.parsed + ' student(s) (' + percentage + ')';
                                    }
                                    return label;
                                }
                            }
                        }
                    }
                }
            });
        }

        // --- Total Payments by Course Chart (Pie Chart) ---
        const ctxPaymentsByCourse = document.getElementById('paymentsByCourseChart');
        if (ctxPaymentsByCourse && paymentsByCourseLabels.length > 0 && paymentsByCourseData.length > 0) {
            new Chart(ctxPaymentsByCourse.getContext('2d'), {
                type: 'pie', // Changed to pie chart as requested
                data: {
                    labels: paymentsByCourseLabels,
                    datasets: [{
                        data: paymentsByCourseData,
                        backgroundColor: getChartColors(paymentsByCourseLabels),
                        borderColor: '#ffffff',
                        borderWidth: 2,
                        hoverOffset: 12
                    }]
                },
                options: {
                    ...commonChartOptions,
                    plugins: {
                        ...commonChartOptions.plugins,
                        tooltip: {
                            ...commonChartOptions.plugins.tooltip,
                            callbacks: {
                                label: function(context) {
                                    let label = context.label || '';
                                    if (label) { label += ': '; }
                                    if (context.parsed !== null) {
                                        const value = context.parsed;
                                        const total = context.dataset.data.reduce((acc, current) => acc + current, 0);
                                        const percentage = total > 0 ? ((value / total) * 100).toFixed(1) + '%' : '0%';
                                        label += '₱' + value.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' (' + percentage + ')';
                                    }
                                    return label;
                                }
                            }
                        }
                    }
                }
            });
        }

        // --- Student Enrollment by Course (Bar Chart) ---
        const ctxBar = document.getElementById('courseBarChart');
        if (ctxBar && courseLabels.length > 0 && courseData.length > 0) {
            new Chart(ctxBar.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: courseLabels,
                    datasets: [{
                        label: 'Number of Students',
                        data: courseData,
                        backgroundColor: getChartColors(courseLabels), // Use the same color function
                        borderColor: getChartColors(courseLabels).map(color => color.replace(')', ', 0.8)').replace('rgb(', 'rgba(')),
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        title: {
                            display: true,
                            text: 'Student Enrollment by Course',
                            font: { size: 18, weight: '600', family: 'Inter' },
                            padding: { top: 10, bottom: 20 }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label) { label += ': '; }
                                    if (context.parsed.y !== null) {
                                        label += context.parsed.y + ' student(s)';
                                    }
                                    return label;
                                }
                            },
                            backgroundColor: 'rgba(51, 65, 85, 0.9)',
                            titleFont: { size: 14, weight: '600' },
                            bodyFont: { size: 12, weight: '400' },
                            padding: 12,
                            cornerRadius: 6
                        }
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: {
                                font: { size: 12, family: 'Inter' },
                                color: '#4B5563',
                                autoSkip: false,
                                maxRotation: 45,
                                minRotation: 45
                            }
                        },
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Number of Students',
                                font: { size: 14, family: 'Inter', weight: 'bold' },
                                color: '#4B5563'
                            },
                            ticks: {
                                precision: 0,
                                font: { size: 12, family: 'Inter' },
                                color: '#4B5563'
                            },
                            grid: { color: '#E5E7EB' }
                        }
                    },
                    animation: {
                        animateScale: true,
                        animateRotate: false
                    }
                }
            });
        }
    </script>
</body>
</html>