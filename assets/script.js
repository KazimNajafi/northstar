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

document.addEventListener('DOMContentLoaded', () => {
    const bookingForm = document.querySelector('[data-price-per-person]');

    if (!bookingForm) {
        return;
    }

    const pricePerPerson = Number.parseFloat(bookingForm.dataset.pricePerPerson || '0');
    const guestInput = bookingForm.querySelector('[data-guest-input]');
    const totalTarget = bookingForm.querySelector('[data-booking-total]');

    if (!guestInput || !totalTarget || !Number.isFinite(pricePerPerson)) {
        return;
    }

    const updateTotal = () => {
        const guests = Number.parseInt(guestInput.value, 10);

        if (!Number.isFinite(guests) || guests < 1) {
            return;
        }

        totalTarget.textContent = 'Rs ' + Math.round(pricePerPerson * guests).toLocaleString('en-US');
    };

    guestInput.addEventListener('input', updateTotal);
    updateTotal();
});
