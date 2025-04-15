function toggleRoleFields() {
    const role = document.getElementById('role').value;
    const teacherFields = document.getElementById('teacherFields');
    const studentFields = document.getElementById('studentFields');
    const parentFields = document.getElementById('parentFields');

    // Hide all role-specific fields first
    teacherFields.style.display = 'none';
    studentFields.style.display = 'none';
    parentFields.style.display = 'none';

    // Show fields based on selected role
    switch(role) {
        case 'teacher':
            teacherFields.style.display = 'block';
            break;
        case 'student':
            studentFields.style.display = 'block';
            break;
        case 'parent':
            parentFields.style.display = 'block';
            break;
    }
}

function toggleSubjects(classId) {
    const subjectsContainer = document.getElementById(`subjects_${classId}`);
    const selectAllSubjectsCheckbox = document.getElementById(`selectAllSubjects_${classId}`);
    const classCheckbox = document.getElementById(`class_${classId}`);

    if (classCheckbox.checked) {
        subjectsContainer.style.display = 'block';
        selectAllSubjectsCheckbox.disabled = false;
    } else {
        subjectsContainer.style.display = 'none';
        selectAllSubjectsCheckbox.disabled = true;
        selectAllSubjectsCheckbox.checked = false;
        // Uncheck all subject checkboxes for this class
        const subjectCheckboxes = document.getElementsByClassName(`subject-checkbox-${classId}`);
        Array.from(subjectCheckboxes).forEach(checkbox => checkbox.checked = false);
    }
}

function toggleAllSubjectsForClass(classId) {
    const selectAllSubjectsCheckbox = document.getElementById(`selectAllSubjects_${classId}`);
    const subjectCheckboxes = document.getElementsByClassName(`subject-checkbox-${classId}`);
    Array.from(subjectCheckboxes).forEach(checkbox => {
        checkbox.checked = selectAllSubjectsCheckbox.checked;
    });
}

function toggleAllClasses() {
    const selectAllClassesCheckbox = document.getElementById('selectAllClasses');
    const classCheckboxes = document.getElementsByClassName('class-checkbox');
    const selectAllSubjectsCheckboxes = document.getElementsByClassName('select-all-subjects');

    Array.from(classCheckboxes).forEach((checkbox, index) => {
        checkbox.checked = selectAllClassesCheckbox.checked;
        const classId = checkbox.id.replace('class_', '');
        toggleSubjects(classId);
        
        if (selectAllClassesCheckbox.checked) {
            selectAllSubjectsCheckboxes[index].checked = true;
            toggleAllSubjectsForClass(classId);
        }
    });
}

function validateForm() {
    const role = document.getElementById('role').value;
    const username = document.getElementById('username').value;
    const password = document.getElementById('password');
    const email = document.getElementById('email').value;
    const fullName = document.getElementById('full_name').value;

    if (!username || !email || !fullName) {
        alert('Please fill in all required fields (Username, Email, and Full Name)');
        return false;
    }

    if (password && password.value.length < 6) {
        alert('Password must be at least 6 characters long');
        return false;
    }
    
    if (role === 'teacher') {
        const subjectCheckboxes = document.querySelectorAll('input[name="subjects[]"]');
        const selectedSubjects = Array.from(subjectCheckboxes).filter(checkbox => checkbox.checked);
        
        if (selectedSubjects.length === 0) {
            alert('Please select at least one subject for the teacher.');
            return false;
        }
    } else if (role === 'student') {
        const classId = document.querySelector('select[name="class_id"]');
        if (!classId || !classId.value) {
            alert('Please select a class for the student.');
            return false;
        }
    } else if (role === 'parent') {
        const wardId = document.getElementById('ward_id');
        const relationship = document.getElementById('relationship');
        if (!wardId || !wardId.value || !relationship || !relationship.value) {
            alert('Please select both a ward and relationship for the parent.');
            return false;
        }
    }
    
    return true;
}