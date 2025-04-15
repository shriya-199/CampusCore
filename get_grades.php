<?php
require_once 'config.php';
require_teacher();

$class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$subject_id = isset($_GET['subject_id']) ? (int)$_GET['subject_id'] : 0;

$where_clause = $_SESSION['role'] === 'admin' ? '1' : 'c.teacher_id = ' . $_SESSION['user_id'];
if ($class_id) {
    $where_clause .= ' AND c.id = ' . $class_id;
}
if ($subject_id) {
    $where_clause .= ' AND s.id = ' . $subject_id;
}

$sql = "SELECT 
            g.id,
            c.name as class_name,
            c.grade_level,
            c.section,
            s.name as subject_name,
            u.full_name as student_name,
            g.test_name,
            g.score,
            g.max_score,
            g.test_date,
            ROUND((g.score / g.max_score * 100), 1) as percentage
        FROM grades g
        JOIN subjects s ON g.subject_id = s.id
        JOIN classes c ON s.class_id = c.id
        JOIN users u ON g.student_id = u.id
        WHERE $where_clause
        ORDER BY g.test_date DESC, c.name, s.name, u.full_name";

$result = $conn->query($sql);

if ($result->num_rows == 0) {
    echo '<div class="alert alert-info">No grades found for the selected criteria.</div>';
    exit();
}

$current_class = '';
$current_subject = '';
?>

<div class="table-responsive">
    <?php while ($row = $result->fetch_assoc()): ?>
        <?php if ($current_class !== $row['class_name'] || $current_subject !== $row['subject_name']): ?>
            <?php if ($current_class !== ''): ?>
                    </tbody>
                </table>
            <?php endif; ?>
            
            <h6 class="mt-4">
                <?php 
                echo htmlspecialchars($row['class_name'] . ' - Grade ' . $row['grade_level'] . '-' . $row['section']);
                if ($current_class === $row['class_name']) {
                    echo ' → ';
                } else {
                    echo '<br>';
                }
                echo htmlspecialchars($row['subject_name']); 
                ?>
            </h6>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Test</th>
                        <th>Score</th>
                        <th>Percentage</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
            <?php 
            $current_class = $row['class_name'];
            $current_subject = $row['subject_name'];
        endif; 
        ?>
        <tr>
            <td><?php echo htmlspecialchars($row['student_name']); ?></td>
            <td><?php echo htmlspecialchars($row['test_name']); ?></td>
            <td><?php echo $row['score'] . ' / ' . $row['max_score']; ?></td>
            <td>
                <?php 
                $percentage = $row['percentage'];
                $color_class = $percentage >= 90 ? 'success' : ($percentage >= 75 ? 'warning' : 'danger');
                ?>
                <div class="progress">
                    <div class="progress-bar bg-<?php echo $color_class; ?>" 
                         role="progressbar" 
                         style="width: <?php echo $percentage; ?>%"
                         aria-valuenow="<?php echo $percentage; ?>" 
                         aria-valuemin="0" 
                         aria-valuemax="100">
                        <?php echo $percentage; ?>%
                    </div>
                </div>
            </td>
            <td><?php echo date('M d, Y', strtotime($row['test_date'])); ?></td>
            <td>
                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editGradeModal<?php echo $row['id']; ?>">
                    <i class="bi bi-pencil"></i>
                </button>
                <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteGradeModal<?php echo $row['id']; ?>">
                    <i class="bi bi-trash"></i>
                </button>

                <!-- Edit Modal -->
                <div class="modal fade" id="editGradeModal<?php echo $row['id']; ?>" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Edit Grade</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <form method="POST">
                                <div class="modal-body">
                                    <input type="hidden" name="action" value="edit">
                                    <input type="hidden" name="grade_id" value="<?php echo $row['id']; ?>">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Score</label>
                                                <input type="number" name="score" class="form-control" step="0.01" value="<?php echo $row['score']; ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Maximum Score</label>
                                                <input type="number" name="max_score" class="form-control" step="0.01" value="<?php echo $row['max_score']; ?>" required>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Test Date</label>
                                        <input type="date" name="test_date" class="form-control" value="<?php echo $row['test_date']; ?>" required>
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

                <!-- Delete Modal -->
                <div class="modal fade" id="deleteGradeModal<?php echo $row['id']; ?>" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Delete Grade</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                Are you sure you want to delete this grade record?<br>
                                <strong>Student:</strong> <?php echo htmlspecialchars($row['student_name']); ?><br>
                                <strong>Test:</strong> <?php echo htmlspecialchars($row['test_name']); ?><br>
                                <strong>Score:</strong> <?php echo $row['score'] . ' / ' . $row['max_score']; ?>
                            </div>
                            <div class="modal-footer">
                                <form method="POST">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="grade_id" value="<?php echo $row['id']; ?>">
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
