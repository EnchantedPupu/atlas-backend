<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    if (!isset($_GET['job_id'])) {
        throw new Exception('Job ID is required');
    }
    
    $jobId = (int)$_GET['job_id'];
    
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception('Database connection failed');
    }
    
    // Get all forms for the job
    $stmt = $db->prepare("
        SELECT 
            f.form_id,
            f.form_type,
            f.form_title,
            f.form_data,
            f.created_at,
            sj.surveyjob_no,
            DATE_FORMAT(f.created_at, '%d/%m/%Y %H:%i') as created_at_formatted
        FROM Form f
        LEFT JOIN SurveyJob sj ON f.survey_job_id = sj.survey_job_id
        WHERE f.survey_job_id = ?
        ORDER BY f.created_at DESC
    ");
    
    $stmt->execute([$jobId]);
    $forms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Define form categories based on form types
    $formCategories = [
        'A1' => [
            'category' => 'Hak Adat Bumiputra',
            'description' => 'Native Customary Rights Forms',
            'icon' => '🏞️'
        ],
        'B1' => [
            'category' => 'Tanah Hakmilik',
            'description' => 'Titled Land Forms', 
            'icon' => '🏘️'
        ],
        'C1' => [
            'category' => 'Laporan Kolam Ikan & Bangunan',
            'description' => 'Fish Pond & Building Reports',
            'icon' => '🏗️'
        ],
        'LS16' => [
            'category' => 'Registered State Land',
            'description' => 'State Land Registration Forms',
            'icon' => '📋'
        ]
    ];
    
    // Group forms by category
    $categorizedForms = [];
    
    foreach ($forms as $form) {
        // Decode form_data to get form_id
        $formData = json_decode($form['form_data'], true);
        $formId = $formData['form_id'] ?? 'unknown';
        
        // Determine category
        $categoryInfo = $formCategories[$formId] ?? [
            'category' => 'Other Forms',
            'description' => 'Miscellaneous Forms',
            'icon' => '📄'
        ];
        
        $category = $categoryInfo['category'];
        
        if (!isset($categorizedForms[$category])) {
            $categorizedForms[$category] = [
                'category' => $category,
                'description' => $categoryInfo['description'],
                'icon' => $categoryInfo['icon'],
                'forms' => []
            ];
        }
        
        // Add form to category
        $categorizedForms[$category]['forms'][] = [
            'form_id' => $form['form_id'],
            'form_type' => $form['form_type'],
            'form_title' => $form['form_title'],
            'form_category' => $category,
            'form_data' => json_decode($form['form_data'], true),
            'created_at' => $form['created_at'],
            'created_at_formatted' => $form['created_at_formatted'],
            'job_number' => $form['surveyjob_no']
        ];
    }
    
    // Convert to indexed array and sort categories
    $sortedCategories = array_values($categorizedForms);
    usort($sortedCategories, function($a, $b) {
        $order = ['Hak Adat Bumiputra', 'Tanah Hakmilik', 'Laporan Kolam Ikan & Bangunan', 'Registered State Land', 'Other Forms'];
        $aIndex = array_search($a['category'], $order);
        $bIndex = array_search($b['category'], $order);
        return ($aIndex !== false ? $aIndex : 999) <=> ($bIndex !== false ? $bIndex : 999);
    });
    
    echo json_encode([
        'success' => true,
        'categories' => $sortedCategories,
        'total_forms' => count($forms),
        'total_categories' => count($categorizedForms)
    ]);
    
} catch (Exception $e) {
    error_log("Get Job Forms Categorized Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
