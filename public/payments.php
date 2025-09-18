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
$supabasePaymentsTable = 'payments';
$supabaseStudentsTable = 'students_account'; // Needed to fetch student names

$allPayments = [];
$studentsLookup = []; // To store student names for display
$groupedPayments = []; // To store payments grouped by student_id

// --- 1. Fetch all payments ---
$fetchPaymentsApiUrl = $supabaseUrl . '/rest/v1/' . $supabasePaymentsTable . '?select=*,student_id';
$chPayments = curl_init($fetchPaymentsApiUrl);
curl_setopt($chPayments, CURLOPT_RETURNTRANSFER, true);
curl_setopt($chPayments, CURLOPT_HTTPHEADER, [
    'apikey: ' . $supabaseAnonKey,
    'Authorization: Bearer ' . $supabaseAnonKey
]);
curl_setopt($chPayments, CURLOPT_CAINFO, 'C:\Program Files\Ampps\php82\extras\ssl\cacert.pem'); // Path for AMPPS
$paymentsResponse = curl_exec($chPayments);
$paymentsHttpCode = curl_getinfo($chPayments, CURLINFO_HTTP_CODE);
$paymentsError = curl_error($chPayments);
curl_close($chPayments);

if ($paymentsHttpCode >= 200 && $paymentsHttpCode < 300) {
    $allPayments = json_decode($paymentsResponse, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $_SESSION['message'] = "Error decoding all payments from Supabase: " . json_last_error_msg();
        $_SESSION['message_type'] = 'error';
        $allPayments = [];
    }
} else {
    $_SESSION['message'] = "Error fetching all payments: HTTP " . $paymentsHttpCode . " - " . ($paymentsError ?: $paymentsResponse);
    $_SESSION['message_type'] = 'error';
}

// --- 2. Fetch student names for display ---
$studentIds = array_unique(array_column($allPayments, 'student_id'));

if (!empty($studentIds)) {
    $fetchStudentsApiUrl = $supabaseUrl . '/rest/v1/' . $supabaseStudentsTable . '?select=id,given_name,middle_initial,last_name&id=in.(' . implode(',', $studentIds) . ')';
    $chStudents = curl_init($fetchStudentsApiUrl);
    curl_setopt($chStudents, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($chStudents, CURLOPT_HTTPHEADER, [
        'apikey: ' . $supabaseAnonKey,
        'Authorization: Bearer ' . $supabaseAnonKey
    ]);
    curl_setopt($chStudents, CURLOPT_CAINFO, 'C:\Program Files\Ampps\php82\extras\ssl\cacert.pem'); // Path for AMPPS
    $studentsResponse = curl_exec($chStudents);
    $studentsHttpCode = curl_getinfo($chStudents, CURLINFO_HTTP_CODE);
    $studentsError = curl_error($chStudents);
    curl_close($chStudents);

    if ($studentsHttpCode >= 200 && $studentsHttpCode < 300) {
        $studentsData = json_decode($studentsResponse, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($studentsData)) {
            foreach ($studentsData as $student) {
                $fullName = htmlspecialchars($student['given_name'] ?? '') . ' ';
                if (!empty($student['middle_initial'])) {
                    $fullName .= htmlspecialchars($student['middle_initial']) . ' ';
                }
                $fullName .= htmlspecialchars($student['last_name'] ?? '');
                $studentsLookup[$student['id']] = trim($fullName);
            }
        } else {
            $_SESSION['message'] = "Warning: Error decoding student names from Supabase: " . json_last_error_msg();
            $_SESSION['message_type'] = 'warning';
        }
    } else {
        $_SESSION['message'] = "Warning: Error fetching some student names: HTTP " . $studentsHttpCode . " - " . ($studentsError ?: $studentsResponse);
        $_SESSION['message_type'] = 'warning';
    }
}

// --- 3. Group payments by student_id ---
foreach ($allPayments as $payment) {
    $student_id = $payment['student_id'];
    if (!isset($groupedPayments[$student_id])) {
        $groupedPayments[$student_id] = [
            'student_name' => $studentsLookup[$student_id] ?? 'N/A',
            'payments' => []
        ];
    }
    $groupedPayments[$student_id]['payments'][] = $payment;
}

$current_page = basename($_SERVER['PHP_SELF']);
$is_payments_active = in_array($current_page, ['new_payments.php', 'payments.php', 'view_payment_history.php']);
$groupedPaymentsForJs = array_values($groupedPayments);
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    
    <link rel="stylesheet" href="sweetalert2/sweetalert2.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flowbite@1.4.0/dist/flowbite.min.css">
</head>
<style>
    body {
        font-family: 'Inter', sans-serif;
        background-color: #f8fafc;
        color: #334155;
    }
    .content-header h1 {
        margin: 0;
        font-size: 20px;
    }
    .message {
        padding: 1rem;
        margin-bottom: 1rem;
        border-radius: 0.375rem;
    }
    .message.info { background-color: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
    .message.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    .message.warning { background-color: #fff3cd; color: #664d03; border: 1px solid #ffecb5; }
    .message.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }

   /* Adjust row height and add borders for DataTables */
    #paymentsTable.dataTable thead th,
    #paymentsTable.dataTable tbody td {
        padding-top: 12px !important; /* Make rows taller */
        padding-bottom: 12px !important; /* Make rows taller */
        border: 1px solid #e2e8f0 !important; /* Subtle border (Tailwind gray-200) */
    }

    /* Ensure table borders collapse for a clean look */
    #paymentsTable.dataTable {
        border-collapse: collapse !important;
        width: 100% !important; /* Ensure table takes full width */
    }

    /* Spacing for DataTables controls (search and show entries) */
    .dataTables_wrapper .dataTables_length,
    .dataTables_wrapper .dataTables_filter {
        padding: 8px 16px !important; /* Add padding around these elements */
        margin-bottom: 0px !important; /* Space below search/entries controls */
    }

    /* Space specifically between the search/entries section and the table */
    .dataTables_wrapper .dataTables_scrollBody,
    .dataTables_wrapper .dataTables_info {
        margin-top: 16px !important; /* Pushes the table/info down from the controls */
    }

    /* Ensure the search input and select elements have some spacing */
    /* Ensure the search input and select elements have some spacing */
    .dataTables_wrapper .dataTables_filter input,
    .dataTables_wrapper .dataTables_length select {
        padding: 6px 10px !important; /* Add padding inside input/select */
        border: 1px solid #d1d5db !important; /* Tailwind gray-300 */
        border-radius: 0.25rem !important; /* Tailwind rounded-md */
    }
    /* Optional: Style for the pagination controls */
    .dataTables_wrapper .dataTables_paginate .paginate_button {
        padding: 6px 12px !important;
        margin-left: 5px !important;
        border: 1px solid #d1d5db !important;
        border-radius: 0.25rem !important;
        background-color: #f9fafb !important; /* Tailwind gray-50 */
    }

    .dataTables_wrapper .dataTables_paginate .paginate_button.current {
        background-color:rgb(60, 113, 22) !important; /* Tailwind blue-500 */
        color: rgb(242, 242, 242) !important;
        border-color:rgb(251, 253, 251) !important;
    }
    body {
        font-family: 'Inter', sans-serif;
        background-color: #f8fafc; /* Lighter background for a cleaner feel */
        color: #334155; /* Darker text for readability */
    }
    .no-underline-hover:hover {
        text-decoration: none !important;
    }

    /* Custom styles for expandable rows */
    tr.details-row {
        background-color: #f0f4f8; /* Slightly different background for detail rows */
    }
    tr.details-row td {
        padding: 0 !important; /* Remove padding from the detail row's td */
    }
    div.slider {
        display: none;
        padding: 10px 20px;
        background-color: #f0f4f8; /* Background for the content inside the slider */
        border-top: 1px solid #e2e8f0;
    }
    /* Style for the nested table */
    .nested-payments-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 10px;
        margin-bottom: 10px;
        color: #374151;
    }
    .nested-payments-table th,
    .nested-payments-table td {
        padding: 8px 12px;
        border: 1px solid #cbd5e1; /* Tailwind gray-300 */
        text-align: left;
        font-size: 0.875rem;
    }
    .nested-payments-table th {
        background-color: #e2e8f0; /* Tailwind gray-200 */
        font-weight: 600;
        color: #475569; /* Tailwind gray-600 */
        text-transform: uppercase;
    }
    .nested-payments-table td:nth-child(2) { /* Targets the second column (Amount) */
        font-weight: bold; /* Make the amount bold */
        color: #1f2937; /* A very dark gray, similar to Tailwind's text-gray-900 */
    }
    .nested-payments-table td {
        color:rgb(78, 93, 117); /* Tailwind gray-700 */
        font-weight: bold;
        text-transform: uppercase;
    }
</style>
<body>
    <div class="flex h-screen bg-gray-100">

        <button data-drawer-target="default-sidebar" data-drawer-toggle="default-sidebar" aria-controls="default-sidebar" type="button" class="inline-flex items-center p-2 mt-2 ms-3 text-sm text-gray-500 rounded-lg sm:hidden hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-gray-200 dark:text-gray-400 dark:hover:bg-gray-700 dark:focus:ring-600">
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
                            <h1>Payment List</h1>
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
                    // Display session messages
                    if (isset($_SESSION['message'])) {
                        $messageClass = $_SESSION['message_type'] ?? 'info';
                        echo '<div class="message ' . htmlspecialchars($messageClass) . '">' . htmlspecialchars($_SESSION['message']) . '</div>';
                        unset($_SESSION['message']);
                        unset($_SESSION['message_type']);
                    }
                    ?>

                    <?php if (!empty($groupedPayments)): ?>
                        <table id="paymentsTable" class="min-w-full divide-y divide-gray-200 shadow-sm rounded-lg">
                            <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-md font-medium text-gray-500 uppercase tracking-wider"></th>
                                <th scope="col" class="px-6 py-3 text-left text-md font-medium text-gray-500 uppercase tracking-wider">Student Name</th>
                                <th scope="col" class="px-6 py-3 text-left text-md font-medium text-gray-500 uppercase tracking-wider">Total Payments</th>
                                <th scope="col" class="px-6 py-3 text-left text-md font-medium text-gray-500 uppercase tracking-wider">Last Payment Date</th>
                            </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($groupedPayments as $student_id => $data): ?>
                                    <tr class="main-row">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            <?php if (count($data['payments']) > 1): ?>
                                                <button class="details-control text-gray-500 hover:text-gray-700 focus:outline-none" data-student-id="<?php echo $student_id; ?>">
                                                    <i class="fa-solid fa-chevron-right expand-icon"></i>
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-600">
                                            <?php echo htmlspecialchars($data['student_name']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-600">
                                            <?php echo count($data['payments']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-600 uppercase">
                                            <?php
                                            // Find the latest payment date for the grouped student
                                            $latestPaymentDate = null;
                                            foreach ($data['payments'] as $payment) {
                                                if ($latestPaymentDate === null || strtotime($payment['payment_date']) > strtotime($latestPaymentDate)) {
                                                    $latestPaymentDate = $payment['payment_date'];
                                                }
                                            }
                                            echo htmlspecialchars(date('F j, Y', strtotime($latestPaymentDate ?? '')));
                                            ?>
                                        </td>
                                        </tr>

                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="p-6 text-center text-gray-500">No payments recorded yet.</p>
                    <?php endif; ?>
                </main>
            </main>
            
            <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
            
            <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
            
            <script src="sweetalert2/sweetalert2.all.min.js"></script>
            
            <script src="../node_modules/flowbite/dist/flowbite.min.js"></script>

           <script>
    // Function to format the details for the child row
    function format(d) {
        var payments = d.payments; // Access the nested payments array
        if (!payments || payments.length === 0) {
            return '<div class="slider p-4"><p class="text-gray-600">No detailed payment records available.</p></div>';
        }

        var html = '<div class="slider p-4">' +
                    '<table class="nested-payments-table">' +
                    '<thead>' +
                    '<tr>' +
                    '<th>OR Number</th>' +
                    '<th>Amount</th>' +
                    '<th>Payment Date</th>' +
                    '</tr>' +
                    '</thead>' +
                    '<tbody>';

        payments.forEach(function(payment) {
            var paymentDate = new Date(payment.payment_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
            var createdAt = new Date(payment.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) + ' at ' +
                                    new Date(payment.created_at).toLocaleTimeString('en-US', { hour: 'numeric', minute: 'numeric', hour12: true });

            html += '<tr>' +
                    '<td>' + (payment.or_number || '') + '</td>' +
                    '<td>₱' + parseFloat(payment.payment_amount || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '</td>' +
                    '<td>' + paymentDate + '</td>' +
                    '</tr>';
        });

        html += '</tbody></table></div>';
        return html;
    }

    $(document).ready(function() {
        if ($.fn.DataTable.isDataTable('#paymentsTable')) {
            $('#paymentsTable').DataTable().destroy();
        }

        var groupedPaymentsJs = <?php echo json_encode($groupedPaymentsForJs); ?>;

        var table = $("#paymentsTable").DataTable({
            "responsive": true,
            "lengthChange": false,
            "autoWidth": false,
            "searching": true,
            "paging": true,
            "ordering": true,
            "info": true,
            "pageLength": 10,
            "dom": 'frtip',
            "columnDefs": [
                { "orderable": false, "targets": [0] }, // Expand/collapse column - no change needed

                // ADDED: Apply bold font to the "Student Name" column (index 1)
                {
                    "className": "px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-600", // Tailwind classes for bold and very dark gray text
                    "targets": [1] // Apply to the "Student Name" column
                },
                {
                    "className": "px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-600", // Total Payments: right align, bold, dark gray
                    "targets": [2]
                },
                {
                    "className": "px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-600", // Last Payment Date: right align, standard gray
                    "targets": [3]
                }
            ],
            "data": groupedPaymentsJs,
            "columns": [
                {
                    "className": 'dt-control',
                    "orderable": false,
                    "data": null,
                    "defaultContent": '<button class="details-control text-gray-500 hover:text-gray-700 focus:outline-none"></i></button>'
                },
                { "data": "student_name" },
                {
                    "data": "payments",
                    "render": function (data, type, row) {
                        return data.length;
                    }
                },
                {
                    "data": "payments",
                    "render": function (data, type, row) {
                        if (!data || data.length === 0) {
                            return 'N/A';
                        }
                        var latestDate = null;
                        data.forEach(function(payment) {
                            var currentDate = new Date(payment.payment_date);
                            if (latestDate === null || currentDate > latestDate) {
                                latestDate = currentDate;
                            }
                        });
                        return latestDate ? latestDate.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : 'N/A';
                    }
                }
            ]
        });

        // Add event listener for opening and closing details (click on the first column's cell)
        $('#paymentsTable tbody').on('click', 'td.dt-control', function () {
            var tr = $(this).closest('tr');
            var row = table.row(tr);
            var icon = $(this).find('.expand-icon');

            if (row.child.isShown()) {
                // This row is already open - close it
                $('div.slider', row.child()).slideUp(function() {
                    row.child.hide();
                    tr.removeClass('details');
                    if (icon.length) {
                        icon.removeClass('fa-chevron-down').addClass('fa-chevron-right');
                    }
                });
            } else {
                // Open this row
                row.child(format(row.data())).show(); // Call the format function with row data
                tr.addClass('details');
                $('div.slider', row.child()).slideDown();
                if (icon.length) {
                    icon.removeClass('fa-chevron-right').addClass('fa-chevron-down');
                }
            }
        });
    });
</script>
        </body>
</html>