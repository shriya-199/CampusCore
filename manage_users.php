<?php
session_start();
require_once 'config.php';

// --- Message Handling (moved near top) ---
$success_message = null;
$error_message = null;
if (isset($_GET['success'])) {
    if ($_GET['success'] === 'created') {
        $success_message = "User created successfully!";
    } elseif ($_GET['success'] === 'updated') {
        $success_message = "User updated successfully!";
    } elseif ($_GET['success'] === 'deleted') {
        $success_message = "User deleted successfully!";
    }
}
if (isset($_GET['error'])) {
    // Decode the error message from the URL
    $error_message = urldecode($_GET['error']);
}
// --- End Message Handling ---

// Check if user is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// Determine which tab is active
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'teacher';

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['addUserForm'])) { 
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role'];
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $status = 'active'; // Default status

    // --- Role-Specific Data Processing --- 
    $teacher_subject_ids = [];
    if ($role === 'teacher' && isset($_POST['subjects'])) {
        // Ensure subjects is an array and sanitize each value
        $teacher_subject_ids = is_array($_POST['subjects']) ? array_map('intval', $_POST['subjects']) : [];
    }

    $parent_ward_ids = [];
    $parent_relationship = '';
    if ($role === 'parent') {
        if (isset($_POST['ward_ids']) && is_array($_POST['ward_ids'])) {
            $parent_ward_ids = array_map('intval', $_POST['ward_ids']);
        }
        if (isset($_POST['relationship'])) {
            // Basic validation for relationship - adjust allowed values if needed
            $allowed_relationships = ['father', 'mother', 'guardian'];
            if (in_array($_POST['relationship'], $allowed_relationships)) {
                $parent_relationship = $_POST['relationship'];
            } else {
                 // Handle invalid relationship? Set flash message and redirect?
                 // For now, just won't save it if invalid.
            }
        }
        // Add validation: Ensure parent selects at least one ward and a relationship
        if (empty($parent_ward_ids) || empty($parent_relationship)) {
            // Set flash message and redirect back with error
            set_flash_message('danger', 'Parents must select at least one ward and specify the relationship.');
            header("Location: manage_users.php?tab=parent&error=parent_data_missing");
            exit();
        }
    }
    // --- End Role-Specific Data Processing ---

    $student_class_id = filter_input(INPUT_POST, 'student_class', FILTER_VALIDATE_INT); // Get class ID if role is student

    // Start transaction
    $conn->begin_transaction(); 

    try {
        // Prepare base user insert statement
        $sql = "INSERT INTO users (username, password, role, full_name, email, status) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: (" . $conn->errno . ") " . $conn->error);
        }

        // Bind parameters including the potentially null class_id
        $stmt->bind_param("ssssss", $username, $password, $role, $full_name, $email, $status);

        // Execute user insert
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: (" . $stmt->errno . ") " . $stmt->error);
        }
        $user_id = $stmt->insert_id;
        $stmt->close();
        
        // Handle additional data based on role
        if ($role === 'teacher' && !empty($teacher_subject_ids)) {
            $sql_ts = "INSERT INTO teacher_subject (teacher_id, subject_id) VALUES (?, ?)";
            $stmt_ts = $conn->prepare($sql_ts);
            if (!$stmt_ts) {
                 throw new Exception("Prepare failed (teacher_subject insert): " . $conn->error);
            }
            foreach ($teacher_subject_ids as $subject_id) {
                $stmt_ts->bind_param("ii", $user_id, $subject_id);
                if (!$stmt_ts->execute()) {
                    throw new Exception("Execute failed (teacher_subject insert): " . $stmt_ts->error);
                }
            }
            $stmt_ts->close();
        }
        elseif ($role === 'parent' && !empty($parent_ward_ids)) {
            $sql_pw = "INSERT INTO parent_ward (parent_id, student_id, relationship) VALUES (?, ?, ?)";
            $stmt_pw = $conn->prepare($sql_pw);
            if (!$stmt_pw) {
                 throw new Exception("Prepare failed (parent_ward insert): " . $conn->error);
            }
            foreach ($parent_ward_ids as $ward_id) {
                $stmt_pw->bind_param("iis", $user_id, $ward_id, $parent_relationship);
                 if (!$stmt_pw->execute()) {
                     throw new Exception("Execute failed (parent_ward insert): " . $stmt_pw->error);
                 }
            }
            $stmt_pw->close();
        }
        
        $conn->commit(); 
        // Redirect on success (PRG Pattern)
        header("Location: manage_users.php?tab=" . urlencode($role) . "&success=created");
        exit();

    } catch (Exception $e) {
        $conn->rollback(); 
        $error_details = "Error creating user."; // Default error
        $exceptionMessage = $e->getMessage(); // Get the message once

        // Check for duplicate entry error more reliably using the message string
        if (strpos($exceptionMessage, 'Duplicate entry') !== false) {
            if (strpos($exceptionMessage, "'username'") !== false) {
                // Check which username caused the issue - it might be the one submitted or already exist
                 $dup_username = isset($username) ? $username : '[unknown]'; // Use submitted username if available
                $error_details = "Username '" . htmlspecialchars($dup_username) . "' already exists. Please choose a different one.";
            } elseif (strpos($exceptionMessage, "'email'") !== false) {
                 // Check which email caused the issue
                 $dup_email = isset($email) ? $email : '[unknown]'; // Use submitted email if available
                 $error_details = "Email '" . htmlspecialchars($dup_email) . "' is already registered. Please use a different email.";
            } else {
                 // Other duplicate key error - less common, provide slightly more detail
                 error_log("User creation duplicate error (other key): " . $exceptionMessage);
                 $error_details = "A unique data conflict occurred. Please check your input.";
            }
         } else {
             // Generic error message for other issues
             error_log("User creation error: " . $exceptionMessage); // Log detailed error
             $error_details = "An unexpected error occurred during user creation. Please check logs or contact support."; // Reverted user message
         }
         // Redirect on error (PRG Pattern)
         // Make sure $role is available from the POST data even on error
         $submitted_role = isset($_POST['role']) ? $_POST['role'] : 'teacher'; // Use submitted role or default
         header("Location: manage_users.php?tab=" . urlencode($submitted_role) . "&error=" . urlencode($error_details));
         exit();
    }
}

// Fetch data needed for modals
$classes = [];
$subjects = [];
$students = [];

// Fetch active classes
$sql_classes = "SELECT id, name FROM classes WHERE status = 'active' ORDER BY name";
$result_classes = $conn->query($sql_classes);
if ($result_classes) {
    while ($row = $result_classes->fetch_assoc()) {
        $classes[] = $row;
    }
    $result_classes->free();
} else {
    // Handle error appropriately, maybe log it
    error_log("Error fetching classes: " . $conn->error);
}

// Fetch active subjects
$sql_subjects = "SELECT id, name, class_id FROM subjects WHERE status = 'active' ORDER BY name";
$result_subjects = $conn->query($sql_subjects);
if ($result_subjects) {
    while ($row = $result_subjects->fetch_assoc()) {
        $subjects[] = $row;
    }
    $result_subjects->free();
} else {
    error_log("Error fetching subjects: " . $conn->error);
}

// Fetch active students (for parent selection)
$sql_students = "SELECT id, full_name FROM users WHERE role = 'student' AND status = 'active' ORDER BY full_name";
$result_students = $conn->query($sql_students);
if ($result_students) {
    while ($row = $result_students->fetch_assoc()) {
        $students[] = $row;
    }
    $result_students->free();
} else {
    error_log("Error fetching students: " . $conn->error);
}

// Get active tab from URL, default to 'teacher'
$allowed_tabs = ['teacher', 'student', 'parent'];
$active_tab = isset($_GET['tab']) && in_array($_GET['tab'], $allowed_tabs) ? $_GET['tab'] : 'teacher';

// Get list of users based on active tab
$sql = "SELECT id, username, role, full_name, email FROM users WHERE role = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $active_tab);
$stmt->execute();
$result = $stmt->get_result();

// Get list of classes
$classes_sql = "SELECT id, name FROM classes ORDER BY id";
$classes_result = $conn->query($classes_sql);
$classes = [];
while ($row = $classes_result->fetch_assoc()) {
    $classes[] = $row;
}

// Get list of subjects
$subjects_sql = "SELECT id, name, class_id FROM subjects WHERE status = 'active' ORDER BY class_id, name";
$subjects_result = $conn->query($subjects_sql);
$subjects = [];
if ($subjects_result) {
    while ($row = $subjects_result->fetch_assoc()) {
        $subjects[] = $row;
    }
} else {
    $error_message = isset($error_message) ? $error_message . "<br>" : "";
    $error_message .= "Error fetching subjects: " . $conn->error;
}

// Get list of students for parent ward selection
$students_sql = "SELECT id, full_name, username FROM users WHERE role = 'student' AND status = 'active'";
$students_result = $conn->query($students_sql);
$students = [];
if ($students_result) {
    while ($row = $students_result->fetch_assoc()) {
        $students[] = $row;
    }
} else {
    $error_message = isset($error_message) ? $error_message . "<br>" : "";
     $error_message .= "Error fetching students: " . $conn->error;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Academic Progress Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
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
                    <li class="nav-item">
                        <a class="nav-link" href="manage_attendance.php">Manage Attendance</a>
                    </li>
                     <li class="nav-item">
                        <a class="nav-link" href="manage_grades.php">Manage Grades</a>
                    </li>
                </ul>
                <div class="navbar-nav">
                    <span class="navbar-text me-3">Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</span>
                    <a class="nav-link" href="logout.php">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($success_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($error_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <ul class="nav nav-tabs" style="border-bottom: none;">
                <li class="nav-item">
                    <a class="nav-link <?php echo $active_tab === 'teacher' ? 'active' : ''; ?>" href="?tab=teacher">Teachers</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $active_tab === 'student' ? 'active' : ''; ?>" href="?tab=student">Students</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $active_tab === 'parent' ? 'active' : ''; ?>" href="?tab=parent">Parents</a>
                </li>
            </ul>
             <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                Add New User
            </button>
        </div>

        <div class="row">
            <div class="col-md-12"> 
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><?php echo ucfirst($active_tab); ?> List</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Username</th>
                                        <th>Full Name</th>
                                        <th>Email</th>
                                        <?php if ($active_tab === 'teacher'): ?>
                                            <th>Classes</th>
                                        <?php elseif ($active_tab === 'student'): ?>
                                            <th>Class</th>
                                        <?php elseif ($active_tab === 'parent'): ?>
                                            <th>Ward</th>
                                        <?php endif; ?>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['username']); ?></td>
                                        <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['email']); ?></td>
                                        <?php if ($active_tab === 'teacher'): ?>
                                            <td>
                                                <?php
                                                $teacher_id = $row['id'];
                                                $sql_teacher_classes = "SELECT DISTINCT c.name 
                                                                        FROM classes c
                                                                        JOIN subjects s ON c.id = s.class_id
                                                                        JOIN teacher_subject ts ON s.id = ts.subject_id
                                                                        WHERE ts.teacher_id = ?";
                                                $stmt_teacher_classes = $conn->prepare($sql_teacher_classes);
                                                $stmt_teacher_classes->bind_param("i", $teacher_id);
                                                $stmt_teacher_classes->execute();
                                                $teacher_classes_result = $stmt_teacher_classes->get_result();
                                                
                                                $classes_list = [];
                                                while ($class = $teacher_classes_result->fetch_assoc()) {
                                                    $classes_list[] = $class['name'];
                                                }
                                                echo htmlspecialchars(implode(", ", array_unique($classes_list))); // Use array_unique just in case
                                                $stmt_teacher_classes->close();
                                                ?>
                                            </td>
                                        <?php elseif ($active_tab === 'student'): ?>
                                            <td>
                                                <?php
                                                $student_id = $row['id'];
                                                $sql_student_class = "SELECT c.name FROM classes c 
                                                                    JOIN student_class sc ON c.id = sc.class_id 
                                                                    WHERE sc.student_id = ? AND sc.status = 'active'"; 
                                                $stmt_student_class = $conn->prepare($sql_student_class);
                                                $class_name = 'N/A (Query Prepare Failed)'; // Default if prepare fails
                                                 if ($stmt_student_class) {
                                                     $stmt_student_class->bind_param("i", $student_id);
                                                     if ($stmt_student_class->execute()) {
                                                         $student_class_result = $stmt_student_class->get_result();
                                                         if ($class = $student_class_result->fetch_assoc()) {
                                                             $class_name = $class['name'];
                                                         } else {
                                                             $class_name = 'N/A (Class Not Found/Inactive)'; // No matching row found
                                                         }
                                                     } else {
                                                         $class_name = 'N/A (Query Execute Failed: ' . $stmt_student_class->error . ')'; // Execute failed
                                                     }
                                                     $stmt_student_class->close();
                                                 }
                                                echo htmlspecialchars($class_name);
                                                ?>
                                            </td>
                                        <?php elseif ($active_tab === 'parent'): ?>
                                            <td>
                                                <?php
                                                $parent_id = $row['id'];
                                                $sql_wards = "SELECT u.full_name, pw.relationship FROM users u 
                                                            JOIN parent_ward pw ON u.id = pw.student_id 
                                                            WHERE pw.parent_id = ?";
                                                $stmt_wards = $conn->prepare($sql_wards);
                                                $wards_list = [];
                                                if ($stmt_wards) {
                                                    $stmt_wards->bind_param("i", $parent_id);
                                                    $stmt_wards->execute();
                                                    $wards_result = $stmt_wards->get_result();
                                                    while ($ward = $wards_result->fetch_assoc()) {
                                                        $wards_list[] = htmlspecialchars($ward['full_name']) . " (" . htmlspecialchars($ward['relationship']) . ")";
                                                    }
                                                    $stmt_wards->close();
                                                }
                                                echo implode(", <br>", $wards_list); // Display multiple wards
                                                ?>
                                            </td>
                                        <?php endif; ?>
                                        <td>
                                            <a href="edit_user.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-primary">Edit</a>
                                            <a href="delete_user.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this user?')">Delete</a>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const phpClasses = <?php echo json_encode($classes ?? []); ?>;
        const phpSubjects = <?php echo json_encode($subjects ?? []); ?>;
        const phpStudents = <?php echo json_encode($students ?? []); ?>;
        const classMap = phpClasses.reduce((map, cls) => {
            map[cls.id] = cls.name;
            return map;
        }, {});
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const roleSelect = document.getElementById('role'); 
            const specificFieldsDiv = document.getElementById('role-specific-fields'); 
            const addUserModal = document.getElementById('addUserModal');

            if (!roleSelect) { 
                console.error('Role select element not found!');
                return;
            }
             if (!specificFieldsDiv) {
                console.error('Role specific fields div not found!');
                return;
            }

            function updateRoleSpecificFields() {
                const role = roleSelect.value;
                console.log('Role selected:', role); 
                specificFieldsDiv.innerHTML = ''; 

                if (role === 'teacher') {
                    console.log('Teacher role detected. Populating fields...');
                    
                    let teacherHtml = '<div class="mb-3">';
                    teacherHtml += '<label for="modal_teacher_classes" class="form-label">Classes Teaching (select one or more)</label>';
                    teacherHtml += '<select class="form-select" id="modal_teacher_classes" name="subjects[]" multiple size="5">'; 
                    if (phpClasses && phpClasses.length > 0) {
                        phpClasses.forEach(c => { teacherHtml += `<option value="${c.id}">${c.name}</option>`; });
                    } else {
                        teacherHtml += '<option disabled>No classes available</option>';
                    }
                    teacherHtml += '</select></div>';

                    teacherHtml += '<div class="mb-3">';
                    teacherHtml += '<label for="modal_teacher_subjects" class="form-label">Subjects Teaching (filtered by selected classes)</label>';
                    teacherHtml += '<select class="form-select" id="modal_teacher_subjects" name="subjects[]" multiple size="8">'; 
                    teacherHtml += '<option value="" disabled>Select classes first...</option>'; 
                    teacherHtml += '</select></div>';

                    specificFieldsDiv.innerHTML = teacherHtml;

                    const classSelect = document.getElementById('modal_teacher_classes');
                    const subjectSelect = document.getElementById('modal_teacher_subjects');

                    if (classSelect && subjectSelect) {
                        classSelect.addEventListener('change', function() {
                            const selectedClassIds = Array.from(classSelect.selectedOptions).map(option => option.value);
                            subjectSelect.innerHTML = ''; 

                            if (selectedClassIds.length === 0) {
                                subjectSelect.innerHTML = '<option value="" disabled>Select classes first...</option>';
                                return;
                            }

                            let filteredSubjectsFound = false;
                            if(phpSubjects && phpSubjects.length > 0) {
                                phpSubjects.forEach(subj => {
                                    if (selectedClassIds.includes(String(subj.class_id))) { 
                                        subjectSelect.innerHTML += `<option value="${subj.id}">${subj.name} (${classMap[subj.class_id] || 'Unknown Class'})</option>`;
                                        filteredSubjectsFound = true;
                                    }
                                });
                            }

                            if (!filteredSubjectsFound) {
                                subjectSelect.innerHTML = '<option value="" disabled>No subjects found for selected class(es)</option>';
                            }
                        });
                        if (!phpClasses || phpClasses.length === 0) {
                            classSelect.disabled = true;
                            subjectSelect.disabled = true;
                            subjectSelect.innerHTML = '<option disabled>No classes available</option>';
                        } else if (!phpSubjects || phpSubjects.length === 0) {
                            subjectSelect.disabled = true;
                            subjectSelect.innerHTML = '<option disabled>No subjects available</option>';
                        }
                    } else {
                        console.error("Class or Subject select not found for teacher fields");
                    }

                } else if (role === 'student') {
                    console.log('Student role detected.'); 
                    let studentHtml = '<div class="mb-3"><label for="modal_student_class" class="form-label">Class</label><select class="form-select" id="modal_student_class" name="student_class">'; 
                    studentHtml += '<option value="" disabled selected>Select Class...</option>'; 
                    phpClasses.forEach(c => { studentHtml += `<option value="${c.id}">${c.name}</option>`; });
                    studentHtml += '</select></div>';
                    specificFieldsDiv.innerHTML = studentHtml;

                } else if (role === 'parent') {
                    console.log('Parent role detected.'); 
                    let parentHtml = '<div class="mb-3"><label for="modal_parent_wards" class="form-label">Select Ward(s)</label><select class="form-select" id="modal_parent_wards" name="ward_ids[]" multiple required size="5">'; 
                    if (phpStudents.length === 0) {
                        parentHtml += '<option disabled>No students available</option>';
                    } else {
                        phpStudents.forEach(s => { parentHtml += `<option value="${s.id}">${s.full_name} (${s.username})</option>`; });
                    }
                    parentHtml += '</select></div>';
                    parentHtml += '<div class="mb-3"><label for="modal_parent_relationship" class="form-label">Relationship to Ward(s)</label><select class="form-select" id="modal_parent_relationship" name="relationship" required>';
                    parentHtml += '<option value="father">Father</option><option value="mother">Mother</option><option value="guardian">Guardian</option>';
                    parentHtml += '</select></div>';
                    specificFieldsDiv.innerHTML = parentHtml;
                    if (phpStudents.length === 0) {
                        document.getElementById('modal_parent_wards').disabled = true;
                        document.getElementById('modal_parent_relationship').disabled = true;
                    }
                } else {
                    console.log('No specific role logic for:', role); 
                }
            }

            roleSelect.addEventListener('change', updateRoleSpecificFields);

            addUserModal.addEventListener('hidden.bs.modal', function () {
                const form = document.getElementById('addUserForm');
                if(form) {
                    form.reset();
                }
                if(specificFieldsDiv) {
                    specificFieldsDiv.innerHTML = '';
                }
            });
        });
    </script>
</body>
</html>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addUserModalLabel">Add New User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addUserForm" method="post" action="manage_users.php?tab=<?php echo $active_tab; ?>"> 
                    <input type="hidden" name="addUserForm" value="1"> 
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" class="form-control" id="username" name="username" required autocomplete="username">
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required autocomplete="new-password">
                    </div>
                    <div class="mb-3">
                        <label for="full_name" class="form-label">Full Name</label>
                        <input type="text" class="form-control" id="full_name" name="full_name" required autocomplete="name">
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" required autocomplete="email">
                    </div>
                    <div class="mb-3">
                        <label for="role" class="form-label">Role</label>
                        <select class="form-select" id="role" name="role" required>
                            <option value="teacher">Teacher</option>
                            <option value="student" selected>Student</option>
                            <option value="parent">Parent</option>
                        </select>
                    </div>
                    <div id="role-specific-fields"></div>
                    <button type="submit" class="btn btn-primary">Add User</button>
                </form>
            </div>
        </div>
    </div>
</div>