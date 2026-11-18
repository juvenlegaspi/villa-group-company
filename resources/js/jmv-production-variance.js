document.querySelectorAll('[data-production-variance]').forEach((output) => {
    const coal = output.closest('form')?.querySelector('[name="coal_output"]');
    if (!coal) return;
    const render = () => {
        if (output.dataset.dailyTarget === '') { output.textContent = 'Target not set'; return; }
        if (coal.value.trim() === '') { output.textContent = 'Enter coal output'; return; }
        const actual = Number(coal.value);
        if (!Number.isInteger(actual) || actual < 0) { output.textContent = 'Enter a valid coal output'; return; }
        const variance = actual - Number(output.dataset.dailyTarget);
        output.textContent = `${variance > 0 ? '+' : ''}${variance.toLocaleString('en-US')} Bagon`;
    };
    coal.addEventListener('input', render);
    coal.addEventListener('change', render);
    output.closest('.modal')?.addEventListener('shown.bs.modal', render);
    render();
});
