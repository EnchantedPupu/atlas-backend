<?php
session_start();
require_once '../config/database.php';

// Simulate being logged in as a VO user
if (!isset($_SESSION['user_logged_in'])) {
    // For testing, let's check if there are any VO users in the database
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        $stmt = $db->prepare("SELECT user_id, name, role FROM User WHERE role = 'VO' LIMIT 1");
        $stmt->execute();
        $voUser = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($voUser) {
            $_SESSION['user_logged_in'] = true;
            $_SESSION['user_id'] = $voUser['user_id'];
            $_SESSION['user_role'] = $voUser['role'];
            $_SESSION['name'] = $voUser['name'];
            echo "<p>Simulated login as VO user: {$voUser['name']} (ID: {$voUser['user_id']})</p>";
        } else {
            echo "<p>No VO users found in database for testing</p>";
        }
    } catch (Exception $e) {
        echo "<p>Error setting up test user: {$e->getMessage()}</p>";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Complete Test - Mark Acquisition Complete</title>
    <style>
        .test-section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; }
        .success { color: green; }
        .error { color: red; }
        button { padding: 10px; margin: 5px; }
    </style>
</head>
<body>
    <h2>Complete Test - Mark Acquisition Complete</h2>
    
    <div class="test-section">
        <h3>Current Session</h3>
        <p>Logged in: <?php echo isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] ? 'Yes' : 'No'; ?></p>
        <p>User ID: <?php echo $_SESSION['user_id'] ?? 'Not set'; ?></p>
        <p>User Role: <?php echo $_SESSION['user_role'] ?? 'Not set'; ?></p>
        <p>User Name: <?php echo $_SESSION['name'] ?? 'Not set'; ?></p>
    </div>
    
    <div class="test-section">
        <h3>Test Report Integration</h3>
        <p><a href="report.php" target="_blank">Open Reports Page</a></p>
        <p>Test the new reporting functionality with completed jobs.</p>
    </div>

    <div class="test-section">
        <h3>Jobs with pbtstatus = 'checked'</h3>
        <?php
        try {
            $database = new Database();
            $db = $database->getConnection();
            
            $stmt = $db->prepare("
                SELECT sj.survey_job_id, sj.surveyjob_no, sj.projectname, sj.status, sj.pbtstatus, 
                       sj.assigned_to, u.name as assigned_to_name
                FROM SurveyJob sj
                LEFT JOIN User u ON sj.assigned_to = u.user_id
                WHERE sj.pbtstatus = 'checked'
                ORDER BY sj.updated_at DESC
                LIMIT 10
            ");
            $stmt->execute();
            $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if ($jobs) {
                echo "<table border='1' style='border-collapse: collapse;'>";
                echo "<tr><th>Job ID</th><th>Job Number</th><th>Project</th><th>Status</th><th>PBT Status</th><th>Assigned To</th><th>Action</th></tr>";
                foreach ($jobs as $job) {
                    $canMarkComplete = (
                        isset($_SESSION['user_role']) && 
                        $_SESSION['user_role'] === 'VO' && 
                        $job['assigned_to'] == ($_SESSION['user_id'] ?? 0)
                    );
                    
                    echo "<tr>";
                    echo "<td>{$job['survey_job_id']}</td>";
                    echo "<td>{$job['surveyjob_no']}</td>";
                    echo "<td>{$job['projectname']}</td>";
                    echo "<td>{$job['status']}</td>";
                    echo "<td>{$job['pbtstatus']}</td>";
                    echo "<td>{$job['assigned_to_name']} (ID: {$job['assigned_to']})</td>";
                    echo "<td>";
                    if ($canMarkComplete) {
                        echo "<button onclick=\"testMarkComplete({$job['survey_job_id']})\" style='background: green; color: white;'>✅ Mark Complete</button>";
                    } else {
                        echo "<span style='color: gray;'>Not assigned to you</span>";
                    }
                    echo "</td>";
                    echo "</tr>";
                }
                echo "</table>";
            } else {
                echo "<p>No jobs with pbtstatus = 'checked' found</p>";
            }
        } catch (Exception $e) {
            echo "<p class='error'>Error: {$e->getMessage()}</p>";
        }
        ?>
    </div>
    
    <div class="test-section">
        <h3>Manual Test</h3>
        <input type="number" id="jobIdInput" placeholder="Enter Job ID" value="1">
        <button onclick="testMarkComplete(document.getElementById('jobIdInput').value)">Test Mark Complete</button>
    </div>
    
    <div id="results"></div>
    
    <script>
    function testMarkComplete(jobId) {
        console.log('Testing markAcquisitionComplete with jobId:', jobId);
        
        const formData = new FormData();
        formData.append('job_id', jobId);
        formData.append('pbtstatus', 'acquisition_complete');
        
        document.getElementById('results').innerHTML += '<p>Sending request for Job ID ' + jobId + '...</p>';
        
        fetch('../api/update_pbtstatus.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            console.log('Response status:', response.status);
            return response.text();
        })
        .then(text => {
            console.log('Raw response:', text);
            try {
                const data = JSON.parse(text);
                console.log('Parsed response:', data);
                
                const resultClass = data.success ? 'success' : 'error';
                const message = data.success ? data.message : data.error;
                
                document.getElementById('results').innerHTML += 
                    `<p class="${resultClass}"><strong>Job ${jobId}:</strong> ${message}</p>`;
                    
                if (data.success) {
                    setTimeout(() => location.reload(), 2000);
                }
            } catch (e) {
                console.error('JSON parse error:', e);
                document.getElementById('results').innerHTML += 
                    `<p class="error"><strong>Job ${jobId}:</strong> Invalid JSON response: ${text}</p>`;
            }
        })
        .catch(error => {
            console.error('Fetch error:', error);
            document.getElementById('results').innerHTML += 
                `<p class="error"><strong>Job ${jobId}:</strong> Network error: ${error.message}</p>`;
        });
    }
    </script>
</body>
</html>
