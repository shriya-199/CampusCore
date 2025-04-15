<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: manage_users.php");
    exit();
}

$id = (int)$_GET['id'];
$error = null; // Initialize error variable
$success = null; // Initialize success variable

// --- Fetch data needed for dropdowns --- 
$classes = [];
$subjects = [];
$students = []; // For parent's ward selection

// Fetch active classes
$sql_classes = "SELECT id, name FROM classes WHERE status = 'active' ORDER BY name";
$result_classes = $conn->query($sql_classes);
if ($result_classes) {
    while ($row = $result_classes->fetch_assoc()) {
        $classes[] = $row;
    }
    $result_classes->free();
} else {
    error_log("Error fetching classes in edit_user: " . $conn->error);
}

// Fetch active subjects
$sql_subjects = "SELECT id, name, class_id FROM subjects WHERE status = 'active' ORDER BY class_id, name";
$result_subjects = $conn->query($sql_subjects);
if ($result_subjects) {
    while ($row = $result_subjects->fetch_assoc()) {
        $subjects[] = $row;
    }
    $result_subjects->free();
} else {
    error_log("Error fetching subjects in edit_user: " . $conn->error);
}

// Fetch active students (for parent's ward selection - excluding the current user if they happen to be a student)
$sql_students = "SELECT id, full_name FROM users WHERE role = 'student' AND status = 'active' AND id != ? ORDER BY full_name";
$stmt_students_fetch = $conn->prepare($sql_students);
if ($stmt_students_fetch) {
    $stmt_students_fetch->bind_param("i", $id);
    $stmt_students_fetch->execute();
    $result_students = $stmt_students_fetch->get_result();
    while ($row = $result_students->fetch_assoc()) {
        $students[] = $row;
    }
    $stmt_students_fetch->close();
} else {
    error_log("Error fetching students in edit_user: " . $conn->error);
}

// --- End Fetch data for dropdowns ---

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Basic user details
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $role = $_POST['role'];
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $new_password = $_POST['password'];

    // Role-specific details
    $student_class_id = filter_input(INPUT_POST, 'student_class_id', FILTER_VALIDATE_INT);
    $teacher_subject_ids = isset($_POST['teacher_subject_ids']) ? array_map('intval', $_POST['teacher_subject_ids']) : [];
    $parent_ward_ids = isset($_POST['parent_ward_ids']) ? array_map('intval', $_POST['parent_ward_ids']) : [];

    $conn->begin_transaction();
    try {
        // Update basic user info
        if (!empty($new_password)) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $sql_user_update = "UPDATE users SET username=?, password=?, role=?, full_name=?, email=? WHERE id=?";
            $stmt_user_update = $conn->prepare($sql_user_update);
            $stmt_user_update->bind_param("sssssi", $username, $hashed_password, $role, $full_name, $email, $id);
        } else {
            $sql_user_update = "UPDATE users SET username=?, role=?, full_name=?, email=? WHERE id=?";
            $stmt_user_update = $conn->prepare($sql_user_update);
            $stmt_user_update->bind_param("ssssi", $username, $role, $full_name, $email, $id);
        }

        if (!$stmt_user_update->execute()) {
            throw new Exception("Error updating user details: " . $stmt_user_update->error);
        }
        $stmt_user_update->close();

        // --- Handle Role-Specific Updates --- 

        // If role is STUDENT
        if ($role === 'student' && $student_class_id) {
            // Remove existing assignments first (safer for changes)
            $sql_delete_sc = "DELETE FROM student_class WHERE student_id = ?";
            $stmt_delete_sc = $conn->prepare($sql_delete_sc);
            $stmt_delete_sc->bind_param("i", $id);
            $stmt_delete_sc->execute();
            $stmt_delete_sc->close();

            // Add the new assignment
            $sql_insert_sc = "INSERT INTO student_class (student_id, class_id, status) VALUES (?, ?, 'active')";
            $stmt_insert_sc = $conn->prepare($sql_insert_sc);
            $stmt_insert_sc->bind_param("ii", $id, $student_class_id);
            if (!$stmt_insert_sc->execute()) {
                throw new Exception("Error updating student class assignment: " . $stmt_insert_sc->error);
            }
            $stmt_insert_sc->close();
        } else {
             // If role changed FROM student, remove old assignments
             $sql_delete_sc = "DELETE FROM student_class WHERE student_id = ?";
             $stmt_delete_sc = $conn->prepare($sql_delete_sc);
             $stmt_delete_sc->bind_param("i", $id);
             $stmt_delete_sc->execute(); 
             $stmt_delete_sc->close();
        }

        // If role is TEACHER
        if ($role === 'teacher') {
            // Remove existing assignments first
            $sql_delete_ts = "DELETE FROM teacher_subject WHERE teacher_id = ?";
            $stmt_delete_ts = $conn->prepare($sql_delete_ts);
            $stmt_delete_ts->bind_param("i", $id);
            $stmt_delete_ts->execute();
            $stmt_delete_ts->close();

            // Add new assignments
            if (!empty($teacher_subject_ids)) {
                $sql_insert_ts = "INSERT INTO teacher_subject (teacher_id, subject_id) VALUES (?, ?)";
                $stmt_insert_ts = $conn->prepare($sql_insert_ts);
                foreach ($teacher_subject_ids as $subject_id) {
                    $stmt_insert_ts->bind_param("ii", $id, $subject_id);
                    if (!$stmt_insert_ts->execute()) {
                        throw new Exception("Error assigning teacher subject ID $subject_id: " . $stmt_insert_ts->error);
                    }
                }
                $stmt_insert_ts->close();
            }
        } else {
             // If role changed FROM teacher, remove old assignments
             $sql_delete_ts = "DELETE FROM teacher_subject WHERE teacher_id = ?";
             $stmt_delete_ts = $conn->prepare($sql_delete_ts);
             $stmt_delete_ts->bind_param("i", $id);
             $stmt_delete_ts->execute();
             $stmt_delete_ts->close();
        }

        // If role is PARENT
        if ($role === 'parent') {
            // Remove existing assignments first
            $sql_delete_pw = "DELETE FROM parent_ward WHERE parent_id = ?";
            $stmt_delete_pw = $conn->prepare($sql_delete_pw);
            $stmt_delete_pw->bind_param("i", $id);
            $stmt_delete_pw->execute();
            $stmt_delete_pw->close();

            // Add new assignments (assuming relationship is 'guardian' for simplicity here, adjust if needed)
            if (!empty($parent_ward_ids)) {
                $sql_insert_pw = "INSERT INTO parent_ward (parent_id, student_id, relationship, status) VALUES (?, ?, 'guardian', 'active')";
                $stmt_insert_pw = $conn->prepare($sql_insert_pw);
                foreach ($parent_ward_ids as $ward_id) {
                    $stmt_insert_pw->bind_param("ii", $id, $ward_id);
                    if (!$stmt_insert_pw->execute()) {
                        throw new Exception("Error assigning parent ward ID $ward_id: " . $stmt_insert_pw->error);
                    }
                }
                $stmt_insert_pw->close();
            }
        } else {
             // If role changed FROM parent, remove old assignments
            $sql_delete_pw = "DELETE FROM parent_ward WHERE parent_id = ?";
            $stmt_delete_pw = $conn->prepare($sql_delete_pw);
            $stmt_delete_pw->bind_param("i", $id);
            $stmt_delete_pw->execute();
            $stmt_delete_pw->close();
        }

        $conn->commit();
        $success = "User updated successfully.";
        // Optional: Redirect after successful update
        // header("Location: manage_users.php?success=1"); 
        // exit();

    } catch (Exception $e) {
        $conn->rollback();
        $error = "Error updating user: " . $e->getMessage();
    }

    // No redirect here if we want to show success/error message on the same page
    // Re-fetch user data after potential update to show latest info
    $sql_refetch = "SELECT * FROM users WHERE id = ?";
    $stmt_refetch = $conn->prepare($sql_refetch);
    $stmt_refetch->bind_param("i", $id);
    $stmt_refetch->execute();
    $result_refetch = $stmt_refetch->get_result();
    $user = $result_refetch->fetch_assoc();
    $stmt_refetch->close();

} else {
    // Fetch user data on initial GET request
    $sql = "SELECT * FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
}

if (!$user) {
    $_SESSION['error_message'] = "User not found.";
    header("Location: manage_users.php");
    exit();
}

// --- Fetch CURRENT role-specific assignments for the user being edited ---
$current_student_class_id = null;
$current_teacher_subject_ids = [];
$current_parent_ward_ids = [];

if ($user['role'] === 'student') {
    $sql_get_sc = "SELECT class_id FROM student_class WHERE student_id = ? AND status = 'active' LIMIT 1";
    $stmt_get_sc = $conn->prepare($sql_get_sc);
    $stmt_get_sc->bind_param("i", $id);
    $stmt_get_sc->execute();
    $result_get_sc = $stmt_get_sc->get_result();
    if ($row_sc = $result_get_sc->fetch_assoc()) {
        $current_student_class_id = $row_sc['class_id'];
    }
    $stmt_get_sc->close();
} elseif ($user['role'] === 'teacher') {
    $sql_get_ts = "SELECT subject_id FROM teacher_subject WHERE teacher_id = ?";
    $stmt_get_ts = $conn->prepare($sql_get_ts);
    $stmt_get_ts->bind_param("i", $id);
    $stmt_get_ts->execute();
    $result_get_ts = $stmt_get_ts->get_result();
    while ($row_ts = $result_get_ts->fetch_assoc()) {
        $current_teacher_subject_ids[] = $row_ts['subject_id'];
    }
    $stmt_get_ts->close();
} elseif ($user['role'] === 'parent') {
    $sql_get_pw = "SELECT student_id FROM parent_ward WHERE parent_id = ? AND status = 'active'";
    $stmt_get_pw = $conn->prepare($sql_get_pw);
    $stmt_get_pw->bind_param("i", $id);
    $stmt_get_pw->execute();
    $result_get_pw = $stmt_get_pw->get_result();
    while ($row_pw = $result_get_pw->fetch_assoc()) {
        $current_parent_ward_ids[] = $row_pw['student_id'];
    }
    $stmt_get_pw->close();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User - Campus Core</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="#">Academic Progress Tracker</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="manage_users.php">Manage Users</a>
                    </li>
                </ul>
                <div class="navbar-nav">
                    <a class="nav-link" href="logout.php">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Edit User (ID: <?php echo $id; ?>)</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                        <?php endif; ?>
                        <form method="POST">
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">New Password (leave blank to keep current)</label>
                                <div class="password-container">
                                    <input type="password" class="form-control" id="password" name="password">
                                    <button type="button" class="password-toggle" onclick="togglePassword('password')" data-target="password">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="role" class="form-label">Role</label>
                                <select class="form-control" id="role" name="role" required>
                                    <option value="teacher" <?php echo $user['role'] == 'teacher' ? 'selected' : ''; ?>>Teacher</option>
                                    <option value="student" <?php echo $user['role'] == 'student' ? 'selected' : ''; ?>>Student</option>
                                    <option value="parent" <?php echo $user['role'] == 'parent' ? 'selected' : ''; ?>>Parent</option>
                                </select>
                            </div>
                            <!-- Role Specific Fields -->
                            <div id="role-specific-fields">
                                <!-- Student Fields -->
                                <div class="mb-3" id="student-fields" style="display: <?php echo $user['role'] == 'student' ? 'block' : 'none'; ?>;">
                                    <label for="student_class_id" class="form-label">Assign to Class</label>
                                    <select class="form-select" id="student_class_id" name="student_class_id">
                                        <option value="">-- Select Class --</option>
                                        <?php foreach ($classes as $class): ?>
                                            <option value="<?php echo $class['id']; ?>" <?php echo ($user['role'] == 'student' && $current_student_class_id == $class['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($class['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Teacher Fields -->
                                <div class="mb-3" id="teacher-fields" style="display: <?php echo $user['role'] == 'teacher' ? 'block' : 'none'; ?>;">
                                    <label for="teacher_subject_ids" class="form-label">Assign Subjects</label>
                                    <select class="form-select" id="teacher_subject_ids" name="teacher_subject_ids[]" multiple size="8">
                                        <?php 
                                        // Group subjects by class for better display
                                        $subjects_by_class = [];
                                        foreach ($subjects as $subject) {
                                            $subjects_by_class[$subject['class_id']][] = $subject;
                                        }
                                        // Get class names map
                                        $class_names = array_column($classes, 'name', 'id');

                                        foreach ($subjects_by_class as $class_id => $class_subjects):
                                            $class_name = isset($class_names[$class_id]) ? htmlspecialchars($class_names[$class_id]) : "Class ID: $class_id";
                                        ?>
                                        <optgroup label="<?php echo $class_name; ?>">
                                            <?php foreach ($class_subjects as $subject): ?>
                                            <option value="<?php echo $subject['id']; ?>" <?php echo ($user['role'] == 'teacher' && in_array($subject['id'], $current_teacher_subject_ids)) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($subject['name']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Parent Fields -->
                                <div class="mb-3" id="parent-fields" style="display: <?php echo $user['role'] == 'parent' ? 'block' : 'none'; ?>;">
                                    <label for="parent_ward_ids" class="form-label">Assign Ward(s)</label>
                                    <select class="form-select" id="parent_ward_ids" name="parent_ward_ids[]" multiple size="8">
                                        <?php foreach ($students as $student): ?>
                                            <option value="<?php echo $student['id']; ?>" <?php echo ($user['role'] == 'parent' && in_array($student['id'], $current_parent_ward_ids)) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($student['full_name']); ?> (ID: <?php echo $student['id']; ?>)
                                            </option>
                                        <?php endforeach; ?>
                                        <?php if (empty($students)): ?>
                                            <option disabled>No active students available</option>
                                        <?php endif; ?>
                                    </select>
                                </div>
                            </div>
                            <!-- End Role Specific Fields -->

                            <div class="mb-3">
                                <label for="full_name" class="form-label">Full Name</label>
                                <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">Update User</button>
                                <a href="manage_users.php" class="btn btn-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/password-toggle.js"></script>

    <script>
        // JavaScript to show/hide role-specific fields when role changes
        document.addEventListener('DOMContentLoaded', function() {
            const roleSelect = document.getElementById('role');
            const studentFields = document.getElementById('student-fields');
            const teacherFields = document.getElementById('teacher-fields');
            const parentFields = document.getElementById('parent-fields');

            function toggleRoleFields() {
                const selectedRole = roleSelect.value;
                studentFields.style.display = (selectedRole === 'student') ? 'block' : 'none';
                teacherFields.style.display = (selectedRole === 'teacher') ? 'block' : 'none';
                parentFields.style.display = (selectedRole === 'parent') ? 'block' : 'none';
            }

            roleSelect.addEventListener('change', toggleRoleFields);

            // Initial call in case the page loads with a role already selected
            // toggleRoleFields(); // Fields are initially shown based on PHP echo
        });
    </script>
</body>
</html>
