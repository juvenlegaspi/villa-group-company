@extends('layouts.app')
@section('title', 'Document preview | '.$log->log_no)
@section('content')
<main class="mx-auto max-w-6xl p-4 sm:p-6">
    <header class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white p-4">
        <div class="min-w-0"><p class="text-xs font-bold text-slate-500">{{ $log->log_no }} · Supporting document</p><h1 class="break-all text-lg font-black text-slate-900">{{ $attachment->original_name }}</h1></div>
        <div class="flex gap-2"><a class="btn btn-light" href="{{ route('jmv.operations.production.show', $log) }}">Back to log</a><a class="btn btn-primary" href="{{ route('jmv.operations.production.attachments.show', [$log, $attachment]) }}">Download</a></div>
    </header>
    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white p-3">
        @if($previewType === 'pdf')
            <iframe title="PDF preview: {{ $attachment->original_name }}" src="{{ route('jmv.operations.production.attachments.show', [$log, $attachment, 'inline' => 1]) }}" class="w-full border-0" style="height:75vh"></iframe>
            <p class="mt-2 text-xs text-slate-500">If the preview does not appear, use Download to open the PDF on your device.</p>
        @elseif($previewType === 'image')
            <img src="{{ route('jmv.operations.production.attachments.show', [$log, $attachment, 'inline' => 1]) }}" alt="{{ $attachment->original_name }}" class="mx-auto h-auto max-w-full" style="max-height:80vh;object-fit:contain">
        @else
            <p class="p-6 text-sm text-slate-600">This file type cannot be previewed here. Use Download to open it in Word, Excel, or the appropriate application.</p>
        @endif
    </section>
</main>
@endsection
