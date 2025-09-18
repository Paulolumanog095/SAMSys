<?php
session_start();

// Redirect to dashboard if already logged in
if (isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true) {
    header('Location: index.php'); // Current assumption: index.php is in the same directory as login.php
    exit();
}

date_default_timezone_set('Asia/Manila'); // San Carlos City, Western Visayas is in Asia/Manila timezone
$hour = date('H');
$greeting = '';

if ($hour >= 5 && $hour < 12) {
    $greeting = 'Good Morning, Cenphilian';
} elseif ($hour >= 12 && $hour < 18) {
    $greeting = 'Good Afternoon, Cenphilian';
} else {
    $greeting = 'Good Evening, Cenphilian';
}

// Supabase Configuration
$supabaseUrl = 'https://tihodezxfrpjtpratrez.supabase.co';
$supabaseAnonKey = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InRpaG9kZXp4ZnJwanRwcmF0cmV6Iiwicm9sZSI6ImFub24iLCJpYXQiOjE3NDcyOTkyODksImV4cCI6MjA2Mjg3NTI4OX0.JU2jbQYhjjq90wrDi35LNr9AWKpqdvJtaO_JDgVR_JM';
$supabaseTable = 'administrators'; // Target the 'administrators' table

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username_input = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($username_input) || empty($password)) {
        $message = "Please enter both username and password.";
        $message_type = 'error';
    } else {
        $sanitizedUsername = urlencode($username_input);
        $apiUrl = $supabaseUrl . '/rest/v1/' . $supabaseTable . '?select=id,username,password&username=eq.' . $sanitizedUsername;

        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'apikey: ' . $supabaseAnonKey,
            'Authorization: Bearer ' . $supabaseAnonKey,
            'Content-Type: application/json'
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            $users = json_decode($response, true);

            if (!empty($users) && is_array($users)) {
                $user = $users[0];

                if ($password === $user['password']) {
                    $_SESSION['user_logged_in'] = true;
                    $_SESSION['username'] = $user['username'];
                    header('Location: index.php'); // Direct redirect
                    exit();
                } else {
                    $message = "Invalid username or password.";
                    $message_type = 'error';
                }
            } else {
                $message = "Invalid username or password.";
                $message_type = 'error';
            }
        } else {
            // Handle HTTP errors or cURL errors
            $message = "Error connecting to authentication service. Please try again later. ";
            if ($error) {
                $message .= "cURL Error: " . $error;
            } else {
                $message .= "HTTP Status: " . $httpCode . ". Response: " . ($response ? $response : 'No response body.');
            }
            $message_type = 'error';
        }
    }
}
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

    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/flowbite@3.1.2/dist/flowbite.min.js"></script>

    <style>
        /* Define custom green colors for Tailwind JIT compilation if not using postcss.config.js */
        :root {
            --color-green-800: #166534;
            --color-green-900: #14532d;
            --color-green-950: #0a2e16;
        }
        .bg-green-900 { background-color: var(--color-green-900); }
        .bg-green-800 { background-color: var(--color-green-800); }
        .bg-green-950 { background-color: var(--color-green-950); }
        .hover\:bg-\[\#256a29\]:hover { background-color: #256a29; }
        .focus\:ring-green-500:focus { border-color: #22c55e; box-shadow: 0 0 0 4px #22c55e40; }
        .focus\:border-green-500:focus { border-color: #22c55e; }
        .text-green-600 { color: #16a34a; }
        .focus\:ring-green-300:focus { box-shadow: 0 0 0 3px #86efac; }

        /* Progress Bar styles */
        #login-progress-container {
            margin-top: 20px; /* Space below the form */
            display: none; /* Hidden by default */
        }
        #login-progress-bar-inner {
            transition: width 0.5s ease-in-out; /* Smooth transition for width change */
            display: flex; /* Use flexbox to center text */
            align-items: center; /* Center text vertically */
            justify-content: center; /* Center text horizontally */
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen flex flex-col items-center justify-center p-4">

    <div class="p-5 bg-white rounded-xl shadow-lg flex flex-col md:flex-row max-w-5xl w-full overflow-hidden">
        <div class="rounded-xl bg-green-900 text-white p-8 md:p-12 flex flex-col items-center justify-center text-center md:w-1/2">
            <img src="../logo/logo.png" alt="CPSU Logo" class="w-24 h-24 mb-6 rounded-full">
            <h1 class="text-4xl font-bold mb-3">SAMS</h1>
            <p class="text-md opacity-90">Student Account Management System</p>
        </div>

        <div class="p-8 md:p-12 flex flex-col justify-center md:w-1/2">
            <h2 class="text-xl font-semibold text-gray-800 mb-2"><?php echo $greeting; ?></h2>
            <p class="text-sm text-gray-600 mb-8">Sign in to start session</p>

            <?php if (!empty($message)): ?>
                <div class="p-4 mb-4 text-sm rounded-lg <?php echo $message_type === 'error' ? 'text-red-800 bg-red-100' : 'text-green-800 bg-green-100'; ?>" role="alert">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST" class="space-y-6" id="loginForm">
                <div>
                    <label for="username" class="block text-sm font-medium text-gray-700 sr-only">Username</label>
                    <input type="text" id="username" name="username"
                           class="bg-gray-50 border border-gray-300 text-gray-900 text-base rounded-lg focus:ring-green-300 focus:border-green-300 block w-full p-3"
                           placeholder="Username" required>
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 sr-only">Password</label>
                    <input type="password" id="password" name="password"
                           class="bg-gray-50 border border-gray-300 text-gray-900 text-base rounded-lg focus:ring-green-300 focus:border-green-300 block w-full p-3"
                           placeholder="Password" required>
                </div>

                <div class="flex items-center">
                    <input id="show-password" type="checkbox" value=""
                           class="w-5 h-5 text-green-600 bg-gray-100 border-gray-300 rounded focus:ring-green-500 focus:ring-2">
                    <label for="show-password" class="ms-2 text-base font-medium text-gray-900">Show Password</label>
                </div>

                <button type="submit"
                        class="w-full text-white bg-[#14532d] hover:bg-[#256a29] focus:ring-4 focus:outline-none focus:ring-green-300 font-medium rounded-lg text-lg px-5 py-3 text-center">
                    Login
                </button>
            </form>

            <div id="login-progress-container" class="w-full bg-gray-200 rounded-full dark:bg-gray-700 h-4">
                <div id="login-progress-bar-inner" class="bg-[#256a29] h-4 rounded-full" style="width: 0%">
                    <span id="progress-message" class="text-xs font-medium text-white p-0.5 leading-none"></span>
                </div>
            </div>

        </div>
    </div>

    <p class="mt-8 text-sm text-gray-600 text-center max-w-xl">
        Central Philippines State University Don Justo V. Valmayor Campus - Assessment Office
    </p>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.3.0/flowbite.min.js"></script>
    <script>
        // JavaScript to toggle password visibility
        const showPasswordCheckbox = document.getElementById('show-password');
        const passwordInput = document.getElementById('password');

        showPasswordCheckbox.addEventListener('change', function() {
            if (this.checked) {
                passwordInput.type = 'text';
            } else {
                passwordInput.type = 'password';
            }
        });

        // JavaScript for the progress bar
        const loginForm = document.getElementById('loginForm');
        const loginProgressContainer = document.getElementById('login-progress-container');
        const loginProgressBarInner = document.getElementById('login-progress-bar-inner');
        const progressMessage = document.getElementById('progress-message'); // Renamed from progressPercentage

        loginForm.addEventListener('submit', function(event) {
            if (loginForm.checkValidity()) {
                loginProgressContainer.style.display = 'block'; // Show the progress bar container
                loginProgressBarInner.style.width = '0%'; // Start at 0%
                progressMessage.textContent = 'Authenticating...'; // Set initial message

                let currentProgress = 0;
                const interval = setInterval(() => {
                    currentProgress += 10;
                    if (currentProgress <= 90) { // Simulate progress up to 90%
                        loginProgressBarInner.style.width = currentProgress + '%';
                        if (currentProgress < 50) {
                            progressMessage.textContent = 'Authenticating...';
                        } else {
                            progressMessage.textContent = 'Verifying credentials...';
                        }
                    } else {
                        clearInterval(interval);
                        // Once the form is submitted, the page will eventually redirect or show an error.
                        // Set to a high value like 99% to indicate near completion.
                        loginProgressBarInner.style.width = '99%';
                        progressMessage.textContent = 'Completing login...';
                    }
                }, 150); // Adjust update speed if needed
            }
        });

        // Hide progress bar if there's an error message on page load
        document.addEventListener('DOMContentLoaded', function() {
            const messageDiv = document.querySelector('.p-4.mb-4.text-sm.rounded-lg');
            if (messageDiv) {
                loginProgressContainer.style.display = 'none'; // Hide progress bar
                loginProgressBarInner.style.width = '0%'; // Reset progress
                progressMessage.textContent = ''; // Clear message
            }
        });
    </script>
</body>
</html>