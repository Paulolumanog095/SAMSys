<?php
session_start();

// Check if the user is logged in
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    // If not logged in, redirect to the login page
    header('Location: login.php');
    exit();
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
    <link rel="stylesheet" href="Admin LTE/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="Admin LTE/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
    <link rel="stylesheet" href="Admin LTE/plugins/datatables-buttons/css/buttons.bootstrap4.min.css">
    <link rel="stylesheet" href="Admin LTE/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="sweetalert2/sweetalert2.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Student Account Management</title>
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
    </style>
<body>
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
                <div class="col-sm-6 text-white font-semibold">
                    <h1>Import Data</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item active text-white"></li>
                    </ol>
                </div>
            </div>
    </section>

        <main class="p-4">
        <?php
        // Display session messages
        if (isset($_SESSION['message'])) {
            $messageClass = $_SESSION['message_type'] ?? 'info';
            // Added Tailwind classes for message display
            echo '<div class="p-4 mb-4 text-sm rounded-lg ' . 
                 ($messageClass == 'success' ? 'bg-green-100 text-green-800' : 
                 ($messageClass == 'error' ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800')) . 
                 '" role="alert">' . htmlspecialchars($_SESSION['message']) . '</div>';
            unset($_SESSION['message']); // Clear the message after displaying
            unset($_SESSION['message_type']); // Clear the type
        }
        ?>

        <div class="bg-white p-6 rounded-lg shadow-md max-w-lg mx-auto">
            <h2 class="text-2xl font-semibold text-gray-800 mb-4">Upload Student Data (Excel)</h2>
            <div class="file-upload">
                <form action="upload.php" method="post" enctype="multipart/form-data" class="space-y-4">
                    <label for="excelFile" class="block text-gray-700 text-sm font-bold mb-2">Select Excel File to Upload:</label>
                    <input type="file" name="excelFile" id="excelFile" accept=".xlsx, .xls" required
                           class="block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 focus:outline-none file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-green-50 file:text-green-700 hover:file:bg-green-100">
                    <button type="submit" name="submit"
                            class="w-full bg-green-700 hover:bg-green-800 text-white font-bold py-2 px-4 rounded-lg focus:outline-none focus:shadow-outline transition duration-150 ease-in-out">
                        Upload Data
                    </button>
                </form>
                <p class="mt-4 text-sm text-gray-500"><small>Accepted formats: .xlsx, .xls</small></p>
            </div>
        </div>
        </main>
</div>
    </div>
    </div>

    </div>
        <script src="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="sweetalert2/sweetalert2.all.min.js"></script>
    <script src="jsQR-master/dist/jsQR.js"></script>
    <script src="sweetalert2/sweetalert2.min.js"></script>
    <script src="Admin LTE/plugins/jquery/jquery.min.js"></script>
    <script src="Admin LTE/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="Admin LTE/dist/js/adminlte.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>  
    <script src="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.3.0/flowbite.min.js"></script>
</body>
</html>