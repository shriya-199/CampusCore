<?php
require_once 'config.php';

// Disable foreign key checks
$conn->query("SET FOREIGN_KEY_CHECKS = 0");

// Drop all tables first to avoid foreign key constraints issues
$drop_tables = [
    "DROP TABLE IF EXISTS grades",
    "DROP TABLE IF EXISTS attendance",
    "DROP TABLE IF EXISTS teacher_permissions",
    "DROP TABLE IF EXISTS parent_ward",
    "DROP TABLE IF EXISTS student_class",
    "DROP TABLE IF EXISTS subjects",
    "DROP TABLE IF EXISTS classes",
    "DROP TABLE IF EXISTS users"
];

foreach ($drop_tables as $sql) {
    if (!$conn->query($sql)) {
        die("Error dropping table: " . $conn->error);
    }
}

// Create tables
$tables = [
    "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        role ENUM('admin', 'teacher', 'student', 'parent') NOT NULL,
        full_name VARCHAR(100) NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        phone VARCHAR(15),
        address TEXT,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )",

    "CREATE TABLE IF NOT EXISTS classes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        grade_level INT NOT NULL,
        section VARCHAR(10) NOT NULL,
        academic_year VARCHAR(9) NOT NULL,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )",

    "CREATE TABLE IF NOT EXISTS subjects (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        class_id INT NOT NULL,
        status ENUM('active', 'inactive') DEFAULT 'active',
        FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE
    )",

    "CREATE TABLE IF NOT EXISTS student_class (
        student_id INT NOT NULL,
        class_id INT NOT NULL,
        enrollment_date DATE NOT NULL DEFAULT (CURRENT_DATE),
        status ENUM('active', 'inactive') DEFAULT 'active',
        PRIMARY KEY (student_id, class_id),
        FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE
    )",

    "CREATE TABLE IF NOT EXISTS parent_ward (
        id INT AUTO_INCREMENT PRIMARY KEY,
        parent_id INT NOT NULL,
        student_id INT NOT NULL,
        relationship ENUM('father', 'mother', 'guardian') NOT NULL,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (parent_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_parent_ward (parent_id, student_id)
    )",

    "CREATE TABLE IF NOT EXISTS teacher_permissions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        teacher_id INT NOT NULL,
        class_id INT NOT NULL,
        subject_id INT NOT NULL,
        can_manage_attendance BOOLEAN DEFAULT false,
        can_manage_grades BOOLEAN DEFAULT false,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
        FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
        UNIQUE KEY unique_teacher_class_subject (teacher_id, class_id, subject_id)
    )",

    "CREATE TABLE IF NOT EXISTS attendance (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        class_id INT NOT NULL,
        subject_id INT NOT NULL,
        date DATE NOT NULL,
        status ENUM('present', 'absent', 'late') NOT NULL DEFAULT 'present',
        marked_by INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
        FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
        FOREIGN KEY (marked_by) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_attendance (student_id, class_id, subject_id, date)
    )",

    "CREATE TABLE IF NOT EXISTS grades (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        class_id INT NOT NULL,
        subject_id INT NOT NULL,
        test_name VARCHAR(100) NOT NULL,
        score DECIMAL(5,2) NOT NULL,
        max_score DECIMAL(5,2) NOT NULL DEFAULT 100,
        test_date DATE NOT NULL,
        marked_by INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
        FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
        FOREIGN KEY (marked_by) REFERENCES users(id) ON DELETE CASCADE
    )"
];

// Execute each table creation query
foreach ($tables as $sql) {
    if (!$conn->query($sql)) {
        die("Error creating table: " . $conn->error);
    }
}

// Create parent_student table
$sql = "CREATE TABLE IF NOT EXISTS parent_student (
    id INT PRIMARY KEY AUTO_INCREMENT,
    parent_id INT NOT NULL,
    student_id INT NOT NULL,
    relationship VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
)";

if ($conn->query($sql) === FALSE) {
    die("Error creating parent_student table: " . $conn->error);
}

// Insert default admin user if not exists
$admin_check = $conn->query("SELECT id FROM users WHERE username = 'admin' LIMIT 1");
if ($admin_check->num_rows === 0) {
    $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
    $sql = "INSERT INTO users (username, password, role, full_name, email) VALUES ('admin', ?, 'admin', 'System Administrator', 'admin@school.com')";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $admin_password);
    $stmt->execute();
}

// Insert default classes if not exists
$classes_check = $conn->query("SELECT id FROM classes LIMIT 1");
if ($classes_check->num_rows === 0) {
    for ($i = 1; $i <= 12; $i++) {
        $sql = "INSERT INTO classes (name, grade_level, section, academic_year) VALUES (?, ?, '-', '2024-2025')";
        $stmt = $conn->prepare($sql);
        $name = "Class " . $i;
        $stmt->bind_param("si", $name, $i);
        $stmt->execute();
    }
}

// Insert default subjects if not exists
$subjects_check = $conn->query("SELECT id FROM subjects LIMIT 1");
if ($subjects_check->num_rows === 0) {
    $primary_subjects = ['Hindi', 'English', 'Mathematics', 'Science', 'Social Studies', 'Computer Science'];
    $higher_subjects = ['Hindi', 'English', 'Mathematics', 'Physics', 'Chemistry', 'Biology', 'History', 'Geography', 'Computer Science'];
    
    $classes = $conn->query("SELECT id, grade_level FROM classes ORDER BY grade_level");
    while ($class = $classes->fetch_assoc()) {
        $subjects = $class['grade_level'] <= 5 ? $primary_subjects : $higher_subjects;
        foreach ($subjects as $subject) {
            $sql = "INSERT INTO subjects (name, class_id) VALUES (?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $subject, $class['id']);
            $stmt->execute();
        }
    }
}

// Re-enable foreign key checks
$conn->query("SET FOREIGN_KEY_CHECKS = 1");

echo "Database setup completed successfully!";
?>
