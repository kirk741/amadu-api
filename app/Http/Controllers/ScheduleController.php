<?php

namespace App\Http\Controllers;

use App\Models\Schedule;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ScheduleController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        $user = auth('sanctum')->user();
        $query = Schedule::query();

        $isPsychologist = $user && $user->role && $user->role->name === 'psychologist';

        if ($isPsychologist) {
            $query->where('user_id', $user->id);
        } else {
            $query->where('is_booked', false)->where('start_time', '>', Carbon::now());

            if ($request->has('psychologist_id')) {
                $query->where('user_id', $request->psychologist_id);
            }
        }

        $slots = $query->with(['appointments'])->latest()->get();

        $transformed = $slots->map(function ($slot) use ($user) {
            if (!$user || ($user->role->name !== 'psychologist' && $user->role->name !== 'admin')) {
                unset($slot->appointments);
            }
            return $slot;
        });

        return response()->json([
            'success' => true,
            'data' => $transformed
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'start_time' => 'required|date|after:now',
            'end_time'   => 'required|date|after:start_time'
        ]);

        $start = $validated['start_time'];
        $end = $validated['end_time'];

        $exists = $request->user()->schedules()
            ->where(function ($query) use ($start, $end) {
                $query->where(function ($q) use ($start, $end) {
                    $q->where('start_time', '<', $end)
                        ->where('end_time', '>', $start);
                });
            })->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'Это время уже занято другим слотом в вашем расписании'
            ], 422);
        }

        $schedule = $request->user()->schedules()->create($validated);

        return response()->json([
            'success' => true,
            'data' => $schedule
        ], 201);
    }

    public function show(Schedule $schedule)
    {
        return response()->json([
            'success' => true,
            'data' => $schedule
        ]);
    }

    public function update(Request $request, Schedule $schedule)
    {
        $this->authorize('update', $schedule);

        $validated = $request->validate([
            'start_time' => 'sometimes|required|date|after:now',
            'end_time'   => 'sometimes|required|date|after:start_time',
            'is_booked'  => 'sometimes|boolean'
        ]);

        if (isset($validated['start_time']) || isset($validated['end_time'])) {
            $start = $validated['start_time'] ?? $schedule->start_time;
            $end = $validated['end_time'] ?? $schedule->end_time;

            $exists = $request->user()->schedules()
                ->where('id', '!=', $schedule->id)
                ->where(function ($q) use ($start, $end) {
                    $q->where('start_time', '<', $end)
                        ->where('end_time', '>', $start);
                })->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Это время уже занято другим слотом в вашем расписании'
                ], 422);
            }
        }
        $schedule->update($validated);

        return response()->json([
            'success' => true,
            'data' => $schedule
        ]);
    }
    public function destroy(Schedule $schedule)
    {
        $this->authorize('delete', $schedule);

        if ($schedule->is_booked) {
            return response()->json([
                'success' => true,
                'message' => 'Нельзя удалить забронированное время'
            ], 422);
        }

        $schedule->delete();

        return response()->json([
            'success' => true,
            'message' => 'Запись удалена'
        ]);
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:schedules,id'
        ]);

        $deletedCount = $request->user()->schedules()
            ->whereIn('id', $validated['ids'])
            ->where('is_booked', false)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => "Удалено записей: $deletedCount"
        ]);
    }

    public function generate(Request $request)
    {
        $validated = $request->validate([
            'dates' => 'required|array',
            'dates.*'       => 'date|after_or_equal:today',
            'start_time'     => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'slot_duration' => 'required|integer|min:15|max:120',
            'gap' => 'nullable|integer|min:0|max:60'
        ]);

        $slots = [];
        $userId = $request->user()->id;
        $duration = (int) $validated['slot_duration'];
        $gap = (int) ($validated['gap'] ?? 0);

        foreach ($validated['dates'] as $date) {
            $current = Carbon::parse($date . ' ' . $validated['start_time']);
            $end = Carbon::parse($date . ' ' . $validated['end_time']);

            while ($current->copy()->addMinutes($duration) <= $end) {
                $slotStart = $current->copy();
                $slotEnd = $current->copy()->addMinutes($duration);

                $exists = $request->user()->schedules()
                    ->where('start_time', $slotStart->toDateTimeString())
                    ->exists();

                if (!$exists) {
                    $slots[] = [
                        'id'         => (string) Str::ulid(),
                        'user_id'    => $userId,
                        'start_time' => $slotStart->toDateTimeString(),
                        'end_time'   => $slotEnd->toDateTimeString(),
                        'is_booked'  => false,
                        'created_at' => now()->toDateTimeString(),
                        'updated_at' => now()->toDateTimeString(),
                    ];
                }
                $current->addMinutes($duration + $gap);
            }
        }

        if (empty($slots)) {
            return response()->json([
                'message' => 'Слоты уже существуют или интервал слишком мал'
            ], 422);
        }

        Schedule::insert($slots);

        return response()->json([
            'success' => true,
            'count' => count($slots),
            'message' => 'Записи добавлены'
        ], 201);
    }
}
