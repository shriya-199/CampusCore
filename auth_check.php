<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: login.php");
    exit();
}

// Function to check if user has required role
function check_role($required_roles) {
    if (!is_array($required_roles)) {
        $required_roles = array($required_roles);
    }
    return in_array($_SESSION['role'], $required_roles);
}
?>
