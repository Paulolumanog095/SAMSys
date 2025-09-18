<?php
session_start();

// Check if the user is logged in
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    // If not logged in, redirect to the login page
    header('Location: login.php');
    exit();
}


// Supabase Configuration - moved directly here for self-containment, or you can keep it in a separate `supabase_config.php` and `require_once` it.
define('SUPABASE_URL', 'https://tihodezxfrpjtpratrez.supabase.co');
define('SUPABASE_ANON_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InRpaG9kZXp4ZnJwanRwcmF0cmV6Iiwicm9sZSI6ImFub24iLCJpYXQiOjE3NDcyOTkyODksImV4cCI6MjA2Mjg3NTI4OX0.JU2jbQYhjjq90wrDi35LNr9AWKpqdvJtaO_JDgVR_JM');
define('SUPABASE_STUDENTS_TABLE', 'students_account'); // Your student table name

// Path to your CA certificate bundle (important for HTTPS curl requests)
define('CURL_CAINFO_PATH', 'C:\Program Files\Ampps\php82\extras\ssl\cacert.pem');

// Autoload files using Composer (for Dompdf)
require_once __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Check if student_id is provided in the URL
if (!isset($_GET['student_id']) || empty($_GET['student_id'])) {
    $_SESSION['message'] = 'Student ID is missing for assessment form generation.';
    $_SESSION['message_type'] = 'error';
    header('Location: payments.php');
    exit();
}

$studentId = htmlspecialchars($_GET['student_id']);

// --- Fetch student details from Supabase API using curl ---
$student = null;
$fetchStudentApiUrl = SUPABASE_URL . '/rest/v1/' . SUPABASE_STUDENTS_TABLE . '?select=*&id=eq.' . urlencode($studentId);

$chStudent = curl_init($fetchStudentApiUrl);
curl_setopt($chStudent, CURLOPT_RETURNTRANSFER, true);
curl_setopt($chStudent, CURLOPT_HTTPHEADER, [
    'apikey: ' . SUPABASE_ANON_KEY,
    'Authorization: Bearer ' . SUPABASE_ANON_KEY,
    'Content-Type: application/json' // Good practice to include
]);
curl_setopt($chStudent, CURLOPT_CAINFO, CURL_CAINFO_PATH); // Use the defined path
$studentResponse = curl_exec($chStudent);
$studentHttpCode = curl_getinfo($chStudent, CURLINFO_HTTP_CODE);
$studentError = curl_error($chStudent);
curl_close($chStudent);

if ($studentHttpCode >= 200 && $studentHttpCode < 300) {
    $studentData = json_decode($studentResponse, true);
    if (json_last_error() === JSON_ERROR_NONE && !empty($studentData)) {
        $student = $studentData[0]; // Assuming 'id' is unique, so expect one result
    } else {
        // Log JSON decode error or empty data for debugging
        error_log("Error decoding student data or student data is empty: " . json_last_error_msg());
        $_SESSION['message'] = 'Student data could not be processed or student not found.';
        $_SESSION['message_type'] = 'error';
        header('Location: payments.php');
        exit();
    }
} else {
    // Log curl error or HTTP error for debugging
    error_log("Error fetching student details from Supabase API: HTTP " . $studentHttpCode . " - " . ($studentError ?: $studentResponse));
    $_SESSION['message'] = 'Error fetching student details for PDF generation.';
    $_SESSION['message_type'] = 'error';
    header('Location: payments.php');
    exit();
}
// --- End Fetch student details from Supabase API ---


// If for some reason $student is still null after API call (e.g., ID not found)
if (!$student) {
    $_SESSION['message'] = 'Student not found.';
    $_SESSION['message_type'] = 'error';
    header('Location: payments.php');
    exit();
}

// Prepare data for the PDF (using fetched $student data)
// Use null coalescing operator (?? '') to handle potentially missing keys gracefully
$fullName = htmlspecialchars($student['last_name'] ?? '') . ', ' . htmlspecialchars($student['given_name'] ?? '') . ' ' . htmlspecialchars($student['middle_initial'] ?? '');
$course = htmlspecialchars($student['course'] ?? '');
$currentBalance = number_format($student['current_balance'] ?? 0, 2);

// Map year_level to human-readable format
$yearLevelText = '';
switch ($student['year_level'] ?? '') {
    case 1: $yearLevelText = 'First Year'; break;
    case 2: $yearLevelText = 'Second Year'; break;
    case 3: $yearLevelText = 'Third Year'; break;
    case 4: $yearLevelText = 'Fourth Year'; break;
    default: $yearLevelText = htmlspecialchars($student['year_level'] ?? ''); break;
}

// Set up Dompdf options
$options = new Options();
$options->set('defaultFont', 'Helvetica');
$options->set('isHtml5ParserEnabled', true); // Enable HTML5 parser
$options->set('isRemoteEnabled', true); // Enable loading of remote assets (e.g., images for header if needed)
$options->set('chroot', 'C:/Program Files/Ampps/www/studentassessment/'); // ADD THIS LINE

$dompdf = new Dompdf($options);

// Output buffer to capture HTML content
ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Assessment Form</title>    
    <link rel="icon" href="../logo/logo.png" type="image/png">
    <style>
        body {
        font-family: 'Helvetica', sans-serif;
        margin: 0;
        padding: 0;
        font-size: 12pt;
    }
    .header {
        text-align: center;
        margin-bottom: 30px;
        border-bottom: 1px solid #ccc;
        padding-bottom: 15px;
        /* Optional: Add a very small line-height here to collapse whitespace between inline-block elements */
        /* line-height: 0; */ /* Only if text-align center is acting weirdly */
    }
    .header-content {
    display: flex;
    justify-content: flex-start; /* Align contents to the very left within the flex container */
    align-items: center;
    margin-left: -8px; /* THIS IS THE VALUE TO ADJUST */
    margin-bottom: 5px;
}
    .header img {
        max-width: 65px;
        height: auto;
        display: inline-block;
        vertical-align: middle;
        margin-right: 15px;
    }
    .header-text-block { /* NEW CSS for the text container */
        display: inline-block;
        vertical-align: middle;
        text-align: left; /* Align the text content within this block to the left */
        /* If using line-height:0 on .header, you'll need to reset line-height here */
        /* line-height: normal; */
    }
    .header h1 {
        margin: 0;
        font-size: 12pt;
        color: green;
        line-height: 1.4; /* Explicitly set to 1 to minimize space around H1 content */
        font-family:  "Times New Roman", Times, serif;
    }
    .header p {
        margin: 0;
        font-size: 10pt;
        color: black;
        line-height: 1.4; /* Try 1.1 or even 1.0 if you want it very tight */
        /* You can add a small margin-top to the first paragraph specifically if needed */
        /* For example: */
        /* &:first-of-type { margin-top: -2px; } */ /* Not valid in straight CSS, needs specific targeting */
    }
    /* To target the first paragraph right after h1 within .header-text-block */
    .header-text-block p:first-of-type {
        margin-top: -2px; /* Adjust as needed, try -1px, -3px etc. */
    }
    .content {
        margin: 0 40px;
    }
    .section-title {
        font-size: 14pt;
        font-weight: bold;
        margin-bottom: 15px;
        color: #34495e;
        border-bottom: 2px solid rgb(15, 187, 110);
        padding-bottom: 5px;
    }
    .details-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 25px;
    }
    .details-table td {
        padding: 8px;
        border-bottom: 1px dashed #eee;
        vertical-align: top;
    }
    .details-table td strong {
        color: #555;
        display: inline-block;
        width: 120px; /* Adjust for label width */
    }
    .footer {
        text-align: center;
        margin-top: 50px;
        font-size: 9pt;
        color: #7f8c8d;
    }
    /* New styles for signatories */
    .signatories {
        margin-top: 110px;
        margin-left: 40px; /* Align with content */
        width: 50%; /* Adjust width as needed */
        text-align: left;
    }
    .signatories p {
        margin : 0;
        line-height: 1.5;
    }
    .signatories .name {
        font-weight: bold;
        border-bottom: 1px solid #000; /* Underline the name */
        padding-bottom: 2px;
        display: inline-block; /* Make sure underline only goes under text */
        margin-top: 50px; /* Space above the name */
    }
    .signatories .position {
        font-size: 9pt;
        color: #555;
        margin-left: 6px; /* Indent position slightly */
    }
    .remaining-balance-value {
        font-family: 'DejaVu Sans', sans-serif;
        color: #c0392b; 
        font-weight: bold; 
        font-size: 1.2em;
    }

</style>
</head>
<body>
    <div class="header">
        <div class="header-content"> <img class="logo-left" src="<?php echo $_SERVER['DOCUMENT_ROOT'] . '/studentassessment/logo/logo.png'; ?>" alt="School Logo">
            <img class="logo-right" src="<?php echo $_SERVER['DOCUMENT_ROOT'] . '/studentassessment/logo/Bagong_Pilipinas_logo.png'; ?>" alt="Second School Logo"> <div class="header-text-block">
                <p>Republic of the Philippines</p>
                <h1>CENTRAL PHILIPPINES STATE UNIVERSITY</h1>
                <p>San Carlos City, Negros Occidental</p>
                <p>Tel No.: (034) 702 9903 | Mobile: +63917 3015 565</p>
                <p>Email: cpsu.sancarlos@cpsu.edu.ph | Website: www.cpsu.edu.ph</p>
                <p>ISO 9001:2015 Certificate Registration Number: 01 100 2234785/09</p>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="section-title">Student Assessment Form</div>
        <table class="details-table">
            <tr>
                <td><strong>Student Name:</strong></td>
                <td style="color: #555;"><b><?php echo $fullName; ?></b></td>
            </tr>
            <tr>
                <td><strong>Course:</strong></td>
                <td><strong><?php echo $course; ?></strong></td>
            </tr>
            <tr>
                <td><strong>Year Level:</strong></td>
                <td><strong><?php echo $yearLevelText; ?></strong></td>
            </tr>
            <tr>
                <td><strong>Remaining Balance:</strong></td>
                <td class="remaining-balance-value">₱<?php echo $currentBalance; ?></td>
            </tr>
        </table>

        
    </div>

    <div class="signatories">
        <p>Prepared by:</p>
        <p class="name">PAULO A. LUMANOG</p>
        <p class="position">ASSESSMENT STAFF</p>
    </div>


</body>
</html>
<?php
$html = ob_get_clean(); // Get the HTML content from the buffer

$dompdf->loadHtml($html);

// (Optional) Set paper size and orientation
$dompdf->setPaper('A4', 'portrait');

// Render the HTML as PDF
$dompdf->render();

// Output the generated PDF to the browser
$dompdf->stream("Assessment_Form_" . str_replace(' ', '_', $fullName) . ".pdf", array("Attachment" => false));

// No database connection to close here, as we're using HTTP API
?>