<section class="mb-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
    <div class="mb-3 flex flex-wrap items-center justify-between gap-3"><h2 class="m-0 text-sm font-black">Tenant Email &amp; SMS</h2><a href="{{ route('haruhay.billing.show', $billing) }}" class="text-xs font-bold text-teal-800">Refresh status</a></div>
    <p class="mb-3 text-xs text-slate-500">Notifications are prepared when the bill is issued, after applying available credit. Submitted means accepted by the provider; inbox or handset delivery is not confirmed.</p>
    @forelse($billing->deliveries as $delivery)
        <div class="flex flex-wrap items-start justify-between gap-2 border-t border-slate-100 py-3 text-xs"><div><strong>{{ strtoupper($delivery->channel) }}</strong><span class="ml-2 text-slate-500">{{ $delivery->recipient ?: 'No recipient on tenant record' }}</span><p class="mb-0 mt-1 text-slate-500">{{ $delivery->status_note ?: 'Waiting for the notification sender.' }}</p></div><div class="text-right"><span class="font-bold {{ $delivery->status === 'Submitted' ? 'text-emerald-700' : ($delivery->status === 'Pending' ? 'text-teal-700' : 'text-amber-700') }}">{{ $delivery->status === 'NeedsReview' ? 'Needs review' : $delivery->status }}</span>@if($delivery->submitted_at)<p class="mb-0 mt-1 text-slate-400">{{ $delivery->submitted_at->format('M d, Y g:i A') }}</p>@endif</div></div>
    @empty
        <p class="mb-0 text-xs text-slate-500">No notification was queued for this earlier bill. Existing bills are not sent retroactively.</p>
    @endforelse
    @if($canManage && $billing->deliveries->contains('status', 'Blocked'))
        <form method="POST" action="{{ route('haruhay.billing.notifications.retry', $billing) }}" class="mt-2">@csrf<button class="btn bg-teal-800 text-xs text-white">Retry blocked notifications</button><p class="mb-0 mt-2 text-xs text-slate-500">Correct the tenant contact or sender configuration first. Submitted messages are never resent by this button.</p></form>
    @endif
    <div class="mt-4 border-t border-slate-100 pt-3"><h3 class="mb-2 text-xs font-bold">Upcoming Due Date SMS Reminder</h3><p class="mb-2 text-xs text-slate-500">One reminder within {{ app(\App\Services\HaruhayDueReminderService::class)->leadDays() }} days before the due date for issued bills with an unpaid balance. Paid bills are checked again before sending. Notifications send between 9 AM and 6 PM, Manila time.</p>
    @forelse($billing->dueReminders as $reminder)
        <div class="border-t border-slate-100 py-2 text-xs"><strong>{{ $reminder->status === 'NeedsReview' ? 'Needs review' : $reminder->status }}</strong><span class="ml-2 text-slate-500">Due {{ $reminder->due_on->format('M d, Y') }} @if($reminder->submitted_at)&middot; Submitted {{ $reminder->submitted_at->format('M d, Y g:i A') }}@endif</span><p class="mb-0 mt-1 text-slate-500">{{ $reminder->status_note ?: 'Waiting for the reminder sender.' }}</p></div>
    @empty<p class="mb-0 text-xs text-slate-400">No upcoming-due reminder queued for this bill yet.</p>@endforelse
    </div>
</section>
