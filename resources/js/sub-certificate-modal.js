const dialog = document.getElementById('sub-certificate-modal');
if (dialog) {
    const form = dialog.querySelector('[data-sub-certificate-form]');
    const errors = dialog.querySelector('[data-sub-certificate-errors]');
    const save = form.querySelector('[type="submit"]');
    let busy = false;
    let parentId;
    let saveLabel = 'Save sub-certificate';
    const showErrors = (messages) => {
        errors.replaceChildren();
        messages.forEach((message) => {
            const line = document.createElement('p');
            line.textContent = message;
            errors.append(line);
        });
        errors.hidden = false;
    };
    document.querySelectorAll('[data-sub-certificate-open]').forEach((button) => button.addEventListener('click', async () => {
        if (busy) return;
        form.reset();
        errors.hidden = true;
        form.action = button.dataset.storeUrl;
        parentId = button.dataset.parentId;
        dialog.querySelector('[data-sub-certificate-parent]').textContent = button.dataset.parentName;
        const mode = button.dataset.mode ?? 'add';
        saveLabel = mode === 'edit' ? 'Save changes' : (mode === 'renew' ? 'Renew sub-certificate' : 'Save sub-certificate');
        save.textContent = saveLabel;
        dialog.querySelector('#sub-certificate-modal-title').textContent = mode === 'edit' ? 'Edit sub-certificate' : (mode === 'renew' ? 'Renew sub-certificate' : 'Add sub-certificate / Annual audit');
        form.elements.document.required = mode !== 'edit';
        const note = dialog.querySelector('[data-sub-certificate-note]');
        note.textContent = mode === 'edit' ? 'Document replacement is optional. Previous documents remain in version history.' : (mode === 'renew' ? 'Verify the suggested dates against the official document. A new attachment is required. The previous record remains in history.' : '');
        const documentLink = dialog.querySelector('[data-sub-certificate-document]');
        documentLink.hidden = true;
        dialog.showModal();
        if (button.dataset.loadUrl) {
            busy = true;
            save.disabled = true;
            save.textContent = 'Loading…';
            try {
                const response = await fetch(button.dataset.loadUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                if (!response.ok) throw new Error('Unable to load');
                const record = await response.json();
                Object.entries(record.fields).forEach(([name, value]) => { form.elements.namedItem(name).value = value ?? ''; });
                if (mode === 'renew') {
                    form.elements.issue_date.value = record.suggested_issue_date ?? '';
                    form.elements.expiry_date.value = record.suggested_expiry_date ?? '';
                }
                if (record.document_url) {
                    documentLink.href = record.document_url;
                    documentLink.textContent = `Download: ${record.document_name}`;
                    documentLink.hidden = false;
                }
            } catch {
                showErrors(['Could not load this sub-certificate. Close this popup and try again.']);
                return;
            } finally {
                busy = false;
                save.textContent = saveLabel;
            }
        }
        save.disabled = false;
    }));
    dialog.querySelectorAll('[data-sub-certificate-close]').forEach((button) => button.addEventListener('click', () => { if (!busy) dialog.close(); }));
    dialog.addEventListener('cancel', (event) => { if (busy) event.preventDefault(); });
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (busy || !form.reportValidity()) return;
        const file = form.elements.document.files[0];
        if (file && file.size > 5 * 1024 * 1024) {
            showErrors(['Choose a document no larger than 5 MB.']);
            return;
        }
        busy = true;
        save.disabled = true;
        save.textContent = 'Saving…';
        errors.hidden = true;
        try {
            const response = await fetch(form.action, { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            const result = await response.json().catch(() => ({}));
            if (!response.ok) {
                showErrors(response.status === 422 ? Object.values(result.errors ?? { form: [result.message ?? 'Please check the entered details.'] }).flat() : [response.status === 419 || response.status === 401 ? 'Your session expired. Reload the page and sign in again.' : (result.message ?? 'Could not save the sub-certificate. Please try again.')]);
                return;
            }
            const url = new URL(window.location.href);
            url.searchParams.set('expand', parentId);
            url.hash = `certificate-${parentId}`;
            window.location.assign(url.href);
        } catch {
            showErrors(['The save result could not be confirmed. Check the listing before submitting again to avoid a duplicate.']);
        } finally {
            busy = false;
            save.disabled = false;
            save.textContent = saveLabel;
        }
    });
}
