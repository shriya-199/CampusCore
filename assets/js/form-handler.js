// Handle form submissions with proper error handling
function handleFormSubmission(form) {
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        if (!validateForm()) {
            return;
        }

        try {
            const formData = new FormData(form);
            const response = await fetch(form.action, {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const result = await response.text();
            window.location.href = 'manage_users.php';
        } catch (error) {
            console.error('Error:', error);
            if (!navigator.onLine) {
                alert('Network error: Please check your internet connection and try again.');
            } else {
                alert('An error occurred while processing your request. Please try again.');
            }
        }
    });
}

// Initialize form handlers when the document is ready
document.addEventListener('DOMContentLoaded', function() {
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        if (form.method.toLowerCase() === 'post') {
            handleFormSubmission(form);
        }
    });
});