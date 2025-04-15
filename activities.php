<?php
require_once 'config.php';
require_teacher();

$class_id = isset($_GET['class_id']) ? intval($_GET['class_id']) : 0;
$classes = get_user_classes($_SESSION['user_id'], $_SESSION['role']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $title = sanitize_input($_POST['title']);
                $description = sanitize_input($_POST['description']);
                $class_id = intval($_POST['class_id']);
                $activity_date = sanitize_input($_POST['activity_date']);
                
                $sql = "INSERT INTO activities (title, description, class_id, activity_date, created_by) 
                        VALUES (?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssisi", $title, $description, $class_id, $activity_date, $_SESSION['user_id']);
                
                if ($stmt->execute()) {
                    set_flash_message('success', 'Activity added successfully!');
                } else {
                    set_flash_message('danger', 'Error adding activity: ' . $conn->error);
                }
                $stmt->close();
                break;

            case 'update':
                $activity_id = intval($_POST['activity_id']);
                $status = sanitize_input($_POST['status']);
                
                $sql = "UPDATE activities SET status = ? WHERE id = ? AND created_by = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sii", $status, $activity_id, $_SESSION['user_id']);
                
                if ($stmt->execute()) {
                    set_flash_message('success', 'Activity status updated!');
                } else {
                    set_flash_message('danger', 'Error updating activity: ' . $conn->error);
                }
                $stmt->close();
                break;

            case 'delete':
                $activity_id = intval($_POST['activity_id']);
                
                $sql = "DELETE FROM activities WHERE id = ? AND created_by = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ii", $activity_id, $_SESSION['user_id']);
                
                if ($stmt->execute()) {
                    set_flash_message('success', 'Activity deleted successfully!');
                } else {
                    set_flash_message('danger', 'Error deleting activity: ' . $conn->error);
                }
                $stmt->close();
                break;
        }
    }
    redirect('activities.php' . ($class_id ? "?class_id=$class_id" : ''));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activities - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container mt-4">
        <?php $flash = get_flash_message(); ?>
        <?php if ($flash): ?>
            <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show">
                <?php echo $flash['message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Add New Activity</h5>
                    </div>
                    <div class="card-body">
                        <form action="activities.php" method="POST">
                            <input type="hidden" name="action" value="add">
                            
                            <div class="mb-3">
                                <label for="class_id" class="form-label">Class</label>
                                <select class="form-select" id="class_id" name="class_id" required>
                                    <option value="">Select Class</option>
                                    <?php while ($class = $classes->fetch_assoc()): ?>
                                        <option value="<?php echo $class['id']; ?>" <?php echo $class_id == $class['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($class['name'] . ' - ' . $class['section']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="title" class="form-label">Title</label>
                                <input type="text" class="form-control" id="title" name="title" required>
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3" required></textarea>
                            </div>

                            <div class="mb-3">
                                <label for="activity_date" class="form-label">Due Date</label>
                                <input type="date" class="form-control" id="activity_date" name="activity_date" required>
                            </div>

                            <button type="submit" class="btn btn-primary">Add Activity</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Manage Activities</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Description</th>
                                        <th>Class</th>
                                        <th>Due Date</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $activities_sql = "SELECT a.*, c.name as class_name, c.section 
                                                     FROM activities a 
                                                     JOIN classes c ON a.class_id = c.id 
                                                     WHERE " . 
                                                     ($_SESSION['role'] === 'admin' ? "1=1" : "a.created_by = " . $_SESSION['user_id']) .
                                                     ($class_id ? " AND a.class_id = " . $class_id : "") .
                                                     " ORDER BY a.activity_date DESC";
                                    $activities = $conn->query($activities_sql);
                                    while ($activity = $activities->fetch_assoc()):
                                    ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($activity['title']); ?></td>
                                            <td><?php echo htmlspecialchars($activity['description']); ?></td>
                                            <td><?php echo htmlspecialchars($activity['class_name'] . ' - ' . $activity['section']); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($activity['activity_date'])); ?></td>
                                            <td>
                                                <form action="activities.php" method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="update">
                                                    <input type="hidden" name="activity_id" value="<?php echo $activity['id']; ?>">
                                                    <select class="form-select form-select-sm" name="status" onchange="this.form.submit()">
                                                        <option value="upcoming" <?php echo $activity['status'] === 'upcoming' ? 'selected' : ''; ?>>Upcoming</option>
                                                        <option value="ongoing" <?php echo $activity['status'] === 'ongoing' ? 'selected' : ''; ?>>Ongoing</option>
                                                        <option value="completed" <?php echo $activity['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                                    </select>
                                                </form>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $activity['id']; ?>">
                                                    <i class="bi bi-trash"></i>
                                                </button>

                                                <!-- Delete Modal -->
                                                <div class="modal fade" id="deleteModal<?php echo $activity['id']; ?>" tabindex="-1">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Delete Activity</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                Are you sure you want to delete the activity "<?php echo htmlspecialchars($activity['title']); ?>"?
                                                            </div>
                                                            <div class="modal-footer">
                                                                <form action="activities.php" method="POST">
                                                                    <input type="hidden" name="action" value="delete">
                                                                    <input type="hidden" name="activity_id" value="<?php echo $activity['id']; ?>">
                                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                    <button type="submit" class="btn btn-danger">Delete</button>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
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
</body>
</html>
