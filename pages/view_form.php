<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/database.php';

// Skip database query if this is the example display script
if (basename($_SERVER['PHP_SELF']) !== 'display_example_b1.php') {
    // Get form ID from URL parameter
    $form_id = isset($_GET['form_id']) ? $_GET['form_id'] : null;

    if (!$form_id) {
        die('Form ID not provided');
    }

    try {
        // Create database connection using the Database class
        $database = new Database();
        $pdo = $database->getConnection();
        
        if (!$pdo) {
            die('Database connection failed');
        }
        
        // Get the form data
    $stmt = $pdo->prepare("SELECT form_data, form_type, surveyjob_id FROM forms WHERE form_id = ?");
    $stmt->execute([$form_id]);
    $form = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$form) {
        die('Form not found');
    }
    
    $formData = json_decode($form['form_data'], true);
    $formType = $form['form_type'];
    $surveyJobId = $form['surveyjob_id'];
    
    // Get survey job details
    $stmt = $pdo->prepare("SELECT surveyjob_no, projectname FROM surveyjob WHERE survey_job_id = ?");
    $stmt->execute([$surveyJobId]);
    $surveyJob = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

} // End of if statement for checking if it's not example display
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Form View - <?php echo htmlspecialchars($formType); ?></title>
    <link rel="stylesheet" href="/assets/css/forms-display.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Inter', 'Roboto', 'Helvetica Neue', Arial, sans-serif;
            margin: 0;
            background-color: #F8FAFC;
            color: #0F172A;
            font-size: 14px;
            line-height: 1.6;
            padding: 2rem 1rem;
        }
        
        .container {
            max-width: 960px;
            margin: 0 auto;
            background: #FFFFFF;
            padding: 3rem;
            border-radius: 16px;
            border: 1px solid #E2E8F0;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.04);
        }
        
        .header {
            text-align: center;
            border-bottom: 2px solid #2563EB;
            padding-bottom: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .header h1 {
            margin: 0 0 0.75rem 0;
            font-size: 1.75rem;
            font-weight: 700;
            color: #0F172A;
            letter-spacing: -0.5px;
        }
        
        .header h2 {
            margin: 0;
            font-size: 1rem;
            font-weight: 500;
            color: #64748B;
        }
        
        .form-section {
            margin-bottom: 2rem;
            border: 1px solid #E2E8F0;
            border-radius: 12px;
            overflow: hidden;
            overflow-x: auto; /* Allow horizontal scrolling */
        }
        
        .section-title {
            background: linear-gradient(135deg, #F8FAFC, #EFF6FF);
            padding: 1rem 1.25rem;
            font-weight: 600;
            border-bottom: 1px solid #E2E8F0;
            font-size: 1rem;
            color: #0F172A;
            letter-spacing: -0.2px;
        }
        
        .form-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .form-table td {
            border: 1px solid #E2E8F0;
            padding: 0.875rem 1rem;
            vertical-align: top;
        }
        
        .form-label {
            width: 220px;
            font-weight: 600;
            background-color: #F8FAFC;
            text-align: left;
            color: #475569;
            font-size: 0.875rem;
        }
        
        .form-value {
            min-height: 24px;
            color: #0F172A;
            font-weight: 500;
        }
        
        .form-value:empty::after {
            content: "—";
            color: #CBD5E1;
        }
        
        .signature-box {
            border: 2px dashed #CBD5E1;
            border-radius: 8px;
            height: 120px;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #FAFBFC;
            margin: 0.5rem 0;
            transition: all 0.2s;
        }
        
        .signature-box:hover {
            border-color: #2563EB;
            background-color: #EFF6FF;
        }
        
        .signature-image {
            max-width: 280px;
            max-height: 100px;
            border-radius: 4px;
        }
        
        .array-item {
            border: 1px solid #E2E8F0;
            padding: 1rem;
            margin-bottom: 1rem;
            background-color: #FAFBFC;
            border-radius: 8px;
        }
        
        .array-item-title {
            font-weight: 600;
            margin-bottom: 0.75rem;
            color: #64748B;
            font-size: 0.8125rem;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        
        .lot-info {
            background: linear-gradient(135deg, #EFF6FF, #DBEAFE);
            padding: 1.25rem;
            margin-bottom: 1.5rem;
            border: 2px solid #2563EB;
            border-radius: 12px;
            text-align: center;
        }
        
        .lot-number {
            font-size: 1.25rem;
            font-weight: 700;
            color: #1E40AF;
            letter-spacing: -0.3px;
        }
        
        .form-type-badge {
            display: inline-block;
            background: linear-gradient(135deg, #2563EB, #1D4ED8);
            color: white;
            padding: 0.375rem 0.875rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-left: 0.75rem;
            letter-spacing: 0.3px;
            text-transform: uppercase;
            box-shadow: 0 2px 4px rgba(37, 99, 235, 0.2);
        }
        
        .nested-table {
            width: 100%;
            border-collapse: collapse;
            margin: 0.5rem 0;
        }
        
        .nested-table td {
            border: 1px solid #E2E8F0;
            padding: 0.5rem 0.75rem;
            font-size: 0.875rem;
        }
        
        .nested-table .nested-label {
            width: 140px;
            font-weight: 600;
            background-color: #F8FAFC;
            color: #64748B;
        }
        
        @media print {
            body { 
                margin: 0; 
                background: white;
                padding: 0;
            }
            .container { 
                box-shadow: none;
                border: none;
                border-radius: 0;
            }
            .form-section { 
                page-break-inside: avoid;
                border-radius: 0;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><?php 
                if (strcmp($formData["form"], "Tanah HakMilik") === 0) {
                    echo "LAPORAN KEBUN DAN TANAMAN (FORM B1)";
                } else if(strcmp($formData["form"], "Laporan Hak Adat Bumiputra") === 0) {
                    echo "LAPORAN HAK ADAT BUMIPUTERA (FORM A1)";
                } else if(strcmp($formData["form"], "Laporan Kolam Ikan, Bangunan & Lain-Lain") === 0) {
                    echo "LAPORAN KOLAM IKAN, BANGUNAN & LAIN-LAIN (FORM C1)";
                } else if(strcmp($formData["form"], "L&S 16") === 0) {
                    echo "VERIFICATION OF BOUNDARY (L&16)";
                } else if(strcmp($formData["form"], "Laporan Tanah Perhutanan") === 0) {
                    echo "LAPORAN TANAH PERHUTANAN (FORM F1)";

                }
                else {
                    echo htmlspecialchars($formData["form"] ?? 'Unknown Form');
                }
            ?></h1>
            <h2>Survey Job: <?php echo htmlspecialchars($surveyJob['surveyjob_no'] ?? ''); ?></h2>
            <h2>Project: <?php echo htmlspecialchars($surveyJob['projectname'] ?? ''); ?></h2>
            <?php if (basename($_SERVER['PHP_SELF']) === 'display_example_b1.php'): ?>
            <div class="alert" style="background-color: #ffeeaa; padding: 8px; border-radius: 4px; margin-top: 10px;">
                <strong>Note:</strong> This is an example B1 form display using the sample data.
            </div>
            <?php endif; ?>
        </div>

        <?php if (isset($formData['tanah']['lot_no']) && !empty($formData['tanah']['lot_no'])): ?>
        <div class="lot-info">
            <div class="lot-number">Lot Number: <?php echo htmlspecialchars($formData['tanah']['lot_no']); ?></div>
            <span class="form-type-badge"><?php echo htmlspecialchars($formType); ?></span>
        </div>
        <?php endif; ?>

        <?php
        // Function to render form sections as tables
        function renderFormSection($data, $sectionTitle, $excludeKeys = [], $tidakBerkaitanSections = [], $sectionKey = null) {
            if (empty($data) || !is_array($data)) return;
            
            // Check if this section is in tidak_berkaitan_sections
            $isTidakBerkaitan = in_array($sectionTitle, $tidakBerkaitanSections);
            if ($sectionKey) {
                $isTidakBerkaitan = $isTidakBerkaitan || in_array($sectionKey, $tidakBerkaitanSections);
            }
            
            echo "<div class='form-section'>";
            echo "<div class='section-title'>" . htmlspecialchars($sectionTitle);
            if ($isTidakBerkaitan) {
                echo " <span style='color: #dc2626; font-weight: 700; margin-left: 10px;'>[TIDAK BERKENAAN]</span>";
            }
            echo "</div>";
            
            // If section is TIDAK BERKENAAN, don't render the content
            if ($isTidakBerkaitan) {
                echo "</div>";
                return;
            }
            
            echo "<table class='form-table'>";
            
            foreach ($data as $key => $value) {
                if (in_array($key, $excludeKeys)) continue;
                
                if (is_array($value)) {
                    if (isset($value[0]) && is_array($value[0])) {
                        // Array of objects
                        echo "<tr>";
                        echo "<td class='form-label'>" . htmlspecialchars(ucwords(str_replace('_', ' ', $key))) . ":</td>";
                        echo "<td class='form-value'>";
                        foreach ($value as $index => $item) {
                            echo "<div class='array-item'>";
                            echo "<div class='array-item-title'>Item " . ($index + 1) . "</div>";
                            echo "<table class='nested-table'>";
                            foreach ($item as $itemKey => $itemValue) {
                                if ($itemKey === 'tandatangan' && !empty($itemValue)) {
                                    echo "<tr>";
                                    echo "<td class='nested-label'>" . htmlspecialchars(ucwords(str_replace('_', ' ', $itemKey))) . ":</td>";
                                    echo "<td>";
                                    echo "<div class='signature-box'>";
                                    $imgPath = base64ToImage($itemValue, 'signature');
                                    echo "<img src='" . htmlspecialchars($imgPath) . "' class='signature-image' alt='Signature'>";
                                    echo "</div>";
                                    echo "</td>";
                                    echo "</tr>";
                                }
                                else if ($itemKey === 'cop' && !empty($itemValue)) {
                                    echo "<tr>";
                                    echo "<td class='nested-label'>" . htmlspecialchars(ucwords(str_replace('_', ' ', $itemKey))) . ":</td>";
                                    echo "<td>";
                                    echo "<div class='signature-box'>";
                                    $imgPath = base64ToImage($itemValue, 'stamp');
                                    echo "<img src='" . htmlspecialchars($imgPath) . "' class='signature-image' alt='Stamp'>";
                                    echo "</div>";
                                    echo "</td>";
                                    echo "</tr>";
                                }
                                else {
                                    echo "<tr>";
                                    echo "<td class='nested-label'>" . htmlspecialchars(ucwords(str_replace('_', ' ', $itemKey))) . ":</td>";
                                    echo "<td>" . formatFormValue($itemValue, $itemKey) . "</td>";
                                    echo "</tr>";
                                }
                            }
                            echo "</table>";
                            echo "</div>";
                        }
                        echo "</td>";
                        echo "</tr>";
                    } else {
                        // Single object
                        echo "<tr>";
                        echo "<td class='form-label'>" . htmlspecialchars(ucwords(str_replace('_', ' ', $key))) . ":</td>";
                        echo "<td class='form-value'>";
                        echo "<table class='nested-table'>";
                        foreach ($value as $subKey => $subValue) {
                            if ($subKey === 'tandatangan' && !empty($subValue)) {
                                echo "<tr>";
                                echo "<td class='nested-label'>" . htmlspecialchars(ucwords(str_replace('_', ' ', $subKey))) . ":</td>";
                                echo "<td>";
                                echo "<div class='signature-box'>";
                                $imgPath = base64ToImage($subValue, 'signature');
                                echo "<img src='" . htmlspecialchars($imgPath) . "' class='signature-image' alt='Signature'>";
                                echo "</div>";
                                echo "</td>";
                                echo "</tr>";
                            } else if ($subKey === 'cop' && !empty($subValue)) {
                                echo "<tr>";
                                echo "<td class='nested-label'>" . htmlspecialchars(ucwords(str_replace('_', ' ', $subKey))) . ":</td>";
                                echo "<td>";
                                echo "<div class='signature-box'>";
                                $imgPath = base64ToImage($subValue, 'stamp');
                                echo "<img src='" . htmlspecialchars($imgPath) . "' class='signature-image' alt='Stamp'>";
                                echo "</div>";
                                echo "</td>";
                                echo "</tr>";
                            } else {
                                echo "<tr>";
                                echo "<td class='nested-label'>" . htmlspecialchars(ucwords(str_replace('_', ' ', $subKey))) . ":</td>";
                                echo "<td>" . formatFormValue($subValue, $subKey) . "</td>";
                                echo "</tr>";
                            }
                        }
                        echo "</table>";
                        echo "</td>";
                        echo "</tr>";
                    }
                } else {
                    echo "<tr>";
                    echo "<td class='form-label'>" . htmlspecialchars(ucwords(str_replace('_', ' ', $key))) . ":</td>";
                    echo "<td class='form-value'>" . formatFormValue($value, $key) . "</td>";
                    echo "</tr>";
                }
            }
            
            echo "</table>";
            echo "</div>";
        }

        // Function to format form values, especially boolean fields
        function formatFormValue($value, $key) {
            // Check if value is a base64 image (signature, stamp, or sketch fields)
            if (is_string($value) && !empty($value)) {
                // Check if the key indicates this should be an image field
                $imageKeywords = ['tandatangan', 'signature', 'cop', 'lakaran'];
                $isImageField = false;
                
                foreach ($imageKeywords as $keyword) {
                    if (stripos($key, $keyword) !== false) {
                        $isImageField = true;
                        break;
                    }
                }
                
                // If it's an image field and contains base64 data (starts with common base64 image prefixes)
                if ($isImageField && (strpos($value, 'iVBORw0KGgo') === 0 || strpos($value, '/9j/') === 0 || strpos($value, 'data:image/') === 0)) {
                    // Determine image type based on key name
                    $prefix = 'signature';
                    if (stripos($key, 'cop') !== false) {
                        $prefix = 'stamp';
                    } elseif (stripos($key, 'lakaran') !== false) {
                        $prefix = 'sketch';
                    }
                    
                    $imgPath = base64ToImage($value, $prefix);
                    $html = '<div class="signature-box">';
                    $html .= '<img src="' . htmlspecialchars($imgPath) . '" class="signature-image" alt="' . ucwords(str_replace('_', ' ', $key)) . '">';
                    $html .= '</div>';
                    return $html;
                }
            }
            
            if ($key === 'keadaan') {
                $options = ['sangat baik', 'baik', 'sederhana', 'tidak baik'];
                $html = '<table style="width: 100%; border-collapse: collapse;">';
                $html .= '<tr><td style="border: 1px solid #333; padding: 4px 8px; text-align: center; font-weight: bold; background-color: #f0f0f0;">Keadaan</td></tr>';
                $html .= '<tr><td style="border: 1px solid #333; padding: 8px;">';
                $html .= '<div style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">';
                foreach ($options as $option) {
                    $isSelected = (strtolower($value) === strtolower($option));
                    $tick = $isSelected ? '<div style="text-align: center; margin-top: 4px;"><span style="font-size: 16px; color: #28a745;">✓</span></div>' : '';
                    $html .= '<div style="text-align: center;">';
                    $html .= '<div style="border: 1px solid #333; padding: 4px 8px; min-width: 80px; text-align: center;">' . ucfirst($option) . '</div>';
                    $html .= $tick;
                    $html .= '</div>';
                }
                $html .= '</div></td></tr></table>';
                return $html;
            }
            
            // Handle specific fields that need table layout despite being boolean/enum
            // 'bersetuju' is kept here to preserve the table layout when it's False (or in non-B1 forms)
            // 'ada_pertikaian' is removed so it falls through to generic boolean handler
            $specificTableFields = ['bersetuju'];
            
            if (in_array($key, $specificTableFields)) {
                $options = ['ya', 'tidak'];
                $html = '<table style="width: 100%; border-collapse: collapse;">';
                $html .= '<tr><td style="border: 1px solid #333; padding: 4px 8px; text-align: center; font-weight: bold; background-color: #f0f0f0;">' . ucwords(str_replace('_', ' ', $key)) . '</td></tr>';
                $html .= '<tr><td style="border: 1px solid #333; padding: 8px;">';
                $html .= '<div style="display: flex; gap: 15px; justify-content: center;">';
                foreach ($options as $option) {
                    $isSelected = ($value === true && $option === 'ya') || ($value === false && $option === 'tidak');
                    $tick = $isSelected ? '<div style="text-align: center; margin-top: 4px;"><span style="font-size: 16px; color: #28a745;">✓</span></div>' : '';
                    $html .= '<div style="text-align: center;">';
                    $html .= '<div style="border: 1px solid #333; padding: 4px 8px; min-width: 60px; text-align: center;">' . ucfirst($option) . '</div>';
                    $html .= $tick;
                    $html .= '</div>';
                }
                $html .= '</div></td></tr></table>';
                return $html;
            }

            // Generic boolean handler for all other boolean fields (including berbuah, penjagaan, ada_pertikaian)
            if (is_bool($value)) {
                return $value ? 'YA' : 'TIDAK';
            }
            
            // Handle empty values
            if (empty($value) && $value !== '0') {
                return '<span style="color: #999;">_________________</span>';
            }
            
            return htmlspecialchars($value);
        }

        // Function to convert base64 image string to data URI for direct browser display
        function base64ToImage($base64String, $prefix = 'form_img') {
            // Check if the base64 string is empty
            if (empty($base64String)) {
                return '';
            }
            
            // Debug
            error_log("Processing base64 image with prefix: " . $prefix);
            
            // Determine image format based on the prefix or default to png
            $imageFormat = 'png';
            if ($prefix === 'stamp') {
                $imageFormat = 'jpeg';
            }
            
            // Check if base64 string already has the data URI prefix
            if (strpos($base64String, 'data:image/') === 0) {
                // Already a data URI, return as is
                return $base64String;
            }
            
            // Create a proper data URI
            $dataUri = 'data:image/' . $imageFormat . ';base64,' . $base64String;
            
            return $dataUri;
        }

        // Extract tidak_berkaitan_sections from form data
        $tidakBerkaitanSections = isset($formData['tidak_berkaitan_sections']) && is_array($formData['tidak_berkaitan_sections']) 
            ? $formData['tidak_berkaitan_sections'] 
            : [];

        // --- B1 FORM CUSTOM TABLE RENDERING ---
        if (($formData['form_id'] ?? '') === 'B1') {
            // 1. Maklumat Tanah
            if (isset($formData['tanah'])) {
                renderFormSection($formData['tanah'], 'Maklumat Tanah', [], $tidakBerkaitanSections, 'tanah');
            }
            // 2. Penuntut
            if (isset($formData['penuntut'])) {
                renderFormSection($formData['penuntut'], 'Penuntut', [], $tidakBerkaitanSections, 'penuntut');
            }
            // 3. Kebun (custom)
            if (isset($formData['kebun'])) {
                $kebun = $formData['kebun'];
                $isTidakBerkaitan = in_array('Maklumat Kebun', $tidakBerkaitanSections) || in_array('kebun', $tidakBerkaitanSections);
                echo '<div class="form-section">';
                echo '<div style="font-weight:bold; background:#ccc; padding:4px 8px; border-bottom:1px solid #333;">3(i) KEBUN I';
                if ($isTidakBerkaitan) {
                    echo ' <span style="color: #dc2626; font-weight: 700; margin-left: 10px;">[TIDAK BERKENAAN]</span>';
                }
                echo '</div>';
                if (!$isTidakBerkaitan) {
                    echo '<table class="form-table" style="text-align:center; margin-bottom:0;">';
                    echo '<tr style="background:#eee; font-weight:bold;">';
                    echo '<td rowspan="2">Jenis Kebun</td>';
                    echo '<td rowspan="2">Anggaran Keluasan<br>(Hektar)</td>';
                    echo '<td rowspan="2">Umur<br>(Tahun)</td>';
                    echo '<td rowspan="2">Berbuah</td>';
                    echo '<td colspan="4">Keadaan</td>';
                    echo '<td rowspan="2">Penjagaan</td>';
                    echo '<td rowspan="2">Catatan Am</td>';
                    echo '</tr>';
                    echo '<tr style="background:#eee; font-weight:bold;">';
                    // echo '<td>Sudah</td><td>Belum</td>'; // Specific cols removed
                    echo '<td>Sangat Baik</td><td>Baik</td><td>Sederhana</td><td>Tidak Baik</td>';
                    // echo '<td>Ya</td><td>Tidak</td>'; // Specific cols removed
                    echo '</tr>';
                    echo '<tr>';
                    echo '<td>' . htmlspecialchars($kebun['jenis_kebun'] ?? '') . '</td>';
                    echo '<td>' . htmlspecialchars($kebun['anggaran_keluasan'] ?? '') . '</td>';
                    echo '<td>' . htmlspecialchars($kebun['umur'] ?? '') . '</td>';
                    
                    // Berbuah Logic
                    $bVal = $kebun['berbuah'] ?? null;
                    $bText = '';
                    if ($bVal === true || $bVal === 'true' || $bVal === 1) $bText = 'YA';
                    elseif ($bVal === false || $bVal === 'false' || $bVal === 0) $bText = 'TIDAK';
                    echo '<td>' . $bText . '</td>';
                    
                    $keadaanOpts = ['sangat baik', 'baik', 'sederhana', 'tidak baik'];
                    foreach ($keadaanOpts as $opt) {
                        echo '<td>' . (strtolower($kebun['keadaan'] ?? '') === $opt ? '✓' : '') . '</td>';
                    }
                    
                    // Penjagaan Logic
                    $pVal = $kebun['penjagaan'] ?? null;
                    $pText = '';
                    if ($pVal === true || $pVal === 'true' || $pVal === 1) $pText = 'YA';
                    elseif ($pVal === false || $pVal === 'false' || $pVal === 0) $pText = 'TIDAK';
                    echo '<td>' . $pText . '</td>';
                    
                    echo '<td>' . htmlspecialchars($kebun['catatan'] ?? '') . '</td>';
                    echo '</tr>';
                    // Faktor Penilaian row (new)
                    if (isset($formData['faktor_penilaian'])) {
                        $fp = $formData['faktor_penilaian'];
                        echo '<tr><td colspan="10" style="text-align:left;">Faktor Penilaian: ';
                        $fpParts = [];
                        $fpParts[] = 'Berbuah: ' . (($fp['berbuah'] ?? false) ? '✓' : '✗');
                        $fpParts[] = 'Keadaan: ' . htmlspecialchars($fp['keadaan'] ?? '');
                        $fpParts[] = 'Penjagaan: ' . (($fp['penjagaan'] ?? false) ? '✓' : '✗');
                        echo implode(' | ', $fpParts);
                        echo '</td></tr>';
                    } else {
                        echo '<tr><td colspan="10" style="text-align:left;">Faktor Penilaian</td></tr>';
                    }
                    echo '</table>';
                    echo '<div style="font-size:11px; margin-top:2px;">Nota : Gunakan para 3(i) jika terdapat lebih dari sebuah kebun dalam Flt. atau Lot diatas.</div>';
                }
                echo '</div>';
            }
            // 4. Tanaman-Tanaman Lain (custom)
            if (isset($formData['tanaman_lain']) && is_array($formData['tanaman_lain'])) {
                $isTidakBerkaitan = in_array('Tanaman Lain', $tidakBerkaitanSections) || in_array('tanaman_lain', $tidakBerkaitanSections);
                echo '<div class="form-section">';
                echo '<div style="font-weight:bold; background:#ccc; padding:4px 8px; border-bottom:1px solid #333;">4. TANAMAN-TANAMAN LAIN';
                if ($isTidakBerkaitan) {
                    echo ' <span style="color: #dc2626; font-weight: 700; margin-left: 10px;">[TIDAK BERKENAAN]</span>';
                }
                echo '</div>';
                if (!$isTidakBerkaitan) {
                    echo '<table class="form-table" style="text-align:center;">';
                    echo '<tr style="background:#eee; font-weight:bold;">'; //dasd
                    echo '<td rowspan="2">Jenis Tanaman</td>';
                    echo '<td rowspan="2">Bilangan Ditanam</td>';
                    echo '<td rowspan="2">Umur<br>(Tahun)</td>';
                    echo '<td rowspan="2">Berbuah</td>';
                    echo '<td colspan="4">Keadaan</td>';
                    echo '<td rowspan="2">Penjagaan</td>';
                    echo '<td rowspan="2">Catatan Am</td>';
                    echo '</tr>';
                    echo '<tr style="background:#eee; font-weight:bold;">';
                    // echo '<td>Ya</td><td>Belum</td>';
                    echo '<td>Sangat Baik</td><td>Baik</td><td>Sederhana</td><td>Tidak Baik</td>';
                    // echo '<td>Ya</td><td>Tidak</td>';
                    echo '</tr>';
                    $keadaanOpts = ['sangat baik', 'baik', 'sederhana', 'tidak baik'];
                    foreach ($formData['tanaman_lain'] as $tanaman) {
                        echo '<tr>';
                        echo '<td>' . htmlspecialchars($tanaman['jenis_tanaman'] ?? '') . '</td>';
                        echo '<td>' . htmlspecialchars($tanaman['bilangan_ditanam'] ?? '') . '</td>';
                        echo '<td>' . htmlspecialchars($tanaman['umur'] ?? '') . '</td>';
                        
                        // Berbuah
                        $bVal = $tanaman['berbuah'] ?? null;
                        $bText = '';
                        if ($bVal === true || $bVal === 'true' || $bVal === 1) $bText = 'YA';
                        elseif ($bVal === false || $bVal === 'false' || $bVal === 0) $bText = 'TIDAK';
                        echo '<td>' . $bText . '</td>';
                        
                        foreach ($keadaanOpts as $opt) {
                            echo '<td>' . (strtolower($tanaman['keadaan'] ?? '') === $opt ? '✓' : '') . '</td>';
                        }
                        
                        // Penjagaan
                        $pVal = $tanaman['penjagaan'] ?? null;
                        $pText = '';
                        if ($pVal === true || $pVal === 'true' || $pVal === 1) $pText = 'YA';
                        elseif ($pVal === false || $pVal === 'false' || $pVal === 0) $pText = 'TIDAK';
                        echo '<td>' . $pText . '</td>';
                        
                        echo '<td>' . htmlspecialchars($tanaman['catatan'] ?? '') . '</td>';
                        echo '</tr>';
                    }
                    for ($i = 0; $i < 8; $i++) {
                        echo '<tr>';
                        for ($j = 0; $j < 10; $j++) echo '<td>&nbsp;</td>';
                        echo '</tr>';
                    }
                    echo '</table>';
                }
                echo '</div>';
            }
            // 5. Lakaran (image)
            if (isset($formData['lakaran']['lakaran_field_lot']) && !empty($formData['lakaran']['lakaran_field_lot'])) {
                echo '<div class="form-section">';
                echo '<div class="section-title">Lakaran Field Lot</div>';
                echo '<div style="text-align:center;">';
                $imgPath = base64ToImage($formData['lakaran']['lakaran_field_lot'], 'lakaran_lot');
                echo '<img src="' . htmlspecialchars($imgPath) . '" alt="Lakaran Field Lot" style="max-width:100%; max-height:600px; border:1px solid #333; margin-bottom:10px;">';
                echo '</div>';
                if (!empty($formData['lakaran']['catatan'])) {
                    echo '<div style="margin-top:8px;">Catatan: ' . htmlspecialchars($formData['lakaran']['catatan']) . '</div>';
                }
                echo '</div>';
            }
            // 6. Pengesahan Penuntut
            if (isset($formData['pengesahan_penuntut'])) {
                $ppData = $formData['pengesahan_penuntut'];
                // Check if 'bersetuju' is true
                if (isset($ppData['bersetuju']) && ($ppData['bersetuju'] === true || $ppData['bersetuju'] === 'true')) {
                    $sectionTitle = 'Pengesahan Penuntut';
                    $isTidakBerkaitan = in_array($sectionTitle, $tidakBerkaitanSections) || in_array('pengesahan_penuntut', $tidakBerkaitanSections);
                    
                    echo "<div class='form-section'>";
                    echo "<div class='section-title'>" . htmlspecialchars($sectionTitle);
                    if ($isTidakBerkaitan) {
                        echo " <span style='color: #dc2626; font-weight: 700; margin-left: 10px;'>[TIDAK BERKENAAN]</span>";
                    }
                    echo "</div>";
                    
                    if (!$isTidakBerkaitan) {
                        echo "<table class='form-table'>";
                        foreach ($ppData as $key => $value) {
                            if ($key === 'bersetuju') {
                                echo "<tr>";
                                echo "<td colspan='2' class='form-value' style='padding: 1rem; font-style: italic;'>saya bersetuju dengan penghitungan dan laporan seperti tersebut diatas</td>";
                                echo "</tr>";
                            } else {
                                echo "<tr>";
                                echo "<td class='form-label'>" . htmlspecialchars(ucwords(str_replace('_', ' ', $key))) . ":</td>";
                                echo "<td class='form-value'>" . formatFormValue($value, $key) . "</td>";
                                echo "</tr>";
                            }
                        }
                        echo "</table>";
                    }
                    echo "</div>";
                } else {
                    renderFormSection($formData['pengesahan_penuntut'], 'Pengesahan Penuntut', [], $tidakBerkaitanSections, 'pengesahan_penuntut');
                }
            }
            // 7. Pengesahan Pengukur
            if (isset($formData['pengesahan_pengukur'])) {
                renderFormSection($formData['pengesahan_pengukur'], 'Pengesahan Pengukur', [], $tidakBerkaitanSections, 'pengesahan_pengukur');
            }
            // 8. Pengesahan (for signatures and stamps)
            if (isset($formData['pengesahan'])) {
                renderFormSection($formData['pengesahan'], 'Pengesahan', [], $tidakBerkaitanSections, 'pengesahan');
            }
            
            // Render Proof Signature (Admin Only)
            if (isset($formData['proof_signature']) && isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in']) {
                renderFormSection($formData['proof_signature'], 'Proof Signature', [], $tidakBerkaitanSections, 'proof_signature');
            }
        } else if (($formData['form_id'] ?? '') === 'C1') {
            // Render Tanah
            if (isset($formData['tanah'])) {
                renderFormSection($formData['tanah'], 'Maklumat Tanah', [], $tidakBerkaitanSections, 'tanah');
            }
            // Render Penuntut
            if (isset($formData['penuntut'])) {
                renderFormSection($formData['penuntut'], 'Penuntut', [], $tidakBerkaitanSections, 'penuntut');
            }
            // Render Kolam Ikan as grid table
            if (isset($formData['kolam_ikan']) && is_array($formData['kolam_ikan'])) {
                $isTidakBerkaitan = in_array('Kolam Ikan', $tidakBerkaitanSections) || in_array('kolam_ikan', $tidakBerkaitanSections);
                echo '<div class="form-section">';
                echo '<div style="font-weight:bold; background:#ccc; padding:4px 8px; border-bottom:1px solid #333;">KOLAM IKAN';
                if ($isTidakBerkaitan) {
                    echo ' <span style="color: #dc2626; font-weight: 700; margin-left: 10px;">[TIDAK BERKENAAN]</span>';
                }
                echo '</div>';
                if (!$isTidakBerkaitan) {
                    echo '<table class="form-table" style="text-align:center;">';
                    echo '<tr style="background:#eee; font-weight:bold;">';
                    echo '<td>Jenis Kolam</td>';
                    echo '<td>Anggaran Keluasan</td>';
                    echo '<td>Keadaan</td>';
                    echo '<td>Penjagaan</td>';
                    echo '<td>Penilaian Keadaan</td>';
                    echo '<td>Penilaian Penjagaan</td>';
                    echo '<td>Catatan Am</td>';
                    echo '</tr>';
                    foreach ($formData['kolam_ikan'] as $kolam) {
                        echo '<tr>';
                        echo '<td>' . htmlspecialchars($kolam['jenis_kolam'] ?? '') . '</td>';
                        echo '<td>' . htmlspecialchars($kolam['anggaran_keluasan'] ?? '') . '</td>';
                        echo '<td>' . htmlspecialchars($kolam['keadaan'] ?? '') . '</td>';
                        echo '<td>' . (($kolam['penjagaan'] === true || $kolam['penjagaan'] === 'true' || $kolam['penjagaan'] === 1) ? '✓' : (($kolam['penjagaan'] === false || $kolam['penjagaan'] === 'false' || $kolam['penjagaan'] === 0) ? '✗' : '')) . '</td>';
                        echo '<td>' . htmlspecialchars($kolam['penilaian_keadaan'] ?? '') . '</td>';
                        echo '<td>' . htmlspecialchars($kolam['penilaian_penjagaan'] ?? '') . '</td>';
                        echo '<td>' . htmlspecialchars($kolam['catatan'] ?? '') . '</td>';
                        echo '</tr>';
                    }
                    echo '</table>';
                }
                echo '</div>';
            }
            // Render Bangunan as grid table
            if (isset($formData['bangunan']) && is_array($formData['bangunan'])) {
                $isTidakBerkaitan = in_array('Bangunan', $tidakBerkaitanSections) || in_array('bangunan', $tidakBerkaitanSections);
                echo '<div class="form-section">';
                echo '<div style="font-weight:bold; background:#ccc; padding:4px 8px; border-bottom:1px solid #333;">BANGUNAN';
                if ($isTidakBerkaitan) {
                    echo ' <span style="color: #dc2626; font-weight: 700; margin-left: 10px;">[TIDAK BERKENAAN]</span>';
                }
                echo '</div>';
                if (!$isTidakBerkaitan) {
                    echo '<table class="form-table" style="text-align:center;">';
                    echo '<tr style="background:#eee; font-weight:bold;">';
                    echo '<td>Jenis Bangunan</td>';
                    echo '<td>Umur</td>';
                    echo '<td>Dimensi</td>';
                    echo '<td>Keadaan</td>';
                    echo '<td>Anggaran Kos</td>';
                    echo '<td>Tiang</td>';
                    echo '<td>Lantai</td>';
                    echo '<td>Dinding</td>';
                    echo '<td>Atap</td>';
                    echo '<td>Tingkap</td>';
                    echo '<td>Siling</td>';
                    echo '<td>Catatan Am</td>';
                    echo '</tr>';
                    foreach ($formData['bangunan'] as $bangunan) {
                        echo '<tr>';
                        echo '<td>' . htmlspecialchars($bangunan['jenis_bangunan'] ?? '') . '</td>';
                        echo '<td>' . htmlspecialchars($bangunan['umur'] ?? '') . '</td>';
                        echo '<td>' . htmlspecialchars($bangunan['dimensi'] ?? '') . '</td>';
                        echo '<td>' . htmlspecialchars($bangunan['keadaan'] ?? '') . '</td>';
                        echo '<td>' . htmlspecialchars($bangunan['anggaran_kos'] ?? '') . '</td>';
                        echo '<td>' . htmlspecialchars($bangunan['tiang'] ?? '') . '</td>';
                        echo '<td>' . htmlspecialchars($bangunan['lantai'] ?? '') . '</td>';
                        echo '<td>' . htmlspecialchars($bangunan['dinding'] ?? '') . '</td>';
                        echo '<td>' . htmlspecialchars($bangunan['atap'] ?? '') . '</td>';
                        echo '<td>' . htmlspecialchars($bangunan['tingkap'] ?? '') . '</td>';
                        echo '<td>' . htmlspecialchars($bangunan['siling'] ?? '') . '</td>';
                        echo '<td>' . htmlspecialchars($bangunan['catatan'] ?? '') . '</td>';
                        echo '</tr>';
                    }
                    echo '</table>';
                }
                echo '</div>';
            }
            // Render Lakaran Dimensi Struktur as image
            if (isset($formData['lakaran']['lakaran_dimensi_struktur']) && !empty($formData['lakaran']['lakaran_dimensi_struktur'])) {
                echo '<div class="form-section">';
                echo '<div class="section-title">Lakaran Dimensi Struktur</div>';
                echo '<div style="text-align:center;">';
                $imgPath = base64ToImage($formData['lakaran']['lakaran_dimensi_struktur'], 'dimensi_struktur');
                echo '<img src="' . htmlspecialchars($imgPath) . '" alt="Lakaran Dimensi Struktur" style="max-width:100%; max-height:600px; border:1px solid #333; margin-bottom:10px;">';
                echo '</div>';
                if (!empty($formData['lakaran']['catatan'])) {
                    echo '<div style="margin-top:8px;">Catatan: ' . htmlspecialchars($formData['lakaran']['catatan']) . '</div>';
                }
                echo '</div>';
            }
            // Render Pengesahan
            if (isset($formData['pengesahan'])) {
                renderFormSection($formData['pengesahan'], 'Pengesahan', [], $tidakBerkaitanSections, 'pengesahan');
            }

            // Render Proof Signature (Admin Only)
            if (isset($formData['proof_signature']) && isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in']) {
                renderFormSection($formData['proof_signature'], 'Proof Signature', [], $tidakBerkaitanSections, 'proof_signature');
            }
        } else {
            // Generic rendering for other forms
            $excludeKeys = ['form', 'form_id', 'created_at', 'instance_id', 'form_type', 'tidak_berkaitan_sections'];
            // Render tanah section first
            if (isset($formData['tanah'])) {
                renderFormSection($formData['tanah'], 'Maklumat Tanah', [], $tidakBerkaitanSections, 'tanah');
            }
            // Render other sections
            foreach ($formData as $section => $data) {
                // Restrict Proof Signature to Admin Only
                if ($section === 'proof_signature' && (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in'])) {
                    continue;
                }

                if (!in_array($section, $excludeKeys) && $section !== 'tanah' && is_array($data)) {
                    $sectionTitle = ucwords(str_replace('_', ' ', $section));
                    renderFormSection($data, $sectionTitle, [], $tidakBerkaitanSections, $section);
                }
            }
        }
        ?>

        <div class="form-section">
            <div class="section-title">Form Information</div>
            <table class="form-table">
                <tr>
                    <td class="form-label">Form ID:</td>
                    <td class="form-value"><?php echo htmlspecialchars($formData['form_id'] ?? ''); ?></td>
                </tr>
                <tr>
                    <td class="form-label">Form Type:</td>
                    <td class="form-value"><?php echo htmlspecialchars($formType); ?></td>
                </tr>
                <tr>
                    <td class="form-label">Created At:</td>
                    <td class="form-value"><?php echo htmlspecialchars($formData['created_at'] ?? ''); ?></td>
                </tr>
            </table>
        </div>
    </div>
</body>
</html>