<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bulk Asset Tags | Yatira</title>
    <style>
        :root { color-scheme: light; font-family: Arial, Helvetica, sans-serif; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #0f172a; background: #eef2f7; }
        .toolbar { position: sticky; top: 0; z-index: 10; display: flex; align-items: center; justify-content: space-between; gap: 18px; padding: 16px 24px; color: #fff; background: linear-gradient(110deg,#17345f,#285794); box-shadow: 0 4px 16px #0f172a33; }
        .toolbar h1 { margin: 0 0 4px; font-size: 18px; }
        .toolbar p { margin: 0; color: #dbeafe; font-size: 12px; }
        .toolbar-actions { display: flex; align-items: center; gap: 10px; }
        .toolbar select, .toolbar button { min-height: 40px; border: 1px solid #ffffff55; border-radius: 10px; padding: 0 14px; font-weight: 700; }
        .toolbar select { color: #17345f; background: #fff; }
        .toolbar button { cursor: pointer; color: #17345f; background: #fcd34d; }
        .sheet-note { max-width: 205mm; margin: 18px auto 0; border: 1px solid #bfdbfe; border-radius: 12px; padding: 12px 14px; color: #1e40af; background: #eff6ff; font-size: 12px; line-height: 1.55; }
        .tag-sheet { display: grid; grid-template-columns: repeat(3, 63mm); grid-auto-rows: 58mm; gap: 4mm; width: 197mm; margin: 18px auto 30px; padding: 0; }
        .asset-label { display: flex; flex-direction: column; width: 63mm; height: 58mm; overflow: hidden; border: .45mm solid #0f172a; border-radius: 3mm; padding: 2.5mm 3mm 2mm; background: #fff; break-inside: avoid; page-break-inside: avoid; }
        .label-brand { display: grid; grid-template-columns: 11mm minmax(0,1fr) auto; align-items: center; gap: 1.5mm; border-bottom: .25mm solid #0e9ab1; padding-bottom: 1.5mm; }
        .label-logo { display: block; width: 11mm; height: 8mm; object-fit: contain; }
        .label-company { min-width: 0; }
        .label-company strong { display: block; overflow: hidden; color: #0f274c; font-size: 7pt; font-weight: 900; line-height: 1.05; text-overflow: ellipsis; white-space: nowrap; }
        .label-company small { display: block; margin-top: .5mm; color: #ef4444; font-size: 6pt; font-weight: 800; }
        .label-status { color: #7c3f13; font-size: 7pt; font-weight: 900; text-transform: uppercase; white-space: nowrap; }
        .label-title { display: flex; justify-content: center; gap: 1.5mm; min-width: 0; margin: 1.5mm 0 .5mm; color: #050b18; font-size: 6.5pt; font-weight: 900; text-align: center; text-transform: uppercase; }
        .asset-name, .asset-meta { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .label-qr { display: grid; flex: 1; min-height: 30mm; place-items: center; }
        .label-qr img { display: block; width: 30mm; height: 30mm; image-rendering: pixelated; }
        .asset-code { color: #0891b2; font-family: Consolas, monospace; font-size: 8pt; font-weight: 900; letter-spacing: .08em; text-align: center; }
        .label-footer { margin-top: .4mm; color: #64748b; font-size: 4.5pt; font-weight: 800; text-align: center; }
        @page { size: 8.5in 13in portrait; margin: 8mm; }
        @media print {
            body { background: #fff; }
            .no-print { display: none !important; }
            .tag-sheet { width: 198mm; margin: 0 auto; }
            .asset-label { box-shadow: none; }
        }
        @media screen and (max-width: 900px) {
            .toolbar { align-items: stretch; flex-direction: column; }
            .toolbar-actions { flex-wrap: wrap; }
            .tag-sheet { grid-template-columns: 63mm; justify-content: center; width: auto; }
        }
    </style>
</head>
<body>
    <header class="toolbar no-print">
        <div><h1>Bulk Asset Tag Printing</h1><p>{{ $assets->count() }} assets &bull; {{ $copies }} {{ Str::plural('copy', $copies) }} each &bull; {{ $labels->count() }} total tags</p></div>
        <div class="toolbar-actions">
            <label for="paper-size">Paper:</label>
            <select id="paper-size">
                <option value="8.5in 13in">Long Bond / Sticker (8.5 × 13 in)</option>
                <option value="A4">A4</option>
                <option value="letter">Letter</option>
            </select>
            <button type="button" onclick="printTags()">Print {{ $labels->count() }} Tags</button>
        </div>
    </header>
    <div class="sheet-note no-print"><strong>Print setup:</strong> Use portrait orientation and 100% / Actual Size. Each tag is 63 &times; 58 mm with a large 30 mm QR code, arranged in three columns. Print a test sheet and scan it before loading adhesive paper.</div>
    <main class="tag-sheet">
        @foreach($labels as $label)
            @php($asset = $label['asset'])
            <article class="asset-label">
                <div class="label-brand">
                    <img class="label-logo" src="{{ asset('images/companies/yatira.webp') }}" alt="Yatira logo">
                    <div class="label-company"><strong>Yatira Construction</strong><small>Fixed Asset</small></div>
                    <span class="label-status">{{ $asset->status }}</span>
                </div>
                <div class="label-title"><span class="asset-name">{{ $asset->asset_name }}</span><span>-</span><span class="asset-meta">{{ $asset->category }}</span></div>
                <div class="label-qr"><img src="{{ $label['qr_code'] }}" alt="QR code for {{ $asset->asset_code }}"></div>
                <div class="asset-code">{{ $asset->asset_code }}</div>
                <div class="label-footer">SCAN FOR DETAILS &amp; ATTACHED FILES</div>
            </article>
        @endforeach
    </main>
    <script>
        function printTags() {
            const size = document.getElementById('paper-size').value;
            let style = document.getElementById('dynamic-page-size');
            if (!style) { style = document.createElement('style'); style.id = 'dynamic-page-size'; document.head.appendChild(style); }
            style.textContent = `@page { size: ${size} portrait; margin: 8mm; }`;
            window.print();
        }
    </script>
</body>
</html>
