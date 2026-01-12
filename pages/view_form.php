<?php
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
        body {
            font-family: 'Times New Roman', serif;
            margin: 20px;
            background-color: #f5f5f5;
            color: #333;
            font-size: 12px;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .header h1 {
            margin: 0;
            font-size: 20px;
            font-weight: bold;
        }
        .header h2 {
            margin: 5px 0 0 0;
            font-size: 14px;
            font-weight: normal;
        }
        .form-section {
            margin-bottom: 25px;
            border: 1px solid #333;
        }
        .section-title {
            background-color: #f0f0f0;
            padding: 8px 12px;
            font-weight: bold;
            border-bottom: 1px solid #333;
            font-size: 14px;
        }
        .form-table {
            width: 100%;
            border-collapse: collapse;
        }
        .form-table td {
            border: 1px solid #333;
            padding: 6px 8px;
            vertical-align: top;
        }
        .form-label {
            width: 200px;
            font-weight: bold;
            background-color: #f9f9f9;
            text-align: left;
        }
        .form-value {
            min-height: 20px;
        }
        .form-value:empty::after {
            content: "_________________";
            color: #999;
        }
        .signature-box {
            border: 1px solid #333;
            height: 100px;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f9f9f9;
            margin: 2px 0;
        }
        .signature-image {
            max-width: 250px;
            max-height: 80px;
        }
        .array-item {
            border: 1px solid #ddd;
            padding: 8px;
            margin-bottom: 8px;
            background-color: #fafafa;
        }
        .array-item-title {
            font-weight: bold;
            margin-bottom: 8px;
            color: #666;
            font-size: 11px;
        }
        .lot-info {
            background-color: #e8f4f8;
            padding: 12px;
            margin-bottom: 20px;
            border: 2px solid #007cba;
            text-align: center;
        }
        .lot-number {
            font-size: 16px;
            font-weight: bold;
            color: #007cba;
        }
        .form-type-badge {
            display: inline-block;
            background-color: #007cba;
            color: white;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 10px;
            margin-left: 10px;
        }
        .nested-table {
            width: 100%;
            border-collapse: collapse;
            margin: 4px 0;
        }
        .nested-table td {
            border: 1px solid #ccc;
            padding: 4px 6px;
            font-size: 11px;
        }
        .nested-table .nested-label {
            width: 120px;
            font-weight: bold;
            background-color: #f5f5f5;
        }
        @media print {
            body { margin: 0; }
            .container { box-shadow: none; }
            .form-section { page-break-inside: avoid; }
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
        function renderFormSection($data, $sectionTitle, $excludeKeys = []) {
            if (empty($data) || !is_array($data)) return;
            
            echo "<div class='form-section'>";
            echo "<div class='section-title'>" . htmlspecialchars($sectionTitle) . "</div>";
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
            // Handle B1 form specific fields with table heading and checkbox display
            if ($key === 'berbuah') {
                $options = ['sudah', 'belum'];
                $html = '<table style="width: 100%; border-collapse: collapse;">';
                $html .= '<tr><td style="border: 1px solid #333; padding: 4px 8px; text-align: center; font-weight: bold; background-color: #f0f0f0;">Berbuah</td></tr>';
                $html .= '<tr><td style="border: 1px solid #333; padding: 8px;">';
                $html .= '<div style="display: flex; gap: 15px; justify-content: center;">';
                foreach ($options as $option) {
                    $isSelected = ($value === true && $option === 'sudah') || ($value === false && $option === 'belum');
                    $tick = $isSelected ? '<div style="text-align: center; margin-top: 4px;"><span style="font-size: 16px; color: #28a745;">✓</span></div>' : '';
                    $html .= '<div style="text-align: center;">';
                    $html .= '<div style="border: 1px solid #333; padding: 4px 8px; min-width: 60px; text-align: center;">' . ucfirst($option) . '</div>';
                    $html .= $tick;
                    $html .= '</div>';
                }
                $html .= '</div></td></tr></table>';
                return $html;
            }
            
            if ($key === 'penjagaan') {
                $options = ['ya', 'tidak'];
                $html = '<table style="width: 100%; border-collapse: collapse;">';
                $html .= '<tr><td style="border: 1px solid #333; padding: 4px 8px; text-align: center; font-weight: bold; background-color: #f0f0f0;">Penjagaan</td></tr>';
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
            
            // Handle other boolean values for specific fields
            $booleanFields = ['ada_pertikaian', 'bersetuju'];
            
            if (in_array($key, $booleanFields)) {
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

        // --- B1 FORM CUSTOM TABLE RENDERING ---
        if (($formData['form_id'] ?? '') === 'B1') {
            // 1. Maklumat Tanah
            if (isset($formData['tanah'])) {
                renderFormSection($formData['tanah'], 'Maklumat Tanah');
            }
            // 2. Penuntut
            if (isset($formData['penuntut'])) {
                renderFormSection($formData['penuntut'], 'Penuntut');
            }
            // 3. Kebun (custom)
            if (isset($formData['kebun'])) {
                $kebun = $formData['kebun'];
                echo '<div class="form-section">';
                echo '<div style="font-weight:bold; background:#ccc; padding:4px 8px; border-bottom:1px solid #333;">3(i) KEBUN I</div>';
                echo '<table class="form-table" style="text-align:center; margin-bottom:0;">';
                echo '<tr style="background:#eee; font-weight:bold;">';
                echo '<td rowspan="2">Jenis Kebun</td>';
                echo '<td rowspan="2">Anggaran Keluasan<br>(Hektar)</td>';
                echo '<td rowspan="2">Umur<br>(Tahun)</td>';
                echo '<td colspan="2">Berbuah</td>';
                echo '<td colspan="4">Keadaan</td>';
                echo '<td colspan="2">Penjagaan</td>';
                echo '<td rowspan="2">Catatan Am</td>';
                echo '</tr>';
                echo '<tr style="background:#eee; font-weight:bold;">';
                echo '<td>Sudah</td><td>Belum</td>';
                echo '<td>Sangat Baik</td><td>Baik</td><td>Sederhana</td><td>Tidak Baik</td>';
                echo '<td>Ya</td><td>Tidak</td>';
                echo '</tr>';
                echo '<tr>';
                echo '<td>' . htmlspecialchars($kebun['jenis_kebun'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($kebun['anggaran_keluasan'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($kebun['umur'] ?? '') . '</td>';
                echo '<td>' . (($kebun['berbuah'] === true || $kebun['berbuah'] === 'true' || $kebun['berbuah'] === 1) ? '✓' : '') . '</td>';
                echo '<td>' . (($kebun['berbuah'] === false || $kebun['berbuah'] === 'false' || $kebun['berbuah'] === 0) ? '✓' : '') . '</td>';
                $keadaanOpts = ['sangat baik', 'baik', 'sederhana', 'tidak baik'];
                foreach ($keadaanOpts as $opt) {
                    echo '<td>' . (strtolower($kebun['keadaan'] ?? '') === $opt ? '✓' : '') . '</td>';
                }
                echo '<td>' . (($kebun['penjagaan'] === true || $kebun['penjagaan'] === 'true' || $kebun['penjagaan'] === 1) ? '✓' : '') . '</td>';
                echo '<td>' . (($kebun['penjagaan'] === false || $kebun['penjagaan'] === 'false' || $kebun['penjagaan'] === 0) ? '✓' : '') . '</td>';
                echo '<td>' . htmlspecialchars($kebun['catatan'] ?? '') . '</td>';
                echo '</tr>';
                // Faktor Penilaian row (new)
                if (isset($formData['faktor_penilaian'])) {
                    $fp = $formData['faktor_penilaian'];
                    echo '<tr><td colspan="12" style="text-align:left;">Faktor Penilaian: ';
                    $fpParts = [];
                    $fpParts[] = 'Berbuah: ' . (($fp['berbuah'] ?? false) ? '✓' : '✗');
                    $fpParts[] = 'Keadaan: ' . htmlspecialchars($fp['keadaan'] ?? '');
                    $fpParts[] = 'Penjagaan: ' . (($fp['penjagaan'] ?? false) ? '✓' : '✗');
                    echo implode(' | ', $fpParts);
                    echo '</td></tr>';
                } else {
                    echo '<tr><td colspan="12" style="text-align:left;">Faktor Penilaian</td></tr>';
                }
                echo '</table>';
                echo '<div style="font-size:11px; margin-top:2px;">Nota : Gunakan para 3(i) jika terdapat lebih dari sebuah kebun dalam Flt. atau Lot diatas.</div>';
                echo '</div>';
            }
            // 4. Tanaman-Tanaman Lain (custom)
            if (isset($formData['tanaman_lain']) && is_array($formData['tanaman_lain'])) {
                echo '<div class="form-section">';
                echo '<div style="font-weight:bold; background:#ccc; padding:4px 8px; border-bottom:1px solid #333;">4. TANAMAN-TANAMAN LAIN</div>';
                echo '<table class="form-table" style="text-align:center;">';
                echo '<tr style="background:#eee; font-weight:bold;">'; //dasd
                echo '<td rowspan="2">Jenis Tanaman</td>';
                echo '<td rowspan="2">Bilangan Ditanam</td>';
                echo '<td rowspan="2">Umur<br>(Tahun)</td>';
                echo '<td colspan="2">Berbuah</td>';
                echo '<td colspan="4">Keadaan</td>';
                echo '<td colspan="2">Penjagaan</td>';
                echo '<td rowspan="2">Catatan Am</td>';
                echo '</tr>';
                echo '<tr style="background:#eee; font-weight:bold;">';
                echo '<td>Ya</td><td>Belum</td>';
                echo '<td>Sangat Baik</td><td>Baik</td><td>Sederhana</td><td>Tidak Baik</td>';
                echo '<td>Ya</td><td>Tidak</td>';
                echo '</tr>';
                $keadaanOpts = ['sangat baik', 'baik', 'sederhana', 'tidak baik'];
                foreach ($formData['tanaman_lain'] as $tanaman) {
                    echo '<tr>';
                    echo '<td>' . htmlspecialchars($tanaman['jenis_tanaman'] ?? '') . '</td>';
                    echo '<td>' . htmlspecialchars($tanaman['bilangan_ditanam'] ?? '') . '</td>';
                    echo '<td>' . htmlspecialchars($tanaman['umur'] ?? '') . '</td>';
                    echo '<td>' . (($tanaman['berbuah'] === true || $tanaman['berbuah'] === 'true' || $tanaman['berbuah'] === 1) ? '✓' : '') . '</td>';
                    echo '<td>' . (($tanaman['berbuah'] === false || $tanaman['berbuah'] === 'false' || $tanaman['berbuah'] === 0) ? '✓' : '') . '</td>';
                    foreach ($keadaanOpts as $opt) {
                        echo '<td>' . (strtolower($tanaman['keadaan'] ?? '') === $opt ? '✓' : '') . '</td>';
                    }
                    echo '<td>' . (($tanaman['penjagaan'] === true || $tanaman['penjagaan'] === 'true' || $tanaman['penjagaan'] === 1) ? '✓' : '') . '</td>';
                    echo '<td>' . (($tanaman['penjagaan'] === false || $tanaman['penjagaan'] === 'false' || $tanaman['penjagaan'] === 0) ? '✓' : '') . '</td>';
                    echo '<td>' . htmlspecialchars($tanaman['catatan'] ?? '') . '</td>';
                    echo '</tr>';
                }
                for ($i = 0; $i < 8; $i++) {
                    echo '<tr>';
                    for ($j = 0; $j < 11; $j++) echo '<td>&nbsp;</td>';
                    echo '</tr>';
                }
                echo '</table>';
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
                renderFormSection($formData['pengesahan_penuntut'], 'Pengesahan Penuntut');
            }
            // 7. Pengesahan Pengukur
            if (isset($formData['pengesahan_pengukur'])) {
                renderFormSection($formData['pengesahan_pengukur'], 'Pengesahan Pengukur');
            }
            // 8. Pengesahan (for signatures and stamps)
            if (isset($formData['pengesahan'])) {
                renderFormSection($formData['pengesahan'], 'Pengesahan');
            }
        } else if (($formData['form_id'] ?? '') === 'C1') {
            // Render Tanah
            if (isset($formData['tanah'])) {
                renderFormSection($formData['tanah'], 'Maklumat Tanah');
            }
            // Render Penuntut
            if (isset($formData['penuntut'])) {
                renderFormSection($formData['penuntut'], 'Penuntut');
            }
            // Render Kolam Ikan as grid table
            if (isset($formData['kolam_ikan']) && is_array($formData['kolam_ikan'])) {
                echo '<div class="form-section">';
                echo '<div style="font-weight:bold; background:#ccc; padding:4px 8px; border-bottom:1px solid #333;">KOLAM IKAN</div>';
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
                echo '</div>';
            }
            // Render Bangunan as grid table
            if (isset($formData['bangunan']) && is_array($formData['bangunan'])) {
                echo '<div class="form-section">';
                echo '<div style="font-weight:bold; background:#ccc; padding:4px 8px; border-bottom:1px solid #333;">BANGUNAN</div>';
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
                renderFormSection($formData['pengesahan'], 'Pengesahan');
            }
        } else {
            // Generic rendering for other forms
            $excludeKeys = ['form', 'form_id', 'created_at', 'instance_id', 'form_type'];
            // Render tanah section first
            if (isset($formData['tanah'])) {
                renderFormSection($formData['tanah'], 'Maklumat Tanah');
            }
            // Render other sections
            foreach ($formData as $section => $data) {
                if (!in_array($section, $excludeKeys) && $section !== 'tanah' && is_array($data)) {
                    $sectionTitle = ucwords(str_replace('_', ' ', $section));
                    renderFormSection($data, $sectionTitle);
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