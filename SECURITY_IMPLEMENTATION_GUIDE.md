# Security Implementation Guide
## Immediate Actions to Secure the Online Voting System

This guide provides step-by-step instructions to implement critical security measures that protect against attacks even when source code is exposed.

---

## 1. CSRF Protection Implementation

### Step 1: Add CSRF Functions to config.php

Add these functions to `src/config/config.php`:

```php
// CSRF Protection Functions
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function getCSRFField() {
    return '<input type="hidden" name="csrf_token" value="' . generateCSRFToken() . '">';
}
```

### Step 2: Add CSRF Validation to All Forms

Example for login.php:
```php
// Add after form processing check
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!validateCSRFToken($csrf_token)) {
        $error_message = 'Security token validation failed. Please try again.';
    } else {
        // Continue with existing form processing
        $username = sanitizeInput($_POST['username']);
        // ... rest of the code
    }
}
```

### Step 3: Add CSRF Fields to HTML Forms

Add to all forms:
```html
<form method="POST" class="auth-form">
    <?php echo getCSRFField(); ?>
    <!-- rest of form fields -->
</form>
```

---

## 2. Rate Limiting Implementation

### Step 1: Create Rate Limiting Table

```sql
CREATE TABLE rate_limits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    action_type VARCHAR(50) NOT NULL,
    attempts INT DEFAULT 1,
    first_attempt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_attempt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    blocked_until TIMESTAMP NULL,
    INDEX idx_ip_action (ip_address, action_type),
    INDEX idx_blocked_until (blocked_until)
);
```

### Step 2: Add Rate Limiting Functions

Add to `src/config/config.php`:

```php
function checkRateLimit($action, $max_attempts = 5, $window_minutes = 15, $block_minutes = 30) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $db = getDBConnection();
    
    // Clean old records
    $stmt = $db->prepare("DELETE FROM rate_limits WHERE first_attempt < DATE_SUB(NOW(), INTERVAL ? MINUTE)");
    $stmt->execute([$window_minutes]);
    
    // Check if currently blocked
    $stmt = $db->prepare("SELECT blocked_until FROM rate_limits WHERE ip_address = ? AND action_type = ? AND blocked_until > NOW()");
    $stmt->execute([$ip, $action]);
    if ($stmt->fetch()) {
        return false; // Still blocked
    }
    
    // Get current attempts
    $stmt = $db->prepare("SELECT attempts FROM rate_limits WHERE ip_address = ? AND action_type = ?");
    $stmt->execute([$ip, $action]);
    $record = $stmt->fetch();
    
    if ($record) {
        $attempts = $record['attempts'] + 1;
        if ($attempts > $max_attempts) {
            // Block the IP
            $stmt = $db->prepare("UPDATE rate_limits SET attempts = ?, blocked_until = DATE_ADD(NOW(), INTERVAL ? MINUTE) WHERE ip_address = ? AND action_type = ?");
            $stmt->execute([$attempts, $block_minutes, $ip, $action]);
            return false;
        } else {
            // Increment attempts
            $stmt = $db->prepare("UPDATE rate_limits SET attempts = ? WHERE ip_address = ? AND action_type = ?");
            $stmt->execute([$attempts, $ip, $action]);
        }
    } else {
        // First attempt
        $stmt = $db->prepare("INSERT INTO rate_limits (ip_address, action_type, attempts) VALUES (?, ?, 1)");
        $stmt->execute([$ip, $action]);
    }
    
    return true;
}

function resetRateLimit($action) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $db = getDBConnection();
    $stmt = $db->prepare("DELETE FROM rate_limits WHERE ip_address = ? AND action_type = ?");
    $stmt->execute([$ip, $action]);
}
```

### Step 3: Implement in Login System

Update `src/login.php`:

```php
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Check rate limit first
    if (!checkRateLimit('login', 5, 15, 30)) {
        $error_message = 'Too many failed login attempts. Please try again later.';
    } else {
        $username = sanitizeInput($_POST['username']);
        $password = $_POST['password'];
        
        // ... existing validation code ...
        
        if ($user && verifyPassword($password, $user['password_hash'])) {
            // Successful login - reset rate limit
            resetRateLimit('login');
            // ... rest of successful login code ...
        } else {
            $error_message = 'Invalid username or password.';
            // Rate limit will automatically increment
        }
    }
}
```

---

## 3. Enhanced File Upload Security

### Step 1: Create Secure Upload Function

Add to `src/config/config.php`:

```php
function secureFileUpload($file, $allowed_types = ['image/jpeg', 'image/png'], $max_size = 2097152) {
    $errors = [];
    
    // Check if file was uploaded
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        $errors[] = 'No file uploaded or upload failed';
        return ['success' => false, 'errors' => $errors];
    }
    
    // Check file size
    if ($file['size'] > $max_size) {
        $errors[] = 'File size exceeds maximum allowed size';
    }
    
    // Check MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime_type, $allowed_types)) {
        $errors[] = 'File type not allowed';
    }
    
    // Check file signature (magic bytes)
    $file_content = file_get_contents($file['tmp_name'], false, null, 0, 8);
    $valid_signature = false;
    
    $signatures = [
        'image/jpeg' => ["\xFF\xD8\xFF"],
        'image/png' => ["\x89\x50\x4E\x47\x0D\x0A\x1A\x0A"],
    ];
    
    foreach ($signatures[$mime_type] ?? [] as $signature) {
        if (strpos($file_content, $signature) === 0) {
            $valid_signature = true;
            break;
        }
    }
    
    if (!$valid_signature) {
        $errors[] = 'Invalid file signature';
    }
    
    // Generate secure filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = bin2hex(random_bytes(16)) . '.' . $extension;
    
    if (empty($errors)) {
        return [
            'success' => true,
            'filename' => $filename,
            'mime_type' => $mime_type,
            'size' => $file['size']
        ];
    } else {
        return ['success' => false, 'errors' => $errors];
    }
}
```

---

## 4. Security Headers Implementation

### Step 1: Add Security Headers Function

Add to `src/config/config.php`:

```php
function setSecurityHeaders() {
    // Prevent MIME type sniffing
    header('X-Content-Type-Options: nosniff');
    
    // Prevent clickjacking
    header('X-Frame-Options: DENY');
    
    // Enable XSS protection
    header('X-XSS-Protection: 1; mode=block');
    
    // Force HTTPS (uncomment when SSL is configured)
    // header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    
    // Content Security Policy
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'");
    
    // Referrer Policy
    header('Referrer-Policy: strict-origin-when-cross-origin');
    
    // Remove server information
    header_remove('X-Powered-By');
}
```

### Step 2: Apply Headers to All Pages

Add to the beginning of each PHP file (after config include):

```php
require_once 'config/config.php';
setSecurityHeaders();
```

---

## 5. Environment Variables Setup

### Step 1: Create .env File

Create `.env` file in project root (outside web directory):

```env
# Database Configuration
DB_HOST=localhost
DB_NAME=voting_system
DB_USER=voting_app_user
DB_PASS=your_secure_random_password_here

# Security Keys
SECRET_KEY=your_64_character_random_string_here
CSRF_SECRET=another_64_character_random_string_here

# Email Configuration
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USER=your_email@gmail.com
SMTP_PASS=your_app_password

# Application Settings
APP_ENV=production
DEBUG_MODE=false
```

### Step 2: Update Database Configuration

Update `src/config/database.php`:

```php
<?php
// Load environment variables
function loadEnv($file) {
    if (!file_exists($file)) {
        throw new Exception('.env file not found');
    }
    
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
}

// Load environment variables
loadEnv(__DIR__ . '/../../.env');

// Database configuration using environment variables
$db_config = [
    'host' => $_ENV['DB_HOST'] ?? 'localhost',
    'dbname' => $_ENV['DB_NAME'] ?? 'voting_system',
    'username' => $_ENV['DB_USER'] ?? 'root',
    'password' => $_ENV['DB_PASS'] ?? '',
    'charset' => 'utf8mb4',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
];

function getDBConnection() {
    global $db_config;
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = "mysql:host={$db_config['host']};dbname={$db_config['dbname']};charset={$db_config['charset']}";
            $pdo = new PDO($dsn, $db_config['username'], $db_config['password'], $db_config['options']);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new Exception("Database connection failed");
        }
    }
    
    return $pdo;
}
?>
```

---

## 6. Database Security Hardening

### Step 1: Create Limited Database User

```sql
-- Create dedicated user with minimal privileges
CREATE USER 'voting_app_user'@'localhost' IDENTIFIED BY 'secure_random_password';

-- Grant only necessary permissions
GRANT SELECT, INSERT, UPDATE ON voting_system.users TO 'voting_app_user'@'localhost';
GRANT SELECT, INSERT, UPDATE ON voting_system.voter_profiles TO 'voting_app_user'@'localhost';
GRANT SELECT, INSERT, UPDATE ON voting_system.elections TO 'voting_app_user'@'localhost';
GRANT SELECT, INSERT, UPDATE ON voting_system.positions TO 'voting_app_user'@'localhost';
GRANT SELECT, INSERT, UPDATE ON voting_system.candidates TO 'voting_app_user'@'localhost';
GRANT SELECT, INSERT ON voting_system.votes TO 'voting_app_user'@'localhost';
GRANT SELECT, INSERT ON voting_system.audit_log TO 'voting_app_user'@'localhost';
GRANT SELECT, INSERT, UPDATE ON voting_system.login_attempts TO 'voting_app_user'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON voting_system.rate_limits TO 'voting_app_user'@'localhost';

-- NO DELETE permissions on critical tables
-- NO DROP, ALTER, CREATE permissions
-- NO GRANT permissions

FLUSH PRIVILEGES;
```

### Step 2: Enable Query Logging

Add to MySQL configuration:
```ini
[mysqld]
general_log = 1
general_log_file = /var/log/mysql/general.log
slow_query_log = 1
slow_query_log_file = /var/log/mysql/slow.log
long_query_time = 2
```

---

## 7. Web Server Security (.htaccess)

Create `.htaccess` file in project root:

```apache
# Security Headers
Header always set X-Content-Type-Options nosniff
Header always set X-Frame-Options DENY
Header always set X-XSS-Protection "1; mode=block"
Header always set Referrer-Policy "strict-origin-when-cross-origin"

# Hide sensitive files
<Files ".env">
    Order Allow,Deny
    Deny from all
</Files>

<Files "*.md">
    Order Allow,Deny
    Deny from all
</Files>

<FilesMatch "\.(sql|log|bak)$">
    Order Allow,Deny
    Deny from all
</FilesMatch>

# Prevent access to config directory from web
<Directory "src/config">
    Order Allow,Deny
    Deny from all
</Directory>

# Prevent directory listing
Options -Indexes

# Prevent access to PHP files in uploads directory
<Directory "uploads">
    <Files "*.php">
        Order Allow,Deny
        Deny from all
    </Files>
</Directory>

# Force HTTPS (uncomment when SSL is configured)
# RewriteEngine On
# RewriteCond %{HTTPS} off
# RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

---

## 8. Implementation Priority

### Phase 1 (Immediate - Critical)
1. ✅ Implement CSRF protection on all forms
2. ✅ Add rate limiting to login and registration
3. ✅ Set up security headers
4. ✅ Create .env file and secure database credentials

### Phase 2 (Within 1 week - High Priority)
1. ✅ Enhance file upload security
2. ✅ Create limited database user
3. ✅ Set up .htaccess security rules
4. ✅ Implement comprehensive input validation

### Phase 3 (Within 1 month - Medium Priority)
1. Set up SSL/TLS certificates
2. Implement comprehensive logging and monitoring
3. Create incident response procedures
4. Regular security audits and penetration testing

---

## Testing the Security Measures

### Test CSRF Protection
```bash
# Try to submit form without CSRF token
curl -X POST http://localhost/voting-system/src/login.php \
  -d "username=test&password=test"
# Should fail with security token error
```

### Test Rate Limiting
```bash
# Make multiple failed login attempts
for i in {1..10}; do
  curl -X POST http://localhost/voting-system/src/login.php \
    -d "username=test&password=wrong"
done
# Should be blocked after 5 attempts
```

### Test File Upload Security
```bash
# Try to upload PHP file as image
# Should be rejected based on MIME type and file signature
```

This implementation guide provides practical, immediately actionable security measures that will significantly improve the system's resistance to attacks, even when source code is exposed.