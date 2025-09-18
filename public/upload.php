<?php
session_start();

// Check if the user is logged in
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    // If not logged in, redirect to the login page
    header('Location: login.php');
    exit();
}


// Include Composer's autoloader for PhpSpreadsheet
require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

// --- Supabase Configuration (IMPORTANT: Replace with your actual details) ---
$supabaseUrl = 'https://tihodezxfrpjtpratrez.supabase.co'; // e.g., 'https://abcdefg.supabase.co'
$supabaseAnonKey = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InRpaG9kZXp4ZnJwanRwcmF0cmV6Iiwicm9sZSI6ImFub24iLCJpYXQiOjE3NDcyOTkyODksImV4cCI6MjA2Mjg3NTI4OX0.JU2jbQYhjjq90wrDi35LNr9AWKpqdvJtaO_JDgVR_JM'; // Your 'anon' key from Supabase Project Settings -> API

// The name of your table in Supabase where data will be inserted
$supabaseTable = 'students_account'; // <--- UPDATED TABLE NAME

// --- File Upload Logic ---
if (isset($_POST['submit'])) {
    $allowedFileTypes = [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // .xlsx
        'application/vnd.ms-excel' // .xls
    ];
    $allowedExtensions = ['xlsx', 'xls'];

    // Check if a file was uploaded without errors
    if (isset($_FILES['excelFile']) && $_FILES['excelFile']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['excelFile'];
        $fileName = $file['name'];
        $fileTmpName = $file['tmp_name'];
        $fileType = $file['type'];
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        // Validate file type and extension
        if (in_array($fileType, $allowedFileTypes) && in_array($fileExt, $allowedExtensions)) {
            try {
                $spreadsheet = IOFactory::load($fileTmpName);
                $sheet = $spreadsheet->getActiveSheet();
                $data = $sheet->toArray(null, true, true, true);

                $insertedCount = 0;
                $skippedCount = 0;
                $failedCount = 0;
                $totalRowsProcessed = 0; // Track rows actually attempted for insert (excluding header/empty)
                $skippedNames = []; // To store names that were skipped due to conflict

                // Based on your Excel screenshot, data starts from row 6
                // So, we need to skip the first 5 rows (index 0 to 4) which are headers
                $headerRowsToSkip = 1;
                $currentRowIndex = 0; // Keep track of physical row index from Excel

                // --- Process each row individually ---
                foreach ($data as $excelRowNumber => $row) {
                    $currentRowIndex++; // Increment for every row in the Excel data

                    // Skip header rows
                    if ($currentRowIndex <= $headerRowsToSkip) {
                        continue;
                    }

                    // Log raw data for debugging
                    error_log("Processing Row " . ($currentRowIndex) . ": Raw data: " . json_encode($row));

                    // --- Extract and Map Excel Columns to Database Columns ---
                    // Adjust 'A', 'B', 'C', etc., to match the actual columns in your Excel file
                    $lastName = trim($row['A'] ?? '');          // Excel Column C: LAST NAME
                    $givenName = trim($row['B'] ?? '');         // Excel Column D: GIVEN NAME
                    $middleInitial = trim($row['C'] ?? '');     // Excel Column E: MIDDLE INITIAL
                    $course = trim($row['D'] ?? '');            // Excel Column F: COURSE
                    $yearLevel = trim($row['E'] ?? '');         // Excel Column G: YEAR LEVEL
                    $semester = trim($row['F'] ?? '');          // Excel Column H: SEMESTER
                    $schoolYear = trim($row['G'] ?? '');        // Excel Column I: SCHOOL YEAR

                    // CRITICAL: This needs to be a numerical value from Excel.
                    // Assuming 'ACCOUNT BALANCE' in Excel Column J is your 'initial_account_balance'
                    // Make sure this column in your Excel contains numbers (e.g., 0, 1500.50, -200)
                    // Read the cell value as string
                    $balanceString = trim($row['H'] ?? ''); // Or 'H' depending on your current Excel map

                    // Remove commas before validating
                    $balanceStringCleaned = str_replace(',', '', $balanceString);

                    $initialAccountBalance = filter_var($balanceStringCleaned, FILTER_VALIDATE_FLOAT);
                    $initialAccountBalance = ($initialAccountBalance === false) ? 0.00 : $initialAccountBalance;


                    // Log mapped values for debugging
                    error_log("Row " . ($currentRowIndex) . " - Mapped values: Last Name='{$lastName}', Given Name='{$givenName}', MI='{$middleInitial}', Initial Balance='{$initialAccountBalance}'");

                    // Basic validation: ensure last name and given name are not empty
                    if (empty($lastName) || empty($givenName)) {
                        error_log("Row " . ($currentRowIndex) . ": Skipping row due to missing last name or given name. Raw data: " . json_encode($row));
                        continue; // Skip to the next row in Excel
                    }

                    $totalRowsProcessed++; // This row is valid and will be attempted for insert

                    // Determine account status based on initial balance
                    $accountStatus = ($initialAccountBalance <= 0) ? 'Fully Paid' : 'With Balance';

                    // Prepare data for this single row, ensuring column names match Supabase DB
                    $dataToInsert = [
                        'last_name' => $lastName,
                        'given_name' => $givenName,
                        'middle_initial' => ($middleInitial === '') ? null : $middleInitial, // Store empty strings as NULL if column is nullable
                        'course' => ($course === '') ? null : $course,
                        'year_level' => ($yearLevel === '') ? null : $yearLevel,
                        'semester' => ($semester === '') ? null : $semester,
                        'school_year' => ($schoolYear === '') ? null : $schoolYear,
                        'initial_account_balance' => $initialAccountBalance,
                        'current_balance' => $initialAccountBalance, // Current balance starts as initial balance
                        'account_status' => $accountStatus
                        // 'email' column is not included here as per your current Excel structure and composite unique key
                    ];

                    // --- Supabase API Call for a SINGLE row with on_conflict ---
                    // The on_conflict parameter uses your composite unique constraint
                    $apiUrl = $supabaseUrl . '/rest/v1/' . $supabaseTable . '?on_conflict=last_name,given_name,middle_initial'; // <--- UPDATED on_conflict

                    $ch = curl_init($apiUrl);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'apikey: ' . $supabaseAnonKey,
                        'Authorization: Bearer ' . $supabaseAnonKey,
                        'Content-Type: application/json',
                        'Prefer: return=representation' // Keep this to get response data (useful for debugging)
                    ]);
                    // Set CA info for SSL verification (CRUCIAL FOR WINDOWS)
                    curl_setopt($ch, CURLOPT_CAINFO, 'C:\Program Files\Ampps\php82\extras\ssl\cacert.pem'); // Ensure this path is correct
                    
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dataToInsert));

                    $response = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $error = curl_error($ch);
                    curl_close($ch);

                    // --- Log each individual API call result ---
                    error_log("Row " . ($currentRowIndex) . " Supabase API Call Result:");
                    error_log("  HTTP Code: " . $httpCode);
                    error_log("  cURL Error: " . ($error ? $error : 'No cURL error'));
                    error_log("  Supabase Response: " . $response);

                    // Handle Supabase API response for this single row
                    if ($httpCode >= 200 && $httpCode < 300) {
                        $responseData = json_decode($response, true);
                        if (!empty($responseData)) {
                            $insertedCount++;
                            error_log("Row " . ($currentRowIndex) . ": Successfully inserted.");
                        } else {
                            // This case means Supabase returned 2xx but no data, likely due to on_conflict preventing insert
                            error_log("Row " . ($currentRowIndex) . ": Supabase returned 2xx but no data. Likely skipped due to 'on_conflict' (Name: {$lastName}, {$givenName}, {$middleInitial}).");
                            $skippedCount++;
                            $skippedNames[] = "{$lastName}, {$givenName}, {$middleInitial}";
                        }
                    } else if ($httpCode == 409) { // 409 Conflict indicates duplicate
                        error_log("Row " . ($currentRowIndex) . ": Conflict (409) - Name '{$lastName}, {$givenName}, {$middleInitial}' already exists. Skipping.");
                        $skippedCount++;
                        $skippedNames[] = "{$lastName}, {$givenName}, {$middleInitial}";
                    }
                    else {
                        error_log("Row " . ($currentRowIndex) . ": Supabase INSERT HTTP Error. Code: " . $httpCode . ". Response: " . $response);
                        $failedCount++;
                    }
                } // End foreach ($data as $row)

                // --- Final Summary Message Logic ---
                if ($totalRowsProcessed > 0 && $insertedCount === 0 && $failedCount === 0) {
                    // All valid rows from Excel were skipped because their names already existed
                    $_SESSION['message'] = "All valid data from the Excel file already exists in the database. No new rows were inserted.";
                    $_SESSION['message_type'] = 'info'; // Use 'info' for this scenario
                } else {
                    // General success/partial success/failure message
                    $_SESSION['message'] = "Upload complete! Inserted: " . $insertedCount . ", Skipped (existing name/empty data in Excel): " . $skippedCount . ", Failed: " . $failedCount . ".";
                    if (!empty($skippedNames)) {
                        $_SESSION['message'] .= " Skipped names: " . implode(', ', array_unique($skippedNames)) . ".";
                    }
                    $_SESSION['message_type'] = ($insertedCount > 0) ? 'success' : 'warning'; // If nothing inserted, but no hard errors, it's a warning
                    if ($failedCount > 0) {
                        $_SESSION['message_type'] = 'error'; // If any failed, it's an error
                    }
                }


            } catch (\PhpOffice\PhpSpreadsheet\Reader\Exception $e) {
                error_log("PhpSpreadsheet Error: " . $e->getMessage());
                $_SESSION['message'] = "Error reading Excel file: " . $e->getMessage();
                $_SESSION['message_type'] = 'error';
            } catch (Exception $e) {
                error_log("Unexpected Error: " . $e->getMessage());
                $_SESSION['message'] = "An unexpected error occurred: " . $e->getMessage();
                $_SESSION['message_type'] = 'error';
            }
        } else {
            $_SESSION['message'] = "Invalid file type. Please upload a .xlsx or .xls file.";
            $_SESSION['message_type'] = 'error';
        }
    } else {
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE     => "The uploaded file exceeds the upload_max_filesize directive in php.ini.",
            UPLOAD_ERR_FORM_SIZE    => "The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.",
            UPLOAD_ERR_PARTIAL      => "The uploaded file was only partially uploaded.",
            UPLOAD_ERR_NO_FILE      => "No file was uploaded.",
            UPLOAD_ERR_NO_TMP_DIR   => "Missing a temporary folder.",
            UPLOAD_ERR_CANT_WRITE   => "Failed to write file to disk.",
            UPLOAD_ERR_EXTENSION    => "A PHP extension stopped the file upload."
        ];
        $errorMessage = $uploadErrors[$_FILES['excelFile']['error']] ?? "Unknown file upload error.";
        $_SESSION['message'] = "File upload error: " . $errorMessage;
        $_SESSION['message_type'] = 'error';
    }
} else {
    $_SESSION['message'] = "Invalid request. Please submit the form to upload a file.";
    $_SESSION['message_type'] = 'error';
}

header('Location: addnew.php');
exit();
?>