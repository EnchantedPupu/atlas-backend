<?php
// Include database connection
require_once('../config/database.php');

// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Load the example B1 form data
$exampleDataPath = '../assets/example-response-B1.json';
$jsonData = file_get_contents($exampleDataPath);

if ($jsonData === false) {
    die("Failed to read example B1 form data file");
}

$formData = json_decode($jsonData, true);

if ($formData === null) {
    die("Failed to parse JSON data: " . json_last_error_msg());
}

// Check if pengesahan section exists
if (!isset($formData['pengesahan'])) {
    error_log("Warning: pengesahan section not found in form data");
} else {
    error_log("pengesahan section found");
    
    // Check direkod_oleh section
    if (!isset($formData['pengesahan']['direkod_oleh'])) {
        error_log("Warning: direkod_oleh section not found in pengesahan data");
    } else {
        error_log("direkod_oleh section found");
    }
}

// If the data contains tandatangan (signature) in base64 format, make sure it's properly loaded
if (isset($formData['pengesahan']['direkod_oleh']['tandatangan'])) {
    // Ensure the base64 data doesn't contain any whitespace which could cause decoding issues
    $formData['pengesahan']['direkod_oleh']['tandatangan'] = 
        trim($formData['pengesahan']['direkod_oleh']['tandatangan']);
        
    // Log the length of the signature data for debugging
    error_log("Signature data length: " . strlen($formData['pengesahan']['direkod_oleh']['tandatangan']));
}

// Handle cop (stamp) data
if (isset($formData['pengesahan']['direkod_oleh']['cop'])) {
    $formData['pengesahan']['direkod_oleh']['cop'] = 
        trim($formData['pengesahan']['direkod_oleh']['cop']);
    
    // Log the length of the cop data for debugging
    error_log("Cop data length: " . strlen($formData['pengesahan']['direkod_oleh']['cop']));
}

// Set up the form type for display
$formType = $formData['form'] ?? 'Unknown Form';

// Set up the survey job information
$surveyJob = [
    'surveyjob_no' => 'EXAMPLE-001',
    'projectname' => 'Example B1 Form Display'
];

// Include the view form template
include('view_form.php');
?>
