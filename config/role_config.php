<?php
// Role-based navigation and permissions configuration

class RoleConfig {
    private static $roleMenus = [
        'OIC' => [
            'create_job' => [
                'title' => 'Create Job',
                'file' => 'create_job.php',
                'icon' => '➕',
                'description' => 'Create new survey jobs'
            ],
            'job_progress' => [
                'title' => 'Job Progress',
                'file' => 'job_progress.php',
                'icon' => '📈',
                'description' => 'Track job progress and status'
            ],
            'report' => [
                'title' => 'Reports',
                'file' => 'report.php',
                'icon' => '📊',
                'description' => 'View comprehensive reports and analytics'
            ]
        ],
        'VO' => [
            'job_list' => [
                'title' => 'Job List',
                'file' => 'job_list.php',
                'icon' => '📋',
                'description' => 'View and manage all survey jobs'
            ],
            'job_progress' => [
                'title' => 'Job Progress',
                'file' => 'job_progress.php',
                'icon' => '📈',
                'description' => 'Track job progress and status'
            ],
            'report' => [
                'title' => 'Reports',
                'file' => 'report.php',
                'icon' => '📊',
                'description' => 'View reports and analytics'
            ]
        ],
        'SS' => [
            'job_list' => [
                'title' => 'Job List',
                'file' => 'job_list.php',
                'icon' => '📋',
                'description' => 'View and manage all survey jobs'
            ],
            'create_job' => [
                'title' => 'Create Job',
                'file' => 'create_job.php',
                'icon' => '➕',
                'description' => 'Create new survey jobs'
            ],
            'job_progress' => [
                'title' => 'Job Progress',
                'file' => 'job_progress.php',
                'icon' => '📈',
                'description' => 'Track job progress and status'
            ],
            'report' => [
                'title' => 'Reports',
                'file' => 'report.php',
                'icon' => '📊',
                'description' => 'View reports and analytics'
            ]
        ],
        'FI' => [
            'job_list' => [
                'title' => 'Job List',
                'file' => 'job_list.php',
                'icon' => '📋',
                'description' => 'View assigned jobs'
            ],
            'sign_forms' => [
                'title' => 'Sign Forms',
                'file' => 'sign_forms.php',
                'icon' => '✍️',
                'description' => 'Sign and approve survey forms'
            ],
            'job_progress' => [
                'title' => 'Job Progress',
                'file' => 'job_progress.php',
                'icon' => '📈',
                'description' => 'Track job progress and status'
            ],
            'report' => [
                'title' => 'Reports',
                'file' => 'report.php',
                'icon' => '📊',
                'description' => 'View reports and analytics'
            ]

        ],
        'SD' => [
            'job_list' => [
                'title' => 'Job List', 
                'file' => 'job_list.php',
                'icon' => '📋',
                'description' => 'View assigned jobs'
            ],
            'job_progress' => [
                'title' => 'Job Progress',
                'file' => 'job_progress.php',
                'icon' => '📈',
                'description' => 'Track job progress and status'
            ],
            'report' => [
                'title' => 'Reports',
                'file' => 'report.php',
                'icon' => '📊',
                'description' => 'View reports and analytics'
            ]
        ],
        'PP' => [
            'my_tasks' => [
                'title' => 'My Tasks',
                'file' => 'my_tasks.php',
                'icon' => '📋',
                'description' => 'View and manage your tasks'
            ],
            'job_progress' => [
                'title' => 'Job Progress',
                'file' => 'job_progress.php',
                'icon' => '📈',
                'description' => 'Track job progress and status'
            ],
            'report' => [
                'title' => 'Reports',
                'file' => 'report.php',
                'icon' => '📊',
                'description' => 'View reports and analytics'
            ]
        ]
    ];    private static $workflowRules = [
        'OIC' => ['VO'],
        'VO' => ['SS', 'OIC'], // VO can assign to SS or send completed jobs to OIC for checking
        'SS' => ['FI', 'VO'], // Can assign to FI or return to VO
        'FI' => ['AS', 'PP', 'SD', 'SS'], // Can assign to AS/PP/SD or return to SS
        'AS' => ['FI'], // Can only submit back to FI
        'SD' => ['PP', 'SS'], // Can assign to PP or return to SS
        'PP' => ['SD'] // Can only submit back to SD
    ];

    private static $fileUploadConfig = [
        'OIC' => [
            'max_file_size' => 20971520, // 20MB
            'max_files' => 10,
            'allowed_types' => [
                'image/jpeg', 'image/png', 'image/gif', 'image/webp',
                'application/pdf', 
                'application/msword', 
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.ms-powerpoint',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'text/plain',
                'application/zip'
            ],
            'upload_path' => 'uploads/jobs/oic/',
            'can_upload' => true
        ],
        'VO' => [
            'max_file_size' => 15728640, // 15MB
            'max_files' => 8,
            'allowed_types' => [
                'image/jpeg', 'image/png', 'image/gif', 'image/webp',
                'application/pdf', 
                'application/msword', 
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'text/plain'
            ],
            'upload_path' => 'uploads/jobs/vo/',
            'can_upload' => true
        ],
        'SS' => [
            'max_file_size' => 10485760, // 10MB
            'max_files' => 5,
            'allowed_types' => [
                'image/jpeg', 'image/png', 'image/gif', 'image/webp',
                'application/pdf', 
                'application/msword', 
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'text/plain'
            ],
            'upload_path' => 'uploads/jobs/ss/',
            'can_upload' => true
        ],
        'FI' => [
            'max_file_size' => 10485760, // 10MB
            'max_files' => 5,
            'allowed_types' => [
                'image/jpeg', 'image/png', 'image/gif', 'image/webp',
                'application/pdf', 
                'text/plain'
            ],
            'upload_path' => 'uploads/jobs/fi/',
            'can_upload' => true
        ],
        'SD' => [
            'max_file_size' => 10485760, // 10MB
            'max_files' => 5,
            'allowed_types' => [
                'image/jpeg', 'image/png', 'image/gif', 'image/webp',
                'application/pdf', 
                'application/msword', 
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'text/plain'
            ],
            'upload_path' => 'uploads/jobs/sd/',
            'can_upload' => true
        ],
        'PP' => [
            'max_file_size' => 20971520, // 20MB (for CAD files)
            'max_files' => 10,
            'allowed_types' => [
                'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/tiff',
                'application/pdf',
                'application/acad', // DWG files
                'application/dxf',  // DXF files
                'text/plain'
            ],
            'upload_path' => 'uploads/pp_files/',
            'can_upload' => true
        ],
        'AS' => [
            'max_file_size' => 5242880, // 5MB
            'max_files' => 3,
            'allowed_types' => [
                'image/jpeg', 'image/png', 'image/gif', 'image/webp'
            ],
            'upload_path' => 'uploads/jobs/AS/',
            'can_upload' => false // App only
        ]
    ];

    public static function getMenusForRole($role) {
        return isset(self::$roleMenus[$role]) ? self::$roleMenus[$role] : [];
    }

    public static function canAssignTo($fromRole, $toRole) {
        return isset(self::$workflowRules[$fromRole]) && 
               in_array($toRole, self::$workflowRules[$fromRole]);
    }

    public static function getAssignableRoles($role) {
        return isset(self::$workflowRules[$role]) ? self::$workflowRules[$role] : [];
    }

    public static function isAppOnlyRole($role) {
        return isset(self::$roleMenus[$role]['app_only']) && self::$roleMenus[$role]['app_only'];
    }

    public static function getAppOnlyMessage($role) {
        return isset(self::$roleMenus[$role]['message']) ? self::$roleMenus[$role]['message'] : '';
    }

    public static function getFileUploadConfig($role) {
        return isset(self::$fileUploadConfig[$role]) ? self::$fileUploadConfig[$role] : null;
    }

    public static function canUploadFiles($role) {
        $config = self::getFileUploadConfig($role);
        return $config && $config['can_upload'];
    }

    public static function getMaxFileSize($role) {
        $config = self::getFileUploadConfig($role);
        return $config ? $config['max_file_size'] : 5242880; // Default 5MB
    }

    public static function getMaxFileCount($role) {
        $config = self::getFileUploadConfig($role);
        return $config ? $config['max_files'] : 3; // Default 3 files
    }

    public static function getAllowedFileTypes($role) {
        $config = self::getFileUploadConfig($role);
        return $config ? $config['allowed_types'] : ['image/jpeg', 'image/png', 'application/pdf'];
    }

    public static function getUploadPath($role) {
        $config = self::getFileUploadConfig($role);
        return $config ? $config['upload_path'] : 'uploads/jobs/default/';
    }

    public static function validateFileUpload($file, $role) {
        $config = self::getFileUploadConfig($role);
        
        if (!$config || !$config['can_upload']) {
            return ['valid' => false, 'error' => 'File upload not allowed for this role'];
        }

        // Check file size
        if ($file['size'] > $config['max_file_size']) {
            $maxSizeMB = round($config['max_file_size'] / 1048576, 2);
            return ['valid' => false, 'error' => "File size exceeds maximum allowed ({$maxSizeMB}MB)"];
        }

        // Check file type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $config['allowed_types'])) {
            return ['valid' => false, 'error' => 'File type not allowed'];
        }

        // Check for malicious files
        if (self::isMaliciousFile($file)) {
            return ['valid' => false, 'error' => 'File appears to be malicious'];
        }

        return ['valid' => true, 'error' => null];
    }

    private static function isMaliciousFile($file) {
        // Check file extension against MIME type
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $dangerousExtensions = ['exe', 'bat', 'cmd', 'com', 'pif', 'scr', 'vbs', 'js', 'jar', 'php', 'asp', 'jsp'];
        
        if (in_array($ext, $dangerousExtensions)) {
            return true;
        }

        // Additional checks for executable content
        $fileContent = file_get_contents($file['tmp_name'], false, null, 0, 1024);
        $maliciousPatterns = ['<?php', '<%', '<script', 'eval(', 'exec(', 'system('];
        
        foreach ($maliciousPatterns as $pattern) {
            if (stripos($fileContent, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function getFileUploadHTML($role) {
        $config = self::getFileUploadConfig($role);
        
        if (!$config || !$config['can_upload']) {
            return '<p class="upload-disabled">File upload not available for your role.</p>';
        }

        $maxSizeMB = round($config['max_file_size'] / 1048576, 2);
        $allowedExtensions = self::getFileExtensions($config['allowed_types']);

        return '
        <div class="file-upload-container">
            <label>Job Attachments</label>
            <div class="file-upload-area">
                <div class="file-upload-icon">📁</div>
                <div class="file-upload-text">
                    <strong>Click to upload</strong> or drag and drop<br>
                    <small>' . implode(', ', $allowedExtensions) . ' (max ' . $maxSizeMB . 'MB each, up to ' . $config['max_files'] . ' files)</small>
                </div>
                <input type="file" name="attachments[]" class="file-upload-input" multiple 
                       accept="' . implode(',', $allowedExtensions) . '"
                       data-max-size="' . $config['max_file_size'] . '"
                       data-max-files="' . $config['max_files'] . '">
            </div>
            <div class="file-preview"></div>
            <div class="file-error"></div>
        </div>';
    }

    private static function getFileExtensions($mimeTypes) {
        $extensions = [];
        $mimeToExt = [
            'image/jpeg' => '.jpg',
            'image/png' => '.png',
            'image/gif' => '.gif',
            'image/webp' => '.webp',
            'application/pdf' => '.pdf',
            'application/msword' => '.doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => '.docx',
            'application/vnd.ms-excel' => '.xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => '.xlsx',
            'application/vnd.ms-powerpoint' => '.ppt',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => '.pptx',
            'text/plain' => '.txt',
            'application/zip' => '.zip'
        ];

        foreach ($mimeTypes as $mime) {
            if (isset($mimeToExt[$mime])) {
                $extensions[] = $mimeToExt[$mime];
            }
        }

        return $extensions;
    }
}
?>