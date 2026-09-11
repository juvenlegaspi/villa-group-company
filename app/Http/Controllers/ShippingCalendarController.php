<?php

namespace App\Http\Controllers;

use App\Models\Division;
use App\Models\ShippingCalendarAttachment;
use App\Models\ShippingCalendarAudit;
use App\Models\ShippingCalendarEvent;
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

class ShippingCalendarController extends Controller
{
    private const RECURRENCES = ['daily', 'weekly', 'monthly'];

    private const CHECKLIST_TYPES = ['routine', 'scheduled', 'one_time'];

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
            ->with(['creator:id,name,lastname', 'department:id,name', 'vessel:id,vessel_name'])
            ->where('starts_at', '<=', $end)
            ->where(function (Builder $query) use ($start): void {
                $query->where('ends_at', '>=', $start)
                    ->orWhere(function (Builder $recurring) use ($start): void {
                        $recurring->whereNotNull('recurrence_frequency')
                            ->where(fn (Builder $until) => $until->whereNull('recurrence_ends_on')->orWhereDate('recurrence_ends_on', '>=', $start));
                    });
            })->get();

        return response()->json($events->flatMap(fn (ShippingCalendarEvent $event) => $this->occurrences($event, $start, $end))->values());
    }

    public function show(ShippingCalendarEvent $event): JsonResponse
    {
        $this->authorizeView($event);
        $event->load([
            'creator:id,name,lastname', 'vessel:id,vessel_name,captain_id', 'vessel.captain:id,name,lastname,email,cell_number',
            'audits.user:id,name,lastname', 'attachments.uploader:id,name,lastname',
        ]);

        return response()->json([
            'event' => $this->eventPayload($event),
            'recipients' => $this->checklistReminders->recipients($event)->map(fn (User $user) => [
                'id' => $user->id, 'name' => trim($user->name.' '.$user->lastname),
                'email' => $user->email, 'cell_number' => $user->cell_number,
            ]),
            'audits' => $event->audits->map(fn ($audit) => [
                'action' => str($audit->action)->replace('_', ' ')->title()->toString(),
                'user' => $audit->user ? trim($audit->user->name.' '.$audit->user->lastname) : 'System',
                'date' => $audit->created_at?->format('M d, Y g:i A'),
            ]),
            'attachments' => $event->attachments->map(fn (ShippingCalendarAttachment $attachment) => [
                'id' => $attachment->id,
                'name' => $attachment->original_name,
                'mime_type' => $attachment->mime_type,
                'size_bytes' => $attachment->size_bytes,
                'uploaded_by' => $attachment->uploader ? trim($attachment->uploader->name.' '.$attachment->uploader->lastname) : 'System',
                'uploaded_at' => $attachment->created_at?->format('M d, Y g:i A'),
                'download_url' => route('shipping.calendar.events.attachments.download', [$event, $attachment]),
                'delete_url' => route('shipping.calendar.events.attachments.destroy', [$event, $attachment]),
            ]),
            'can_edit' => $this->canManage($event, auth()->user()),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatedEvent($request);
        $attachments = $this->validatedAttachments($request);
        $this->authorizeCreate(auth()->user());
        $this->vesselAccess->authorize(auth()->user(), (int) $data['vessel_id']);

        $storedPaths = [];
        try {
            $event = DB::transaction(function () use ($data, $attachments, &$storedPaths): ShippingCalendarEvent {
                $event = ShippingCalendarEvent::create([
                    ...$data,
                    'division_id' => $this->shippingDivisionId(),
                    'created_by' => auth()->id(),
                    'updated_by' => auth()->id(),
                ]);
                $this->audit($event, 'created', ['title' => $event->title]);
                $this->storeAttachments($event, $attachments, $storedPaths);

                return $event;
            });
        } catch (\Throwable $exception) {
            if ($storedPaths) {
                Storage::disk('local')->delete($storedPaths);
            }
            throw $exception;
        }

        $this->notifyRecipients($event, 'New vessel checklist scheduled', "{$event->title} was scheduled for {$event->vessel?->vessel_name}.");

        return response()->json(['message' => 'Checklist scheduled successfully.', 'event_id' => $event->id], 201);
    }

    public function update(Request $request, ShippingCalendarEvent $event): JsonResponse
    {
        $this->authorizeManage($event);
        $data = $this->validatedEvent($request);
        $attachments = $this->validatedAttachments($request);
        $this->vesselAccess->authorize(auth()->user(), (int) $data['vessel_id']);

        $storedPaths = [];
        try {
            DB::transaction(function () use ($event, $data, $attachments, &$storedPaths): void {
                $before = $event->only(array_keys($data));
                $event->update([...$data, 'updated_by' => auth()->id()]);
                $this->audit($event, 'updated', ['before' => $before, 'after' => $event->fresh()->only(array_keys($data))]);
                $this->storeAttachments($event, $attachments, $storedPaths);
            });
        } catch (\Throwable $exception) {
            if ($storedPaths) {
                Storage::disk('local')->delete($storedPaths);
            }
            throw $exception;
        }
        $this->notifyRecipients($event, 'Vessel checklist schedule updated', "{$event->title} for {$event->vessel?->vessel_name} has been updated.");

        return response()->json(['message' => 'Checklist updated successfully.']);
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
        $this->authorizeManage($event);
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

    public function cancel(ShippingCalendarEvent $event): JsonResponse
    {
        $this->authorizeManage($event);
        abort_if($event->status === 'cancelled', 422, 'This event is already cancelled.');
        $event->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancelled_by' => auth()->id(), 'updated_by' => auth()->id()]);
        $this->audit($event, 'cancelled');
        $this->notifyRecipients($event, 'Vessel checklist cancelled', "{$event->title} for {$event->vessel?->vessel_name} has been cancelled.");

        return response()->json(['message' => 'Checklist cancelled.']);
    }

    public function destroy(ShippingCalendarEvent $event): JsonResponse
    {
        $this->authorizeManage($event);
        $this->audit($event, 'deleted');
        $event->delete();

        return response()->json(['message' => 'Checklist deleted.']);
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
            'recurrence_ends_on' => 'nullable|date|after_or_equal:starts_at',
            'reminder_minutes' => 'required|integer|min:0|max:10080',
        ]);
        $data['all_day'] = $request->boolean('all_day');
        $data['email_reminder'] = true;
        $data['visibility'] = 'vessel';
        $data['department_id'] = null;
        $data['recurrence_interval'] = $data['recurrence_frequency'] ? ($data['recurrence_interval'] ?? 1) : 1;
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

    private function storeAttachments(ShippingCalendarEvent $event, array $files, array &$storedPaths): void
    {
        $existingCount = $event->attachments()->count();
        $existingBytes = (int) $event->attachments()->sum('size_bytes');
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
        $user->loadMissing('position');

        return $user->isSystemAdministrator() || $this->isOperationsManager($user) || $this->isCaptain($user);
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
            $occurrenceEnd = $starts->copy()->addSeconds($duration);
            if ($occurrenceEnd >= $rangeStart && $starts <= $rangeEnd) {
                $occurrences[] = [
                    'occurrence_id' => $event->id.'_'.$starts->timestamp,
                    'event_id' => $event->id, 'title' => $event->title,
                    'start' => $starts->toIso8601String(), 'end' => $occurrenceEnd->toIso8601String(),
                    'all_day' => $event->all_day, 'color' => $event->color, 'status' => $event->status,
                    'checklist_type' => $event->checklist_type, 'vessel' => $event->vessel?->vessel_name,
                    'location' => $event->location,
                    'can_edit' => $this->canManage($event, auth()->user()), 'recurring' => filled($event->recurrence_frequency),
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

    private function eventPayload(ShippingCalendarEvent $event): array
    {
        return [
            ...Arr::only($event->toArray(), ['id', 'title', 'checklist_type', 'description', 'location', 'all_day', 'vessel_id', 'color', 'recurrence_frequency', 'recurrence_interval', 'reminder_minutes', 'status']),
            'starts_at' => $event->starts_at->format('Y-m-d\TH:i'), 'ends_at' => $event->ends_at->format('Y-m-d\TH:i'),
            'recurrence_ends_on' => $event->recurrence_ends_on?->toDateString(),
            'creator' => $event->creator ? trim($event->creator->name.' '.$event->creator->lastname) : 'Unknown',
            'vessel' => $event->vessel?->vessel_name,
        ];
    }

    private function shippingDivisionId(): int
    {
        return (int) Division::whereRaw('LOWER(name) = ?', ['villa shipping lines'])->value('id');
    }
}
