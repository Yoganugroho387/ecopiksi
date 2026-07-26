
    const passwordInput = document.getElementById('add_password');
    const togglePassword = document.getElementById('togglePassword');
    const eyeClosed = document.getElementById('eyeClosed');
    const eyeOpen = document.getElementById('eyeOpen');

    togglePassword.addEventListener('click', () => {
        const type = passwordInput.type === 'password' ? 'text' : 'password';
        passwordInput.type = type;

        eyeClosed.classList.toggle('hidden');
        eyeOpen.classList.toggle('hidden');
    });
