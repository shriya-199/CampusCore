<?php
session_start();
require_once 'config.php';

// Check if user is logged in and is Admin or Teacher
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'teacher'])) {
    $_SESSION['error_message'] = "You do not have permission to access this page.";
    header("Location: dashboard.php");
    exit();
}

$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Fetch classes for the dropdown
$classes = [];
$sql_classes = "SELECT id, name FROM classes ORDER BY name";
if ($role === 'teacher') {
    // Teachers might only see classes they are assigned subjects in - adjust if needed
    $sql_classes = "SELECT DISTINCT c.id, c.name 
                    FROM classes c 
                    JOIN subjects s ON c.id = s.class_id 
                    JOIN teacher_subject ts ON s.id = ts.subject_id 
                    WHERE ts.teacher_id = ? 
                    ORDER BY c.name";
}

if ($stmt_classes = $conn->prepare($sql_classes)) {
    if ($role === 'teacher') {
        $stmt_classes->bind_param("i", $user_id);
    }
    $stmt_classes->execute();
    $result_classes = $stmt_classes->get_result();
    while ($row = $result_classes->fetch_assoc()) {
        $classes[] = $row;
    }
    $stmt_classes->close();
} else {
    $error_message = "Error fetching classes: " . $conn->error;
}


// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_activity'])) {
    $class_id = filter_input(INPUT_POST, 'class_id', FILTER_VALIDATE_INT);
    $title = isset($_POST['title']) ? trim(htmlspecialchars($_POST['title'], ENT_QUOTES, 'UTF-8')) : '';
    $description = isset($_POST['description']) ? trim(htmlspecialchars($_POST['description'], ENT_QUOTES, 'UTF-8')) : '';
    $activity_date = isset($_POST['activity_date']) ? htmlspecialchars($_POST['activity_date'], ENT_QUOTES, 'UTF-8') : ''; // Keep date format YYYY-MM-DD
    $status = 'pending'; // Default status, adjust as needed

    // Basic Validation
    if (empty($class_id) || empty($title) || empty($activity_date)) {
        $error_message = "Please fill in Class, Title, and Date.";
    } else {
        // Validate date format (basic check)
        if (DateTime::createFromFormat('Y-m-d', $activity_date) === false) {
             $error_message = "Invalid Date format. Please use YYYY-MM-DD.";
        } else {
            // Verify the user ID from session exists in the users table
            $sql_user_check = "SELECT id FROM users WHERE id = ?";
            if ($stmt_user_check = $conn->prepare($sql_user_check)) {
                $stmt_user_check->bind_param("i", $user_id);
                $stmt_user_check->execute();
                $stmt_user_check->store_result();
                if ($stmt_user_check->num_rows == 0) {
                     $error_message = "Error: Invalid user session. Please log out and log back in.";
                     // Optional: Destroy session here?
                     // session_destroy(); 
                } else {
                    // User ID is valid, proceed with insert
                    $sql_insert = "INSERT INTO activities (class_id, title, description, activity_date, status, created_by) VALUES (?, ?, ?, ?, ?, ?)";
                    if ($stmt_insert = $conn->prepare($sql_insert)) {
                        $stmt_insert->bind_param("issssi", $class_id, $title, $description, $activity_date, $status, $user_id);
                        if ($stmt_insert->execute()) {
                            $success_message = "Activity added successfully!";
                            // --- START: Log activity for students in the class ---
                            $actor_id = $_SESSION['user_id'];
                            $activity_type = 'class_activity_added';

                            // Fetch class name
                            $class_name = "the class"; // Default
                            $sql_class_name = "SELECT name FROM classes WHERE id = ?";
                            if($stmt_class_name = $conn->prepare($sql_class_name)) {
                                $stmt_class_name->bind_param("i", $class_id);
                                $stmt_class_name->execute();
                                $result_class_name = $stmt_class_name->get_result();
                                if($row_class_name = $result_class_name->fetch_assoc()) {
                                    $class_name = htmlspecialchars($row_class_name['name']);
                                }
                                $stmt_class_name->close();
                            }

                            $description_log = "New activity added for class '$class_name': " . htmlspecialchars($title);

                            // Fetch students in the class using the function from config.php
                            // Assuming get_class_students uses the global $conn defined in config.php
                            $students_result = get_class_students($class_id);

                            if ($students_result && $students_result->num_rows > 0) {
                                $log_sql = "INSERT INTO activity_log (user_id, actor_id, activity_type, description) VALUES (?, ?, ?, ?)";
                                if ($log_stmt = $conn->prepare($log_sql)) {
                                    while ($student = $students_result->fetch_assoc()) {
                                        $student_id = $student['id'];
                                        // Bind params: student_id (user_id), actor_id, type, desc
                                        $log_stmt->bind_param("iiss", $student_id, $actor_id, $activity_type, $description_log);
                                        if (!$log_stmt->execute()) {
                                            error_log("Failed to log activity for student ID $student_id: " . $log_stmt->error);
                                        }
                                    }
                                    $log_stmt->close();
                                } else {
                                    error_log("Failed to prepare student activity log statement: " . $conn->error);
                                }
                                // Free the result set if it's a mysqli_result object
                                if ($students_result instanceof mysqli_result) {
                                    $students_result->free();
                                }
                            } elseif ($students_result === false) {
                                 error_log("Failed to fetch students for logging activity: " . $conn->error);
                            }
                            // --- END: Log activity for students in the class ---
                        } else {
                            // Check for specific foreign key error again, though ideally the check above prevents it.
                            if ($conn->errno == 1452) { // Error number for foreign key constraint failure
                                $error_message = "Error adding activity: Invalid Class ID or User ID provided.";
                            } else {
                                $error_message = "Error adding activity: " . $stmt_insert->error . " (Code: " . $conn->errno . ")";
                            }
                        }
                        $stmt_insert->close();
                    } else {
                        $error_message = "Error preparing statement: " . $conn->error;
                    }
                }
                $stmt_user_check->close();
            } else {
                 $error_message = "Error checking user validity: " . $conn->error;
            }
        }
    }
}

// TODO: Add logic to fetch and display existing activities

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Activities - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container mt-4">
        <h2>Manage Activities</h2>

        <?php if ($success_message): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="alert alert-danger"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-header">Add New Activity</div>
            <div class="card-body">
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                    <div class="mb-3">
                        <label for="class_id" class="form-label">Class/Section</label>
                        <select class="form-select" id="class_id" name="class_id" required>
                            <option value="">Select Class</option>
                            <?php foreach ($classes as $class): ?>
                                <option value="<?php echo $class['id']; ?>"><?php echo htmlspecialchars($class['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="title" class="form-label">Activity Title</label>
                        <input type="text" class="form-control" id="title" name="title" required>
                    </div>
                     <div class="mb-3">
                        <label for="activity_date" class="form-label">Date</label>
                        <input type="date" class="form-control" id="activity_date" name="activity_date" required>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description (Optional)</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                    <button type="submit" name="add_activity" class="btn btn-primary">Add Activity</button>
                </form>
            </div>
        </div>

        <div class="card">
             <div class="card-header">Existing Activities</div>
             <div class="card-body">
                <p class="text-muted">Displaying existing activities will be implemented here.</p>
                <!-- TODO: Add table to display activities -->
             </div>
        </div>

    </div>

    <?php include 'includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
