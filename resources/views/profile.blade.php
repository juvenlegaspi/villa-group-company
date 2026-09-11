@extends('layouts.app')

@section('title', 'Profile Management | Villa Group')

@section('content')
@php
    $user = Auth::user();
@endphp
<section class="min-h-[calc(100svh-74px)] bg-gradient-to-br from-white to-slate-100 px-4 py-6 sm:px-7 lg:px-12">
    <div class="mx-auto max-w-4xl">
        <article class="relative overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
            <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-villa-gold via-villa-600 to-villa-900"></div>
            <header class="border-b border-slate-100 px-5 py-6 sm:px-8">
                <p class="mb-1 text-xs font-extrabold uppercase tracking-[.14em] text-villa-600">Account settings</p>
                <h1 class="text-2xl font-extrabold tracking-tight text-slate-950 sm:text-3xl">Profile Management</h1>
                <p class="mt-1 text-sm text-slate-500">Manage your personal information and profile photo.</p>
            </header>

            <div class="p-5 sm:p-8">
                @if(session('success'))
                    <div class="alert alert-success mb-6" role="status"><i class="bi bi-check2-circle mr-2"></i>{{ session('success') }}</div>
                @endif
                @if($errors->any())
                    <div class="alert alert-danger mb-6" role="alert"><p class="mb-1 font-extrabold">Please check your profile details.</p><ul class="m-0 list-disc space-y-1 pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
                @endif

                <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data">
                    @csrf

                    <section class="mb-8 flex flex-col gap-5 rounded-2xl border border-slate-200 bg-slate-50/70 p-5 sm:flex-row sm:items-center sm:p-6">
                        <div class="relative h-28 w-28 shrink-0 overflow-hidden rounded-full border-4 border-white bg-villa-100 shadow-md ring-1 ring-slate-200">
                            @if($user->avatar_path)
                                <img id="avatarPreview" class="h-full w-full object-cover" src="{{ route('profile.avatar', ['v' => optional($user->updated_at)->timestamp]) }}" alt="{{ $user->name }} profile photo">
                                <svg id="avatarPlaceholder" class="hidden h-full w-full p-5 text-villa-600" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 22a8 8 0 0 1 16 0"/></svg>
                            @else
                                <img id="avatarPreview" class="hidden h-full w-full object-cover" alt="Profile photo preview">
                                <svg id="avatarPlaceholder" class="h-full w-full p-5 text-villa-600" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 22a8 8 0 0 1 16 0"/></svg>
                            @endif
                        </div>
                        <div class="min-w-0 flex-1">
                            <h2 class="text-base font-extrabold text-slate-900">Profile Avatar</h2>
                            <p class="mt-1 text-xs leading-5 text-slate-500">Upload a clear JPG, PNG, GIF, or WebP image. Maximum file size is 2 MB.</p>
                            <div class="mt-4 flex flex-wrap items-center gap-3">
                                <label class="inline-flex min-h-10 cursor-pointer items-center gap-2 rounded-xl bg-villa-700 px-4 text-sm font-extrabold text-white shadow-sm transition hover:bg-villa-900 focus-within:ring-4 focus-within:ring-villa-100">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 16V4M7 9l5-5 5 5M5 14v5h14v-5"/></svg>
                                    <span>Upload Photo</span>
                                    <input id="avatarInput" class="sr-only" type="file" name="avatar" accept="image/jpeg,image/png,image/gif,image/webp">
                                </label>
                                <span id="avatarFileName" class="max-w-xs truncate text-xs font-semibold text-slate-500">No new photo selected</span>
                            </div>
                            @error('avatar')<p class="field-error">{{ $message }}</p>@enderror
                        </div>
                    </section>

                    <section>
                        <div class="mb-5 flex items-center gap-3"><span class="h-px flex-1 bg-slate-200"></span><h2 class="shrink-0 text-sm font-extrabold uppercase tracking-wider text-slate-500">Personal Information</h2><span class="h-px flex-1 bg-slate-200"></span></div>
                        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                            <div><label class="form-label" for="name">First Name</label><input id="name" type="text" name="name" value="{{ old('name', $user->name) }}" class="form-control @error('name') is-invalid @enderror" maxlength="255" autocomplete="given-name" required>@error('name')<p class="field-error">{{ $message }}</p>@enderror</div>
                            <div><label class="form-label" for="lastname">Last Name</label><input id="lastname" type="text" name="lastname" value="{{ old('lastname', $user->lastname) }}" class="form-control @error('lastname') is-invalid @enderror" maxlength="255" autocomplete="family-name" required>@error('lastname')<p class="field-error">{{ $message }}</p>@enderror</div>
                            <div class="sm:col-span-2"><label class="form-label" for="email">Email Address</label><input id="email" type="email" name="email" value="{{ old('email', $user->email) }}" class="form-control @error('email') is-invalid @enderror" maxlength="255" autocomplete="email" required>@error('email')<p class="field-error">{{ $message }}</p>@enderror</div>
                            <div class="sm:col-span-2"><label class="form-label" for="cell_number">Contact Number</label><input id="cell_number" type="tel" name="cell_number" value="{{ old('cell_number', $user->cell_number) }}" class="form-control @error('cell_number') is-invalid @enderror" maxlength="30" autocomplete="tel" placeholder="Enter contact number">@error('cell_number')<p class="field-error">{{ $message }}</p>@enderror</div>
                        </div>
                    </section>

                    <footer class="mt-8 flex flex-col-reverse justify-between gap-3 border-t border-slate-100 pt-5 sm:flex-row sm:items-center">
                        <a href="{{ route('password.change') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 bg-white px-4 text-sm font-extrabold text-slate-700 no-underline shadow-sm hover:bg-slate-50 hover:text-villa-800">Password Management</a>
                        <button class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-villa-700 px-5 text-sm font-extrabold text-white shadow-md shadow-villa-900/15 hover:bg-villa-900 focus:outline-none focus:ring-4 focus:ring-villa-100" type="submit"><i class="bi bi-check2-circle"></i>Update Profile</button>
                    </footer>
                </form>
            </div>
        </article>
    </div>
</section>
@endsection

@push('scripts')
<script>
(() => {
    const input = document.getElementById('avatarInput');
    const preview = document.getElementById('avatarPreview');
    const placeholder = document.getElementById('avatarPlaceholder');
    const fileName = document.getElementById('avatarFileName');
    let previewUrl;

    input.addEventListener('change', () => {
        const file = input.files?.[0];
        if (!file) return;
        if (previewUrl) URL.revokeObjectURL(previewUrl);
        previewUrl = URL.createObjectURL(file);
        preview.src = previewUrl;
        preview.classList.remove('hidden');
        placeholder.classList.add('hidden');
        fileName.textContent = file.name;
    });

    window.addEventListener('beforeunload', () => previewUrl && URL.revokeObjectURL(previewUrl));
})();
</script>
@endpush
