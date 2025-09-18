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

$students = [];
$searchQuery = $_GET['search'] ?? '';

// Build the API URL with search filters
$apiUrl = $supabaseUrl . '/rest/v1/' . $supabaseTable . '?select=*';

if (!empty($searchQuery)) {
    // Basic search on last_name, given_name, middle_initial, course
    // Using `ilike` for case-insensitive partial match
    // Note: Supabase REST API filters are applied with `&`
    $searchQueryEncoded = urlencode('%' . $searchQuery . '%');
    $apiUrl .= '&or=(last_name.ilike.' . $searchQueryEncoded . ',given_name.ilike.' . $searchQueryEncoded . ',middle_initial.ilike.' . $searchQueryEncoded . ',course.ilike.' . $searchQueryEncoded . ')';
}

// Order by last_name and given_name
$apiUrl .= '&order=last_name.asc,given_name.asc';

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
    $students = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $_SESSION['message'] = "Error decoding JSON from Supabase: " . json_last_error_msg();
        $_SESSION['message_type'] = 'error';
        $students = [];
    }
} else {
    $_SESSION['message'] = "Error fetching students from Supabase: HTTP " . $httpCode . " - " . ($error ?: $response);
    $_SESSION['message_type'] = 'error';
}
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
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <link rel="stylesheet" href="Admin LTE/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="Admin LTE/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="sweetalert2/sweetalert2.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<style>
    /* Custom styles for the table */
    .action-link {
        color: #3B82F6; /* Tailwind blue-500 for example */
        text-decoration: none;
        margin-right: 8px; /* Space between links */
    }
    .action-link:hover {
        text-decoration: underline;
    }

    /* Adjust row height and add borders for DataTables */
    #employeeDataTable.dataTable thead th,
    #employeeDataTable.dataTable tbody td {
        padding-top: 12px !important; /* Make rows taller */
        padding-bottom: 12px !important; /* Make rows taller */
        border: 1px solid #e2e8f0 !important; /* Subtle border (Tailwind gray-200) */
    }

    /* Ensure table borders collapse for a clean look */
    #employeeDataTable.dataTable {
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
        background-color:rgb(60, 113, 22) !important; /* Tailwind green */
        color: rgb(242, 242, 242) !important;
        border-color:rgb(251, 253, 251) !important;
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
                            <h1>Students Data</h1>
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
                        echo '<div class="message ' . htmlspecialchars($messageClass) . '">' . htmlspecialchars($_SESSION['message']) . '</div>';
                        unset($_SESSION['message']);
                        unset($_SESSION['message_type']);
                    }
                    ?>
<?php if (!empty($students)): ?>
    <div class="mb-1">
        <h3 class="uppercase tracking-wider font-semibold text-gray-600 dark:text-white">Filter by</h3>
    </div>

    <div class="mb-4 flex justify-between items-center flex-wrap"> 
        <div class="flex space-x-4 flex-wrap"> 
            <div>
                <select id="courseFilter" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-1.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500">
                    <option value="">All Courses</option>
                    <?php
                    // Collect unique courses for the filter dropdown
                    $uniqueCourses = [];
                    foreach ($students as $student) {
                        if (!empty($student['course'])) {
                            $uniqueCourses[htmlspecialchars($student['course'])] = htmlspecialchars($student['course']);
                        }
                    }
                    sort($uniqueCourses); // Sort courses alphabetically
                    foreach ($uniqueCourses as $course) {
                        echo '<option value="' . $course . '">' . $course . '</option>';
                    }
                    ?>
                </select>
            </div>

            <div>
                <select id="yearLevelFilter" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-1.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500">
                    <option value="">All Year Levels</option>
                    <?php
                    // Collect unique year levels for the filter dropdown
                    $uniqueYearLevels = [];
                    foreach ($students as $student) {
                        if (isset($student['year_level'])) {
                            $yearLevelValue = trim($student['year_level']); // Trim whitespace

                            // Normalize values and ensure they are valid
                            $normalizedYearLevel = '';
                            $displayLabel = '';

                            // Attempt to convert to integer for robust checking and ordering
                            $numericYear = (int) $yearLevelValue;

                            if ($numericYear >= 1 && $numericYear <= 4) {
                                // If it's a valid number 1-4
                                $normalizedYearLevel = (string)$numericYear; // Use the number as value
                                // Map numeric year to display string
                                switch ($numericYear) {
                                    case 1: $displayLabel = 'First Year'; break;
                                    case 2: $displayLabel = 'Second Year'; break;
                                    case 3: $displayLabel = 'Third Year'; break;
                                    case 4: $displayLabel = 'Fourth Year'; break;
                                }
                            } else {
                                // Handle specific string cases if they are legitimate but not numeric
                                // Or you can choose to skip them if they are truly "other characters"
                                if (strtolower($yearLevelValue) === 'first year') {
                                    $normalizedYearLevel = '1'; $displayLabel = 'First Year';
                                } elseif (strtolower($yearLevelValue) === 'second year') {
                                    $normalizedYearLevel = '2'; $displayLabel = 'Second Year';
                                } elseif (strtolower($yearLevelValue) === 'third year') {
                                    $normalizedYearLevel = '3'; $displayLabel = 'Third Year';
                                } elseif (strtolower($yearLevelValue) === 'fourth year') {
                                    $normalizedYearLevel = '4'; $displayLabel = 'Fourth Year';
                                }

                                // If it's still not normalized, it's an invalid entry, so skip it
                                if (empty($normalizedYearLevel)) {
                                    continue; // Skip this iteration if it's an unrecognized, invalid value
                                }
                            }

                            // Add to unique list using the normalized value as the key
                            $uniqueYearLevels[$normalizedYearLevel] = $displayLabel;
                        }
                    }

                    // Sort year levels numerically
                    ksort($uniqueYearLevels, SORT_NUMERIC);

                    foreach ($uniqueYearLevels as $value => $label) {
                        echo '<option value="' . htmlspecialchars($value) . '">' . htmlspecialchars($label) . '</option>';
                    }
                    ?>
                </select>
            </div>

            <div>
                <select id="semesterFilter" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-1.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500">
                    <option value="">All Semesters</option>
                    <?php
                    // Collect unique semesters for the filter dropdown
                    $uniqueSemesters = [];
                    foreach ($students as $student) {
                        if (!empty($student['semester'])) {
                            $semesterValue = htmlspecialchars($student['semester']);
                            $displayLabel = $semesterValue; // Start with the value
                            if ($semesterValue === '1ST' || $semesterValue === '2ND' || $semesterValue === 'SUMMER') {
                                $displayLabel .= ' SEM'; // Add ' SEM' for these specific values
                            }
                            $uniqueSemesters[$semesterValue] = $displayLabel;
                        }
                    }
                    uksort($uniqueSemesters, function($a, $b) {
                        $order = ['1ST', '2ND', 'SUMMER'];
                        $posA = array_search($a, $order);
                        $posB = array_search($b, $order);

                        if ($posA === false && $posB === false) {
                            return strcmp($a, $b); // Fallback for unexpected values
                        } elseif ($posA === false) {
                            return 1; // $a is not in order, $b is
                        } elseif ($posB === false) {
                            return -1; // $b is not in order, $a is
                        } else {
                            return $posA - $posB;
                        }
                    });
                    foreach ($uniqueSemesters as $value => $label) {
                        echo '<option value="' . $value . '">' . $label . '</option>';
                    }
                    ?>
                </select>
            </div>

            <div>
                <select id="statusFilter" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-1.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500">
                    <option value="">Account Status</option>
                    <?php
                    // Collect unique statuses for the filter dropdown
                    $uniqueStatuses = [];
                    foreach ($students as $student) {
                        // *** CHANGE: Use 'account_status' instead of 'status' ***
                        if (!empty($student['account_status'])) {
                            $uniqueStatuses[htmlspecialchars($student['account_status'])] = htmlspecialchars($student['account_status']);
                        }
                    }
                    sort($uniqueStatuses); // Sort statuses alphabetically
                    foreach ($uniqueStatuses as $status) {
                        echo '<option value="' . $status . '">' . $status . '</option>';
                    }
                    ?>
                </select>
            </div>
        </div> <a href="javascript:void(0);" onclick="openModal('addStudentModal')" class="font-semibold inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-green-700 hover:bg-green-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
            <i class="fas fa-plus-circle mr-2"></i> Add New Student
        </a>
    </div> <table id="employeeDataTable" class="min-w-full divide-y divide-gray-200 shadow-md rounded-lg">
        <thead class="bg-gray-50">
            <tr>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Name</th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Given Name</th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">M.I.</th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Course</th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Year Level</th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Semester</th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">School Year</th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Initial Balance</th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Current Balance</th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"></th>
            </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
            <?php foreach ($students as $student): ?>
                <tr data-student-id="<?php echo htmlspecialchars($student['id']); ?>"
                    data-last-name="<?php echo htmlspecialchars($student['last_name'] ?? ''); ?>"
                    data-given-name="<?php echo htmlspecialchars($student['given_name'] ?? ''); ?>"
                    data-middle-initial="<?php echo htmlspecialchars($student['middle_initial'] ?? ''); ?>"
                    data-course="<?php echo htmlspecialchars($student['course'] ?? ''); ?>"
                    data-year-level="<?php echo htmlspecialchars($student['year_level'] ?? ''); ?>"
                    data-semester="<?php echo htmlspecialchars($student['semester'] ?? ''); ?>"
                    data-school-year="<?php echo htmlspecialchars($student['school_year'] ?? ''); ?>">
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-600"><?php echo htmlspecialchars($student['last_name'] ?? ''); ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-600"><?php echo htmlspecialchars($student['given_name'] ?? ''); ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-600"><?php echo htmlspecialchars($student['middle_initial'] ?? ''); ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-600"><?php echo htmlspecialchars($student['course'] ?? ''); ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-600 uppercase">
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
                                    echo htmlspecialchars($yearLevel); // Fallback
                                    break;
                            }
                        ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-600"><?php echo htmlspecialchars($student['semester'] ?? ''); ?> SEM</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-600"><?php echo htmlspecialchars($student['school_year'] ?? ''); ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-semibold">₱<?php echo number_format($student['initial_account_balance'] ?? 0, 2); ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold
                        <?php echo ($student['current_balance'] ?? 0) == 0 ? 'text-green-600' : 'text-red-600'; ?>">
                        ₱<?php echo number_format($student['current_balance'] ?? 0, 2); ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        <?php
                            $status = htmlspecialchars($student['account_status'] ?? '');
                            if ($status === 'Fully Paid') {
                                echo '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">' . $status . '</span>';
                            } elseif ($status === 'With Balance') {
                                echo '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">' . $status . '</span>';
                            } else {
                                echo $status; // Fallback
                            }
                        ?>
                    </td>
                    <td class="px-4 py-2 whitespace-nowrap text-right text-sm font-sm">
                        <div class="relative inline-block text-left">
                            <button type="button" class="inline-flex justify-center w-full rounded-md px-2 py-1 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-gray-100 focus:ring-indigo-500" id="options-menu-button" aria-expanded="true" aria-haspopup="true">
                                <i class="fa-solid fa-ellipsis-v"></i> 
                            </button>

                            <div class="origin-top-right absolute right-0 w-48 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 hidden" role="menu" aria-orientation="vertical" aria-labelledby="options-menu-button" tabindex="-1">
                                <div class="py-1" role="none">
                                    <a title="Edit Student" href="#" class="edit-student-btn action-link text-gray-700 block px-4 py-2 text-sm no-underline-hover hover:bg-gray-50" role="menuitem" tabindex="-1" id="menu-item-0">
                                        <i class="fa-solid fa-edit mr-2"></i>Edit Student
                                    </a>
                                    <a title="Add Payment" href="add_payment.php?student_id=<?php echo htmlspecialchars($student['id']); ?>" class="action-link text-gray-700 block px-4 py-2 text-sm no-underline-hover hover:bg-gray-50" role="menuitem" tabindex="-1" id="menu-item-1">
                                        <i class="fa-solid fa-plus mr-2"></i>Add Payment
                                    </a>
                                    <a title="View Payment History" href="view_payments_history.php?student_id=<?php echo htmlspecialchars($student['id']); ?>" class="action-link text-gray-700 block px-4 py-2 text-sm no-underline-hover hover:bg-gray-50" role="menuitem" tabindex="-1" id="menu-item-2">
                                        <i class="fa-solid fa-eye mr-2"></i>Payment History
                                    </a>
                                    <a title="Generate Assessment Form" href="generate_assessment_pdf.php?student_id=<?php echo htmlspecialchars($student['id'] ?? ''); ?>" target="_blank" class="action-link text-gray-700 block px-4 py-2 text-sm no-underline-hover hover:bg-gray-50" role="menuitem" tabindex="-1" id="menu-item-2">
                                        <i class="fas fa-file-pdf mr-2"></i>Assessment Form
                                    </a>
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php else: ?>
    <p class="no-results"><?php echo !empty($searchQuery) ? "No students found matching your search." : "No student data available. Please upload an Excel file."; ?></p>
<?php endif; ?>
</section>
</main>
</div>
</div>

<div id="editStudentModal" class="modal-overlay fixed inset-0 z-50 overflow-y-auto bg-gray-900 bg-opacity-50 flex items-center justify-center hidden">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-lg mx-auto my-8 p-6 relative">
        <div class="flex justify-between items-center pb-3 border-b border-green-200">
            <h3 class="text-lg font-semibold text-gray-600">Edit Student Details</h3>
            <button type="button" onclick="closeModal('editStudentModal')" class="text-gray-400 hover:text-gray-600">
                <span class="sr-only">Close modal</span>
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>

        <div class="py-4">
            <form id="editStudentForm" class="space-y-4"> 
                <input type="hidden" id="edit_student_id" name="id">

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label for="edit_last_name" class="block text-sm font-medium text-gray-700">Last Name:</label>
                        <input type="text" id="edit_last_name" name="last_name" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500 sm:text-sm" required>
                    </div>
                    <div>
                        <label for="edit_given_name" class="block text-sm font-medium text-gray-700">Given Name:</label>
                        <input type="text" id="edit_given_name" name="given_name" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500 sm:text-sm" required>
                    </div>
                    <div>
                        <label for="edit_middle_initial" class="block text-sm font-medium text-gray-700">Middle Initial:</label>
                        <input type="text" id="edit_middle_initial" name="middle_initial" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500 sm:text-sm">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="edit_course" class="block text-sm font-medium text-gray-700">Course:</label>
                        <input type="text" id="edit_course" name="course" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500 sm:text-sm" required>
                    </div>
                    <div>
                        <label for="edit_year_level" class="block text-sm font-medium text-gray-700">Year Level:</label>
                        <select id="edit_year_level" name="year_level" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500 sm:text-sm" required>
                            <option value="">Select Year Level</option>
                            <option value="1">First Year</option>
                            <option value="2">Second Year</option>
                            <option value="3">Third Year</option>
                            <option value="4">Fourth Year</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-1">
                    <div>
                        <label for="edit_semester" class="block text-sm font-medium text-gray-700">Semester:</label>
                        <select id="edit_semester" name="semester" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500 sm:text-sm" required>
                            <option value="">Select Semester</option>
                            <option value="1ST">1st SEM</option>
                            <option value="2ND">2nd SEM</option>
                            <option value="SUMMER">SUMMER</option>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-1">
                    <div>
                        <label for="edit_school_year" class="block text-sm font-medium text-gray-700">School Year:</label>
                        <input type="text" id="edit_school_year" name="school_year" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500 sm:text-sm" required>
                    </div>
                </div>

                <div class="flex justify-end pt-4 border-t border-gray-200">
                    <button type="button" onclick="closeModal('editStudentModal')" class="mr-2 px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">Cancel</button>
                    <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-green-700 rounded-md hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="addStudentModal" class="modal-overlay fixed inset-0 z-50 overflow-y-auto bg-gray-900 bg-opacity-50 flex items-center justify-center hidden">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-lg mx-auto my-8 p-6 relative">
        <div class="flex justify-between items-center pb-3 border-b border-green-200">
            <h3 class="text-lg font-semibold text-gray-600">Add New Student</h3>
            <button type="button" onclick="closeModal('addStudentModal')" class="text-gray-400 hover:text-gray-600">
                <span class="sr-only">Close modal</span>
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>

        <div class="py-4">
            <form id="addStudentForm" action="process_add_student.php" method="POST" class="space-y-4"> 
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label for="add_last_name" class="block text-sm font-medium text-gray-700">Last Name:</label>
                        <input type="text" id="add_last_name" name="last_name" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500 sm:text-sm" required>
                    </div>
                    <div>
                        <label for="add_given_name" class="block text-sm font-medium text-gray-700">Given Name:</label>
                        <input type="text" id="add_given_name" name="given_name" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500 sm:text-sm" required>
                    </div>
                    <div>
                        <label for="add_middle_initial" class="block text-sm font-medium text-gray-700">M.I.:</label>
                        <input type="text" id="add_middle_initial" name="middle_initial" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500 sm:text-sm">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="add_course" class="block text-sm font-medium text-gray-700">Course:</label>
                        <input type="text" id="add_course" name="course" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500 sm:text-sm" required>
                    </div>
                    <div>
                        <label for="add_year_level" class="block text-sm font-medium text-gray-700">Year Level:</label>
                        <select id="add_year_level" name="year_level" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500 sm:text-sm" required>
                            <option value="">Select Year Level</option>
                            <option value="1">First Year</option>
                            <option value="2">Second Year</option>
                            <option value="3">Third Year</option>
                            <option value="4">Fourth Year</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="add_semester" class="block text-sm font-medium text-gray-700">Semester:</label>
                        <select id="add_semester" name="semester" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500 sm:text-sm" required>
                            <option value="">Select Semester</option>
                            <option value="1ST">1st SEM</option>
                            <option value="2ND">2nd SEM</option>
                            <option value="SUMMER">SUMMER</option>
                        </select>
                    </div>
                    <div>
                        <label for="add_school_year" class="block text-sm font-medium text-gray-700">School Year:</label>
                        <input type="text" id="add_school_year" name="school_year" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500 sm:text-sm" required>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-1">
                    <div>
                        <label for="add_initial_account_balance" class="block text-sm font-medium text-gray-700">Initial Account Balance (₱):</label>
                        <input type="number" step="0.01" id="add_initial_account_balance" name="initial_account_balance" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500 sm:text-sm" value="0.00" required>
                    </div>
                </div>

                <div class="flex justify-end pt-4 border-t border-gray-200">
                    <button type="button" onclick="closeModal('addStudentModal')" class="mr-2 px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">Cancel</button>
                    <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-green-700 rounded-md hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">Add Student</button>
                </div>
            </form>
        </div>
    </div>
</div>

        <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.3.0/flowbite.min.js"></script>
    <script src="../sweetalert2/sweetalert2.all.min.js"></script>
    <script src="../sweetalert2/sweetalert2.min.js"></script>

<script>
    $(document).ready(function() {
        var table = $('#employeeDataTable').DataTable({
            "paging": true,
            "ordering": true,
            "info": true,
            "searching": true // Keep the default search box
        });

        // Function to open modal - MODIFIED HERE TO BE GLOBAL
        window.openModal = function(modalId) {
            var modal = document.getElementById(modalId);
            modal.classList.remove('hidden');
            setTimeout(() => modal.classList.add('show'), 10);
            // Add a small timeout to allow the 'hidden' class to be removed
            // before 'show' is added for the transition to work correctly.
            setTimeout(() => {
                var modalContent = modal.querySelector('.relative'); // Assuming your inner modal content has 'relative' class
                if (modalContent) {
                    modalContent.classList.remove('opacity-0', '-translate-y-full');
                    modalContent.classList.add('opacity-100', 'translate-y-0');
                }
            }, 50); // Small delay
        }


        // Function to close modal (already correctly global)
        window.closeModal = function(modalId) {
            var modal = document.getElementById(modalId);
            var modalContent = modal.querySelector('.relative'); // Assuming your inner modal content has 'relative' class

            if (modalContent) {
                modalContent.classList.remove('opacity-100', 'translate-y-0');
                modalContent.classList.add('opacity-0', '-translate-y-full');
            }

            setTimeout(() => modal.classList.add('hidden'), 300); // Add 'hidden' after transition
        }

        // Event listener for Course filter dropdown (existing code)
        $('#courseFilter').on('change', function() {
            var selectedCourse = $(this).val();
            // Column index for 'Course' is 3 (0-indexed)
            table.column(3).search(selectedCourse ? '^' + $.fn.dataTable.util.escapeRegex(selectedCourse) + '$' : '', true, false).draw();
        });

        // Event listener for Year Level filter dropdown (existing code)
        $('#yearLevelFilter').on('change', function() {
            var selectedYearLevel = $(this).val();
            // Column index for 'Year Level' is 4 (0-indexed)
            var searchText = '';
            if (selectedYearLevel) {
                switch (selectedYearLevel) {
                    case '1':
                        searchText = 'First Year';
                        break;
                    case '2':
                        searchText = 'Second Year';
                        break;
                    case '3':
                        searchText = 'Third Year';
                        break;
                    case '4':
                        searchText = 'Fourth Year';
                        break;
                    default:
                        searchText = selectedYearLevel;
                        break;
                }
            }
            table.column(4).search(searchText ? '^' + $.fn.dataTable.util.escapeRegex(searchText) + '$' : '', true, false).draw();
        });

        // Event listener for Semester filter dropdown (existing code)
        $('#semesterFilter').on('change', function() {
            var selectedSemester = $(this).val();
            // Column index for 'Semester' is 5 (0-indexed)
            var searchText = '';
            if (selectedSemester) {
                var suffix = '';
                if (selectedSemester === '1') {
                    suffix = 'ST';
                } else if (selectedSemester === '2') {
                    suffix = 'ND';
                }
                searchText = selectedSemester + suffix + " SEM";
            }
            table.column(5).search(searchText ? '^' + $.fn.dataTable.util.escapeRegex(searchText) + '$' : '', true, false).draw();
        });

        // Event listener for Account Status filter dropdown (existing code)
        $('#statusFilter').on('change', function() {
            var selectedStatus = $(this).val();
            var statusColumnIndex = 9; // Column index for 'Status'
            table.column(statusColumnIndex).search(selectedStatus ? '^' + $.fn.dataTable.util.escapeRegex(selectedStatus) + '$' : '', true, false).draw();
        });

        // Handle edit button click (existing code)
        $('#employeeDataTable tbody').on('click', '.edit-student-btn', function(e) {
            e.preventDefault();
            var row = $(this).closest('tr');
            var studentData = {
                id: row.data('student-id'),
                last_name: row.data('last-name'),
                given_name: row.data('given-name'),
                middle_initial: row.data('middle-initial'),
                course: row.data('course'),
                year_level: row.data('year-level'),
                semester: row.data('semester'),
                school_year: row.data('school-year')
            };

            // Populate the modal form
            $('#edit_student_id').val(studentData.id);
            $('#edit_last_name').val(studentData.last_name);
            $('#edit_given_name').val(studentData.given_name);
            $('#edit_middle_initial').val(studentData.middle_initial);
            $('#edit_course').val(studentData.course);
            $('#edit_year_level').val(studentData.year_level);
            $('#edit_semester').val(studentData.semester);
            $('#edit_school_year').val(studentData.school_year);

            openModal('editStudentModal');
        });

        // Handle modal form submission (existing code for edit)
        $('#editStudentForm').on('submit', function(e) {
            e.preventDefault();

            var formData = $(this).serialize();

            $.ajax({
                url: 'edit_student_process.php',
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            toast: true,
                            position: 'top-end',
                            icon: 'success',
                            title: response.message,
                            showConfirmButton: false,
                            timer: 3000,
                            timerProgressBar: true,
                            didOpen: (toast) => {
                                toast.addEventListener('mouseenter', Swal.stopTimer);
                                toast.addEventListener('mouseleave', Swal.resumeTimer);
                            }
                        }).then(() => {
                            closeModal('editStudentModal');
                            window.location.reload();
                        });
                    } else {
                        Swal.fire({
                            toast: true,
                            position: 'top-end',
                            icon: 'error',
                            title: response.message,
                            showConfirmButton: false,
                            timer: 3000,
                            timerProgressBar: true,
                            didOpen: (toast) => {
                                toast.addEventListener('mouseenter', Swal.stopTimer);
                                toast.addEventListener('mouseleave', Swal.resumeTimer);
                            }
                        });
                    }
                },
                error: function(xhr, status, error) {
                    console.error("AJAX Error:", status, error);
                    console.error("Response Text:", xhr.responseText);
                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: 'error',
                        title: 'Could not connect to the server or an unexpected error occurred.',
                        showConfirmButton: false,
                        timer: 3000,
                        timerProgressBar: true,
                        didOpen: (toast) => {
                            toast.addEventListener('mouseenter', Swal.stopTimer);
                            toast.addEventListener('mouseleave', Swal.resumeTimer);
                        }
                    });
                }
            });
        });

        // NEW CODE FOR "ADD NEW" MODAL STARTS HERE

        // Event listener to open the "Add New" modal
        $('#openAddStudentModalBtn').on('click', function() {
            // Clear any previous data from the form if necessary
            $('#addStudentForm')[0].reset();
            window.openModal('addStudentModal'); // Calling the global function
        });

        // Handle "Add New" form submission
        $('#addStudentForm').on('submit', function(e) {
            e.preventDefault();

            var formData = $(this).serialize(); // Get form data for the new student

            $.ajax({
                url: 'add_student_process.php', // **CREATE THIS NEW PHP FILE**
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            toast: true,
                            position: 'top-end',
                            icon: 'success',
                            title: response.message,
                            showConfirmButton: false,
                            timer: 3000,
                            timerProgressBar: true,
                            didOpen: (toast) => {
                                toast.addEventListener('mouseenter', Swal.stopTimer);
                                toast.addEventListener('mouseleave', Swal.resumeTimer);
                            }
                        }).then(() => {
                            closeModal('addStudentModal');
                            // Reload the page or ideally, add the new row to the DataTable
                            window.location.reload();
                            // If you want to add the row to the DataTable without a full reload,
                            // you'd need to get the new student data from the response and use table.row.add().draw();
                        });
                    } else {
                        Swal.fire({
                            toast: true,
                            position: 'top-end',
                            icon: 'error',
                            title: response.message,
                            showConfirmButton: false,
                            timer: 3000,
                            timerProgressBar: true,
                            didOpen: (toast) => {
                                toast.addEventListener('mouseenter', Swal.stopTimer);
                                toast.addEventListener('mouseleave', Swal.resumeTimer);
                            }
                        });
                    }
                },
                error: function(xhr, status, error) {
                    console.error("AJAX Error:", status, error);
                    console.error("Response Text:", xhr.responseText);
                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: 'error',
                        title: 'Could not connect to the server or an unexpected error occurred.',
                        showConfirmButton: false,
                        timer: 3000,
                        timerProgressBar: true,
                        didOpen: (toast) => {
                            toast.addEventListener('mouseenter', Swal.stopTimer);
                            toast.addEventListener('mouseleave', Swal.resumeTimer);
                        }
                    });
                }
            });
        });
        // NEW CODE FOR "ADD NEW" MODAL ENDS HERE
    });

    // The DOMContentLoaded listener should be outside the jQuery ready function
    document.addEventListener('DOMContentLoaded', function() {
        // Get all menu buttons
        const menuButtons = document.querySelectorAll('[id^="options-menu-button"]');

        menuButtons.forEach(button => {
            button.addEventListener('click', function() {
                const dropdown = this.nextElementSibling; // Get the next sibling, which is the dropdown
                dropdown.classList.toggle('hidden'); // Toggle the 'hidden' class

                // Close other open menus
                menuButtons.forEach(otherButton => {
                    if (otherButton !== this) {
                        otherButton.nextElementSibling.classList.add('hidden');
                    }
                });
            });
        });

        // Close the dropdown when clicking outside
        document.addEventListener('click', function(event) {
            menuButtons.forEach(button => {
                const dropdown = button.nextElementSibling;
                if (!button.contains(event.target) && !dropdown.contains(event.target) && !dropdown.classList.contains('hidden')) {
                    dropdown.classList.add('hidden');
                }
            });
        });
    });
</script>
</body>
</html>