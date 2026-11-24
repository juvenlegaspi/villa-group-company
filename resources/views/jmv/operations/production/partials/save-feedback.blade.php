@php
    $phpFileLimit = ini_parse_quantity((string) ini_get('upload_max_filesize'));
    $phpPostLimit = ini_parse_quantity((string) ini_get('post_max_size'));
    $fileLimit = $phpFileLimit > 0 ? min(10485760, $phpFileLimit) : 10485760;
@endphp
<div data-production-save data-file-limit="{{ $fileLimit }}" data-post-limit="{{ $phpPostLimit }}" data-file-count="{{ min(5, (int) ini_get('max_file_uploads')) }}" hidden role="status" aria-live="polite" class="mb-4 rounded-xl border border-blue-200 bg-blue-50 p-3 text-sm text-slate-800">
    <p data-save-message class="m-0 whitespace-pre-line"></p>
    <progress data-save-progress max="100" value="0" hidden style="width:100%;height:12px;margin-top:10px"></progress>
</div>
<p class="mb-3 text-xs text-slate-500">Upload limit on this server: {{ number_format($fileLimit / 1048576, 1) }} MB per file@if($phpPostLimit > 0), {{ number_format($phpPostLimit / 1048576, 1) }} MB total request@endif. Large mobile photos may take longer to upload.</p>
@once
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-production-save]').forEach(panel => {
        const form = panel.closest('form');
        if (!form || !window.XMLHttpRequest || !window.FormData) return;
        const message = panel.querySelector('[data-save-message]');
        const progress = panel.querySelector('[data-save-progress]');
        let busy = false;
        let buttons = [];
        const modal = form.closest('.modal');
        modal?.addEventListener('click', event => { if (busy && event.target === modal) { event.preventDefault(); event.stopPropagation(); } }, true);
        document.addEventListener('keydown', event => { if (busy && modal && event.key === 'Escape') { event.preventDefault(); event.stopImmediatePropagation(); } }, true);
        const show = (text, upload = false) => {
            panel.hidden = false;
            message.textContent = text;
            progress.hidden = !upload;
            panel.scrollIntoView({block: 'nearest'});
        };
        const unlock = () => {
            busy = false;
            form.dataset.productionBusy = 'false';
            form.removeAttribute('aria-busy');
            buttons.forEach(([button, disabled, html]) => { button.disabled = disabled; button.innerHTML = html; });
            buttons = [];
        };
        window.addEventListener('pageshow', unlock);
        window.addEventListener('beforeunload', event => { if (busy) { event.preventDefault(); event.returnValue = ''; } });
        form.addEventListener('submit', event => {
            event.preventDefault();
            if (busy) return;
            const files = [...(form.querySelector('[type="file"]')?.files ?? [])];
            if (files.length > Number(panel.dataset.fileCount)) { show('Too many files. Attach up to ' + panel.dataset.fileCount + ' files per save.'); return; }
            const tooLarge = files.find(file => file.size > Number(panel.dataset.fileLimit));
            if (tooLarge) { show(tooLarge.name + ' exceeds this server’s file limit. Choose a smaller file or compress the photo before saving.'); return; }
            const total = files.reduce((sum, file) => sum + file.size, 0);
            const postLimit = Number(panel.dataset.postLimit);
            if (postLimit > 0 && total + 65536 >= postLimit) { show('The combined attachments exceed this server’s total upload limit. Upload fewer or smaller files.'); return; }
            const data = new FormData(form);
            busy = true;
            form.dataset.productionBusy = 'true';
            form.setAttribute('aria-busy', 'true');
            buttons = [...form.querySelectorAll('button[type="submit"], button:not([type]), [data-bs-dismiss="modal"]')].map(button => [button, button.disabled, button.innerHTML]);
            buttons.forEach(([button]) => { button.disabled = true; if (button.type === 'submit') button.textContent = 'Saving draft…'; });
            progress.value = 0;
            show(files.length ? 'Uploading attachments… Please keep this page open.' : 'Saving your draft… Please wait.', files.length > 0);
            const xhr = new XMLHttpRequest();
            xhr.open('POST', form.action);
            xhr.timeout = 240000;
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.upload.addEventListener('progress', event => {
                if (!event.lengthComputable) return;
                progress.value = Math.round(event.loaded / event.total * 100);
                message.textContent = progress.value < 100 ? 'Uploading attachments: ' + progress.value + '%. Please wait.' : 'Upload complete. Saving the draft on the server…';
            });
            const uncertain = () => { unlock(); show('The save result could not be confirmed. Keep this form open and check the production listing in another tab before trying again, to avoid a duplicate. Your entered values have been kept here.'); };
            xhr.addEventListener('error', uncertain);
            xhr.addEventListener('timeout', uncertain);
            xhr.addEventListener('abort', uncertain);
            xhr.addEventListener('load', () => {
                let result;
                try { result = JSON.parse(xhr.responseText); } catch { result = {}; }
                if (xhr.status >= 200 && xhr.status < 300 && result.redirect_url) {
                    busy = false;
                    form.dataset.productionBusy = 'false';
                    show('Draft saved. Opening your record…');
                    window.location.assign(result.redirect_url);
                    return;
                }
                unlock();
                if (xhr.status === 422) show(Object.values(result.errors ?? {form: [result.message ?? 'Please check the entered details.']}).flat().join('\n'));
                else if (xhr.status === 413) show('The server rejected this upload as too large. Choose smaller or fewer files. Your entered values have been kept.');
                else if (xhr.status === 419 || xhr.status === 401) show('Your sign-in session expired. Sign in using another tab, then return here. Keep a copy of your entries before refreshing this form.');
                else if (xhr.status === 403) show('Your account does not currently have permission to save this log. Contact your administrator.');
                else show('The server did not confirm the save. Check the listing in another tab before retrying. Your entries have been kept; report this error to your administrator.');
            });
            xhr.send(data);
        });
    });
});
</script>
@endpush
@endonce
