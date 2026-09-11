@extends('layouts.app')

@section('title', 'User Management | Villa Group')

@section('content')
<section class="min-h-[calc(100svh-74px)] bg-gradient-to-br from-slate-50 via-white to-villa-50 px-4 py-6 sm:px-7 lg:px-12">
    <div class="mx-auto max-w-7xl">
        <header class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="mb-1 text-xs font-extrabold uppercase tracking-[.16em] text-villa-600">Administration</p>
                <h1 class="m-0 text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">User Management</h1>
                <p class="mt-1 text-sm text-slate-500">Manage employee companies, departments, positions, access profiles, and approval authority.</p>
            </div>
            <div class="flex flex-wrap gap-2"><a href="{{ route('users.organization.index') }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 text-sm font-extrabold text-villa-700 no-underline shadow-sm"><i class="bi bi-diagram-3"></i>Organization Setup</a><a href="{{ route('users.create') }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-villa-700 px-4 text-sm font-extrabold text-white no-underline shadow-lg shadow-villa-700/20 hover:bg-villa-800 focus:outline-none focus:ring-4 focus:ring-villa-100"><i class="bi bi-person-plus"></i>Add User</a></div>
        </header>

        @if(session('temporary_password'))
            <div class="mb-5 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900" role="alert">
                <p class="mb-1 font-extrabold"><i class="bi bi-key me-1"></i> Temporary password</p>
                <div class="flex flex-wrap items-center gap-3"><code class="rounded-lg bg-white px-3 py-2 font-bold text-slate-900 shadow-sm">{{ session('temporary_password') }}</code><span>The user must change this password on first login.</span></div>
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-5 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm font-semibold text-rose-800" role="alert">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="GET" action="{{ route('users.index') }}" class="mb-5 flex max-w-2xl gap-2">
            <label class="relative min-w-0 flex-1">
                <span class="sr-only">Search users</span>
                <i class="bi bi-search pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                <input type="search" name="search" value="{{ $search ?? '' }}" class="h-12 w-full rounded-xl border border-slate-300 bg-white pl-11 pr-4 text-sm outline-none shadow-sm placeholder:text-slate-400 focus:border-villa-500 focus:ring-4 focus:ring-villa-100" placeholder="Search name, username or email">
            </label>
            <button class="inline-flex h-12 items-center justify-center rounded-xl bg-villa-700 px-5 text-sm font-extrabold text-white hover:bg-villa-800 focus:outline-none focus:ring-4 focus:ring-villa-100">Search</button>
            @if($search)
                <a href="{{ route('users.index') }}" class="inline-flex h-12 items-center justify-center rounded-xl border border-slate-300 bg-white px-4 text-sm font-bold text-slate-600 no-underline hover:bg-slate-50">Clear</a>
            @endif
        </form>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl shadow-slate-200/50">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[920px] border-collapse text-left">
                    <thead class="bg-slate-50 text-xs font-extrabold uppercase tracking-wider text-slate-500">
                        <tr><th class="px-5 py-4">User</th><th class="px-4 py-4">Assignment</th><th class="px-4 py-4">Access</th><th class="px-4 py-4">Contact</th><th class="px-4 py-4">Status</th><th class="px-5 py-4 text-right">Actions</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                    @forelse($users as $user)
                        <tr class="transition hover:bg-slate-50/80">
                            <td class="px-5 py-4"><div class="flex items-center gap-3"><span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-villa-100 font-black text-villa-700">{{ strtoupper(substr($user->name, 0, 1).substr($user->lastname, 0, 1)) }}</span><div><p class="mb-0 font-extrabold text-slate-900">{{ $user->name }} {{ $user->lastname }}</p><p class="mb-0 text-xs text-slate-500">{{ '@'.$user->username }}</p></div></div></td>
                            <td class="px-4 py-4"><p class="mb-0 text-sm font-bold text-slate-700">{{ optional($user->division)->name ?? 'No company' }}</p><p class="mb-0 text-xs text-slate-500">{{ optional($user->department)->name ?? 'No department' }}</p><p class="mb-0 mt-1 text-xs font-bold text-villa-700">{{ optional($user->position)->name ?? 'No position assigned' }}</p></td>
                            <td class="px-4 py-4"><span class="inline-flex rounded-full bg-villa-50 px-2.5 py-1 text-xs font-extrabold text-villa-700">{{ optional($user->accessRole)->name ?? strtoupper($user->role) }}</span>@if($user->approvalAuthorities->where('is_active',true)->isNotEmpty())<span class="ml-1 inline-flex rounded-full bg-violet-50 px-2.5 py-1 text-xs font-extrabold text-violet-700">Approver · {{ $user->approvalAuthorities->where('is_active',true)->count() }}</span>@endif</td>
                            <td class="px-4 py-4"><p class="mb-0 text-sm text-slate-700">{{ $user->email }}</p><p class="mb-0 text-xs text-slate-500">{{ $user->cell_number ?: 'No contact number' }}</p></td>
                            <td class="px-4 py-4"><span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-extrabold {{ $user->status ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}"><span class="h-1.5 w-1.5 rounded-full {{ $user->status ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>{{ $user->status ? 'Active' : 'Inactive' }}</span></td>
                            <td class="px-5 py-4"><div class="flex justify-end gap-2"><a href="{{ route('users.edit', $user->id) }}" class="grid h-9 w-9 place-items-center rounded-lg border border-slate-200 bg-white text-villa-700 no-underline hover:border-villa-400 hover:bg-villa-50" title="Edit user" aria-label="Edit {{ $user->name }}"><i class="bi bi-pencil"></i></a><form method="POST" action="{{ route('users.reset-password', $user->id) }}" class="reset-form">@csrf<button type="button" class="btn-reset grid h-9 w-9 place-items-center rounded-lg border border-amber-200 bg-amber-50 text-amber-700 hover:bg-amber-100" title="Reset password" aria-label="Reset password for {{ $user->name }}"><i class="bi bi-key"></i></button></form>@if($user->status && auth()->id() !== $user->id)<form method="POST" action="{{ route('users.destroy', $user->id) }}" class="deactivate-form">@csrf<button type="button" class="btn-deactivate grid h-9 w-9 place-items-center rounded-lg border border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100" title="Deactivate user" aria-label="Deactivate {{ $user->name }}"><i class="bi bi-person-x"></i></button></form>@endif</div></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-16 text-center"><i class="bi bi-people block text-4xl text-slate-300"></i><p class="mb-0 mt-3 font-bold text-slate-600">No users found.</p></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if($users->hasPages())<div class="border-t border-slate-100 px-5 py-4">{{ $users->links() }}</div>@endif
        </div>
    </div>
</section>
@endsection

@push('scripts')
<script>
document.querySelectorAll('.btn-deactivate').forEach(button => button.addEventListener('click', function () {
    const form = this.closest('.deactivate-form');
    VillaDialog.confirm({ title: 'Deactivate this user?', text: 'The account will be signed out and blocked from logging in. Historical records will be preserved.', tone: 'danger', confirmText: 'Deactivate user' }).then(confirmed => confirmed && form.submit());
}));
document.querySelectorAll('.btn-reset').forEach(button => button.addEventListener('click', function () {
    const form = this.closest('.reset-form');
    VillaDialog.confirm({ title: 'Reset password?', text: 'A new one-time temporary password will be generated.', confirmText: 'Reset password' }).then(confirmed => confirmed && form.submit());
}));
</script>
@endpush
