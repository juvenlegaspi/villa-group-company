<?php

namespace App\Http\Controllers;

use App\Models\Division;
use App\Models\ShippingCalendarAttachment;
use App\Models\ShippingCalendarAudit;
use App\Models\ShippingCalendarEvent;
use App\Models\ShippingCalendarOccurrenceCompletion;
use App\Models\ShippingCalendarOccurrenceOverride;
use App\Models\User;
use App\Models\Vessel;
use App\Notifications\CalendarEventNotification;
use App\Services\ShippingChecklistReminderService;
use App\Services\VesselAccessService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ShippingCalendarController extends Controller
{
    private const RECURRENCES = ['daily', 'weekly', 'monthly'];

    private const CHECKLIST_TYPES = ['routine', 'scheduled', 'one_time', 'preventive_maintenance'];

    private const OPERATIONS_MANAGER_CODES = ['operations-manager', 'operation-manager', 'marine-operations-manager'];

    public function __construct(
        private VesselAccessService $vesselAccess,
        private ShippingChecklistReminderService $checklistReminders,
    ) {}

    public function index()
    {
        $user = auth()->user();
        $divisionId = $this->shippingDivisionId();

        $vessels = $this->vesselAccess->scopeAccessible(Vessel::query(), $user)
            ->orderBy('vessel_name')->get(['id', 'vessel_name']);

        return view('shipping.calendar.index', [
            'vessels' => $vessels,
            'canCreateChecklist' => $this->canCreateChecklist($user) && $vessels->isNotEmpty(),
        ]);
    }

    public function events(Request $request): JsonResponse
    {
        $data = $request->validate([
            'start' => 'required|date',
            'end' => 'required|date|after_or_equal:start',
            'vessel_id' => 'required|integer|exists:vessels,id',
        ]);
        $this->vesselAccess->authorize(auth()->user(), (int) $data['vessel_id']);
        $start = Carbon::parse($data['start'])->startOfDay();
        $end = Carbon::parse($data['end'])->endOfDay();

        $events = $this->accessibleQuery(auth()->user())->where('vessel_id', $data['vessel_id'])
            ->with(['creator:id,name,lastname', 'department:id,name', 'vessel:id,vessel_name', 'occurrenceCompletions', 'occurrenceOverrides'])
            ->where('starts_at', '<=', $end)
            ->where(function (Builder $query) use ($start): void {
                $query->where('ends_at', '>=', $start)
                    ->orWhere(function (Builder $recurring) use ($start): void {
                        $recurring->whereNotNull('recurrence_frequency')
                            ->where(fn (Builder $until) => $until->whereNull('recurrence_ends_on')->orWhereDate('recurrence_ends_on', '>=', $start));
                    });
            })->get();

        return response()->json($events->flatMap(fn (ShippingCalendarEvent $event) => $this->occurrences($event, $start, $end))->values())
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    public function show(Request $request, ShippingCalendarEvent $event): JsonResponse
    {
        $this->authorizeView($event);
        $requestedOccurrence = $request->validate(['occurrence_starts_at' => 'nullable|date'])['occurrence_starts_at'] ?? null;
        $occurrenceStart = $this->resolveOccurrenceStart($event, $requestedOccurrence);
        $override = $event->occurrenceOverrides()->where('occurrence_starts_at', $occurrenceStart)->first();
        $occurrenceCompleted = $event->status === 'completed'
            || ($event->recurrence_frequency && $event->occurrenceCompletions()->where('occurrence_starts_at', $occurrenceStart)->exists());
        $occurrenceStatus = $occurrenceCompleted ? 'completed' : ($override?->status ?? $event->status);
        $occurrenceScheduled = $occurrenceStatus === 'scheduled';
        $event->load([
            'creator:id,name,lastname', 'vessel:id,vessel_name,captain_id', 'vessel.captain:id,name,lastname,email,cell_number',
            'audits.user:id,name,lastname',
        ]);
        $attachments = $event->attachments()->where('occurrence_starts_at', $occurrenceStart)
            ->with('uploader:id,name,lastname')->latest('created_at')->get();
        $occurrenceKey = $event->id.'_'.$occurrenceStart->format('Y-m-d_H-i-s');
        $payload = $this->eventPayload($event, $occurrenceStart, $override);
        $canPrepareAttachment = in_array($occurrenceStatus, ['scheduled', 'completed'], true)
            && $this->canUploadChecklist($event, auth()->user());
        $canUploadAttachment = $canPrepareAttachment
            && now()->greaterThanOrEqualTo(Carbon::parse($payload['ends_at']));

        return response()->json([
            'event' => $payload,
            'recipients' => $this->checklistReminders->recipients($event)->map(fn (User $user) => [
                'id' => $user->id, 'name' => trim($user->name.' '.$user->lastname),
                'email' => $user->email, 'cell_number' => $user->cell_number,
            ]),
            'audits' => $event->audits->map(fn ($audit) => [
                'action' => str($audit->action)->replace('_', ' ')->title()->toString(),
                'user' => $audit->user ? trim($audit->user->name.' '.$audit->user->lastname) : 'System',
                'date' => $audit->created_at?->format('M d, Y g:i A'),
            ]),
            'attachments' => $attachments->map(fn (ShippingCalendarAttachment $attachment) => [
                'id' => $attachment->id,
                'name' => $attachment->original_name,
                'occurrence_starts_at' => $attachment->occurrence_starts_at?->toIso8601String(),
                'occurrence_key' => $event->id.'_'.$attachment->occurrence_starts_at?->format('Y-m-d_H-i-s'),
                'mime_type' => $attachment->mime_type,
                'size_bytes' => $attachment->size_bytes,
                'uploaded_by' => $attachment->uploader ? trim($attachment->uploader->name.' '.$attachment->uploader->lastname) : 'System',
                'uploaded_at' => $attachment->created_at?->format('M d, Y g:i A'),
                'download_url' => route('shipping.calendar.events.attachments.download', [$event, $attachment]),
                'delete_url' => route('shipping.calendar.events.attachments.destroy', [$event, $attachment]),
            ]),
            'can_edit' => $occurrenceScheduled && $this->canManage($event, auth()->user()),
            'can_complete' => $occurrenceScheduled && $this->canManage($event, auth()->user()),
            'can_upload_attachment' => $canUploadAttachment,
            'can_prepare_attachment' => $canPrepareAttachment,
            'attachment_available_at' => Carbon::parse($payload['ends_at'])->format('M d, Y g:i A'),
            'attachment_upload_url' => route('shipping.calendar.events.attachments.store', $event),
            'can_remove_attachments' => auth()->user()->isSystemAdministrator() && $attachments->isNotEmpty(),
            'attachment_remove_url' => route('shipping.calendar.events.attachments.destroy-scope', $event),
            'can_delete' => auth()->user()->isSystemAdministrator(),
            'occurrence_starts_at' => $occurrenceStart->toIso8601String(),
            'occurrence_key' => $occurrenceKey,
            'occurrence_status' => $occurrenceStatus,
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate(['attachments' => 'prohibited']);
        $data = $this->validatedEvent($request);
        $this->authorizeCreate(auth()->user());
        $this->vesselAccess->authorize(auth()->user(), (int) $data['vessel_id']);

        $event = DB::transaction(function () use ($data): ShippingCalendarEvent {
            $event = ShippingCalendarEvent::create([
                ...$data,
                'division_id' => $this->shippingDivisionId(),
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);
            $this->audit($event, 'created', ['title' => $event->title]);

            return $event;
        });

        $this->notifyRecipients($event, 'New vessel checklist scheduled', "{$event->title} was scheduled for {$event->vessel?->vessel_name}.");

        return response()->json(['message' => 'Checklist scheduled successfully.', 'event_id' => $event->id], 201);
    }

    public function storeAttachment(Request $request, ShippingCalendarEvent $event): JsonResponse
    {
        $this->authorizeView($event);
        abort_unless($this->canUploadChecklist($event, auth()->user()), 403, 'Only the Operations Manager, system administrator, or assigned Vessel Captain can upload a completed checklist.');
        $selection = $request->validate([
            'occurrence_starts_at' => 'required|date',
            'occurrence_key' => 'required|string|max:100',
        ]);
        $occurrenceStart = $this->resolveOccurrenceStart($event, $selection['occurrence_starts_at']);
        $expectedOccurrenceKey = $event->id.'_'.$occurrenceStart->format('Y-m-d_H-i-s');
        abort_unless(
            hash_equals($expectedOccurrenceKey, $selection['occurrence_key']),
            422,
            'The selected checklist date or time changed. Reopen that exact schedule and upload again.'
        );
        $override = $event->occurrenceOverrides()->where('occurrence_starts_at', $occurrenceStart)->first();
        $completed = $event->status === 'completed'
            || ($event->recurrence_frequency && $event->occurrenceCompletions()->where('occurrence_starts_at', $occurrenceStart)->exists());
        $status = $completed ? 'completed' : ($override?->status ?? $event->status);
        abort_unless(in_array($status, ['scheduled', 'completed'], true), 422, 'A checklist cannot be uploaded to a cancelled or deleted date.');

        $payload = $this->eventPayload($event, $occurrenceStart, $override);
        abort_unless(now()->greaterThanOrEqualTo(Carbon::parse($payload['ends_at'])), 422, 'Upload the completed checklist after the expected schedule end time.');
        $files = $this->validatedAttachments($request);
        abort_if($files === [], 422, 'Select at least one completed checklist file.');

        $storedPaths = [];
        try {
            DB::transaction(function () use ($event, $files, &$storedPaths, $occurrenceStart): void {
                $this->storeAttachments($event, $files, $storedPaths, $occurrenceStart);
            });
        } catch (\Throwable $exception) {
            if ($storedPaths) {
                Storage::disk('local')->delete($storedPaths);
            }
            throw $exception;
        }

        return response()->json(['message' => 'Completed checklist uploaded for this selected date.'], 201);
    }

    public function update(Request $request, ShippingCalendarEvent $event): JsonResponse
    {
        $this->authorizeManage($event);
        $request->validate(['attachments' => 'prohibited']);
        $data = $this->validatedEvent($request);
        if ($event->recurrence_frequency) {
            $requested = $request->validate(['occurrence_starts_at' => 'required|date'])['occurrence_starts_at'];
            $occurrenceStart = $this->resolveOccurrenceStart($event, $requested);
            abort_unless((int) $data['vessel_id'] === (int) $event->vessel_id, 422, 'A single repeated date cannot be moved to another vessel.');
            if (! Carbon::parse($data['starts_at'])->isSameDay($occurrenceStart)) {
                throw ValidationException::withMessages(['starts_at' => 'Keep the edited checklist on the selected calendar date.']);
            }
            $existing = $event->occurrenceOverrides()->where('occurrence_starts_at', $occurrenceStart)->first();
            abort_unless(($existing?->status ?? 'scheduled') === 'scheduled', 422, 'Only a scheduled checklist date can be edited.');
            $values = Arr::only($data, ['title', 'checklist_type', 'description', 'location', 'starts_at', 'ends_at', 'color', 'reminder_minutes']);
            $event->occurrenceOverrides()->updateOrCreate(
                ['occurrence_starts_at' => $occurrenceStart],
                [...$values, 'has_changes' => true, 'status' => 'scheduled', 'updated_by' => auth()->id()],
            );
            $this->audit($event, 'occurrence_updated', ['occurrence_starts_at' => $occurrenceStart->format('Y-m-d H:i:s'), 'after' => $values]);
            $message = 'This checklist date was updated successfully.';
        } else {
            $this->vesselAccess->authorize(auth()->user(), (int) $data['vessel_id']);
            $before = $event->only(array_keys($data));
            $event->update([...$data, 'updated_by' => auth()->id()]);
            $this->audit($event, 'updated', ['before' => $before, 'after' => $event->fresh()->only(array_keys($data))]);
            $message = 'Checklist updated successfully.';
        }
        $this->notifyRecipients($event, 'Vessel checklist schedule updated', "{$event->title} for {$event->vessel?->vessel_name} has been updated.");

        return response()->json(['message' => $message]);
    }

    public function downloadAttachment(ShippingCalendarEvent $event, ShippingCalendarAttachment $attachment)
    {
        $this->authorizeView($event);
        abort_unless((int) $attachment->event_id === (int) $event->id, 404);
        abort_unless(Storage::disk('local')->exists($attachment->path), 404, 'The checklist attachment could not be found.');

        return Storage::disk('local')->download($attachment->path, $attachment->original_name);
    }

    public function destroyAttachment(ShippingCalendarEvent $event, ShippingCalendarAttachment $attachment): JsonResponse
    {
        abort_unless(auth()->user()->isSystemAdministrator(), 403, 'Only a system administrator can remove checklist attachments.');
        $this->authorizeView($event);
        abort_unless((int) $attachment->event_id === (int) $event->id, 404);
        $path = $attachment->path;

        DB::transaction(function () use ($event, $attachment): void {
            $this->audit($event, 'attachment_deleted', [
                'attachment_id' => $attachment->id,
                'name' => $attachment->original_name,
                'size_bytes' => $attachment->size_bytes,
            ]);
            $attachment->delete();
        });
        if ($path && Storage::disk('local')->exists($path) && ! Storage::disk('local')->delete($path)) {
            report(new \RuntimeException("Unable to remove orphaned calendar attachment: {$path}"));
        }

        return response()->json(['message' => 'Checklist attachment removed.']);
    }

    public function destroyAttachments(Request $request, ShippingCalendarEvent $event): JsonResponse
    {
        abort_unless(auth()->user()->isSystemAdministrator(), 403, 'Only a system administrator can remove checklist attachments.');
        $this->authorizeView($event);
        $data = $request->validate([
            'scope' => ['required', Rule::in(['occurrence', 'series'])],
            'occurrence_starts_at' => 'nullable|date',
        ]);

        $query = $event->attachments();
        $occurrenceStart = null;
        if ($data['scope'] === 'occurrence') {
            $occurrenceStart = $this->resolveOccurrenceStart($event, $data['occurrence_starts_at'] ?? null);
            $query->where('occurrence_starts_at', $occurrenceStart);
        }
        $attachments = $query->get();
        abort_if($attachments->isEmpty(), 422, $data['scope'] === 'occurrence'
            ? 'There are no attachments on this selected checklist date.'
            : 'There are no attachments in this checklist schedule.');
        $paths = $attachments->pluck('path')->filter()->values()->all();

        DB::transaction(function () use ($event, $attachments, $data, $occurrenceStart): void {
            $this->audit($event, $data['scope'] === 'occurrence' ? 'occurrence_attachments_removed' : 'series_attachments_removed', [
                'occurrence_starts_at' => $occurrenceStart?->format('Y-m-d H:i:s'),
                'attachment_count' => $attachments->count(),
                'attachment_names' => $attachments->pluck('original_name')->values()->all(),
            ]);
            $event->attachments()->whereKey($attachments->pluck('id'))->delete();
        });
        if ($paths) {
            Storage::disk('local')->delete($paths);
        }

        $scopeLabel = $data['scope'] === 'occurrence' ? 'selected checklist date' : 'entire checklist schedule';

        return response()->json(['message' => $attachments->count()." attachment(s) removed from the {$scopeLabel}."]);
    }

    public function move(Request $request, ShippingCalendarEvent $event): JsonResponse
    {
        $this->authorizeManage($event);
        $data = $request->validate([
            'starts_at' => 'required|date',
            'ends_at' => 'required|date|after:starts_at',
        ]);
        $before = $event->only(['starts_at', 'ends_at']);
        $event->update([...$data, 'updated_by' => auth()->id()]);
        $this->audit($event, 'rescheduled', ['before' => $before, 'after' => $event->fresh()->only(['starts_at', 'ends_at'])]);

        return response()->json(['message' => 'Checklist rescheduled.']);
    }

    public function cancel(Request $request, ShippingCalendarEvent $event): JsonResponse
    {
        $this->authorizeManage($event);
        abort_unless($event->status === 'scheduled', 422, 'Only a scheduled checklist can be cancelled.');
        $requested = $request->validate(['occurrence_starts_at' => 'nullable|date'])['occurrence_starts_at'] ?? null;
        $occurrenceStart = $this->resolveOccurrenceStart($event, $requested);
        if ($event->recurrence_frequency) {
            $override = $event->occurrenceOverrides()->firstOrNew(['occurrence_starts_at' => $occurrenceStart]);
            abort_unless(($override->exists ? $override->status : 'scheduled') === 'scheduled', 422, 'Only a scheduled checklist date can be cancelled.');
            $override->fill(['status' => 'cancelled', 'cancelled_at' => now(), 'cancelled_by' => auth()->id(), 'updated_by' => auth()->id()])->save();
        } else {
            $event->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancelled_by' => auth()->id(), 'updated_by' => auth()->id()]);
        }
        $this->audit($event, 'occurrence_cancelled', ['occurrence_starts_at' => $occurrenceStart->format('Y-m-d H:i:s')]);
        $this->notifyRecipients($event, 'Vessel checklist cancelled', "{$event->title} for {$event->vessel?->vessel_name} on {$occurrenceStart->format('M d, Y g:i A')} has been cancelled.");

        return response()->json(['message' => 'This checklist date was cancelled.']);
    }

    public function complete(Request $request, ShippingCalendarEvent $event): JsonResponse
    {
        $this->authorizeManage($event);
        abort_unless($event->status === 'scheduled', 422, 'Only a scheduled checklist can be completed.');
        $requestedOccurrence = $request->validate(['occurrence_starts_at' => 'nullable|date'])['occurrence_starts_at'] ?? null;
        $occurrenceStart = $this->resolveOccurrenceStart($event, $requestedOccurrence);
        $overrideStatus = $event->occurrenceOverrides()->where('occurrence_starts_at', $occurrenceStart)->value('status');
        abort_unless(! $overrideStatus || $overrideStatus === 'scheduled', 422, 'Only a scheduled checklist date can be completed.');

        if ($event->recurrence_frequency) {
            $completion = ShippingCalendarOccurrenceCompletion::firstOrCreate(
                ['event_id' => $event->id, 'occurrence_starts_at' => $occurrenceStart],
                ['completed_by' => auth()->id(), 'completed_at' => now()],
            );
            abort_unless($completion->wasRecentlyCreated, 422, 'This checklist occurrence is already complete.');
        } else {
            $event->update(['status' => 'completed', 'updated_by' => auth()->id()]);
        }

        $this->audit($event, 'completed', ['occurrence_starts_at' => $occurrenceStart->format('Y-m-d H:i:s')]);
        $this->notifyRecipients($event, 'Vessel checklist completed', "{$event->title} for {$event->vessel?->vessel_name} on {$occurrenceStart->format('M d, Y g:i A')} has been marked complete.");

        return response()->json(['message' => 'This checklist date was marked complete.']);
    }

    public function destroy(Request $request, ShippingCalendarEvent $event): JsonResponse
    {
        abort_unless(auth()->user()->isSystemAdministrator(), 403, 'Only a system administrator can delete a checklist schedule.');
        $this->authorizeView($event);
        $data = $request->validate([
            'scope' => ['nullable', Rule::in(['occurrence', 'series'])],
            'occurrence_starts_at' => 'nullable|date',
        ]);
        $scope = $data['scope'] ?? 'series';
        if ($scope === 'occurrence' && $event->recurrence_frequency) {
            $occurrenceStart = $this->resolveOccurrenceStart($event, $data['occurrence_starts_at'] ?? null);
            $event->occurrenceOverrides()->updateOrCreate(
                ['occurrence_starts_at' => $occurrenceStart],
                ['status' => 'deleted', 'updated_by' => auth()->id()],
            );
            $this->audit($event, 'occurrence_deleted', ['occurrence_starts_at' => $occurrenceStart->format('Y-m-d H:i:s')]);

            return response()->json(['message' => 'Only the selected checklist date was deleted.']);
        }
        $this->audit($event, 'series_deleted');
        $event->delete();

        return response()->json(['message' => 'The entire checklist schedule was deleted.']);
    }

    private function validatedEvent(Request $request): array
    {
        $data = $request->validate([
            'title' => 'required|string|max:180',
            'checklist_type' => ['required', Rule::in(self::CHECKLIST_TYPES)],
            'description' => 'nullable|string|max:5000',
            'location' => 'nullable|string|max:255',
            'starts_at' => 'required|date',
            'ends_at' => 'required|date|after:starts_at',
            'all_day' => 'sometimes|boolean',
            'vessel_id' => 'required|integer|exists:vessels,id',
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'recurrence_frequency' => ['nullable', Rule::in(self::RECURRENCES)],
            'recurrence_interval' => 'nullable|integer|min:1|max:52',
            'recurrence_ends_on' => 'nullable|date',
            'reminder_minutes' => 'required|integer|min:0|max:10080',
        ]);
        $data['all_day'] = $request->boolean('all_day');
        $data['email_reminder'] = true;
        $data['visibility'] = 'vessel';
        $data['department_id'] = null;
        $data['recurrence_interval'] = $data['recurrence_frequency'] ? ($data['recurrence_interval'] ?? 1) : 1;
        if ($data['recurrence_frequency'] && filled($data['recurrence_ends_on'])
            && Carbon::parse($data['recurrence_ends_on'])->endOfDay()->lt(Carbon::parse($data['starts_at']))) {
            throw ValidationException::withMessages(['recurrence_ends_on' => 'The repeat-until date must be on or after the scheduled date.']);
        }
        if (! $data['recurrence_frequency']) {
            $data['recurrence_ends_on'] = null;
        }

        return $data;
    }

    private function validatedAttachments(Request $request): array
    {
        $request->validate([
            'attachments' => 'nullable|array|max:5',
            'attachments.*' => 'file|mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx|max:10240',
        ], [
            'attachments.max' => 'You may upload up to 5 checklist files at a time.',
            'attachments.*.mimes' => 'Checklist attachments must be an image, PDF, Word, or Excel document.',
            'attachments.*.max' => 'Each checklist attachment must not exceed 10 MB.',
        ]);

        return array_values(array_filter($request->file('attachments', [])));
    }

    private function storeAttachments(ShippingCalendarEvent $event, array $files, array &$storedPaths, Carbon $occurrenceStart): void
    {
        $occurrenceAttachments = $event->attachments()->where('occurrence_starts_at', $occurrenceStart);
        $existingCount = (clone $occurrenceAttachments)->count();
        $existingBytes = (int) (clone $occurrenceAttachments)->sum('size_bytes');
        $maximumFiles = max(1, (int) config('services.shipping_calendar.max_attachments', 10));
        $maximumBytes = max(10485760, (int) config('services.shipping_calendar.max_attachment_bytes', 52428800));
        $incomingBytes = array_sum(array_map(fn ($file) => (int) $file->getSize(), $files));
        abort_if($existingCount + count($files) > $maximumFiles, 422, "A checklist may contain no more than {$maximumFiles} attachments.");
        abort_if($existingBytes + $incomingBytes > $maximumBytes, 422, 'The combined checklist attachment size may not exceed 50 MB.');

        foreach ($files as $file) {
            $path = $file->store("shipping-calendar/{$event->id}", 'local');
            if (! $path) {
                throw new \RuntimeException('The checklist attachment could not be stored.');
            }
            $storedPaths[] = $path;
            $attachment = $event->attachments()->create([
                'occurrence_starts_at' => $occurrenceStart,
                'uploaded_by' => auth()->id(),
                'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                'path' => $path,
                'mime_type' => $file->getMimeType(),
                'size_bytes' => $file->getSize(),
            ]);
            $this->audit($event, 'attachment_uploaded', [
                'attachment_id' => $attachment->id,
                'name' => $attachment->original_name,
                'size_bytes' => $attachment->size_bytes,
            ]);
        }
    }

    private function authorizeCreate(User $user): void
    {
        abort_unless($this->canCreateChecklist($user), 403, 'Only an Operations Manager or assigned Vessel Captain can schedule a checklist.');
    }

    private function accessibleQuery(User $user): Builder
    {
        $query = ShippingCalendarEvent::query()->where('division_id', $this->shippingDivisionId())->whereNotNull('vessel_id');
        if ($user->isSystemAdministrator() || $this->isOperationsManager($user)) {
            return $query;
        }
        $vesselIds = $this->vesselAccess->scopeAccessible(Vessel::query(), $user)->pluck('id');

        return $query->whereIn('vessel_id', $vesselIds);
    }

    private function authorizeView(ShippingCalendarEvent $event): void
    {
        abort_unless($this->accessibleQuery(auth()->user())->whereKey($event->id)->exists(), 403, 'You cannot view this calendar event.');
    }

    private function authorizeManage(ShippingCalendarEvent $event): void
    {
        abort_unless($this->canManage($event, auth()->user()), 403, 'Only an Operations Manager, system administrator, or the assigned Captain who created this checklist can change it.');
    }

    private function canManage(ShippingCalendarEvent $event, User $user): bool
    {
        if ($user->isExecutiveViewer()) {
            return false;
        }

        if ($user->isSystemAdministrator() || $this->isOperationsManager($user)) {
            return true;
        }

        return $this->isCaptain($user)
            && (int) $event->division_id === $this->shippingDivisionId()
            && (int) $event->created_by === (int) $user->id
            && $this->vesselAccess->canAccess($user, (int) $event->vessel_id);
    }

    private function canCreateChecklist(User $user): bool
    {
        if ($user->isExecutiveViewer()) {
            return false;
        }

        $user->loadMissing('position');

        return $user->isSystemAdministrator() || $this->isOperationsManager($user) || $this->isCaptain($user);
    }

    private function canUploadChecklist(ShippingCalendarEvent $event, User $user): bool
    {
        if ($user->isExecutiveViewer() || (int) $event->division_id !== $this->shippingDivisionId()) {
            return false;
        }
        if ($user->isSystemAdministrator() || $this->isOperationsManager($user)) {
            return true;
        }

        return $this->isCaptain($user) && $this->vesselAccess->canAccess($user, (int) $event->vessel_id);
    }

    private function isOperationsManager(User $user): bool
    {
        $user->loadMissing(['position', 'department']);
        $department = strtolower(trim((string) $user->department?->name));

        return in_array($department, ['marine operation', 'marine operations'], true)
            && in_array(strtolower((string) $user->position?->code), self::OPERATIONS_MANAGER_CODES, true);
    }

    private function isCaptain(User $user): bool
    {
        $user->loadMissing('position');

        return $user->role === 'captain'
            || strtolower((string) $user->position?->legacy_role) === 'captain'
            || strtolower((string) $user->position?->code) === 'vessel-captain';
    }

    private function notifyRecipients(ShippingCalendarEvent $event, string $title, string $message): void
    {
        $this->checklistReminders->recipients($event)->where('id', '!=', auth()->id())->each(fn (User $user) => $user->notify(new CalendarEventNotification([
            'title' => $title, 'message' => $message, 'type' => 'calendar',
            'url' => route('shipping.calendar').'?vessel='.$event->vessel_id.'&event='.$event->id,
            'event_id' => $event->id, 'vessel_id' => $event->vessel_id,
        ])));
    }

    private function audit(ShippingCalendarEvent $event, string $action, array $changes = []): void
    {
        ShippingCalendarAudit::create([
            'event_id' => $event->id, 'user_id' => auth()->id(), 'action' => $action,
            'changes' => $changes ?: null, 'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent() ? mb_substr(request()->userAgent(), 0, 1000) : null,
            'created_at' => now(),
        ]);
    }

    private function occurrences(ShippingCalendarEvent $event, Carbon $rangeStart, Carbon $rangeEnd): array
    {
        $starts = $event->starts_at->copy();
        $duration = max(60, $event->starts_at->diffInSeconds($event->ends_at));
        $occurrences = [];
        $limit = $event->recurrence_ends_on?->copy()->endOfDay()->min($rangeEnd) ?? $rangeEnd;
        $iterations = 0;
        while ($event->recurrence_frequency && $starts->copy()->addSeconds($duration) < $rangeStart && $iterations < 20000) {
            $this->advanceOccurrence($starts, $event);
            $iterations++;
        }
        do {
            $anchor = $starts->copy();
            $override = $event->occurrenceOverrides->first(fn (ShippingCalendarOccurrenceOverride $item) => $item->occurrence_starts_at->equalTo($anchor));
            $hasChanges = (bool) $override?->has_changes;
            $effectiveStart = $hasChanges ? $override->starts_at->copy() : $anchor->copy();
            $occurrenceEnd = $hasChanges ? $override->ends_at->copy() : $effectiveStart->copy()->addSeconds($duration);
            if (($override?->status ?? $event->status) !== 'deleted' && $occurrenceEnd >= $rangeStart && $effectiveStart <= $rangeEnd) {
                $occurrenceCompleted = $event->status === 'completed'
                    || $event->occurrenceCompletions->contains(fn (ShippingCalendarOccurrenceCompletion $completion) => $completion->occurrence_starts_at->equalTo($anchor));
                $occurrenceStatus = $occurrenceCompleted ? 'completed' : ($override?->status ?? $event->status);
                $occurrences[] = [
                    'occurrence_id' => $event->id.'_'.$anchor->timestamp,
                    'occurrence_starts_at' => $anchor->toIso8601String(),
                    'event_id' => $event->id, 'title' => $hasChanges ? $override->title : $event->title,
                    'start' => $effectiveStart->toIso8601String(), 'end' => $occurrenceEnd->toIso8601String(),
                    'all_day' => $event->all_day, 'color' => $hasChanges ? $override->color : $event->color, 'status' => $occurrenceStatus,
                    'checklist_type' => $hasChanges ? $override->checklist_type : $event->checklist_type, 'vessel' => $event->vessel?->vessel_name,
                    'location' => $hasChanges ? $override->location : $event->location,
                    'can_edit' => $occurrenceStatus === 'scheduled' && $this->canManage($event, auth()->user()), 'recurring' => filled($event->recurrence_frequency),
                ];
            }
            if (! $event->recurrence_frequency) {
                break;
            }
            $this->advanceOccurrence($starts, $event);
            $iterations++;
        } while ($starts <= $limit && $iterations < 21000);

        return $occurrences;
    }

    private function resolveOccurrenceStart(ShippingCalendarEvent $event, ?string $requested): Carbon
    {
        $target = $requested ? Carbon::parse($requested) : $event->starts_at->copy();
        $occurrence = $event->starts_at->copy();

        if (! $event->recurrence_frequency) {
            abort_unless($occurrence->equalTo($target), 422, 'The selected checklist date is invalid.');

            return $occurrence;
        }

        $limit = $event->recurrence_ends_on?->copy()->endOfDay();
        $iterations = 0;
        while ($occurrence < $target && $iterations < 21000) {
            $this->advanceOccurrence($occurrence, $event);
            $iterations++;
        }

        abort_unless($occurrence->equalTo($target) && (! $limit || $occurrence <= $limit), 422, 'The selected checklist date is not part of this recurring schedule.');

        return $occurrence;
    }

    private function advanceOccurrence(Carbon $date, ShippingCalendarEvent $event): void
    {
        $interval = max(1, (int) $event->recurrence_interval);
        match ($event->recurrence_frequency) {
            'daily' => $date->addDays($interval),
            'weekly' => $date->addWeeks($interval),
            'monthly' => $this->advanceMonthlyOccurrence($date, $event->starts_at, $interval),
        };
    }

    private function advanceMonthlyOccurrence(Carbon $date, Carbon $anchor, int $interval): Carbon
    {
        $month = $date->copy()->startOfMonth()->addMonths($interval);

        return $date->setDate($month->year, $month->month, min($anchor->day, $month->daysInMonth));
    }

    private function eventPayload(ShippingCalendarEvent $event, Carbon $occurrenceStart, ?ShippingCalendarOccurrenceOverride $override): array
    {
        $duration = max(60, $event->starts_at->diffInSeconds($event->ends_at));
        $hasChanges = (bool) $override?->has_changes;
        $effectiveStart = $hasChanges ? $override->starts_at->copy() : $occurrenceStart->copy();
        $effectiveEnd = $hasChanges ? $override->ends_at->copy() : $effectiveStart->copy()->addSeconds($duration);

        return [
            ...Arr::only($event->toArray(), ['id', 'all_day', 'vessel_id', 'recurrence_frequency', 'recurrence_interval']),
            'title' => $hasChanges ? $override->title : $event->title,
            'checklist_type' => $hasChanges ? $override->checklist_type : $event->checklist_type,
            'description' => $hasChanges ? $override->description : $event->description,
            'location' => $hasChanges ? $override->location : $event->location,
            'color' => $hasChanges ? $override->color : $event->color,
            'reminder_minutes' => $hasChanges ? $override->reminder_minutes : $event->reminder_minutes,
            'status' => $override?->status ?? $event->status,
            'starts_at' => $effectiveStart->format('Y-m-d\TH:i'), 'ends_at' => $effectiveEnd->format('Y-m-d\TH:i'),
            'recurrence_ends_on' => $event->recurrence_ends_on?->toDateString(),
            'recurring' => filled($event->recurrence_frequency),
            'creator' => $event->creator ? trim($event->creator->name.' '.$event->creator->lastname) : 'Unknown',
            'vessel' => $event->vessel?->vessel_name,
        ];
    }

    private function shippingDivisionId(): int
    {
        return (int) Division::whereRaw('LOWER(name) = ?', ['villa shipping lines'])->value('id');
    }
}
