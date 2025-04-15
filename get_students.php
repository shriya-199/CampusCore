<?php
require_once 'config.php';
require_teacher();

if (!isset($_GET['class_id'])) {
    exit('Class ID is required');
}

$class_id = (int)$_GET['class_id'];
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

$sql = "SELECT u.id, u.full_name, IFNULL(a.status, 'present') as status
        FROM users u 
        JOIN student_class sc ON u.id = sc.student_id 
        LEFT JOIN attendance a ON u.id = a.student_id 
            AND a.date = ? AND a.class_id = ?
        WHERE sc.class_id = ? 
            AND sc.status = 'active' 
            AND u.role = 'student'
        ORDER BY u.full_name";

$stmt = $conn->prepare($sql);
$stmt->bind_param("sii", $date, $class_id, $class_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo '<div class="alert alert-info">No students found in this class.</div>';
    exit();
}
?>

<div class="table-responsive">
    <table class="table">
        <thead>
            <tr>
                <th>Student Name</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($student = $result->fetch_assoc()): ?>
            <tr>
                <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                <td>
                    <div class="btn-group" role="group">
                        <input type="radio" class="btn-check" name="status[<?php echo $student['id']; ?>]" 
                               id="present_<?php echo $student['id']; ?>" value="present" 
                               <?php echo ($student['status'] === 'present') ? 'checked' : ''; ?>>
                        <label class="btn btn-outline-success" for="present_<?php echo $student['id']; ?>">Present</label>

                        <input type="radio" class="btn-check" name="status[<?php echo $student['id']; ?>]" 
                               id="absent_<?php echo $student['id']; ?>" value="absent"
                               <?php echo ($student['status'] === 'absent') ? 'checked' : ''; ?>>
                        <label class="btn btn-outline-danger" for="absent_<?php echo $student['id']; ?>">Absent</label>

                        <input type="radio" class="btn-check" name="status[<?php echo $student['id']; ?>]" 
                               id="late_<?php echo $student['id']; ?>" value="late"
                               <?php echo ($student['status'] === 'late') ? 'checked' : ''; ?>>
                        <label class="btn btn-outline-warning" for="late_<?php echo $student['id']; ?>">Late</label>
                    </div>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>
