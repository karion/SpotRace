// Keep the server-rendered form usable when JavaScript is unavailable.
document.querySelectorAll('[data-vehicle-choice]').forEach(function (choice) {
    const form = choice.closest('form');
    const field = form.querySelector('[data-new-vehicle-field]');
    const input = field.querySelector('input');
    const recipient = form.querySelector('[data-recipient-choice]');
    const allOptions = Array.from(choice.options).map(option => option.cloneNode(true));
    const plateRequired = choice.dataset.plateRequired === '1';
    const syncField = function () {
        const hasSavedVehicles = Array.from(choice.options).some(option => option.value && option.value !== '__other__');
        const ready = !recipient || recipient.value !== '';
        const show = ready && (choice.value === '__other__' || !hasSavedVehicles || input.value !== '');
        field.hidden = !show;
        input.required = show && (plateRequired || choice.value === '__other__');
        choice.required = ready && plateRequired && !show;
    };
    const filterVehicles = function (reset) {
        const previous = reset ? '' : choice.value;
        choice.replaceChildren(...allOptions.filter(option => !option.dataset.ownerId || option.dataset.ownerId === recipient.value).map(option => option.cloneNode(true)));
        choice.value = Array.from(choice.options).some(option => option.value === previous) ? previous : '';
        choice.disabled = recipient.value === '';
        if (reset) input.value = '';
        syncField();
    };
    choice.addEventListener('change', function () {
        if (choice.value !== '__other__') input.value = '';
        syncField();
    });
    if (recipient) {
        recipient.addEventListener('change', function () { filterVehicles(true); });
        filterVehicles(false);
    } else {
        syncField();
    }
});

document.querySelectorAll('[data-reservation-modal]').forEach(function (modal) {
    modal.addEventListener('show.bs.modal', function (event) {
        const trigger = event.relatedTarget;
        if (!trigger) return;
        const form = document.getElementById(trigger.dataset.formTarget);
        modal.querySelectorAll('.reservation-form').forEach(item => item.classList.toggle('d-none', item !== form));
        modal.querySelector('[data-reservation-context]').textContent = trigger.dataset.context;
        form.querySelector('[type="submit"]').textContent = trigger.dataset.actionLabel;
        const spot = form.querySelector('[name="spot_id"]');
        if (spot) spot.value = trigger.dataset.spotId;
    });
});
