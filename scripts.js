// Initialize charts if they exist on the page
document.addEventListener('DOMContentLoaded', function() {
    // Grades Chart
    const gradesChartEl = document.getElementById('gradesChart');
    if (gradesChartEl) {
        new Chart(gradesChartEl, {
            type: 'bar',
            data: {
                labels: ['Math', 'Science', 'English', 'History', 'Art'],
                datasets: [{
                    label: 'Current Grades',
                    data: [85, 92, 88, 95, 90],
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.5)',
                        'rgba(54, 162, 235, 0.5)',
                        'rgba(255, 206, 86, 0.5)',
                        'rgba(75, 192, 192, 0.5)',
                        'rgba(153, 102, 255, 0.5)'
                    ],
                    borderColor: [
                        'rgba(255, 99, 132, 1)',
                        'rgba(54, 162, 235, 1)',
                        'rgba(255, 206, 86, 1)',
                        'rgba(75, 192, 192, 1)',
                        'rgba(153, 102, 255, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100
                    }
                }
            }
        });
    }

    // Attendance Chart
    const attendanceChartEl = document.getElementById('attendanceChart');
    if (attendanceChartEl) {
        new Chart(attendanceChartEl, {
            type: 'doughnut',
            data: {
                labels: ['Present', 'Absent'],
                datasets: [{
                    data: [90, 10],
                    backgroundColor: [
                        'rgba(75, 192, 192, 0.5)',
                        'rgba(255, 99, 132, 0.5)'
                    ],
                    borderColor: [
                        'rgba(75, 192, 192, 1)',
                        'rgba(255, 99, 132, 1)'
                    ],
                    borderWidth: 1
                }]
            }
        });
    }
});

// Function to handle attendance marking
function markAttendance(studentId, present) {
    fetch('mark_attendance.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `student_id=${studentId}&present=${present}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Attendance marked successfully!');
        } else {
            alert('Error marking attendance');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error marking attendance');
    });
}

// Function to update grades
function updateGrade(studentId, subjectId, grade) {
    fetch('update_grade.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `student_id=${studentId}&subject_id=${subjectId}&grade=${grade}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Grade updated successfully!');
        } else {
            alert('Error updating grade');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error updating grade');
    });
}
