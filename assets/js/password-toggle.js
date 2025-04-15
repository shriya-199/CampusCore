function togglePassword(inputId) {
    try {
        const input = document.getElementById(inputId);
        const button = document.querySelector(`button[data-target="${inputId}"]`);
        const icon = button.querySelector('i');
        
        if (!input || !button || !icon) {
            console.error('Required elements not found for password toggle');
            return;
        }

        const isPassword = input.type === 'password';
        input.type = isPassword ? 'text' : 'password';
        
        icon.classList.toggle('bi-eye', !isPassword);
        icon.classList.toggle('bi-eye-slash', isPassword);
        
        button.setAttribute('aria-label', `${isPassword ? 'Hide' : 'Show'} password`);
        button.setAttribute('aria-pressed', isPassword ? 'true' : 'false');
    } catch (error) {
        console.error('Error toggling password visibility:', error);
    }
}
