<?php
require_once 'config.php';
require_admin();

// Function to add subjects for a class
function add_subjects_for_class($conn, $class_id, $subjects, $teacher_id) {
    $stmt = $conn->prepare("INSERT INTO subjects (name, class_id, teacher_id) VALUES (?, ?, ?)");
    foreach ($subjects as $subject) {
        $stmt->bind_param("sii", $subject, $class_id, $teacher_id);
        $stmt->execute();
    }
    $stmt->close();
}

// Start transaction
$conn->begin_transaction();

try {
    // Define subjects for different class ranges
    $primary_subjects = ['Hindi', 'English', 'Mathematics', 'Science', 'Social Studies', 'Computer Science'];
    $secondary_subjects = ['Hindi', 'English', 'Mathematics', 'Physics', 'Chemistry', 'Biology', 'History', 'Geography', 'Computer Science'];

    // Get admin user for initial setup
    $admin_id = $conn->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1")->fetch_assoc()['id'];

    // Add classes 1-12 with only section A
    for ($grade = 1; $grade <= 12; $grade++) {
        $stmt = $conn->prepare("INSERT INTO classes (name, grade_level, section, teacher_id, academic_year) VALUES (?, ?, ?, ?, ?)");
        $class_name = "Class " . $grade;
        $academic_year = "2025-2026";
        $stmt->bind_param("sisss", $class_name, $grade, 'A', $admin_id, $academic_year);
        $stmt->execute();
        $class_id = $conn->insert_id;

        // Add appropriate subjects based on grade level
        if ($grade <= 5) {
            add_subjects_for_class($conn, $class_id, $primary_subjects, $admin_id);
        } else {
            add_subjects_for_class($conn, $class_id, $secondary_subjects, $admin_id);
        }
    }

    // Commit transaction
    $conn->commit();
    set_flash_message('success', 'Classes and subjects have been set up successfully!');

} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    set_flash_message('danger', 'Error setting up classes and subjects: ' . $e->getMessage());
}

// Redirect to manage users page
redirect('manage_users.php');
?>
