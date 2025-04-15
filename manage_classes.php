<?php
// Start session if not already started
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Database connection
require_once 'includes/db_connection.php';

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add') {
            // Get form data
            $role = $_POST['role'];
            $username = mysqli_real_escape_string($conn, $_POST['username']);
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT); // Always hash passwords
            $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
            $email = mysqli_real_escape_string($conn, $_POST['email']);
            
            // Check if username already exists
            $check_query = "SELECT * FROM users WHERE username = '$username'";
            $check_result = mysqli_query($conn, $check_query);
            
            if (mysqli_num_rows($check_result) > 0) {
                $error = "Username already exists. Please choose a different username.";
            } else {
                // Insert the user based on role
                $insert_query = "INSERT INTO users (username, password, full_name, email, role) 
                                VALUES ('$username', '$password', '$full_name', '$email', '$role')";
                
                if (mysqli_query($conn, $insert_query)) {
                    $user_id = mysqli_insert_id($conn);
                    
                    // Handle role-specific data
                    if ($role === 'teacher' && isset($_POST['subjects'])) {
                        foreach ($_POST['subjects'] as $subject_id) {
                            // Insert teacher-subject relationships
                            $subject_id = mysqli_real_escape_string($conn, $subject_id);
                            $subject_query = "INSERT INTO teacher_subjects (teacher_id, subject_id) VALUES ($user_id, $subject_id)";
                            mysqli_query($conn, $subject_query);
                        }
                        $success = "Teacher added successfully.";
                    } elseif ($role === 'student' && isset($_POST['class_id'])) {
                        $class_id = mysqli_real_escape_string($conn, $_POST['class_id']);
                        $student_query = "INSERT INTO students (user_id, class_id) VALUES ($user_id, $class_id)";
                        if (mysqli_query($conn, $student_query)) {
                            $success = "Student added successfully.";
                        } else {
                            $error = "Error adding student details: " . mysqli_error($conn);
                        }
                    } elseif ($role === 'parent' && isset($_POST['ward_id']) && isset($_POST['relationship'])) {
                        $ward_id = mysqli_real_escape_string($conn, $_POST['ward_id']);
                        $relationship = mysqli_real_escape_string($conn, $_POST['relationship']);
                        $parent_query = "INSERT INTO parent_student (parent_id, student_id, relationship) 
                                        VALUES ($user_id, $ward_id, '$relationship')";
                        if (mysqli_query($conn, $parent_query)) {
                            $success = "Parent added successfully.";
                        } else {
                            $error = "Error adding parent details: " . mysqli_error($conn);
                        }
                    } else {
                        $success = "User added successfully.";
                    }
                } else {
                    $error = "Error adding user: " . mysqli_error($conn);
                }
            }
        } elseif ($_POST['action'] === 'edit') {
            // Handle edit user form submission
            $user_id = mysqli_real_escape_string($conn, $_POST['user_id']);
            $username = mysqli_real_escape_string($conn, $_POST['username']);
            $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
            $email = mysqli_real_escape_string($conn, $_POST['email']);
            $role = mysqli_real_escape_string($conn, $_POST['role']);
            
            // Check if username already exists for another user
            $check_query = "SELECT * FROM users WHERE username = '$username' AND id != $user_id";
            $check_result = mysqli_query($conn, $check_query);
            
            if (mysqli_num_rows($check_result) > 0) {
                $error = "Username already exists. Please choose a different username.";
            } else {
                // Update user
                $update_query = "UPDATE users SET username = '$username', full_name = '$full_name', email = '$email' WHERE id = $user_id";
                
                if (mysqli_query($conn, $update_query)) {
                    // Handle role-specific updates
                    if ($role === 'student' && isset($_POST['class_id'])) {
                        $class_id = mysqli_real_escape_string($conn, $_POST['class_id']);
                        $student_query = "UPDATE students SET class_id = $class_id WHERE user_id = $user_id";
                        mysqli_query($conn, $student_query);
                    }
                    
                    $success = "User updated successfully.";
                } else {
                    $error = "Error updating user: " . mysqli_error($conn);
                }
            }
        }
    }
}

// Fetch teachers
$teachers_query = "SELECT u.id, u.username, u.full_name FROM users u WHERE u.role = 'teacher'";
$teachers_result = mysqli_query($conn, $teachers_query);

// Fetch students
$students_query = "SELECT u.id, u.username, u.full_name, c.class_name 
                  FROM users u 
                  JOIN students s ON u.id = s.user_id 
                  JOIN classes c ON s.class_id = c.id 
                  WHERE u.role = 'student'";
$students_result = mysqli_query($conn, $students_query);

// Fetch parents
$parents_query = "SELECT u.id, u.username, u.full_name, 
                 GROUP_CONCAT(DISTINCT su.full_name SEPARATOR ', ') as wards
                 FROM users u 
                 LEFT JOIN parent_student ps ON u.id = ps.parent_id 
                 LEFT JOIN students s ON ps.student_id = s.user_id 
                 LEFT JOIN users su ON s.user_id = su.id 
                 WHERE u.role = 'parent' 
                 GROUP BY u.id";
$parents_result = mysqli_query($conn, $parents_query);

// Fetch classes for dropdowns
$classes_query = "SELECT * FROM classes ORDER BY class_name";
$classes_result = mysqli_query($conn, $classes_query);
$classes = [];
while ($class = mysqli_fetch_assoc($classes_result)) {
    $classes[] = $class;
}

// Fetch subjects for dropdowns
$subjects_query = "SELECT s.*, c.class_name, c.id as class_id 
                  FROM subjects s 
                  JOIN classes c ON s.class_id = c.id 
                  ORDER BY c.class_name, s.subject_name";
$subjects_result = mysqli_query($conn, $subjects_query);
$subjects_by_class = [];
while ($subject = mysqli_fetch_assoc($subjects_result)) {
    $class_id = $subject['class_id'];
    if (!isset($subjects_by_class[$class_id])) {
        $subjects_by_class[$class_id] = [];
    }
    $subjects_by_class[$class_id][] = $subject;
}

// Fetch students for parent assignment
$students_for_parents_query = "SELECT u.id, u.full_name, c.class_name 
                              FROM users u 
                              JOIN students s ON u.id = s.user_id 
                              JOIN classes c ON s.class_id = c.id 
                              WHERE u.role = 'student'";
$students_for_parents_result = mysqli_query($conn, $students_for_parents_query);
$students_for_parents = [];
while ($student = mysqli_fetch_assoc($students_for_parents_result)) {
    $students_for_parents[] = $student;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Academic Progress Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        .subjects-container {
            max-height: 200px;
            overflow-y: auto;
            padding: 10px;
            border: 1px solid #dee2e6;
            border-radius: 0.25rem;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">Academic Progress Tracker</a>
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
                    <li class="nav-item">
                        <a class="nav-link" href="attendance.php">Attendance</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="grades.php">Grades</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <?php echo $_SESSION['username']; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h2>Manage Users</h2>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <!-- Add User Button -->
        <div class="mb-3">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                <i class="bi bi-person-plus"></i> Add User
            </button>
        </div>

        <!-- Nav tabs -->
        <ul class="nav nav-tabs mb-3">
            <li class="nav-item">
                <a class="nav-link active" data-bs-toggle="tab" href="#teachers">Teachers</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#students">Students</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#parents">Parents</a>
            </li>
        </ul>

        <!-- Tab content -->
        <div class="tab-content">
            <!-- Teachers Tab -->
            <div class="tab-pane fade show active" id="teachers">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Username</th>
                                <th>Details</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($teacher = mysqli_fetch_assoc($teachers_result)): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($teacher['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($teacher['username']); ?></td>
                                    <td>
                                        <?php
                                        // Fetch teacher's classes and subjects
                                        $teacher_id = $teacher['id'];
                                        $teacher_subjects_query = "SELECT s.subject_name, c.class_name 
                                                                  FROM teacher_subjects ts 
                                                                  JOIN subjects s ON ts.subject_id = s.id 
                                                                  JOIN classes c ON s.class_id = c.id 
                                                                  WHERE ts.teacher_id = $teacher_id 
                                                                  ORDER BY c.class_name, s.subject_name";
                                        $teacher_subjects_result = mysqli_query($conn, $teacher_subjects_query);
                                        
                                        $classes = [];
                                        $subjects = [];
                                        
                                        while ($subject = mysqli_fetch_assoc($teacher_subjects_result)) {
                                            $class_name = $subject['class_name'];
                                            $subject_name = $subject['subject_name'];
                                            
                                            if (!in_array($class_name, $classes)) {
                                                $classes[] = $class_name;
                                            }
                                            
                                            $subjects[] = "$subject_name ($class_name)";
                                        }
                                        ?>
                                        <strong>Classes:</strong><br>
                                        <?php echo !empty($classes) ? implode("<br>", $classes) : "No classes assigned"; ?>
                                        <br><br>
                                        <strong>Subjects:</strong><br>
                                        <?php echo !empty($subjects) ? implode("<br>", $subjects) : "No subjects assigned"; ?>
                                    </td>
                                    <td>
                                        <a href="edit_user.php?id=<?php echo $teacher['id']; ?>" class="btn btn-sm btn-primary">
                                            <i class="bi bi-pencil"></i> Edit
                                        </a>
                                        <a href="delete_user.php?id=<?php echo $teacher['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this teacher? This will remove all their class and subject assignments.');">
                                            <i class="bi bi-trash"></i> Delete
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Students Tab -->
            <div class="tab-pane fade" id="students">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Username</th>
                                <th>Class</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($student = mysqli_fetch_assoc($students_result)): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($student['username']); ?></td>
                                    <td><?php echo htmlspecialchars($student['class_name']); ?></td>
                                    <td>
                                        <a href="edit_user.php?id=<?php echo $student['id']; ?>" class="btn btn-sm btn-primary">
                                            <i class="bi bi-pencil"></i> Edit
                                        </a>
                                        <a href="delete_user.php?id=<?php echo $student['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this student?');">
                                            <i class="bi bi-trash"></i> Delete
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Parents Tab -->
            <div class="tab-pane fade" id="parents">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Username</th>
                                <th>Wards</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($parent = mysqli_fetch_assoc($parents_result)): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($parent['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($parent['username']); ?></td>
                                    <td><?php echo $parent['wards'] ? htmlspecialchars($parent['wards']) : 'No wards assigned'; ?></td>
                                    <td>
                                        <a href="edit_user.php?id=<?php echo $parent['id']; ?>" class="btn btn-sm btn-primary">
                                            <i class="bi bi-pencil"></i> Edit
                                        </a>
                                        <a href="delete_user.php?id=<?php echo $parent['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this parent?');">
                                            <i class="bi bi-trash"></i> Delete
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addUserModalLabel">Add User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addUserForm" method="POST" action="manage_users.php">
                        <input type="hidden" name="action" value="add">
                        
                        <div class="mb-3">
                            <label for="role" class="form-label">Role</label>
                            <select class="form-select" id="role" name="role" required onchange="toggleRoleFields()">
                                <option value="">Select Role</option>
                                <option value="teacher">Teacher</option>
                                <option value="student">Student</option>
                                <option value="parent">Parent</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <div class="password-container">
                                <input type="password" class="form-control" id="password" name="password" required>
                                <button type="button" class="password-toggle" onclick="togglePassword('password')">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="full_name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" required>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>

                        <div class="mb-3">
                            <label for="role" class="form-label">Role</label>
                            <select class="form-select" id="role" name="role" required>
                                <option value="teacher">Teacher</option>
                                <option value="student">Student</option>
                                <option value="parent">Parent</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <div class="mb-3">
                            <label for="full_name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <div id="teacher-options" style="display:none;">
                            <div class="mb-3">
                                <label class="form-label">Select Years</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="year1" id="year1">
                                    <label class="form-check-label" for="year1">Year 1</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="year2" id="year2">
                                    <label class="form-check-label" for="year2">Year 2</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="year3" id="year3">
                                    <label class="form-check-label" for="year3">Year 3</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="year4" id="year4">
                                    <label class="form-check-label" for="year4">Year 4</label>
                                </div>
                                <button type="button" class="btn btn-link" id="selectAllYears">Select All</button>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Subjects</label>
                                <div class="subjects-container">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" value="subject1" id="subject1">
                                        <label class="form-check-label" for="subject1">Subject 1</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" value="subject2" id="subject2">
                                        <label class="form-check-label" for="subject2">Subject 2</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" value="subject3" id="subject3">
                                        <label class="form-check-label" for="subject3">Subject 3</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" value="subject4" id="subject4">
                                        <label class="form-check-label" for="subject4">Subject 4</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" value="subject5" id="subject5">
                                        <label class="form-check-label" for="subject5">Subject 5</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div id="student-options" style="display:none;">
                            <div class="mb-3">
                                <label for="class_id" class="form-label">Class</label>
                                <select class="form-select" id="class_id" name="class_id">
                                    <?php foreach ($classes as $class): ?>
                                        <option value="<?php echo $class['id']; ?>"><?php echo $class['class_name']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div id="parent-options" style="display:none;">
                            <div class="mb-3">
                                <label for="ward_id" class="form-label">Ward</label>
                                <select class="form-select" id="ward_id" name="ward_id">
                                    <?php foreach ($students_for_parents as $student): ?>
                                        <option value="<?php echo $student['id']; ?>"><?php echo $student['full_name']; ?> (<?php echo $student['class_name']; ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="relationship" class="form-label">Relationship</label>
                                <input type="text" class="form-control" id="relationship" name="relationship">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">Add User</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="manage_users.php">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="user_id" id="edit_user_id">
                        <input type="hidden" name="role" id="edit_role">
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" id="edit_username" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="full_name" id="edit_full_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" id="edit_email" class="form-control" required>
                        </div>
                        <div id="edit_student_fields" style="display: none;">
                            <div class="mb-3">
                                <label class="form-label">Class</label>
                                <select name="class_id" id="edit_class_id" class="form-select">
                                    <option value="">Select Class</option>
                                    <?php foreach ($classes as $class): ?>
                                        <option value="<?php echo $class['id']; ?>"><?php echo htmlspecialchars($class['class_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleRoleFields() {
            const role = document.getElementById('role').value;
            
            // Hide all role-specific fields first
            document.getElementById('teacherFields').style.display = 'none';
            document.getElementById('studentFields').style.display = 'none';
            document.getElementById('parentFields').style.display = 'none';
            
            // Show fields based on selected role
            if (role === 'teacher') {
                document.getElementById('teacherFields').style.display = 'block';
            } else if (role === 'student') {
                document.getElementById('studentFields').style.display = 'block';
            } else if (role === 'parent') {
                document.getElementById('parentFields').style.display = 'block';
            }
        }

        function toggleSubjects(classId) {
            const isChecked = document.getElementById(`class_${classId}`).checked;
            const subjectsContainer = document.getElementById(`subjects_${classId}`);
            const selectAllSubjects = document.getElementById(`selectAllSubjects_${classId}`);
            
            if (isChecked) {
                subjectsContainer.style.display = 'block';
                selectAllSubjects.disabled = false;
            } else {
                subjectsContainer.style.display = 'none';
                selectAllSubjects.disabled = true;
                selectAllSubjects.checked = false;
                
                // Uncheck all subject checkboxes for this class
                const subjectCheckboxes = document.querySelectorAll(`.subject-checkbox-${classId}`);
                subjectCheckboxes.forEach(checkbox => {
                    checkbox.checked = false;
                });
            }
        }

        function toggleAllSubjectsForClass(classId) {
            const isChecked = document.getElementById(`selectAllSubjects_${classId}`).checked;
            const subjectCheckboxes = document.querySelectorAll(`.subject-checkbox-${classId}`);
            
            subjectCheckboxes.forEach(checkbox => {
                checkbox.checked = isChecked;
            });
        }

        function toggleAllClasses() {
            const isChecked = document.getElementById('selectAllClasses').checked;
            const classCheckboxes = document.querySelectorAll('.class-checkbox');
            
            classCheckboxes.forEach(checkbox => {
                checkbox.checked = isChecked;
                const classId = checkbox.id.split('_')[1];
                toggleSubjects(classId);
                
                if (isChecked) {
                    document.getElementById(`selectAllSubjects_${classId}`).checked = true;
                    toggleAllSubjectsForClass(classId);
                }
            });
        }

        function togglePassword(fieldId) {
            const passwordField = document.getElementById(fieldId);
            const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordField.setAttribute('type', type);
            
            const icon = passwordField.nextElementSibling.querySelector('i');
            if (type === 'text') {
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        }

        // Edit user modal functions
        function openEditModal(userId, username, fullName, email, role, classId) {
            document.getElementById('edit_user_id').value = userId;
            document.getElementById('edit_username').value = username;
            document.getElementById('edit_full_name').value = fullName;
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_role').value = role;
            
            // Show/hide student-specific fields
            if (role === 'student') {
                document.getElementById('edit_student_fields').style.display = 'block';
                document.getElementById('edit_class_id').value = classId;
            } else {
                document.getElementById('edit_student_fields').style.display = 'none';
            }
            
            // Open the modal
            const editModal = new bootstrap.Modal(document.getElementById('editUserModal'));
            editModal.show();
        }
    </script>
</body>
</html>
<div class="mb-3">
    <label for="btechYear" class="form-label">BTech Year</label>
    <select class="form-select" id="btechYear" name="btech_year">
        <option value="1">BTech 1st Year</option>
        <option value="2">BTech 2nd Year</option>
        <option value="3">BTech 3rd Year</option>
        <option value="4">BTech 4th Year</option>
    </select>
</div>