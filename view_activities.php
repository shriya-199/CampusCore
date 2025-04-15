<?php
session_start();
require_once 'config.php';
require_once 'includes/functions.php'; // Assumes functions like is_logged_in, redirect, display_flash_message exist

// Check login and role
if (!is_logged_in()) {
    redirect(BASE_URL . 'login.php');
}

$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];
$student_id_to_view = 0; // Initialize to 0
$page_title = "My Activities";
$student_name = $_SESSION['full_name'] ?? 'User'; // Default if full_name isn't set

// Determine which student's activities to view
if ($role === 'student') { // Student views their own activities
    $student_id_to_view = $user_id;
} elseif ($role === 'parent') { // Parent needs a specific student_id
    $page_title = "Ward Activities"; // Default title for parent
    if (isset($_GET['student_id']) && filter_var($_GET['student_id'], FILTER_VALIDATE_INT)) {
        $requested_student_id = (int)$_GET['student_id'];

        // Validate: Is the logged-in parent linked to this student?
        $sql_validate = "SELECT pw.student_id, u.full_name 
                         FROM parent_ward pw
                         JOIN users u ON pw.student_id = u.id
                         WHERE pw.parent_id = ? AND pw.student_id = ?";
        $stmt_validate = $conn->prepare($sql_validate);
        if ($stmt_validate) {
            $stmt_validate->bind_param("ii", $user_id, $requested_student_id);
            $stmt_validate->execute();
            $result_validate = $stmt_validate->get_result();

            if ($valid_ward = $result_validate->fetch_assoc()) {
                // Valid link found
                $student_id_to_view = $valid_ward['student_id'];
                $student_name = $valid_ward['full_name'];
                $page_title = "Activities for " . htmlspecialchars($student_name);
            } else {
                // Not linked or student doesn't exist
                set_flash_message('error', 'You are not authorized to view activities for this student.');
                $student_id_to_view = 0; // Prevent fetching data
                // Optional: Redirect immediately
                // redirect(BASE_URL . 'dashboard.php');
            }
            $stmt_validate->close();
        } else {
            set_flash_message('error', 'Database error validating access.');
            error_log("Prepare failed (parent_ward validate): (" . $conn->errno . ") " . $conn->error);
            $student_id_to_view = 0;
        }
    } else {
        // Parent accessed page without a valid student_id
        set_flash_message('warning', 'Please select a child from your dashboard to view their activities.');
        // Redirect to dashboard where child selection will be added
        redirect(BASE_URL . 'dashboard.php');
    }
} else {
    // Admins/Teachers currently don't view activities this way
    set_flash_message('error', 'Access Denied for this page.');
    redirect(BASE_URL . 'dashboard.php');
}

// Fetch activities for the determined student_id
$activities = [];
if ($student_id_to_view > 0) {
    // Assuming activity_log structure: user_id, timestamp, description, activity_type
    $sql = "SELECT timestamp, description, activity_type
            FROM activity_log
            WHERE user_id = ?
            ORDER BY timestamp DESC
            LIMIT 100"; // Limit results
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $student_id_to_view);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $activities[] = $row;
        }
        $stmt->close();
    } else {
        set_flash_message('error', 'Failed to retrieve activity log.');
        error_log("Prepare failed (activity_log select): (" . $conn->errno . ") " . $conn->error);
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo BASE_URL; ?>css/style.css" rel="stylesheet"> <!-- Optional: Link your custom CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">

</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container mt-4">
        <?php display_flash_message(); // Function to display flash messages ?>

        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-list-ul me-2"></i><?php echo htmlspecialchars($page_title); ?></h5>
            </div>
            <div class="card-body">
                <?php if (!empty($activities)): ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($activities as $activity): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center flex-wrap">
                                <div class="me-3">
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $activity['activity_type'] ?? 'Log'))); ?>
                                    </small><br>
                                    <?php echo htmlspecialchars($activity['description'] ?? 'No description'); ?>
                                </div>
                                <span class="badge bg-secondary rounded-pill" style="min-width: 130px;">
                                   <i class="bi bi-clock me-1"></i> <?php echo date("Y-m-d H:i", strtotime($activity['timestamp'])); ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php elseif ($student_id_to_view > 0): ?>
                    <p class="text-center text-muted">No activities found for <?php echo htmlspecialchars($student_name); ?>.</p>
                 <?php elseif ($role === 'parent' && $student_id_to_view == 0): ?>
                     <!-- Message already shown by flash if no wards or error -->
                 <?php else:
                     // Should not happen if logic is correct, but good to have a fallback
                      echo "<p class='text-center text-muted'>Unable to load activities.</p>";
                 ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; // Include a common footer if you have one ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
