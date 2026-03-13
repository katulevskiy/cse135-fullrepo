<?php
// One-time setup: creates tables and seeds users. DELETE THIS FILE after running.
$mysqli = new mysqli("localhost", "root", "devtheWorld#135cse", "collector_logs");
if ($mysqli->connect_errno) {
    die("Connection failed: " . $mysqli->connect_error);
}

$steps = [];

// Users table
$mysqli->query("CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('super_admin','analyst','viewer') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
$steps[] = "users table: " . ($mysqli->error ?: "OK");

// Analyst sections table
$mysqli->query("CREATE TABLE IF NOT EXISTS analyst_sections (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    section ENUM('visitor','performance','behavioral') NOT NULL,
    UNIQUE KEY uq_user_section (user_id, section),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)");
$steps[] = "analyst_sections table: " . ($mysqli->error ?: "OK");

// Saved reports table
$mysqli->query("CREATE TABLE IF NOT EXISTS saved_reports (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    section ENUM('visitor','performance','behavioral') NOT NULL,
    analyst_comments TEXT,
    created_by INT UNSIGNED NOT NULL,
    html_path VARCHAR(255),
    pdf_path VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
)");
$steps[] = "saved_reports table: " . ($mysqli->error ?: "OK");

// Seed users (skip if exist)
$seeds = [
    ['admin',  'Admin@135!',    'super_admin', []],
    ['sam',    'Analyst@135!',  'analyst',     ['performance']],
    ['sally',  'Analyst@135!',  'analyst',     ['performance', 'behavioral']],
    ['viewer', 'Viewer@135!',   'viewer',      []],
];

foreach ($seeds as [$username, $pass, $role, $sections]) {
    $hash = password_hash($pass, PASSWORD_DEFAULT);
    $stmt = $mysqli->prepare("INSERT IGNORE INTO users (username, password_hash, role) VALUES (?,?,?)");
    $stmt->bind_param("sss", $username, $hash, $role);
    $stmt->execute();
    $uid = $mysqli->insert_id;
    $steps[] = "user '$username': " . ($uid ? "inserted (id=$uid)" : "already exists");

    if (!$uid) {
        $r = $mysqli->query("SELECT id FROM users WHERE username='$username'");
        $uid = $r->fetch_assoc()['id'];
    }

    foreach ($sections as $section) {
        $mysqli->query("INSERT IGNORE INTO analyst_sections (user_id, section) VALUES ($uid, '$section')");
        $steps[] = "  section '$section': " . ($mysqli->error ?: "OK");
    }
}

$mysqli->close();
echo "<pre style='font-family:monospace;padding:20px'>";
echo "<strong>Setup complete. DELETE setup.php immediately after reviewing.</strong>\n\n";
foreach ($steps as $s) echo htmlspecialchars($s) . "\n";
echo "</pre>";
