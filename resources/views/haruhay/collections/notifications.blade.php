<section class="mb-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
<div class="mb-3 flex flex-wrap items-center justify-between gap-3"><h2 class="m-0 text-sm font-black">Payment Confirmation Email &amp; SMS</h2><a href="{{ route('haruhay.collections.show', $collection) }}" class="text-xs font-bold text-teal-800">Refresh status</a></div>
<p class="mb-3 text-xs text-slate-500">The email includes transaction details and an attached PDF receipt. Submitted means accepted by the provider; inbox or handset delivery is not confirmed.</p>
@forelse($collection->deliveries as $delivery)
<div class="flex flex-wrap items-start justify-between gap-2 border-t border-slate-100 py-3 text-xs"><div><strong>{{ strtoupper($delivery->channel) }}</strong><span class="ml-2 text-slate-500">{{ $delivery->recipient ?: 'No recipient on tenant record' }}</span><p class="mb-0 mt-1 text-slate-500">{{ $delivery->status_note ?: 'Waiting for the notification sender.' }}</p></div><div class="text-right"><strong class="{{ $delivery->status === 'Submitted' ? 'text-emerald-700' : 'text-amber-700' }}">{{ $delivery->status === 'NeedsReview' ? 'Needs review' : $delivery->status }}</strong>@if($delivery->submitted_at)<p class="mb-0 mt-1 text-slate-400">{{ $delivery->submitted_at->format('M d, Y g:i A') }}</p>@endif</div></div>
@empty<p class="mb-0 text-xs text-slate-500">No confirmation was queued for this earlier receipt. Existing receipts are not sent retroactively.</p>@endforelse
@if($canManage && !$collection->voided_at && $collection->deliveries->contains('status', 'Blocked'))
<form method="POST" action="{{ route('haruhay.collections.notifications.retry', $collection) }}" class="mt-2">@csrf<button class="btn bg-teal-800 text-xs text-white">Retry blocked notifications</button><p class="mb-0 mt-2 text-xs text-slate-500">Correct tenant contact details or sender configuration first. Submitted messages will not be resent.</p></form>
@endif
</section>
