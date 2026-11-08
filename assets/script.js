document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('form').forEach((form) => {
        const requiredFields = Array.from(form.querySelectorAll('[required]'));
        const password = form.querySelector('input[name="password"]');
        const confirmation = form.querySelector('input[name="confirm_password"]');

        const validateConfirmation = () => {
            if (!password || !confirmation) {
                return;
            }

            confirmation.setCustomValidity(
                confirmation.value !== '' && confirmation.value !== password.value
                    ? 'Your passwords do not match.'
                    : ''
            );
        };

        password?.addEventListener('input', validateConfirmation);
        confirmation?.addEventListener('input', validateConfirmation);

        form.addEventListener('submit', (event) => {
            validateConfirmation();

            if (requiredFields.some((field) => field.value.trim() === '') || !form.checkValidity()) {
                event.preventDefault();
                form.reportValidity();
            }
        });
    });
});
