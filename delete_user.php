<?php
session_start();
require_once 'config.php';

// Check if user is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// Check if ID is provided
if (!isset($_GET['id'])) {
    header("Location: manage_users.php");
    exit();
}

$id = (int)$_GET['id'];

// Get user role before deletion
$sql = "SELECT role FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if ($user) {
    // Use a transaction for safety
    $conn->begin_transaction();

    try {
        // If user was a student, delete related records first
        if ($user['role'] == 'student') {
            // Delete attendance records
            $sql_attendance = "DELETE FROM attendance WHERE student_id = ?";
            $stmt_attendance = $conn->prepare($sql_attendance);
            $stmt_attendance->bind_param("i", $id);
            $stmt_attendance->execute();
            $stmt_attendance->close();

            // Delete grades
            $sql_grades = "DELETE FROM grades WHERE student_id = ?";
            $stmt_grades = $conn->prepare($sql_grades);
            $stmt_grades->bind_param("i", $id);
            $stmt_grades->execute();
            $stmt_grades->close();

            // Delete from student_class
            $sql_sc = "DELETE FROM student_class WHERE student_id = ?";
            $stmt_sc = $conn->prepare($sql_sc);
            $stmt_sc->bind_param("i", $id);
            $stmt_sc->execute();
            $stmt_sc->close();

            // Delete from parent_ward (Corrected table name)
            $sql_pw = "DELETE FROM parent_ward WHERE student_id = ?";
            $stmt_pw = $conn->prepare($sql_pw);
            $stmt_pw->bind_param("i", $id);
            $stmt_pw->execute();
            $stmt_pw->close();
        }

        // Now delete the user itself
        $sql_user = "DELETE FROM users WHERE id = ?";
        $stmt_user = $conn->prepare($sql_user);
        $stmt_user->bind_param("i", $id);

        if ($stmt_user->execute()) {
            $conn->commit();
            $_SESSION['success_message'] = "User deleted successfully.";
        } else {
            throw new Exception("Error deleting user: " . $stmt_user->error);
        }
        $stmt_user->close();

    } catch (Exception $e) {
        $conn->rollback();
        // Log the error internally if possible
        error_log("Error deleting user ID $id: " . $e->getMessage());
        $_SESSION['error_message'] = "Error deleting user and related data.";
        header("Location: manage_users.php?error=1");
        exit();
    }

    // Redirect after successful deletion or if user wasn't student (no dependent records to delete)
    // Or if transaction failed but user deletion itself succeeded somehow (shouldn't happen with transaction)
    header("Location: manage_users.php"); // Redirect back, messages handled there
    exit();

} else {
    $_SESSION['error_message'] = "User not found.";
    header("Location: manage_users.php?error=1");
}

exit();
?>
