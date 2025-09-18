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
$supabaseStudentsTable = 'students_account';
$supabasePaymentsTable = 'payments';

$student = null;
$payments = [];
$studentId = $_GET['student_id'] ?? null;

// Pagination variables
$limit = 4; // Number of payments per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

if (empty($studentId)) {
    $_SESSION['message'] = "No student ID provided to view payment history.";
    $_SESSION['message_type'] = 'error';
    header('Location: view_students.php'); // Redirect if no ID
    exit();
}

// 1. Fetch student details
$fetchStudentApiUrl = $supabaseUrl . '/rest/v1/' . $supabaseStudentsTable . '?select=*&id=eq.' . urlencode($studentId);
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

if ($studentHttpCode >= 200 && $studentHttpCode < 300) {
    $studentData = json_decode($studentResponse, true);
    if (json_last_error() === JSON_ERROR_NONE && !empty($studentData)) {
        $student = $studentData[0];
    } else {
        $_SESSION['message'] = "Student not found.";
        $_SESSION['message_type'] = 'error';
        header('Location: view_students.php');
        exit();
    }
} else {
    $_SESSION['message'] = "Error fetching student details: HTTP " . $studentHttpCode . " - " . ($studentError ?: $studentResponse);
    $_SESSION['message_type'] = 'error';
    header('Location: view_students.php');
    exit();
}

// --- Fetch total payment count for pagination ---
// You were correctly trying to get the count. Let's make sure it's robust.
$fetchTotalPaymentsApiUrl = $supabaseUrl . '/rest/v1/' . $supabasePaymentsTable . '?select=count&student_id=eq.' . urlencode($studentId);
$chTotalPayments = curl_init($fetchTotalPaymentsApiUrl);
curl_setopt($chTotalPayments, CURLOPT_RETURNTRANSFER, true);
// This is important to get the Content-Range header for the count
curl_setopt($chTotalPayments, CURLOPT_HEADER, 1); // Get headers in the response
curl_setopt($chTotalPayments, CURLOPT_HTTPHEADER, [
    'apikey: ' . $supabaseAnonKey,
    'Authorization: Bearer ' . $supabaseAnonKey,
    'Range-Unit: items' // Important for count
]);
curl_setopt($chTotalPayments, CURLOPT_CAINFO, 'C:\Program Files\Ampps\php82\extras\ssl\cacert.pem');
$totalPaymentsResponse = curl_exec($chTotalPayments);
$totalPaymentsHttpCode = curl_getinfo($chTotalPayments, CURLINFO_HTTP_CODE);
$totalPaymentsError = curl_error($chTotalPayments);
curl_close($chTotalPayments);

$totalPayments = 0;
if ($totalPaymentsHttpCode >= 200 && $totalPaymentsHttpCode < 300) {
    $header_size = curl_getinfo($chTotalPayments, CURLINFO_HEADER_SIZE);
    $headers = substr($totalPaymentsResponse, 0, $header_size);
    $body = substr($totalPaymentsResponse, $header_size);

    if (preg_match('/Content-Range: \*\/\d+/', $headers, $matches)) {
        // Old Supabase style: Content-Range: */COUNT
        $totalPayments = (int) explode('/', $matches[0])[1];
    } elseif (preg_match('/Content-Range: items \d+-\d+\/(\d+)/', $headers, $matches)) {
        // Newer Supabase style: Content-Range: items START-END/COUNT
        $totalPayments = (int)$matches[1];
    } else {
        // Fallback for when count is returned in body as [{count: X}]
        $decodedCount = json_decode($body, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decodedCount) && !empty($decodedCount) && isset($decodedCount[0]['count'])) {
            $totalPayments = (int)$decodedCount[0]['count'];
        }
    }
} else {
    $_SESSION['message'] = "Error fetching total payment count: HTTP " . $totalPaymentsHttpCode . " - " . ($totalPaymentsError ?: $totalPaymentsResponse);
    $_SESSION['message_type'] = 'error';
    // Continue without pagination if count fails, or handle as desired
}

$totalPages = ceil($totalPayments / $limit);


// 2. Fetch payment history for this student with LIMIT and OFFSET
$fetchPaymentsApiUrl = $supabaseUrl . '/rest/v1/' . $supabasePaymentsTable . '?select=*&student_id=eq.' . urlencode($studentId) . '&order=payment_date.desc,payment_time.desc' . '&limit=' . $limit . '&offset=' . $offset;

$chPayments = curl_init($fetchPaymentsApiUrl);
curl_setopt($chPayments, CURLOPT_RETURNTRANSFER, true);
curl_setopt($chPayments, CURLOPT_HTTPHEADER, [
    'apikey: ' . $supabaseAnonKey,
    'Authorization: Bearer ' . $supabaseAnonKey
]);
curl_setopt($chPayments, CURLOPT_CAINFO, 'C:\Program Files\Ampps\php82\extras\ssl\cacert.pem');
$paymentsResponse = curl_exec($chPayments);
$paymentsHttpCode = curl_getinfo($chPayments, CURLINFO_HTTP_CODE);
$paymentsError = curl_error($chPayments);
curl_close($chPayments);

if ($paymentsHttpCode >= 200 && $paymentsHttpCode < 300) {
    $payments = json_decode($paymentsResponse, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $_SESSION['message'] = "Error decoding payment history from Supabase: " . json_last_error_msg();
        $_SESSION['message_type'] = 'error';
        $payments = [];
    }
} else {
    $_SESSION['message'] = "Error fetching payment history: HTTP " . $paymentsHttpCode . " - " . ($paymentsError ?: $paymentsResponse);
    $_SESSION['message_type'] = 'error';
}

// Calculate the running balance for each payment (using ALL payments, sorted chronologically)
// This logic was previously correct, but make sure to apply it only once and effectively.
// Instead of re-fetching all payments just for running balance, it's better to calculate
// it client-side if the entire set of payments for running balance calculation is already fetched,
// or adjust your API calls to get the running balance directly if Supabase supports it.
// For now, let's keep your existing logic for simplicity but acknowledge it's not the most efficient.
$fetchAllPaymentsApiUrl = $supabaseUrl . '/rest/v1/' . $supabasePaymentsTable . '?select=*&student_id=eq.' . urlencode($studentId) . '&order=payment_date.asc,payment_time.asc'; // Oldest first for calculation
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

$allStudentPayments = [];
if ($allPaymentsHttpCode >= 200 && $allPaymentsHttpCode < 300) {
    $allStudentPayments = json_decode($allPaymentsResponse, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        // Handle error, maybe log or set a message
    }
} else {
    // Handle error fetching all payments
}

// Calculate the total amount paid across ALL payments (for true initial balance)
$totalPaymentsMadeOverall = array_reduce($allStudentPayments, function($sum, $item) {
    return $sum + ($item['payment_amount'] ?? 0);
}, 0);

// Infer the original total amount the student was liable for, based on *current* balance and *all* payments made.
$totalOriginalAmountDue = ($student['current_balance'] ?? 0) + $totalPaymentsMadeOverall;

// Calculate the running balance for each payment (using ALL payments, sorted chronologically)
$runningBalance = $totalOriginalAmountDue;
foreach ($allStudentPayments as &$paymentItem) { // Use & to modify array elements directly
    $runningBalance -= ($paymentItem['payment_amount'] ?? 0);
    $paymentItem['balance_after_payment'] = $runningBalance;
}
unset($paymentItem); // Unset the reference after the loop

// Now, apply the `balance_after_payment` to the *paginated* `$payments` array.
// This requires finding the corresponding payment in `allStudentPayments` for each `$payment`.
$processedPayments = [];
foreach ($payments as $paginatedPayment) {
    $found = false;
    foreach ($allStudentPayments as $calculatedPayment) {
        if (($paginatedPayment['id'] ?? null) === ($calculatedPayment['id'] ?? null)) {
            $processedPayments[] = $calculatedPayment; // Use the payment with calculated balance
            $found = true;
            break;
        }
    }
    if (!$found) {
        $processedPayments[] = $paginatedPayment; // Fallback if not found (shouldn't happen if IDs match)
    }
}
$payments = $processedPayments; // Update $payments with the ones that have `balance_after_payment`

// Sort payments back to descending order (newest first) for table display
usort($payments, function($a, $b) {
    $timeA = strtotime(($a['payment_date'] ?? '1970-01-01') . ' ' . ($a['payment_time'] ?? '00:00:00'));
    $timeB = strtotime(($b['payment_date'] ?? '1970-01-01') . ' ' . ($b['payment_time'] ?? '00:00:00'));
    return $timeB <=> $timeA; // Sort descending by datetime
});


// Define current_page for sidebar highlighting
$current_page = basename($_SERVER['PHP_SELF']);
$is_payments_active = in_array($current_page, ['new_payments.php', 'payments.php', 'view_payments_history.php']);
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
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flowbite@1.4.0/dist/flowbite.min.css">
</head>
<style>
    body {
        font-family: 'Inter', sans-serif;
        background-color: #f8fafc; /* Lighter background for a cleaner feel */
        color: #334155; /* Darker text for readability */
    }
    .content-header h1 {
        margin: 0;
        font-size: 20px;
    }
    /* Custom styles for messages */
    .message {
        padding: 1rem;
        margin-bottom: 1rem;
        border-radius: 0.375rem;
    }
    .message.info { background-color: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
    .message.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    .message.warning { background-color: #fff3cd; color: #664d03; border: 1px solid #ffecb5; }
    .message.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }

    /* Modal specific styles */
    .modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 1000;
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.3s ease-in-out, visibility 0.3s ease-in-out;
    }

    .modal-overlay.show {
        opacity: 1;
        visibility: visible;
    }

    .modal-container {
        background-color: white;
        padding: 2rem;
        border-radius: 0.5rem;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        width: 90%;
        max-width: 500px;
        transform: translateY(-20px);
        transition: transform 0.3s ease-in-out;
    }

    .modal-overlay.show .modal-container {
        transform: translateY(0);
    }
</style>
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
                        <div class="col-sm-6 text-white font-semibold">
                            <h1>Payment History</h1>
                        </div>
                        <div class="col-sm-6">
                            <ol class="breadcrumb float-sm-right">
                                <li class="breadcrumb-item active text-white"></li>
                            </ol>
                        </div>
                    </div>
                </section>

                <main class="container mx-auto p-4">
                    <?php
    if (isset($_SESSION['message'])) {
        $messageClass = $_SESSION['message_type'] ?? 'info';
        echo '<div class="message ' . htmlspecialchars($messageClass) . '">' . htmlspecialchars($_SESSION['message']) . '</div>';
        unset($_SESSION['message']);
        unset($_SESSION['message_type']);
    }
    ?>
                    <div class="mb-5 flex justify-end"> <a href="add_payment.php?student_id=<?php echo htmlspecialchars($student['id'] ?? ''); ?>" class="mr-2 font-semibold inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-green-700 hover:bg-green-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                <i class="fas fa-plus-circle mr-2"></i> Add New
                            </a>
                            <a href="generate_assessment_pdf.php?student_id=<?php echo htmlspecialchars($student['id'] ?? ''); ?>" target="_blank" class="font-semibold inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-yellow-600 hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500">
        <i class="fas fa-file-pdf mr-2"></i> Assessment Form
    </a>
                    </div>
    <?php if ($student): ?>
        <div class="flex flex-col lg:flex-row gap-8">
            <div class="lg:w-1/3 bg-white p-6 rounded-lg shadow-md border border-gray-200 mb-6 lg:mb-0">
                <h1 class="text-lg font-bold mb-6 text-gray-800">Student Details</h1>
                <div class="min-w-full p-2 bg-gray-200 mb-4">
                <h2 class="text-2xl font-bold text-gray-600"><?php echo htmlspecialchars($student['last_name'] ?? '') . ', ' . htmlspecialchars($student['given_name'] ?? '') . ' ' . htmlspecialchars($student['middle_initial'] ?? ''); ?></h2>
                </div>
                <dl class="divide-y divide-gray-200">
                    <div class="py-2 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-0">
                        <dt class="text-sm font-medium text-gray-600 font-semibold uppercase tracking-wider">Course</dt>
                        <dd class="mt-1 text-base font-semibold text-gray-500 sm:col-span-2 sm:mt-0"><?php echo htmlspecialchars($student['course'] ?? ''); ?></dd>
                    </div>
                    <div class="py-2 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-0">
                        <dt class="text-sm font-medium text-gray-600 font-semibold uppercase tracking-wider">Year Level</dt>
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
                    <div class="py-2 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-0">
                        <dt class="text-sm font-medium text-gray-600 font-semibold uppercase tracking-wider">Current Balance</dt>
                        <dd class="mt-1 text-xl font-bold sm:col-span-2 sm:mt-0 text-red-600">₱<?php echo number_format($student['current_balance'] ?? 0, 2); ?></dd>
                    </div>
                    <div class="py-2 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-0">
                        <dt class="text-sm font-medium text-gray-600 font-semibold uppercase tracking-wider">Account Status</dt>
                        <dd class="uppercase mt-1 text-base font-semibold sm:col-span-2 sm:mt-0 <?php echo ($student['account_status'] === 'Fully Paid') ? 'text-green-600' : 'text-orange-500'; ?>"><?php echo htmlspecialchars($student['account_status'] ?? ''); ?></dd>
                    </div>
                </dl>
            </div>

            <div class="lg:w-3/4 bg-white shadow-md rounded-lg overflow-hidden border border-gray-200">
                <h1 class="text-lg font-bold mb-6 ml-2 text-gray-800 p-4 pb-0">Payment Details</h1> <?php if (!empty($payments)): ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">OR Number</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Remaining Balance</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment Type</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"></th> </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($payments as $index => $payment): ?>
                                    <tr class="<?php echo ($index % 2 == 0) ? 'bg-white' : 'bg-gray-50'; ?>">
    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 uppercase">
        <?php echo htmlspecialchars(date('F j, Y', strtotime($payment['payment_date'] ?? ''))); ?>
    </td>
    <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900"><?php echo htmlspecialchars($payment['or_number'] ?? ''); ?></td>
    <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold">₱<?php echo number_format($payment['payment_amount'] ?? 0, 2); ?></td>
    <td class="px-6 py-4 whitespace-nowrap text-sm
        <?php
        // Check if balance_after_payment is effectively zero (or very close to it)
        if (isset($payment['balance_after_payment']) && floatval($payment['balance_after_payment']) <= 0.001) { // Using a small epsilon for floating point comparison
            echo 'text-green-500';
        } else {
            echo 'text-red-500';
        }
        ?>
        font-semibold">
        ₱<?php echo number_format($payment['balance_after_payment'] ?? 0, 2); ?>
    </td>
    <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold
        <?php
        // Determine the class based on balance_after_payment for the Status
        if (isset($payment['balance_after_payment']) && floatval($payment['balance_after_payment']) <= 0.001) {
            echo 'text-green-600'; // Stronger green for status
        } else {
            echo 'text-red-600'; // Stronger red for status
        }
        ?>">
        <?php
        // Determine the text based on balance_after_payment for the Status
        if (isset($payment['balance_after_payment']) && floatval($payment['balance_after_payment']) <= 0.001) {
            echo 'FULLY PAID';
        } else {
            echo 'PARTIAL PAYMENT';
        }
        ?>
    </td>
    <td class="px-6 py-4 whitespace-nowrap text-right text-lg font-medium">
        <button
            class="edit-payment-btn text-green-600 hover:text-green-900 font-semibold py-1 px-2 rounded-md transition duration-150 ease-in-out"
            data-payment-id="<?php echo htmlspecialchars($payment['id'] ?? ''); ?>"
            data-or-number="<?php echo htmlspecialchars($payment['or_number'] ?? ''); ?>"
            data-payment-amount="<?php echo htmlspecialchars($payment['payment_amount'] ?? ''); ?>"
            data-payment-date="<?php echo htmlspecialchars($payment['payment_date'] ?? ''); ?>"
            data-student-id="<?php echo htmlspecialchars($student['id'] ?? ''); ?>"
        >
            <i class="fa-solid fa-square-pen"></i>
        </button>
    </td>
</tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($totalPages > 1): ?>
                        <nav class="flex items-center justify-between p-4 border-t border-gray-200 bg-white" aria-label="Pagination">
                            <div class="flex-1 flex justify-between sm:justify-end">
                                <?php if ($page > 1): ?>
                                    <a href="?student_id=<?php echo htmlspecialchars($studentId); ?>&page=<?php echo $page - 1; ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                        Previous
                                    </a>
                                <?php endif; ?>
                                <?php if ($page < $totalPages): ?>
                                    <a href="?student_id=<?php echo htmlspecialchars($studentId); ?>&page=<?php echo $page + 1; ?>" class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                        Next
                                    </a>
                                <?php endif; ?>
                            </div>
                            <div class="hidden sm:flex sm:items-center">
                                <p class="text-sm text-gray-700">
                                    Showing
                                    <span class="font-medium"><?php echo ($offset + 1); ?></span>
                                    to
                                    <span class="font-medium"><?php echo min($offset + $limit, $totalPayments); ?></span>
                                    of
                                    <span class="font-medium"><?php echo $totalPayments; ?></span>
                                    results
                                </p>
                            </div>
                            <div class="hidden sm:block">
                                <ul class="flex pl-0 rounded list-none flex-wrap">
                                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                        <li>
                                            <a href="?student_id=<?php echo htmlspecialchars($studentId); ?>&page=<?php echo $i; ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium <?php echo ($i === $page) ? 'bg-green-700 text-white' : 'text-gray-700 bg-white hover:bg-gray-50'; ?> rounded-md mx-1">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                </ul>
                            </div>
                        </nav>
                    <?php endif; ?>

                <?php else: ?>
                    <p class="p-6 text-center text-gray-500">No payment history found for this student.</p>
                <?php endif; ?>
            </div>
        </div> <?php else: ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
            <strong class="font-bold">Error!</strong>
            <span class="block sm:inline">Student details could not be loaded or ID was missing.</span>
        </div>
    <?php endif; ?>
</main>
            <script src="../node_modules/flowbite/dist/flowbite.min.js"></script>
            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

            <div id="editPaymentModal" class="modal-overlay hidden">
                <div class="modal-container">
                    <h2 class="text-xl font-bold mb-4 text-gray-800">Edit Payment</h2>
                    <form id="editPaymentForm" method="POST" action="edit_payment.php">
                        <input type="hidden" id="editPaymentId" name="payment_id">
                        <input type="hidden" id="editStudentId" name="student_id">
                        <input type="hidden" id="originalPaymentAmount" name="original_payment_amount">

                        <div class="mb-4">
                            <label for="editPaymentDate" class="block text-sm font-medium text-gray-700">Payment Date:</label>
                            <input type="date" id="editPaymentDate" name="payment_date" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" required>
                        </div>
                        <div class="mb-4">
                            <label for="editORNumber" class="block text-sm font-medium text-gray-700">OR Number:</label>
                            <input type="text" id="editORNumber" name="or_number" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" required>
                        </div>
                        <div class="mb-4">
                            <label for="editPaymentAmount" class="block text-sm font-medium text-gray-700">Payment Amount (₱):</label>
                            <input type="number" step="0.01" id="editPaymentAmount" name="payment_amount" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" required>
                        </div>
                        <div class="flex justify-end gap-3">
                            <button type="button" id="cancelEditBtn" class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                Cancel
                            </button>
                            <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const editPaymentModal = document.getElementById('editPaymentModal');
                    const cancelEditBtn = document.getElementById('cancelEditBtn');
                    const editPaymentForm = document.getElementById('editPaymentForm');

                    // Function to show the modal
                    function showModal() {
                        editPaymentModal.classList.remove('hidden');
                        setTimeout(() => editPaymentModal.classList.add('show'), 10); // Add 'show' after a slight delay for transition
                    }

                    // Function to hide the modal
                    function hideModal() {
                        editPaymentModal.classList.remove('show');
                        setTimeout(() => editPaymentModal.classList.add('hidden'), 300); // Hide after transition
                    }

                    // Event listener for all "Edit" buttons
                    document.querySelectorAll('.edit-payment-btn').forEach(button => {
                        button.addEventListener('click', function() {
                            const paymentId = this.dataset.paymentId;
                            const orNumber = this.dataset.orNumber;
                            const paymentAmount = this.dataset.paymentAmount;
                            const paymentDate = this.dataset.paymentDate;
                            const studentId = this.dataset.studentId;

                            // Populate the modal form fields
                            document.getElementById('editPaymentId').value = paymentId;
                            document.getElementById('editStudentId').value = studentId; // Pass student_id
                            document.getElementById('editORNumber').value = orNumber;
                            document.getElementById('editPaymentAmount').value = paymentAmount;
                            document.getElementById('editPaymentDate').value = paymentDate;
                            document.getElementById('originalPaymentAmount').value = paymentAmount; // Store original amount

                            showModal();
                        });
                    });

                    // Event listener for the "Cancel" button in the modal
                    cancelEditBtn.addEventListener('click', function() {
                        hideModal();
                    });

                    // Event listener for clicking outside the modal to close it
                    editPaymentModal.addEventListener('click', function(event) {
                        if (event.target === editPaymentModal) {
                            hideModal();
                        }
                    });

                    // Handle form submission via AJAX
                    editPaymentForm.addEventListener('submit', function(e) {
                        e.preventDefault(); // Prevent default form submission

                        const formData = new FormData(this);

fetch(this.action, {
    method: 'POST',
    body: formData
})
.then(response => response.json())
.then(data => {
    if (data.success) {
        Swal.fire({
            toast: true, // This makes it a toast
            icon: 'success',
            title: data.message, // For toasts, the message is usually the title
            position: 'top-end', // You can change the position (e.g., 'top-start', 'bottom-end', 'center')
            showConfirmButton: false, // Toasts generally don't have a confirm button
            timer: 2000, // How long the toast stays visible (in milliseconds)
            timerProgressBar: true, // Shows a progress bar for the timer
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer);
                toast.addEventListener('mouseleave', Swal.resumeTimer);
            }
        }).then(() => {
            // Actions to perform after the toast closes (optional, as toasts are less intrusive)
            hideModal(); // Assuming this function hides some modal
            window.location.reload(); // Reload the page to show updated data
        });
    } else {
        // Handle error case (e.g., show an error toast)
        Swal.fire({
            toast: true,
            icon: 'error',
            title: data.message || 'Something went wrong!', // Use data.message if available, otherwise a generic error
            position: 'top-end',
            showConfirmButton: false,
            timer: 5000,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer);
                toast.addEventListener('mouseleave', Swal.resumeTimer);
            }
        });
    }
})
.catch(error => {
    console.error('Error:', error);
    Swal.fire({
        toast: true,
        icon: 'error',
        title: 'Network error or server unreachable.',
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer);
            toast.addEventListener('mouseleave', Swal.resumeTimer);
        }
    });
});
                    });
                });
            </script>
            <script src="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.js"></script>
            <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
            <script src="sweetalert2/sweetalert2.all.min.js"></script>
            <script src="jsQR-master/dist/jsQR.js"></script>
            <script src="sweetalert2/sweetalert2.min.js"></script>
            <script src="Admin LTE/plugins/jquery/jquery.min.js"></script>
            <script src="Admin LTE/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
            <script src="Admin LTE/dist/js/adminlte.min.js"></script>
            <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
            <script src="../node_modules/flowbite/dist/flowbite.min.js"></script>
</body>
</html>