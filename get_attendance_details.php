<?php
require_once 'config.php';
require_teacher();

if (!isset($_GET['date'])) {
    exit('Date is required');
}

$date = $_GET['date'];
$class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$where_clause = $_SESSION['role'] === 'admin' ? '1' : 'c.teacher_id = ' . $_SESSION['user_id'];
if ($class_id) {
    $where_clause .= ' AND c.id = ' . $class_id;
}

$sql = "SELECT 
            c.name as class_name,
            c.grade_level,
            c.section,
            u.full_name,
            a.status,
            m.full_name as marked_by,
            a.created_at
        FROM attendance a
        JOIN classes c ON a.class_id = c.id
        JOIN users u ON a.student_id = u.id
        JOIN users m ON a.marked_by = m.id
        WHERE $where_clause
            AND a.date = ?
        ORDER BY c.name, u.full_name";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $date);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo '<div class="alert alert-info">No detailed attendance records found for ' . date('M d, Y', strtotime($date)) . '.</div>';
    exit();
}

$current_class = '';
?>

<h5 class="mb-3">Attendance Details for <?php echo date('M d, Y', strtotime($date)); ?></h5>

<div class="table-responsive">
    <?php while ($row = $result->fetch_assoc()): ?>
        <?php if ($current_class !== $row['class_name']): ?>
            <?php if ($current_class !== ''): ?>
                    </tbody>
                </table>
            <?php endif; ?>
            
            <h6 class="mt-4"><?php echo htmlspecialchars($row['class_name'] . ' - Grade ' . $row['grade_level'] . '-' . $row['section']); ?></h6>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Student Name</th>
                        <th>Status</th>
                        <th>Marked By</th>
                        <th>Time</th>
                    </tr>
                </thead>
                <tbody>
            <?php 
            $current_class = $row['class_name'];
        endif; 
        ?>
        <tr>
            <td><?php echo htmlspecialchars($row['full_name']); ?></td>
            <td>
                <?php 
                $status_class = [
                    'present' => 'success',
                    'absent' => 'danger',
                    'late' => 'warning'
                ][$row['status']];
                ?>
                <span class="badge bg-<?php echo $status_class; ?>">
                    <?php echo ucfirst($row['status']); ?>
                </span>
            </td>
            <td><?php echo htmlspecialchars($row['marked_by']); ?></td>
            <td><?php echo date('h:i A', strtotime($row['created_at'])); ?></td>
        </tr>
    <?php endwhile; ?>
        </tbody>
    </table>
</div>
